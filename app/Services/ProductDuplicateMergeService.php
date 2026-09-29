<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductFavorite;
use App\Models\ProductMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductDuplicateMergeService
{
    // Known 2-char catalog abbreviation stems, safe to trust as genuine
    // truncations regardless of length — an explicit whitelist, not a
    // length threshold, precisely because length alone can't tell a real
    // abbreviation ("sk." for "skonio") from an unrelated short code that
    // just happens to prefix a longer one ("AA" is not short for "AAA").
    // Sourced from real catalog data (2026-09-11): counted every `\w{2}\.`
    // token across all product names, then sampled real occurrences of
    // each frequent one to check it always expands to the same word.
    // "sk" (skonio, "flavor", 1213x), "įd" (įdaras, "filling", 191x),
    // "ob" (obuolių, "apple(s)"), "dž" (džiovintas/-a/-i/-ų, "dried") and
    // "įv" (įvairių, "assorted" — always paired with "rūšių") each checked
    // out unambiguous across 8+ sampled real names. Other frequent 2-char
    // stems from the same pass ("pl", "gr", "gl", "kl", "kv", "av", ...)
    // were left out because sampling showed them genuinely ambiguous
    // (e.g. "pl." is "plaukų" in one product, "plautų" in another; "gl."
    // is "glaistytas" in one, "glitimo" in another) — adding them risks
    // the same kind of false merge this whitelist exists to prevent.
    private const KNOWN_SHORT_ABBREVIATIONS = ['sk', 'įd', 'ob', 'dž', 'įv'];

    public function analyzePairs(Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        $productIds = $pairs->flatMap(fn ($pair) => [(int) $pair->id1, (int) $pair->id2])->unique()->values();
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return $pairs->map(function ($pair) use ($products) {
            $first = $products->get((int) $pair->id1);
            $second = $products->get((int) $pair->id2);

            if (!$first || !$second) {
                return [
                    'pair' => $pair,
                    'keep' => false,
                    'reason' => 'missing product',
                ];
            }

            if ($this->pairHasSpecialCharInDifferingWord($first->name, $second->name)) {
                return [
                    'pair' => $pair,
                    'keep' => false,
                    'reason' => 'special character in differing word',
                ];
            }

            if ($this->pairHasConflictingSuffixSize($first->name, $second->name)) {
                return [
                    'pair' => $pair,
                    'keep' => false,
                    'reason' => 'conflicting suffix size',
                ];
            }

            return [
                'pair' => $pair,
                'keep' => true,
                'reason' => null,
            ];
        })->values();
    }

    public function filterPairs(Collection $pairs): Collection
    {
        return $this->analyzePairs($pairs)
            ->filter(fn (array $result) => $result['keep'])
            ->map(fn (array $result) => $result['pair'])
            ->values();
    }

    public function pairHasConflictingSuffixSize(string $name1, string $name2): bool
    {
        $suffix1 = $this->extractSuffix($name1);
        $suffix2 = $this->extractSuffix($name2);

        if ($this->suffixHasSizeRange($suffix1) || $this->suffixHasSizeRange($suffix2)) {
            return $suffix1 !== $suffix2;
        }

        return false;
    }

    private function extractSuffix(string $name): string
    {
        $commaPos = strrpos($name, ',');

        if ($commaPos === false) {
            return trim($name);
        }

        return trim(substr($name, $commaPos + 1));
    }

    private function suffixHasSizeRange(string $suffix): bool
    {
        if (preg_match('/\d[^,]*[-–—][^,]*\d/u', $suffix)) {
            return true;
        }

        return (bool) preg_match('/\d\s*[x×]\s*\d/ui', $suffix);
    }

    public function pairHasSpecialCharInDifferingWord(string $name1, string $name2): bool
    {
        $mismatchWords = $this->getMismatchWords(
            $this->extractBaseWords($name1),
            $this->extractBaseWords($name2)
        );

        foreach ($mismatchWords as $word) {
            if ($this->wordHasSpecialCharacters($word)) {
                return true;
            }
        }

        return false;
    }

    public function buildClusters(Collection $pairs): array
    {
        $parent = [];

        $find = function (int $id) use (&$parent, &$find): int {
            if (!isset($parent[$id])) {
                $parent[$id] = $id;
            }
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        $union = function (int $a, int $b) use ($find, &$parent): void {
            $rootA = $find($a);
            $rootB = $find($b);
            if ($rootA !== $rootB) {
                $parent[$rootB] = $rootA;
            }
        };

        foreach ($pairs as $pair) {
            $union((int) $pair->id1, (int) $pair->id2);
        }

        $clusters = [];

        foreach ($pairs as $pair) {
            foreach ([(int) $pair->id1, (int) $pair->id2] as $id) {
                $root = $find($id);
                $clusters[$root][$id] = $id;
            }
        }

        return array_values(array_map(
            fn (array $ids) => array_values($ids),
            $clusters
        ));
    }

    public function pickBaseProduct(array $productIds): Product
    {
        // The surviving row keeps its own id/slug/URL, its discount history,
        // and everyone's favorites — the oldest product has accumulated the
        // most of that, so it survives regardless of which source (scraper
        // vs. flyer) happened to create it. `id` is a stable tiebreaker for
        // rows created in the same batch with an identical `created_at`.
        //
        // sortBy()'s array-of-criteria form calls a callable criterion as a
        // 2-arg comparator ($a, $b) => <spaceship result>, NOT as a 1-arg
        // value extractor — passing a 1-arg extractor still "works" (PHP
        // silently drops the extra $b) but returns $a's raw field value as
        // if it were the comparison result, which sorts on that value's
        // magnitude instead of comparing $a to $b at all.
        return Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy([
                fn (Product $a, Product $b) => ($a->created_at?->getTimestamp() ?? 0) <=> ($b->created_at?->getTimestamp() ?? 0),
                fn (Product $a, Product $b) => $a->id <=> $b->id,
            ])
            ->firstOrFail();
    }

    public function nameHasSpecialCharacters(string $name): bool
    {
        return (bool) preg_match('/[^\p{L}\p{N}\s,.-]/u', $name);
    }

    public function wordHasSpecialCharacters(string $word): bool
    {
        return (bool) preg_match('/[\'"`+\-*\/=_%$#@!^&(){}\[\]:;<>|\\\\]/u', $word);
    }

    private function extractBaseWords(string $name): array
    {
        $commaPos = strrpos($name, ',');
        $base = $commaPos !== false ? trim(substr($name, 0, $commaPos)) : trim($name);
        $base = $this->normalizeBaseForWords($base);
        $words = preg_split('/\s+/u', mb_strtolower($base), -1, PREG_SPLIT_NO_EMPTY);

        return $words ?: [];
    }

    private function normalizeBaseForWords(string $base): string
    {
        $normalized = str_replace(['.', ','], ' ', $base);

        return trim(preg_replace('/\s+/u', ' ', $normalized));
    }

    private function getMismatchWords(array $words1, array $words2): array
    {
        $remaining = $words2;
        $mismatch = [];

        foreach ($words1 as $word1) {
            $matchedIndex = null;
            $matchedWord = null;

            foreach ($remaining as $index => $word2) {
                if ($this->wordsFuzzyMatch($word1, $word2)) {
                    $matchedIndex = $index;
                    $matchedWord = $word2;
                    break;
                }
            }

            if ($matchedIndex === null) {
                $mismatch[] = $word1;
            } else {
                unset($remaining[$matchedIndex]);
                if ($word1 !== $matchedWord) {
                    $mismatch[] = $word1;
                    $mismatch[] = $matchedWord;
                }
            }
        }

        foreach ($remaining as $word2) {
            $mismatch[] = $word2;
        }

        return array_values(array_unique($mismatch));
    }

    public function wordsFuzzyMatch(string $word1, string $word2): bool
    {
        if ($word1 === $word2) {
            return true;
        }

        $len1 = mb_strlen($word1);
        $len2 = mb_strlen($word2);

        // Near-equal-length, differs-near-the-end (typo-style) match. Kept
        // to words longer than 3 chars only — below that, "shares all but
        // the last character" stops being a meaningful signal ("su" vs
        // "sū" would otherwise wrongly match).
        if ($len1 > 3 && $len2 > 3 && abs($len1 - $len2) <= 3) {
            $prefixLength = min($len1, $len2) - 1;

            if ($prefixLength >= 1 && mb_substr($word1, 0, $prefixLength) === mb_substr($word2, 0, $prefixLength)) {
                return true;
            }
        }

        // Abbreviation vs full word (e.g. catalog copy "Šalt." vs a flyer's
        // "Šaltasis"): one word is a genuine, literal prefix of the other,
        // regardless of how much shorter the abbreviation is.
        //
        // Floor is 3 chars, not 2 — tried 2 (to also catch "įd." -> "įdaru")
        // and found real false positives on real catalog data: "VS" is a
        // genuine prefix of "VSOP" but they're different cognac grades
        // (ALBERT JARRAUD VS vs VSOP), and "AA"/"AAA" battery sizes matched
        // each other the same way across 7 separate pairs. A 2-char code is
        // just as often a distinct specifier as it is a truncation, and
        // nothing in the string itself (no trailing period survives this
        // far — periods are stripped before word-splitting) distinguishes
        // the two cases. Missing a genuine 2-char abbreviation is a much
        // smaller cost than silently merging two different SKUs.
        $minLen = min($len1, $len2);
        $shorter = $len1 <= $len2 ? $word1 : $word2;
        $longer = $len1 <= $len2 ? $word2 : $word1;

        // Whitelisted 2-char abbreviation, checked before the general
        // 3-char floor below — see KNOWN_SHORT_ABBREVIATIONS.
        if ($minLen === 2 && in_array($shorter, self::KNOWN_SHORT_ABBREVIATIONS, true)) {
            return mb_substr($longer, 0, $minLen) === $shorter;
        }

        if ($minLen < 3) {
            return false;
        }

        return mb_substr($longer, 0, $minLen) === $shorter;
    }

    public function pickFullerName(string $name1, string $name2): string
    {
        $abbrev1 = $this->countAbbreviations($name1);
        $abbrev2 = $this->countAbbreviations($name2);

        if ($abbrev1 !== $abbrev2) {
            return $abbrev2 < $abbrev1 ? $name2 : $name1;
        }

        // Tie on abbreviation count (e.g. neither name has any, or both
        // abbreviate the same number of words) — fall back to the longer
        // string, which for same-word-count duplicates reliably means more
        // complete wording.
        return mb_strlen($name2) > mb_strlen($name1) ? $name2 : $name1;
    }

    // Catalog convention (same one MeilisearchService::buildNameStem() relies
    // on): a truncated word is written with a trailing period ("Šalt.",
    // "kav.", "gėr."). Counts those, not every period in the string — a
    // decimal like "0.25" or a unit like "l." shouldn't inflate the count.
    private function countAbbreviations(string $name): int
    {
        preg_match_all('/(?<![0-9])[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+\./u', $name, $matches);

        return count($matches[0]);
    }

    public function mergeAllClusters(array $clusters, bool $dryRun): array
    {
        $merged = [];

        $run = function () use ($clusters, $dryRun, &$merged) {
            foreach ($clusters as $productIds) {
                if (count($productIds) < 2) {
                    continue;
                }

                $base = $this->pickBaseProduct($productIds);
                $duplicateIds = array_values(array_filter($productIds, fn (int $id) => $id !== $base->id));

                foreach ($duplicateIds as $duplicateId) {
                    $duplicate = Product::findOrFail($duplicateId);

                    if ($this->pairHasSpecialCharInDifferingWord($base->name, $duplicate->name)) {
                        continue;
                    }

                    if ($this->pairHasConflictingSuffixSize($base->name, $duplicate->name)) {
                        continue;
                    }

                    $this->adoptFullerName($base, $duplicate, $dryRun);

                    $merged = array_merge(
                        $merged,
                        $this->mergeDuplicateIntoBase($base, $duplicate, $dryRun)
                    );
                }
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        return $merged;
    }

    // Merges a confirmed flyer/web pair without the name-shape filters
    // mergeAllClusters() applies — the match was established another way
    // (CrossSourceDuplicateFinder: same store/period/price). The survivor
    // takes whichever name has fewer abbreviations ("ŠEIMOS vytinta dešra,
    // 200 g" over "..., a. r., 200 g"; "Šaldytos bulvių ..." over
    // "Šald.bulvių ..."); on a tie the web name, the store's own format
    // with details like "2,5% rieb." inline instead of in info.
    public function mergePair(int $flyerId, int $webId, bool $dryRun): array
    {
        $run = function () use ($flyerId, $webId, $dryRun) {
            $flyer = Product::findOrFail($flyerId);
            $web = Product::findOrFail($webId);

            $base = $this->pickBaseProduct([$flyerId, $webId]);
            $duplicate = $base->id === $flyerId ? $web : $flyer;

            if (!$dryRun) {
                $this->mapName($base->name, $base->id);
            }

            $this->renameBase($base, $this->pickCrossSourceName($flyer->name, $web->name), $dryRun);

            return $this->mergeDuplicateIntoBase($base, $duplicate, $dryRun);
        };

        return $dryRun ? $run() : DB::transaction($run);
    }

    public function pickCrossSourceName(string $flyerName, string $webName): string
    {
        return $this->countNameAbbreviations($webName) > $this->countNameAbbreviations($flyerName)
            ? $flyerName
            : $webName;
    }

    // A short form right after a number is part of a value, not a
    // shortened word ("2,5% rieb.", "3 sl.", "(2 rūš.)", "10 tabl."), so it
    // isn't counted. Everything else is: "a. r.", "Šald.", "art.", "/pak.".
    private function countNameAbbreviations(string $name): int
    {
        preg_match_all('/(?<![\p{L}\d])\p{L}+\./u', $name, $matches, PREG_OFFSET_CAPTURE);

        $count = 0;
        foreach ($matches[0] as [$abbreviation, $offset]) {
            $before = rtrim(substr($name, 0, $offset));
            if (!preg_match('/\d\s*%?\s*\(?$/u', $before)) {
                $count++;
            }
        }

        return $count;
    }

    private function adoptFullerName(Product $base, Product $duplicate, bool $dryRun): void
    {
        // Which row survives (base->id, its slug/URL) is picked by
        // pickBaseProduct() above for other reasons (image
        // quality, id), but its raw catalog `name` is often the
        // abbreviated store-card copy ("Šalt. kavos gėr. ...")
        // while a duplicate carries the fuller flyer-print
        // wording ("Šaltasis kavos gėrimas ..."). Once the caller
        // has established both rows are the same product, the
        // less-abbreviated string is the better display name.
        $this->renameBase($base, $this->pickFullerName($base->name, $duplicate->name), $dryRun);
    }

    private function renameBase(Product $base, string $newName, bool $dryRun): void
    {
        if ($newName !== $base->name) {
            if (!$dryRun) {
                // Record the name we're about to overwrite, not
                // just the duplicate's — otherwise this exact
                // string only still resolves to $base because
                // its slug happens to have been derived from it
                // and slug is left untouched here. That's an
                // implicit, easy-to-break coincidence; an
                // explicit mapping row is the real guarantee,
                // and it also makes product_mapping a complete
                // record of every raw name this product has
                // ever carried instead of missing the very one
                // it started with.
                $this->mapName($base->name, $base->id);
                $base->update(['name' => $newName]);
            } else {
                $base->name = $newName;
            }
        }
    }

    public function mergeDuplicateIntoBase(Product $base, Product $duplicate, bool $dryRun): array
    {
        $this->resolveDiscountConflicts($base->id, $duplicate->id, $dryRun);

        if (!$dryRun) {
            Discount::withoutEvents(function () use ($base, $duplicate) {
                Discount::query()
                    ->where('product_id', $duplicate->id)
                    ->update(['product_id' => $base->id]);
            });

            DB::table('discount_histories')
                ->where('product_id', $duplicate->id)
                ->update(['product_id' => $base->id]);

            $this->reassignFavorites($base->id, $duplicate->id);
            $this->adoptStoreImage($base, $duplicate);

            // Every name either product was known by must resolve to the
            // survivor, or the next scrape of that source recreates the
            // duplicate: the duplicate's own name, and any mapping rows
            // that pointed at it from earlier merges.
            ProductMapping::query()
                ->where('product_id', $duplicate->id)
                ->update(['product_id' => $base->id]);
            $this->mapName($duplicate->name, $base->id);

            $duplicate->delete();
        }

        return [[
            'duplicate_id' => $duplicate->id,
            'duplicate_name' => $duplicate->name,
            'base_id' => $base->id,
            'base_name' => $base->name,
        ]];
    }

    // The survivor is the older row, which is often the flyer one with an
    // image cropped out of the leaflet page. A store e-shop photo is always
    // the better one — same swap ProcessDiscounts does when a web scrape
    // hits a flyer-image product.
    private function adoptStoreImage(Product $base, Product $duplicate): void
    {
        if (!$base->image_from_flyer || $duplicate->image_from_flyer || empty($duplicate->image_url)) {
            return;
        }

        $base->update([
            'image_url' => $duplicate->image_url,
            'image_from_flyer' => false,
            'image_cache_failed_at' => null,
        ]);
    }

    // updateOrCreate, not firstOrCreate: an existing row for this name may
    // point at a product that is being merged away.
    private function mapName(string $name, int $productId): void
    {
        ProductMapping::updateOrCreate(['name' => $name], ['product_id' => $productId]);
    }

    public function resolveDiscountConflicts(int $baseId, int $duplicateId, bool $dryRun): int
    {
        $duplicateDiscounts = Discount::query()
            ->where('product_id', $duplicateId)
            ->get();

        $removed = 0;

        foreach ($duplicateDiscounts as $duplicateDiscount) {
            $conflict = Discount::query()
                ->where('product_id', $baseId)
                ->where('store_id', $duplicateDiscount->store_id)
                ->where('start_at', $duplicateDiscount->start_at)
                ->where('end_at', $duplicateDiscount->end_at)
                ->first();

            if ($conflict) {
                // Keep whichever copy links to the store's own product page
                // (a web-scraped offer) over a flyer one with no URL.
                $loser = empty($conflict->product_url) && !empty($duplicateDiscount->product_url)
                    ? $conflict
                    : $duplicateDiscount;

                if (!$dryRun) {
                    Discount::withoutEvents(function () use ($loser) {
                        $loser->delete();
                    });
                }
                $removed++;
            }
        }

        return $removed;
    }

    private function reassignFavorites(int $baseId, int $duplicateId): void
    {
        $baseUserIds = ProductFavorite::query()
            ->where('product_id', $baseId)
            ->pluck('user_id');

        ProductFavorite::query()
            ->where('product_id', $duplicateId)
            ->whereIn('user_id', $baseUserIds)
            ->delete();

        ProductFavorite::query()
            ->where('product_id', $duplicateId)
            ->update(['product_id' => $baseId]);
    }
}
