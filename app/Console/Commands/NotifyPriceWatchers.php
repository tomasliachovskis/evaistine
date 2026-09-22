<?php

namespace App\Console\Commands;

use App\Mail\PriceWatchDiscountMail;
use App\Models\Discount;
use App\Models\MagicLoginLink;
use App\Models\PriceWatchNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

// Emails a user about a favorited (= "sekama", tracked via <x-favorite-button>/
// price-watch-modal) product's active Discount. No per-user rate limit
// (no daily cap, no "wait N hours between emails") — the only throttle is
// per product: a product already emailed to this user within the last 5
// days is skipped, everything else pending goes out. So a run that's late
// or skipped just catches up next time, and the same product won't spam the
// user more than once per 5-day window regardless of how often this command
// runs.
//
// Deliberately independent of discounts:process's own timing (that pipeline
// runs Discount::create inside withoutEvents(), so no model event fires here
// — same reason cache:clear-discounts/deal-pool:refresh are separate,
// explicitly-run steps rather than Discount observers) — this only depends
// on Discount rows existing, so it's safe to schedule on its own cadence.
class NotifyPriceWatchers extends Command
{
    protected $signature = 'price-watch:notify {--dry-run : List what would be sent without sending or recording anything}';

    protected $description = 'Email users a digest of active discounts on their favorited ("sekamos") products';

    // Longer than AuthController's 30-minute interactive-login TTL — this is
    // a "come back when you get a chance" email, often opened days after it
    // arrives, not an immediate login action.
    private const MAGIC_LINK_TTL_DAYS = 14;

    // Per-product re-notify window: a product (by product_id, regardless of
    // which store's Discount row triggers it) already emailed to this user
    // within the last 5 days is skipped; otherwise it's sent every run it's
    // still on sale. Replaces the old per-user daily-cap/48h-cooldown
    // scheme.
    private const RENOTIFY_COOLDOWN_DAYS = 5;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // (user_id, discount_id) pairs for every favorited product currently
        // on an active Discount — no longer filtered by notification history
        // at the query level; the per-product 5-day cooldown below decides
        // what actually gets sent. discounts:archive-expired only runs as
        // part of whichever store's own processing job just touched it (see
        // FinalizeScrapedStoresJob) — not a standalone sweep — so an
        // already-expired Discount row can sit in the table for a while
        // before archiving deletes it (confirmed: 23 rows with a past
        // end_at existed at once when this was checked). Re-verify validity
        // here rather than trust the row's mere existence.
        $pending = DB::table('product_favorites')
            ->join('discounts', 'discounts.product_id', '=', 'product_favorites.product_id')
            // Opted out via the signed unsubscribe link in a previous
            // digest email (see PriceWatchController) — excluded up front so
            // they never enter the per-user loop below at all. Favoriting
            // itself is untouched; this only silences future emails.
            ->join('users', 'users.id', '=', 'product_favorites.user_id')
            ->whereNull('users.price_watch_unsubscribed_at')
            ->where(function ($query) {
                // now()->startOfDay(): end_at is a DATE stored at midnight
                // ("valid through this day") — comparing against the exact
                // current moment wrongly treated a discount expiring today
                // as already expired for the rest of today, so a user's
                // last real day of a price drop could silently never email.
                $query->whereNull('discounts.end_at')->orWhere('discounts.end_at', '>=', now()->startOfDay());
            })
            ->where(function ($query) {
                $query->whereNull('discounts.start_at')->orWhere('discounts.start_at', '<=', now());
            })
            ->select('product_favorites.user_id', 'discounts.id as discount_id')
            ->get()
            ->groupBy('user_id');

        if ($pending->isEmpty()) {
            $this->info('Nothing to notify.');

            return 0;
        }

