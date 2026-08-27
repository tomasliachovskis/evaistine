<x-layouts.app :title="$title" :robots="$robots">
    @php
        $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';

        // price_change_amount is already computed server-side per product
        // (Api\ProductController::getFavoriteProducts() ->
        // HomePageSectionsService::resolvePriceChangeAmount()) but was never
        // surfaced anywhere on this page — it's exactly "how much cheaper
        // this got since we last saw it", the whole point of favoriting
        // something. Sorted biggest drop first, capped to keep the strip
        // from growing unbounded.
        $dropped = collect($products)
            ->filter(fn ($deal) => ($deal['price_change_amount'] ?? 0) > 0)
            ->sortByDesc('price_change_amount')
            ->take(8)
            ->values();

        // Same 24h window resolveSavingsSummary() already uses for the
        // header count — recomputed per product here so each card can flag
        // itself, not just contribute to one aggregate number.
        $today = \Illuminate\Support\Carbon::today();
        $isExpiringSoon = function (array $deal) use ($today) {
            if (empty($deal['to_date'])) {
                return false;
            }
            $end = \Illuminate\Support\Carbon::parse($deal['to_date'])->endOfDay();
            $daysLeft = (int) ceil(($end->timestamp - $today->timestamp) / 86400);

            return $daysLeft >= 0 && $daysLeft <= 1;
        };

        // A favorited product can currently have no active discount at all
        // (Api\ProductController::getFavoriteProducts() still includes it,
        // via formatProduct() rather than the Discount-backed formatter, with
        // discounted_price/discount_percent both null) — push those to the
        // end instead of leaving them mixed in among products actually on
        // sale right now, and flag them so it's obvious why there's no price.
        $hasActiveDiscount = fn (array $deal) => ($deal['discounted_price'] ?? 0) > 0 || !empty($deal['discount_percent']);
        $sortedProducts = collect($products)->sortBy(fn ($deal) => $hasActiveDiscount($deal) ? 0 : 1)->values();
        $activeCount = $sortedProducts->filter($hasActiveDiscount)->count();

        // Primary store per card for the store dropdown filter — the active
        // offer matching this deal's own store_id when there is one, else the
        // first offer, else (no active discount left at all) the last known
        // store from price history, so even faded-out cards stay filterable.
        $dealStore = function (array $deal) {
            $offers = collect($deal['offers'] ?? []);
            $storeId = $deal['store_id'] ?? null;
            $match = $storeId ? $offers->firstWhere('store.id', $storeId) : null;

            return $match['store'] ?? $offers->first()['store'] ?? collect($deal['history'] ?? [])->first()['store'] ?? null;
        };
        $dealCategory = fn (array $deal) => $deal['product']['category'] ?? null;

        $storeFilterOptions = $sortedProducts->map($dealStore)->filter()->groupBy('slug')
            ->map(fn ($g) => ['slug' => $g->first()['slug'], 'name' => $g->first()['name'], 'count' => $g->count()])
            ->sortBy('name')->values();
        $categoryFilterOptions = $sortedProducts->map($dealCategory)->filter()->groupBy('slug')
            ->map(fn ($g) => ['slug' => $g->first()['slug'], 'name' => $g->first()['name'], 'count' => $g->count()])
            ->sortBy('name')->values();
    @endphp

    <section class="base-container py-8 sm:py-16" x-data="{ filter: 'all', storeFilter: '', categoryFilter: '' }">
        <h1 class="mb-4 text-2xl font-extrabold text-gray-900 sm:text-3xl">Stebimos prekės</h1>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-6 flex items-center gap-4 rounded-2xl border border-green/20 bg-green/5 p-4 sm:p-5">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-green/15 sm:size-14">
                    <x-app-icon name="wallet" class="size-6 text-dark-green sm:size-7" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-gray-600">Galite sutaupyti dabar</p>
                    <p class="text-2xl font-bold leading-tight text-dark-green sm:text-3xl">{{ $euro($savingsSummary['total_savings']) }}</p>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ count($products) }} {{ count($products) === 1 ? 'stebima prekė' : 'stebimos prekės' }}
                        @if ($savingsSummary['expiring_soon_count'] > 0)
                            · {{ $savingsSummary['expiring_soon_count'] === 1 ? '1 akcija baigiasi per 24 val.' : $savingsSummary['expiring_soon_count'] . ' akcijos baigiasi per 24 val.' }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        @if (count($storeTotals) > 0)
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                @foreach ($storeTotals as $total)
                    <a
                        href="{{ $total['store_slug'] ? '/akcijos/' . $total['store_slug'] : '#' }}"
                        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 transition-colors hover:border-green/40"
                    >
                        @if ($total['store_slug'])
                            <img
                                src="/assets/stores/{{ $total['store_slug'] }}.svg"
                                alt=""
                                class="h-8 w-auto max-w-[3.5rem] shrink-0 object-contain"
                            >
                        @else
                            <span class="shrink-0 text-sm font-semibold text-gray-700">{{ $total['store_name'] }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">
                                {{ $total['product_count'] }} {{ $total['product_count'] === 1 ? 'prekė' : 'prekės' }}
                            </p>
                            @if ($total['total_savings'] > 0)
                                <p class="text-xs font-semibold text-dark-green">−{{ $euro($total['total_savings']) }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($dropped->isNotEmpty())
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold sm:text-xl">Atpigo nuo paskutinio karto</h2>
                <div class="scroll-cards-x -mx-1 flex min-w-0 gap-3 px-1 sm:mx-0 sm:px-0">
                    @foreach ($dropped as $deal)
                        <div class="relative w-[160px] shrink-0 sm:w-[186px]">
                            <span class="absolute left-2 top-2 z-20 rounded-lg bg-green px-2 py-1 text-xs font-bold text-white">
                                −{{ $euro($deal['price_change_amount']) }}
                            </span>
                            <x-deal-card :deal="$deal" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (count($products) === 0)
            <div class="rounded-xl border bg-card p-8 text-center">
                <x-app-icon name="heart" class="mx-auto size-8 text-gray-300" />
                <p class="mt-3 text-gray-600">
                    Jūs dar neturite mėgstamiausių prekių. Pridėkite prekes prie mėgstamiausių, paspaudę ant širdelės ikonos.
                </p>
            </div>
        @else
            <div class="mb-4 flex items-center gap-2 overflow-x-auto">
                <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-dark-green text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors">
                    Visos <span class="tabular-nums">({{ count($products) }})</span>
                </button>
                @if ($dropped->isNotEmpty())
                    <button type="button" @click="filter = 'drops'" :class="filter === 'drops' ? 'bg-dark-green text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors">
                        Krenta kaina <span class="tabular-nums">({{ $dropped->count() }})</span>
                    </button>
                @endif
                @if ($savingsSummary['expiring_soon_count'] > 0)
                    <button type="button" @click="filter = 'expiring'" :class="filter === 'expiring' ? 'bg-dark-green text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors">
                        Baigiasi greitai <span class="tabular-nums">({{ $savingsSummary['expiring_soon_count'] }})</span>
                    </button>
                @endif
                @if ($activeCount < count($sortedProducts))
                    <button type="button" @click="filter = 'active'" :class="filter === 'active' ? 'bg-dark-green text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'" class="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors">
                        Tik su akcija <span class="tabular-nums">({{ $activeCount }})</span>
                    </button>
                @endif
                @if ($storeFilterOptions->isNotEmpty())
                    <div class="relative shrink-0">
                        <select x-model="storeFilter" class="appearance-none rounded-full border border-gray-200 py-1.5 pl-3 pr-7 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 focus:outline-none">
                            <option value="">Visos parduotuvės</option>
                            @foreach ($storeFilterOptions as $option)
                                <option value="{{ $option['slug'] }}">{{ $option['name'] }} ({{ $option['count'] }})</option>
                            @endforeach
                        </select>
                        <x-app-icon name="chevron-down" x-show="storeFilter === ''" class="pointer-events-none absolute right-2 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
                        <button type="button" x-show="storeFilter !== ''" x-cloak @click="storeFilter = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <x-app-icon name="x" class="size-3.5" />
                        </button>
                    </div>
                @endif
                @if ($categoryFilterOptions->isNotEmpty())
                    <div class="relative shrink-0">
                        <select x-model="categoryFilter" class="appearance-none rounded-full border border-gray-200 py-1.5 pl-3 pr-7 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50 focus:outline-none">
                            <option value="">Visos kategorijos</option>
                            @foreach ($categoryFilterOptions as $option)
                                <option value="{{ $option['slug'] }}">{{ $option['name'] }} ({{ $option['count'] }})</option>
                            @endforeach
                        </select>
                        <x-app-icon name="chevron-down" x-show="categoryFilter === ''" class="pointer-events-none absolute right-2 top-1/2 size-3.5 -translate-y-1/2 text-gray-400" />
                        <button type="button" x-show="categoryFilter !== ''" x-cloak @click="categoryFilter = ''" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <x-app-icon name="x" class="size-3.5" />
                        </button>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-3 gap-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($sortedProducts as $deal)
                    @php
                        $cardStoreSlug = $dealStore($deal)['slug'] ?? '';
                        $cardCategorySlug = $dealCategory($deal)['slug'] ?? '';
                    @endphp
                    <div
                        x-show="(filter === 'all' || (filter === 'drops' && {{ ($deal['price_change_amount'] ?? 0) > 0 ? 'true' : 'false' }}) || (filter === 'expiring' && {{ $isExpiringSoon($deal) ? 'true' : 'false' }}) || (filter === 'active' && {{ $hasActiveDiscount($deal) ? 'true' : 'false' }})) && (storeFilter === '' || storeFilter === '{{ $cardStoreSlug }}') && (categoryFilter === '' || categoryFilter === '{{ $cardCategorySlug }}')"
                        @if (!$hasActiveDiscount($deal)) class="relative opacity-60" @endif
                    >
                        @unless ($hasActiveDiscount($deal))
                            <span class="absolute left-1/2 top-1 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-gray-900/80 px-2 py-0.5 text-[10px] font-semibold text-white">
                                Nėra akcijos šiuo metu
                            </span>
                        @endunless
                        <x-deal-card :deal="$deal" />
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
