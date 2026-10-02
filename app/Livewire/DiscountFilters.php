<?php

namespace App\Livewire;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
use App\Support\ListingDealsFetcher;
use App\Support\MyStores;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

// Ported from discount/src/components/common/product-filter-controls.tsx —
// functional parity (store/category multi-select, order, card/1+1, pagination),
// not every render mode. Mount receives the deals/pagination the controller
// already computed for the current request, so the FIRST response stays
// exactly what Phase 2 verified (plain server-rendered grid, no extra query,
// satisfies the plan's "grid must be in the raw HTML" SEO constraint) — only
// interactions after that re-fetch, in-process, through the same
// Api\ProductController/Api\KeywordPageController methods the JSON API used.
class DiscountFilters extends Component
{
    #[Locked]
    public string $mode; // 'discounts' | 'keyword'

    #[Locked]
    public ?string $primarySlug = null;

    #[Locked]
    public ?string $secondarySlug = null;

    // Per explicit product decision: category and store+category pages show
    // BOTH filters at once (each pre-highlighting whichever facet the URL
    // already fixes) — no longer a single either/or "sidebarMode". Store-only
    // pages show neither (showFilters is false there, unchanged) — that page
    // type still uses <x-store-nav-tabs> for category-switching, untouched.
    #[Locked]
    public bool $showStoreFilter = false;

    #[Locked]
    public bool $showCategoryFilter = false;

    // The slug to pre-highlight in each panel — null means "no row is
    // currently active" (e.g. the plain /akcijos hub's category list, or a
    // category page's store list, where nothing is fixed by the URL).
    #[Locked]
    public ?string $activeStoreSlug = null;

    #[Locked]
    public ?string $activeCategorySlug = null;

    // Filtering/sorting UI (the "Filtrai" bar + sort dropdown) only makes
    // sense where there's something meaningful to narrow down: a category
    // (pick a store), a store+category combo, or a keyword page. The plain
    // /akcijos hub and a plain store page just show everything, in order —
    // no filter/sort bar there, per explicit product decision.
    #[Locked]
    public bool $showFilters = true;

    // Plain store pages show the facet bar (store/category/leaflets pills)
    // but never a sort control — they render curated carousels, not the
    // sortable flat grid, so there's nothing for setOrder() to act on.
    #[Locked]
    public bool $showSort = true;

    // Lists offers from several stores (not a store or store+category page),
    // so the visitor's "Mano parduotuvės" choice applies here.
    #[Locked]
    public bool $multiStore = false;

    #[Url(as: 'order', except: 'popular')]
    public string $order = 'popular';

