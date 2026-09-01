<?php

namespace App\Console\Commands;

use App\Models\GenericProduct;
use App\Models\Product;
use App\Services\MeilisearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MatchGenericProducts extends Command
{
    protected $signature = 'generic-products:match {--reset : Clear existing assignments in touched categories before matching}';

    protected $description = 'Assign products.generic_product_id by matching product names against each generic product\'s keyword search terms, scoped to its category';

    public function handle(): int
    {
        $genericProducts = GenericProduct::with('keywordPage:id,search_terms')->get();
        $byCategory = $genericProducts->groupBy('category_id');

        $totalAssigned = 0;

        foreach ($byCategory as $categoryId => $categoryGenericProducts) {
            // A generic product's OWN name (e.g. "Batonas") must win against a
            // sibling that merely mentions the same word among its own terms
            // (e.g. "Duona"'s terms include the bare word "batonas") — otherwise
            // whichever happens to sort first on term length starves the other
            // entirely. So self-name terms are matched in their own pass,
            // before falling back to the full descriptive term list.
            $selfTermSets = $categoryGenericProducts
                ->map(fn (GenericProduct $gp) => ['generic_product_id' => $gp->id, 'words' => $this->wordsOf($gp->name)])
                ->filter(fn ($t) => count($t['words']) > 0)
                ->sortByDesc(fn ($t) => count($t['words']))
                ->values();

            // Longer/multi-word terms first, so e.g. "avižinis pienas" claims a
            // product before the bare "pienas" term gets a chance to.
            $termSets = $categoryGenericProducts
                ->map(fn (GenericProduct $gp) => [
                    'generic_product' => $gp,
                    'terms' => $this->termsFor($gp),
                ])
                ->flatMap(fn ($entry) => collect($entry['terms'])->map(fn ($term) => [
                    'generic_product_id' => $entry['generic_product']->id,
                    'words' => $term,
                ]))
                ->sortByDesc(fn ($t) => count($t['words']))
                ->values();

            if ($this->option('reset')) {
                Product::where('category_id', $categoryId)->update(['generic_product_id' => null]);
            }

            $assignedInCategory = 0;

            Product::where('category_id', $categoryId)
                ->whereNull('generic_product_id')
                ->select('id', 'name')
                ->chunkById(500, function ($products) use ($selfTermSets, $termSets, &$assignedInCategory) {
                    foreach ($products as $product) {
                        $productWords = $this->wordsOf($product->name);

                        $match = null;
                        foreach ($selfTermSets as $termSet) {
                            if ($this->containsSequence($productWords, $termSet['words'])) {
                                $match = $termSet;
                                break;
                            }
                        }
                        if ($match === null) {
                            foreach ($termSets as $termSet) {
                                if ($this->containsSequence($productWords, $termSet['words'])) {
                                    $match = $termSet;
                                    break;
                                }
                            }
                        }

                        if ($match !== null) {
                            $product->update(['generic_product_id' => $match['generic_product_id']]);
                            $assignedInCategory++;
                        }
                    }
                });

            $categoryName = \App\Models\Category::find($categoryId)?->name ?? "#{$categoryId}";
            $this->info("{$categoryName}: assigned {$assignedInCategory}");
            $totalAssigned += $assignedInCategory;
        }

        $this->info("Total newly assigned: {$totalAssigned}");
        $this->info('Total products with generic_product_id: ' . Product::whereNotNull('generic_product_id')->count());

        return self::SUCCESS;
    }

    /**
     * @return list<list<string>> list of terms, each term as a list of lowercase words
     */
    private function termsFor(GenericProduct $gp): array
    {
        $terms = $gp->search_terms ?? $gp->keywordPage?->search_terms ?? [];

        if (empty($terms)) {
            $terms = [$gp->name];
        }

        return collect($terms)
            ->map(fn ($term) => $this->wordsOf($term))
            ->filter(fn ($words) => count($words) > 0)
            ->values()
            ->all();
    }

    /**
     * Lithuanian diacritics vary across GPT-generated search_terms for the
     * same underlying word (e.g. "varškė"/"varske"/"varskė" all appear in one
     * keyword_page's terms) — fold them away so matching isn't sensitive to
     * which spelling variant a given term happened to keep.
     */
    private const DIACRITIC_MAP = [
        'ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i',
        'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z',
    ];

    /**
     * Words here are Lithuanian-stemmed (via the same MeilisearchService::
     * buildNameStem() used for "similar products" search), not full words —
     * "jautiena"/"jautienos"/"jautienai" and "lašišos"/"lašišų" all reduce
     * to the same stem ("jautien"/"lasis" after diacritic folding), closing
     * a whole class of declension-mismatch misses that used to require a
     * hand-added 'terms' variant per affected generic_products row (see
     * lasisos-file, higieniniai-paketai, dezodorantas in
     * SeedGenericProducts::MANUAL_ADDITIONS). buildNameStem() also strips
     * ALL-CAPS brand words and package-size/unit noise, which matches this
     * command's own "never a brand name" rule for search terms.
     *
     * @return list<string>
     */
    private function wordsOf(string $text): array
    {
        $stem = MeilisearchService::buildNameStem($text);
        $words = $stem !== '' ? explode(' ', $stem) : [];

        $folded = array_map(fn ($w) => strtr(mb_strtolower($w), self::DIACRITIC_MAP), $words);

        return array_values(array_filter($folded, fn ($w) => mb_strlen($w) >= 3));
    }

    /**
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    private function containsSequence(array $haystack, array $needle): bool
    {
        $needleLen = count($needle);
        if ($needleLen === 0) {
            return false;
        }

        $haystackLen = count($haystack);
        for ($i = 0; $i <= $haystackLen - $needleLen; $i++) {
            if (array_slice($haystack, $i, $needleLen) === $needle) {
                return true;
            }
        }

        return false;
    }
}