        $this->info("Found {$pending->count()} user(s) with active discounts on favorited products" . ($dryRun ? ' (dry run)' : '') . '.');

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($pending as $userId => $rows) {
            $user = User::find($userId);
            if (!$user || empty($user->email)) {
                continue;
            }

            $discountIds = $rows->pluck('discount_id')->all();
            $discounts = Discount::with(['product.category', 'store'])
                ->whereIn('id', $discountIds)
                ->get()
                ->filter(fn ($d) => $d->product !== null);

            if ($discounts->isEmpty()) {
                continue;
            }

            // The same product can have several simultaneous Discount rows
            // (one per store selling it) — show the product once, with every
            // store's offer listed underneath (cheapest first), not once per
            // store. Every discount in the group still gets a
            // price_watch_notifications row below so they all share the same
            // "last emailed" timestamp for the cooldown check next run.
            $productGroups = $discounts->groupBy('product_id')
                ->map(fn ($group) => $group->sortBy('discounted_price')->values())
                ->values();

            // Products already emailed to this user within the cooldown
            // window are dropped — everything else goes out regardless of
            // whether the price actually changed since last time.
            $recentlyNotifiedProductIds = PriceWatchNotification::where('user_id', $userId)
                ->whereIn('product_id', $productGroups->map(fn ($g) => $g->first()->product_id))
                ->where('sent_at', '>=', now()->subDays(self::RENOTIFY_COOLDOWN_DAYS))
                ->pluck('product_id')
                ->unique();

            $productGroups = $productGroups
                ->reject(fn ($group) => $recentlyNotifiedProductIds->contains($group->first()->product_id))
                ->values();

            if ($productGroups->isEmpty()) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("Would email {$user->email}: " . $productGroups->map(fn ($g) => $g->first()->product->name)->implode(', '));
                continue;
            }

            // Viewing a product needs no login — only one auto-login link is
            // needed per email, for a single "see your favorites" CTA. Each
            // product row below links straight to its own plain (public)
            // product page instead.
            $magicLink = MagicLoginLink::create([
                'email' => $user->email,
                'token' => str()->random(64),
                'expires_at' => now()->addDays(self::MAGIC_LINK_TTL_DAYS),
                'redirect_to' => '/favorites',
            ]);
            $favoritesUrl = url("/auth/magic-link/{$magicLink->token}");

            // Permanent (no expiry) signed link — this may sit unopened in an
            // inbox for weeks, unlike the short-lived magic login link above.
            $unsubscribeUrl = URL::signedRoute('price-watch.unsubscribe', ['user' => $user->id]);

            // Same calc as FavoritesController::resolveSavingsSummary() ("Galite
            // sutaupyti dabar" on /favorites) — one savings figure per
            // product, from its cheapest offer, not summed across every
            // store's duplicate row.
            $totalSavings = $productGroups->sum(function ($group) {
                $cheapest = $group->first();

                return $cheapest->original_price > $cheapest->discounted_price
                    ? $cheapest->original_price - $cheapest->discounted_price
                    : 0;
            });

            try {
                Mail::to($user->email)->send(new PriceWatchDiscountMail($productGroups, $favoritesUrl, $totalSavings, $unsubscribeUrl));

                $now = now();
                // Every underlying discount in a product's group is stamped
                // with the same sent_at/notified_price (that group's cheapest
                // price, not each store's own) — the cooldown check above
                // only needs sent_at, but notified_price is kept for record.
                $rowsToInsert = $productGroups->flatMap(function ($group) use ($user, $now) {
                    $cheapestPrice = $group->first()->discounted_price;

                    return $group->map(fn ($d) => [
                        'user_id' => $user->id,
                        'product_id' => $d->product_id,
                        'discount_id' => $d->id,
                        'notified_price' => $cheapestPrice,
                        'sent_at' => $now,
                    ]);
                })->all();

                PriceWatchNotification::insert($rowsToInsert);

                $sent++;
                $this->line("Emailed {$user->email}: {$productGroups->count()} product(s) (" . count($rowsToInsert) . ' underlying discount(s) marked notified)');
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('NotifyPriceWatchers: failed to send digest', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("Failed to email {$user->email}: {$e->getMessage()}");
            }
        }

        if (!$dryRun) {
            $this->info("Done: {$sent} email(s) sent, {$failed} failed, {$skipped} user(s) with nothing outside the cooldown window.");
        }

        return 0;
    }
}