    #[Url(as: 'store', except: '')]
    public string $storeFilter = '';

    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'card', except: false)]
    public bool $cardOnly = false;

    #[Url(as: 'plus', except: false)]
    public bool $plusOnly = false;

    // Not URL-synced: loadMore() accumulates pages into $deals, so a bookmarked
    // ?page=N would only reproduce that single page, not everything shown by
    // then — same reasoning as not persisting scroll position.
    public int $page = 1;

    public array $deals = [];

    public array $pagination = [];

    public array $allStores = [];

    public array $allCategories = [];

    // Which single popup (if any) is open — 'store' | 'category' | null.
    // Only one at a time, same UX as the old single-panel toggle, just keyed
    // now that up to two independent buttons can open it.
    public ?string $openPanel = null;

    // Rendered carousel markup from the parent listing view — kept only for
    // the initial full-page response and stripped in dehydrate() so it never
    // lands in wire:snapshot.
    public string $carouselHtml = '';

    public bool $showCarousels = false;

    public function mount(string $mode, ?string $primarySlug, ?string $secondarySlug, array $initialDeals, array $initialPagination, string $carouselHtml = '', bool $showCarousels = false, bool $showStoreFilter = false, bool $showCategoryFilter = false, ?string $activeStoreSlug = null, ?string $activeCategorySlug = null, bool $showFilters = true, bool $multiStore = false): void
    {
        $this->multiStore = $multiStore;
        $this->mode = $mode;
        $this->primarySlug = $primarySlug;
        $this->secondarySlug = $secondarySlug;
        $this->showStoreFilter = $showStoreFilter;
        $this->showCategoryFilter = $showCategoryFilter;
        $this->activeStoreSlug = $activeStoreSlug;
        $this->activeCategorySlug = $activeCategorySlug;
        $this->showFilters = $showFilters;
        $this->deals = $initialDeals;
        $this->pagination = $initialPagination;
        $this->carouselHtml = $carouselHtml;
        $this->showCarousels = $showCarousels
            && $this->storeFilter === ''
            && $this->categoryFilter === '';

        // Scoped to whichever facet is already fixed by the URL — production
        // only lists categories a store actually has discounts in (and vice
        // versa), not every category/store site-wide. $activeStoreSlug/
        // $activeCategorySlug (not the raw $primarySlug/$secondarySlug, whose
        // meaning differs by page type) are what tell us which scoping
        // applies:
        // - Categories: scoped to the store on a store+category combo page
        //   ($activeStoreSlug set); unscoped (site-wide) everywhere else
        //   (the plain hub and a category-only page, where there's no store
        //   to scope by).
        // - Stores: scoped to the category on both a category-only page and
        //   a store+category combo page ($activeCategorySlug is the current
        //   category in both cases); on a keyword page, scoped to that
        //   page's own mapped products instead (a store list for "duona"
        //   used to include pharmacies/cosmetics chains that obviously don't
        //   sell bread — every store site-wide, not just the ones actually
        //   selling this keyword's products).
        $this->allCategories = $this->showCategoryFilter
            ? ($activeStoreSlug !== null
                ? json_decode(app(ProductController::class)->getCategoriesForStore($activeStoreSlug)->getContent(), true) ?? []
                : json_decode(app(ProductController::class)->getCategories()->getContent(), true) ?? [])
            : [];

        $this->allStores = $this->showStoreFilter
            ? ($this->mode === 'keyword'
                ? json_decode(app(ProductController::class)->getStoresForKeyword($primarySlug)->getContent(), true)['data'] ?? []
                : ($activeCategorySlug !== null
                    ? json_decode(app(ProductController::class)->getStoresForCategory($activeCategorySlug)->getContent(), true)['data'] ?? []
                    // Plain store page: no category to scope by (same reasoning
                    // as $allCategories' unscoped branch above) — every store
                    // site-wide, not just the current one.
                    : json_decode(app(ProductController::class)->getStores()->getContent(), true)['data'] ?? []))
            : [];
    }

    public function toggleStore(string $slug): void
    {
        $this->storeFilter = $this->toggleInCommaList($this->storeFilter, $slug);
        $this->page = 1;
        $this->showCarousels = false;
        $this->refreshResults();
    }

    // "Mano parduotuvės" changed in the sheet, or the first page view
    // before the browser had mirrored them into the cookie that
    // MyStores::applyToRequest() reads. Empty clears the store filter.
    public function applyStores(string $slugs): void
    {
        if (! $this->multiStore) {
            return;
        }

        $this->storeFilter = implode(',', MyStores::clean($slugs));
        $this->page = 1;
        $this->showCarousels = false;
        $this->refreshResults();
    }

    public function toggleCategory(string $slug): void
    {
        $this->categoryFilter = $this->toggleInCommaList($this->categoryFilter, $slug);
        $this->page = 1;
        $this->showCarousels = false;
        $this->refreshResults();
    }

    public function clearFilters(): void
    {
        $this->storeFilter = '';
        $this->categoryFilter = '';
        $this->page = 1;
        $this->showCarousels = false;
        $this->refreshResults();
    }

    public function setOrder(string $order): void
    {
        // Deliberately doesn't clear $showCarousels — verified against production
        // (/akcijos?order=price_discount_proc_max still shows the per-category
        // carousels, just re-fetched): sorting only switches to the flat grid
        // when it's combined with actually picking a category/store, which
        // toggleCategory()/toggleStore() already handle.
        $this->order = $order;
        $this->page = 1;
        $this->refreshResults();
    }

    public function toggleCard(): void
    {
        $this->cardOnly = ! $this->cardOnly;
        $this->page = 1;
        $this->refreshResults();
    }

    public function togglePlus(): void
    {
        $this->plusOnly = ! $this->plusOnly;
        $this->page = 1;
        $this->refreshResults();
    }

    public function loadMore(): void
    {
        $this->page++;
        $this->pagination = $this->fetchPage();
        $this->deals = [...$this->deals, ...($this->pagination['data'] ?? [])];
    }

    private function toggleInCommaList(string $list, string $slug): string
    {
        $items = array_values(array_filter(array_map('trim', explode(',', $list))));

        if (in_array($slug, $items, true)) {
            $items = array_values(array_diff($items, [$slug]));
        } else {
            $items[] = $slug;
        }

        return implode(',', $items);
    }

    private function fetchPage(): array
    {
        return ListingDealsFetcher::fetch(
            mode: $this->mode,
            primarySlug: $this->primarySlug,
            secondarySlug: $this->secondarySlug,
            page: $this->page,
            order: $this->order,
            storeFilter: $this->storeFilter,
            categoryFilter: $this->categoryFilter,
            cardOnly: $this->cardOnly,
            plusOnly: $this->plusOnly,
        );
    }

    private function refreshResults(): void
    {
        $this->pagination = $this->fetchPage();
        $this->deals = $this->pagination['data'] ?? [];
    }

    public function dehydrate(): void
    {
        $this->carouselHtml = '';
    }

    public function render()
    {
        return view('livewire.discount-filters');
    }
}
