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

// Emails a user once a favorited (= "sekama", tracked via <x-favorite-button>/
// price-watch-modal) product gets a new Discount. "New" means: this (user,
// discount) pair has no price_watch_notifications row yet — that table is
// the anti-join key, not a timestamp cutoff, so a run that's late or skipped
// still catches everything it missed next time, and a discount already
// notified never gets re-sent even if this command runs again before the
// discount itself expires.
//
// Deliberately independent of discounts:process's own timing (that pipeline
// runs Discount::create inside withoutEvents(), so no model event fires here
// — same reason cache:clear-discounts/deal-pool:refresh are separate,
// explicitly-run steps rather than Discount observers) — this only depends
// on Discount rows existing, so it's safe to schedule on its own cadence.
class NotifyPriceWatchers extends Command
{
    protected $signature = 'price-watch:notify {--dry-run : List what would be sent without sending or recording anything}';

    protected $description = 'Email users a digest of new discounts on their favorited ("sekamos") products';

    // Longer than AuthController's 30-minute interactive-login TTL — this is
    // a "come back when you get a chance" email, often opened days after it
    // arrives, not an immediate login action.
    private const MAGIC_LINK_TTL_DAYS = 14;

    // Caps how often any one user gets emailed, regardless of how often this
    // command itself runs — at most a few times a week, not once per new
    // discount. Only applies when nothing pending is a genuine price drop
    // (see below); a real drop always bypasses it. A user rate-limited out
    // this run keeps every one of their pending (user, discount) pairs
    // un-notified (no price_watch_notifications row written for them)
    // rather than losing them — they just roll forward into their next
    // eligible run and arrive as one bigger digest.
    private const MIN_HOURS_BETWEEN_EMAILS = 48;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // (user_id, discount_id) pairs where the user favorited the
        // discount's product and hasn't been notified about this exact
        // discount yet. discounts:archive-expired only runs as part of
        // whichever store's own processing job just touched it (see
        // FinalizeScrapedStoresJob) — not a standalone sweep — so an
        // already-expired Discount row can sit in the table for a while
        // before archiving deletes it (confirmed: 23 rows with a past
        // end_at existed at once when this was checked). Re-verify validity
        // here rather than trust the row's mere existence.
        $pending = DB::table('product_favorites')
            ->join('discounts', 'discounts.product_id', '=', 'product_favorites.product_id')
            ->leftJoin('price_watch_notifications', function ($join) {
                $join->on('price_watch_notifications.discount_id', '=', 'discounts.id')
                    ->on('price_watch_notifications.user_id', '=', 'product_favorites.user_id');
            })
            ->whereNull('price_watch_notifications.id')
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

        $this->info("Found {$pending->count()} user(s) with new discounts on favorited products" . ($dryRun ? ' (dry run)' : '') . '.');

        // Last time each of these users was actually emailed (not just
        // "had something pending") — the rate-limit check below.
        $lastSentAt = PriceWatchNotification::whereIn('user_id', $pending->keys())
            ->selectRaw('user_id, MAX(sent_at) as last_sent_at')
            ->groupBy('user_id')
            ->pluck('last_sent_at', 'user_id');

        $sent = 0;
        $failed = 0;
        $rateLimited = 0;

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
            // price_watch_notifications row below so none of them re-trigger
            // a future run, even ones not the cheapest.
            $productGroups = $discounts->groupBy('product_id')
                ->map(fn ($group) => $group->sortBy('discounted_price')->values())
                ->values();

            // The most recent price we told this user about, per product —
            // used to decide whether ANY pending product is a genuine price
            // drop (bypasses the rate limit below) versus still sitting at
            // the same lowest price/discount we already showed them (stays
            // subject to it). No prior notification for a product counts as
            // a drop too — a brand-new tracked deal should never be held
            // back by the rate limit.
            $previousPrices = PriceWatchNotification::where('user_id', $userId)
                ->whereIn('product_id', $productGroups->map(fn ($g) => $g->first()->product_id))
                ->orderByDesc('sent_at')
                ->get()
                ->groupBy('product_id')
                ->map(fn ($rows) => $rows->first()->notified_price);

            $hasGenuineDrop = $productGroups->contains(function ($group) use ($previousPrices) {
                $cheapest = $group->first();
                $previousPrice = $previousPrices->get($cheapest->product_id);

                return $previousPrice === null || $cheapest->discounted_price < $previousPrice;
            });

            // selectRaw()'s aggregate column comes back as a plain string,
            // not cast to Carbon like a normal Eloquent attribute would be.
            $lastSent = $lastSentAt->get($userId);
            $lastSent = $lastSent ? \Carbon\Carbon::parse($lastSent) : null;

            // Hard daily cap — no genuine-drop bypass here, unlike the 48h
            // rule below. A user already emailed today never gets a second
            // one today even if a separate real price drop shows up a few
            // hours later; it rolls forward and goes out tomorrow instead.
            if (!$dryRun && $lastSent && $lastSent->isToday()) {
                $rateLimited++;
                continue;
            }

            if (!$dryRun && !$hasGenuineDrop && $lastSent && now()->diffInHours($lastSent) < self::MIN_HOURS_BETWEEN_EMAILS) {
                $rateLimited++;
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
                Mail::to($user->email)->send(new PriceWatchDiscountMail($productGroups, $favoritesUrl, $totalSavings));

                $now = now();
                // Every underlying discount in a product's group is stamped
                // with that group's cheapest price (not each store's own
                // price) — next run's genuine-drop check always compares
                // against the figure actually shown to the user, regardless
                // of which specific row it later looks up.
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
                $this->line("Emailed {$user->email}: {$productGroups->count()} product(s) ({$discounts->count()} underlying discount(s) marked notified)");
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
            $this->info("Done: {$sent} email(s) sent, {$failed} failed, {$rateLimited} rate-limited (queued for next eligible run).");
        }

        return 0;
    }
}
