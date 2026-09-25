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
// price-watch-modal) product's active Discount. Decided per product and
// price, not per Discount row: a product is emailed when it was never
// emailed to this user, when its cheapest current price is LOWER than the
// last price emailed (every run it keeps dropping), or when the last email
// about it is older than RENOTIFY_AFTER_DAYS (an offer coming back weeks
// later is news again). Same or higher price inside that window is skipped.
//
// Not keyed on discount_id: Rimi/Maxima re-create most Discount rows (new
// id) on nearly every scrape — 99% / 55% of their rows were under 2 days old
// when checked 2026-09-23 — so "never re-send the same discount_id" would
// re-email the same offer almost daily.
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

    // A product last emailed longer ago than this is emailed again even at
    // the same price — see the class comment.
    private const RENOTIFY_AFTER_DAYS = 7;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // (user_id, discount_id) pairs for every favorited product currently
        // on an active Discount — no longer filtered by notification history
        // at the query level; the per-product price check below decides
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
            // price_watch_notifications row below, all stamped with the
            // group's cheapest price as notified_price.
            $productGroups = $discounts->groupBy('product_id')
                ->map(fn ($group) => $group->sortBy('discounted_price')->values())
                ->values();

            // Latest email per product for this user (all rows of one send
            // share sent_at and notified_price, so any row at the max
            // sent_at carries that send's price).
            $lastNotified = PriceWatchNotification::where('user_id', $userId)
                ->whereIn('product_id', $productGroups->map(fn ($g) => $g->first()->product_id))
                ->orderByDesc('sent_at')
                ->get(['product_id', 'notified_price', 'sent_at'])
                ->unique('product_id')
                ->keyBy('product_id');

            $productGroups = $productGroups
                ->filter(function ($group) use ($lastNotified) {
                    $last = $lastNotified->get($group->first()->product_id);

                    if ($last === null || $last->sent_at < now()->subDays(self::RENOTIFY_AFTER_DAYS)) {
                        return true;
                    }

                    // Rows from before notified_price was recorded have no
                    // price to compare against — only the RENOTIFY_AFTER_DAYS rule
                    // re-sends those, never a guess.
                    return $last->notified_price !== null
                        && round((float) $group->first()->discounted_price, 2) < round((float) $last->notified_price, 2);
                })
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
                // UTM goes on the redirect target, not the token URL —
                // verifyMagicLink() redirects before GA ever loads.
                'redirect_to' => PriceWatchDiscountMail::trackedUrl('/favorites', 'favorites_cta'),
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
                $now = now();
                // Every underlying discount in a product's group is stamped
                // with the same sent_at/notified_price (that group's cheapest
                // price, not each store's own) — the next run compares its
                // price against this notified_price.
                $rowsToRecord = $productGroups->flatMap(function ($group) use ($user, $now) {
                    $cheapestPrice = $group->first()->discounted_price;

                    return $group->map(fn ($d) => [
                        'user_id' => $user->id,
                        'product_id' => $d->product_id,
                        'discount_id' => $d->id,
                        'notified_price' => $cheapestPrice,
                        'sent_at' => $now,
                    ]);
                })->all();

                // Record first, send second, one transaction: a failed send
                // rolls the rows back, and a failed write can never happen
                // after the email already went out. It used to send first
                // and then insert() — once the 5-day cooldown started
                // re-sending the same discount, insert() hit the
                // (user_id, discount_id) unique key, the whole batch failed,
                // nothing was recorded, and those users got the same email
                // every run, twice a day (found live 2026-09-23). upsert()
                // refreshes sent_at/notified_price on an already-notified
                // discount instead.
                DB::transaction(function () use ($rowsToRecord, $user, $productGroups, $favoritesUrl, $totalSavings, $unsubscribeUrl) {
                    PriceWatchNotification::upsert($rowsToRecord, ['user_id', 'discount_id'], ['product_id', 'notified_price', 'sent_at']);

                    Mail::to($user->email)->send(new PriceWatchDiscountMail($productGroups, $favoritesUrl, $totalSavings, $unsubscribeUrl));
                });

                $sent++;
                $this->line("Emailed {$user->email}: {$productGroups->count()} product(s) (" . count($rowsToRecord) . ' underlying discount(s) marked notified)');
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
            $this->info("Done: {$sent} email(s) sent, {$failed} failed, {$skipped} user(s) with no new or cheaper price.");
        }

        return 0;
    }
}
