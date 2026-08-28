<?php

namespace App\Livewire;

use App\Http\Controllers\Api\KeywordPageController;
use App\Http\Controllers\Api\ProductController;
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

    // Only set on a store+category combo page (both slugs from the URL path,
    // e.g. /akcijos/lidl/bakaleja) — lets the selected-filters row show a
    // removable "Lidl" chip without an extra store lookup, since allStores
    // stays empty in that mode (see mount()).
    #[Locked]
    public ?string $primaryStoreName = null;

    // 'categories' when browsing a store (pick a category) or 'stores' when
    // browsing a category/keyword page (pick a store) — see AkcijosController.
    #[Locked]
    public string $sidebarMode = 'categories';

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

    public bool $panelOpen = false;

    // Ported from akcijos/[...slug].tsx's showStoreCarousels: a plain store
    // page (no filters active) shows per-category "best deals" carousels
    // instead of the flat grid. Any filter/sort interaction clears this and
    // falls back to the flat grid — same "still one click away" behavior as
    // the original's CategoryCarouselsLayout, just without a full page nav.
    public array $sections = [];

    public function mount(string $mode, ?string $primarySlug, ?string $secondarySlug, array $initialDeals, array $initialPagination, array $initialSections = [], string $sidebarMode = 'categories', ?string $primaryStoreName = null): void
    {
        $this->mode = $mode;
        $this->primarySlug = $primarySlug;
        $this->secondarySlug = $secondarySlug;
        $this->sidebarMode = $sidebarMode;
        $this->primaryStoreName = $primaryStoreName;
        $this->deals = $initialDeals;
        $this->pagination = $initialPagination;
        $this->sections = $initialSections;

        // Scoped to the store/category already fixed by the URL — production
        // only lists categories a store actually has discounts in (and vice
        // versa), not every category/store site-wide. The plain /akcijos hub
        // has no store to scope by, so it gets the unscoped (site-wide) list.
        if ($this->sidebarMode === 'categories' && $this->primarySlug === null) {
            $this->allStores = [];
            $this->allCategories = json_decode(app(ProductController::class)->getCategories()->getContent(), true) ?? [];
        } elseif ($this->sidebarMode === 'categories') {
            $this->allStores = [];
            $this->allCategories = json_decode(app(ProductController::class)->getCategoriesForStore($this->primarySlug)->getContent(), true) ?? [];
        } elseif ($this->mode !== 'keyword') {
            $this->allStores = json_decode(app(ProductController::class)->getStoresForCategory($this->primarySlug)->getContent(), true)['data'] ?? [];
            $this->allCategories = [];
        } else {
            $this->allStores = json_decode(app(ProductController::class)->getStores()->getContent(), true)['data'] ?? [];
            $this->allCategories = [];
        }
    }

    public function toggleStore(string $slug): void
    {
        $this->storeFilter = $this->toggleInCommaList($this->storeFilter, $slug);
        $this->page = 1;
        $this->sections = [];
        $this->refreshResults();
    }

    public function toggleCategory(string $slug): void
    {
        $this->categoryFilter = $this->toggleInCommaList($this->categoryFilter, $slug);
        $this->page = 1;
        $this->sections = [];
        $this->refreshResults();
    }

    public function setOrder(string $order): void
    {
        // Deliberately doesn't clear $sections — verified against production
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
        $this->cardOnly = !$this->cardOnly;
        $this->page = 1;
        $this->refreshResults();
    }

    public function togglePlus(): void
    {
        $this->plusOnly = !$this->plusOnly;
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
        $filters = array_filter([
            'order' => $this->order,
            'card' => $this->cardOnly ? '1' : null,
            'plus' => $this->plusOnly ? '1' : null,
            'page' => $this->page > 1 ? (string) $this->page : null,
            'store' => $this->storeFilter !== '' ? $this->storeFilter : null,
            'category' => $this->categoryFilter !== '' ? $this->categoryFilter : null,
        ], fn ($value) => $value !== null);

        // ProductController/KeywordPageController read filters off the global
        // request() helper rather than taking them as params — a Livewire
        // update request has no meaningful query string of its own to clobber,
        // so replacing it here is how the existing filter-building/caching
        // logic gets reused unmodified.
        request()->query->replace($filters);

        if ($this->mode === 'keyword') {
            $payload = json_decode(app(KeywordPageController::class)->show(request(), $this->primarySlug)->getContent(), true);
        } elseif ($this->primarySlug === null) {
            // Plain /akcijos hub — no store or category fixed by the URL.
            $payload = json_decode(app(ProductController::class)->getAllDiscounts()->getContent(), true);
        } else {
            $response = $this->secondarySlug
                ? app(ProductController::class)->getDiscounts($this->primarySlug, $this->secondarySlug)
                : app(ProductController::class)->getDiscounts($this->primarySlug);
            $payload = json_decode($response->getContent(), true);
        }

        return $payload['data'] ?? [];
    }

    private function refreshResults(): void
    {
        $this->pagination = $this->fetchPage();
        $this->deals = $this->pagination['data'] ?? [];
    }

    public function render()
    {
        return view('livewire.discount-filters');
    }
}
