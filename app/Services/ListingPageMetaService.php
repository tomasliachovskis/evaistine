<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ListingPageMetaService
{
    public function __construct(
        private PageFreshnessService $freshnessService
    ) {
    }

    public function buildForStore(Store $store): array
    {
        $storeName = $store->name;
        $validity = $this->resolveStoreValidity($store);
        $topCategories = $this->getTopCategoriesForStore($store);
        $otherStores = $this->getOtherStores($store->id);

        return [
            'type' => 'store',
            'store_slug' => $store->slug,
            'store_name' => $storeName,
            'intro' => $this->buildStoreIntro($storeName, $store->slug, $validity),
            'popular_this_week' => $this->mapPopularCategoriesForStore($store->slug, $topCategories),
            'popular_carousel_title' => 'Populiaru šią savaitę',
            'sections' => [
                'top_categories' => $topCategories,
                'latest_leaflet' => $this->buildLeaflet($store, $storeName, $validity),
                'faq' => $this->buildStoreFaq($storeName, $store->slug),
                'other_stores' => $otherStores,
            ],
        ];
    }

    public function buildForCategory(Category $category): array
    {
        $categoryName = $category->name;
        $categorySlug = $category->slug;
        $validity = $this->freshnessService->getCurrentWeekRange();
        $storeComparison = $this->getStoreComparisonForCategory($category);
        $stats = $this->buildCategoryStats($category, $categoryName, $storeComparison);
        $maxDiscount = $this->getMaxDiscountForCategory($category);
        $totalOffers = $this->getDiscountCountForCategory($category);

        return [
            'type' => 'category',
            'category_slug' => $categorySlug,
            'category_name' => $categoryName,
            'intro' => [
                'description' => 'Palyginkite ' . mb_strtolower($categoryName) . ' akcijas visuose pagrindiniuose prekybos tinkluose. Matysite didžiausias nuolaidas ir aktyvių pasiūlymų skaičių kiekvienoje parduotuvėje.',
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
                'quick_stats' => [
                    ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers],
                    ['label' => 'Didžiausia nuolaida', 'value' => '-' . (int) $maxDiscount . '%'],
                    ['label' => 'Parduotuvių', 'value' => (string) count($storeComparison)],
                ],
                'discovery_chips' => [
                    ['label' => 'Žemiausios kainos šiandien', 'href' => "/akcijos/{$categorySlug}?order=price"],
                    ['label' => 'Didžiausios nuolaidos', 'href' => "/akcijos/{$categorySlug}?order=discount"],
                    ['label' => 'Vasaros derlius', 'href' => "/akcijos/{$categorySlug}"],
                    ['label' => 'Ekologiški produktai', 'href' => "/akcijos/{$categorySlug}"],
                ],
            ],
            'popular_carousel_title' => 'Kur šiuo metu daugiausia akcijų',
            'popular_this_week' => array_map(function ($row) use ($categorySlug) {
                return [
                    'label' => $row['store'],
                    'href' => $row['href'],
                    'discounts_count' => $row['offers_count'],
                    'subtitle' => 'nuo -' . $row['max_discount_percent'] . '%',
                    'image_slug' => $categorySlug,
                ];
            }, $storeComparison),
            'sections' => [
                'category_stats' => $stats,
                'top_brands' => $this->getTopBrandsForCategory($category),
                'weekly_deals' => $this->getWeeklyDealsForCategory($category, $categorySlug),
                'seasonal_modules' => $this->getSeasonalModules($categorySlug),
                'faq' => $this->buildCategoryFaq($categoryName),
            ],
        ];
    }

    public function buildForStoreCategory(Store $store, Category $category): array
    {
        $storeName = $store->name;
        $categoryName = $category->name;
        $validity = $this->resolveStoreValidity($store);

        return [
            'type' => 'store_category',
            'store_slug' => $store->slug,
            'store_name' => $storeName,
            'category_slug' => $category->slug,
            'category_name' => $categoryName,
            'intro' => [
                'description' => "Visos {$storeName} " . mb_strtolower($categoryName) . ' akcijos vienoje vietoje. Peržiūrėkite savaitės pasiūlymus ir sutaupykite apsipirkdami sezoninius produktus.',
                'valid_from' => $validity['valid_from'],
                'valid_to' => $validity['valid_to'],
            ],
            'popular_this_week' => [],
            'popular_carousel_title' => 'Populiaru šią savaitę',
            'sections' => [
                'faq' => $this->buildStoreFaq($storeName, $store->slug),
            ],
        ];
    }

    private function resolveStoreValidity(Store $store): array
    {
        if ($store->flyer_valid_from && $store->flyer_valid_to) {
            return [
                'valid_from' => $store->flyer_valid_from->format('Y-m-d'),
                'valid_to' => $store->flyer_valid_to->format('Y-m-d'),
            ];
        }

        $minStart = Discount::where('store_id', $store->id)->min('start_at');
        $maxEnd = Discount::where('store_id', $store->id)->max('end_at');

        if ($minStart && $maxEnd) {
            return [
                'valid_from' => Carbon::parse($minStart)->format('Y-m-d'),
                'valid_to' => Carbon::parse($maxEnd)->format('Y-m-d'),
            ];
        }

        return $this->freshnessService->getCurrentWeekRange();
    }

    private function buildStoreIntro(string $storeName, string $storeSlug, array $validity): array
    {
        $words = $this->getStoreLeafletWords($storeSlug);

        return [
            'description' => "Peržiūrėkite {$storeName} savaitės {$words['accusative']} ir visas aktyvias akcijas vienoje vietoje. Kainos atnaujinamos kasdien pagal galiojantį leidinį.",
            'valid_from' => $validity['valid_from'],
            'valid_to' => $validity['valid_to'],
        ];
    }

    private function buildLeaflet(Store $store, string $storeName, array $validity): ?array
    {
        $imageUrl = $store->flyer_image_url;
        if (!$imageUrl) {
            return null;
        }

        return [
            'title' => "{$storeName} akcijų leidinys",
            'image_url' => $imageUrl,
            'view_url' => "/akcijos/{$store->slug}",
            'pdf_url' => $store->flyer_pdf_url,
            'valid_from' => $validity['valid_from'],
            'valid_to' => $validity['valid_to'],
        ];
    }

    private function getTopCategoriesForStore(Store $store): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('discounts.store_id', $store->id)
            ->whereNull('categories.parent_id')
            ->select(
                'categories.name',
                'categories.slug',
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.slug')
            ->orderByDesc('offers_count')
            ->limit(8)
            ->get();

        return $rows->map(function ($row) use ($store) {
            return [
                'name' => $row->name,
                'href' => "/akcijos/{$store->slug}/{$row->slug}",
                'max_discount_percent' => (int) round($row->max_discount_percent ?? 0),
                'image_slug' => $row->slug,
                'offers_count' => (int) $row->offers_count,
            ];
        })->values()->all();
    }

    private function mapPopularCategoriesForStore(string $storeSlug, array $topCategories): array
    {
        return array_slice(array_map(function ($cat) use ($storeSlug) {
            return [
                'label' => $cat['name'],
                'href' => $cat['href'],
                'discounts_count' => $cat['offers_count'] ?? 0,
                'image_slug' => $cat['image_slug'],
            ];
        }, $topCategories), 0, 6);
    }

    private function getOtherStores(int $excludeStoreId): array
    {
        return Store::query()
            ->where('id', '!=', $excludeStoreId)
            ->withCount('discounts')
            ->get()
            ->filter(fn (Store $s) => $s->discounts_count > 0)
            ->sortByDesc('discounts_count')
            ->take(5)
            ->map(fn (Store $s) => [
                'name' => $s->name,
                'slug' => $s->slug,
                'href' => "/akcijos/{$s->slug}",
                'discounts_count' => $s->discounts_count,
            ])
            ->values()
            ->all();
    }

    private function getStoreComparisonForCategory(Category $category): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->join('stores', 'stores.id', '=', 'discounts.store_id')
            ->where('products.category_id', $category->id)
            ->select(
                'stores.name as store',
                'stores.slug as store_slug',
                DB::raw('COUNT(discounts.id) as offers_count'),
                DB::raw('MAX(discounts.discount_percent) as max_discount_percent'),
                DB::raw('ROUND(AVG(discounts.discount_percent), 0) as avg_discount_percent')
            )
            ->groupBy('stores.id', 'stores.name', 'stores.slug')
            ->orderByDesc('offers_count')
            ->limit(8)
            ->get();

        return $rows->map(function ($row) use ($category) {
            return [
                'store' => $row->store,
                'href' => "/akcijos/{$row->store_slug}/{$category->slug}",
                'offers_count' => (int) $row->offers_count,
                'max_discount_percent' => (int) round($row->max_discount_percent ?? 0),
                'avg_discount_percent' => (int) round($row->avg_discount_percent ?? 0),
            ];
        })->values()->all();
    }

    private function buildCategoryStats(Category $category, string $categoryName, array $storeComparison): array
    {
        $totalOffers = $this->getDiscountCountForCategory($category);
        $maxDiscount = $this->getMaxDiscountForCategory($category);
        $avgDiscount = (int) round(
            Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->avg('discount_percent') ?? 0
        );
        $storeCount = count($storeComparison);

        return [
            'title' => $categoryName . ' akcijų statistika',
            'summary' => "Šiuo metu SuperAkcijos.lt stebi {$totalOffers} aktyvių " . mb_strtolower($categoryName) . " akcijų {$storeCount} prekybos tinkluose. Didžiausia aptikta nuolaida siekia {$maxDiscount} %, o vidutinis sutaupymas šioje kategorijoje – apie {$avgDiscount} %.",
            'highlights' => [
                ['label' => 'Aktyvios akcijos', 'value' => (string) $totalOffers],
                ['label' => 'Vidutinė nuolaida', 'value' => $avgDiscount . ' %'],
                ['label' => 'Didžiausia nuolaida', 'value' => $maxDiscount . ' %'],
                ['label' => 'Prekybos tinklai', 'value' => (string) $storeCount],
            ],
            'store_comparison' => $storeComparison,
            'top_discounted_products' => $this->getTopDiscountedProductsForCategory($category),
            'updated_at' => Carbon::now()->format('Y-m-d'),
        ];
    }

    private function getTopDiscountedProductsForCategory(Category $category): array
    {
        return Discount::query()
            ->with(['product', 'store'])
            ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->limit(5)
            ->get()
            ->map(fn (Discount $d) => [
                'name' => $d->product->name,
                'store' => $d->store->name,
                'discount_percent' => (int) round($d->discount_percent),
            ])
            ->values()
            ->all();
    }

    private function getTopBrandsForCategory(Category $category): array
    {
        $rows = DB::table('discounts')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->where('products.category_id', $category->id)
            ->whereNotNull('products.brand')
            ->where('products.brand', '!=', '')
            ->select('products.brand as name', DB::raw('COUNT(discounts.id) as deals_count'))
            ->groupBy('products.brand')
            ->orderByDesc('deals_count')
            ->limit(5)
            ->get();

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'href' => "/akcijos/{$category->slug}",
            'deals_count' => (int) $row->deals_count,
        ])->values()->all();
    }

    private function getWeeklyDealsForCategory(Category $category, string $categorySlug): array
    {
        return Discount::query()
            ->with(['product', 'store'])
            ->whereHas('product', fn ($q) => $q->where('category_id', $category->id))
            ->whereNotNull('discount_percent')
            ->orderByDesc('discount_percent')
            ->limit(4)
            ->get()
            ->map(fn (Discount $d) => [
                'name' => $d->product->name,
                'href' => "/akcijos/{$categorySlug}",
                'discount_percent' => (int) round($d->discount_percent),
                'store_name' => $d->store->name,
                'image_slug' => $categorySlug,
            ])
            ->values()
            ->all();
    }

    private function getSeasonalModules(string $categorySlug): array
    {
        $modules = config("listing.seasonal_modules.{$categorySlug}", []);

        return array_map(function ($module) use ($categorySlug) {
            $hrefSuffix = $module['href_suffix'] ?? '';
            $href = $hrefSuffix !== ''
                ? "/akcijos/{$hrefSuffix}"
                : "/akcijos/{$categorySlug}";

            return [
                'title' => $module['title'],
                'description' => $module['description'],
                'href' => $href,
                'image_slug' => $module['image_slug'] ?? $categorySlug,
                'tag' => $module['tag'] ?? null,
            ];
        }, $modules);
    }

    private function getDiscountCountForCategory(Category $category): int
    {
        return Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))->count();
    }

    private function getMaxDiscountForCategory(Category $category): int
    {
        return (int) round(
            Discount::whereHas('product', fn ($q) => $q->where('category_id', $category->id))
                ->max('discount_percent') ?? 0
        );
    }

    private function getStoreLeafletWords(string $storeSlug): array
    {
        if ($storeSlug === 'iki') {
            return ['accusative' => 'leidynį', 'nominative' => 'leidynys'];
        }

        return ['accusative' => 'leidinį', 'nominative' => 'leidinys'];
    }

    private function buildStoreFaq(string $storeName, string $storeSlug): array
    {
        $words = $this->getStoreLeafletWords($storeSlug);

        return [
            [
                'question' => "Kur rasti {$storeName} {$words['nominative']}?",
                'answer' => "Naujausią {$storeName} {$words['nominative']} rasite šiame puslapyje – viršuje matote akcijų pasiūlymus, o po prekių sąrašu pateiktas aktualus {$storeName} akcijų leidinys su galiojimo datomis.",
            ],
            [
                'question' => "Nuo kada galioja {$storeName} leidinys?",
                'answer' => "{$storeName} savaitės akcijos paprastai galioja nuo pirmadienio iki sekmadienio. Tikslią datą matote prie leidinio bloko ir kiekvieno pasiūlymo.",
            ],
            [
                'question' => "Kaip dažnai atnaujinamos {$storeName} akcijos?",
                'answer' => "{$storeName} akcijos atnaujinamos kasdien. Naujas savaitės {$words['nominative']} skelbiamas kiekvieną savaitę, o akcijų kainos syncinamos automatiškai.",
            ],
            [
                'question' => "Ar {$storeName} akcijos galioja visose parduotuvėse?",
                'answer' => "Dažniausiai taip – savaitės akcijos galioja visame {$storeName} tinkle Lietuvoje, nebent leidinyje nurodyta kitaip.",
            ],
            [
                'question' => "Ar galima atsisiųsti {$storeName} {$words['accusative']} PDF formatu?",
                'answer' => "Jei turime PDF nuorodą, ją rasite {$storeName} leidinio bloke po prekių sąrašu. Ten pat galite peržiūrėti visas akcijas interaktyviai.",
            ],
        ];
    }

    private function buildCategoryFaq(string $categoryName): array
    {
        $lower = mb_strtolower($categoryName);

        return [
            [
                'question' => "Kur šiandien pigiausia pirkti bananus?",
                'answer' => 'Kainos keičiasi kasdien. Palyginkite akcijas pagal parduotuvę arba filtruokite pagal didžiausią nuolaidą – matysite aktualius pasiūlymus visuose tinkluose.',
            ],
            [
                'question' => "Ar sezoniniai {$lower} visada pigesni akcijų metu?",
                'answer' => 'Dažniausiai taip – sezoniniai produktai būna pigiausi būtent per savaitės akcijas.',
            ],
            [
                'question' => 'Kaip greitai rasti ekologiškus produktus?',
                'answer' => 'Naudokite filtrus arba kategorijos nuorodas viršuje.',
            ],
        ];
    }
}
