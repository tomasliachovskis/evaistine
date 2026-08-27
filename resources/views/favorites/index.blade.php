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

    <section class="base-container pb-8 pt-4 sm:pb-16 sm:pt-6" x-data="{ filter: 'all', storeFilter: '', categoryFilter: '' }">
        <h1 class="mb-4 text-2xl font-extrabold text-gray-900 sm:text-3xl">Stebimos prekės</h1>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-green/30 bg-green/10 p-4 shadow-sm sm:p-5">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-green/20 sm:size-14">
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

        @if (count($storeTotals) > 0)
            <h2 class="mb-4 text-lg font-semibold sm:text-xl">Parduotuvės</h2>
            <div class="mb-8 flex flex-row flex-wrap justify-start gap-2 sm:gap-3">
                @foreach ($storeTotals as $total)
                    <button
                        type="button"
                        @click="storeFilter = '{{ $total['store_slug'] }}'; $nextTick(() => document.getElementById('favorites-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="w-[calc(33.333%-6px)] rounded-xl border border-gray-200 bg-white p-1.5 text-left transition-colors hover:border-gray-400 sm:w-[140px] sm:p-2"
                    >
                        <div class="mb-1 flex h-5 items-center justify-center sm:h-8 sm:justify-start">
                            @if ($total['store_slug'])
                                <x-store-logo :slug="$total['store_slug']" :name="$total['store_name']" size="xs" class="object-left sm:hidden" />
                                <x-store-logo :slug="$total['store_slug']" :name="$total['store_name']" size="sm" class="hidden object-left sm:block" />
                            @else
                                <span class="text-xs font-semibold text-gray-700 sm:text-sm">{{ $total['store_name'] }}</span>
                            @endif
                        </div>
                        <div class="flex flex-col gap-0.5 sm:gap-1">
                            <div class="text-xs text-gray-600 sm:text-sm">
                                <span class="font-medium">{{ $total['product_count'] }} {{ $total['product_count'] === 1 ? 'akcija' : 'akcijos' }}</span>
                            </div>
                            <div class="text-sm font-bold text-green sm:text-lg">
                                {{ number_format($total['total_price'], 2, ',', ' ') }}€
                            </div>
                        </div>
                    </button>
                @endforeach
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
            @php
                $chipFilled = 'inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white';
                $chipOutline = 'inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green px-4 py-2 text-base font-bold text-green transition-colors hover:bg-green/5';
            @endphp
            <div id="favorites-grid" class="mb-4 flex scroll-mt-24 flex-nowrap items-center gap-2.5 overflow-x-auto">
                <button type="button" @click="filter = 'all'" :class="filter === 'all' ? '{{ $chipFilled }}' : '{{ $chipOutline }}'">
                    Visos
                    <span class="inline-flex min-w-[1.375rem] items-center justify-center rounded-full px-1.5 text-sm font-bold tabular-nums" :class="filter === 'all' ? 'bg-white/25 text-white' : 'bg-green/10 text-green'">{{ count($products) }}</span>
                </button>
                @if ($dropped->isNotEmpty())
                    <button type="button" @click="filter = 'drops'" :class="filter === 'drops' ? '{{ $chipFilled }}' : '{{ $chipOutline }}'">
                        Krenta kaina
                        <span class="inline-flex min-w-[1.375rem] items-center justify-center rounded-full px-1.5 text-sm font-bold tabular-nums" :class="filter === 'drops' ? 'bg-white/25 text-white' : 'bg-green/10 text-green'">{{ $dropped->count() }}</span>
                    </button>
                @endif
                @if ($savingsSummary['expiring_soon_count'] > 0)
                    <button type="button" @click="filter = 'expiring'" :class="filter === 'expiring' ? '{{ $chipFilled }}' : '{{ $chipOutline }}'">
                        Baigiasi greitai
                        <span class="inline-flex min-w-[1.375rem] items-center justify-center rounded-full px-1.5 text-sm font-bold tabular-nums" :class="filter === 'expiring' ? 'bg-white/25 text-white' : 'bg-green/10 text-green'">{{ $savingsSummary['expiring_soon_count'] }}</span>
                    </button>
                @endif
                @if ($activeCount < count($sortedProducts))
                    <button type="button" @click="filter = 'active'" :class="filter === 'active' ? '{{ $chipFilled }}' : '{{ $chipOutline }}'">
                        Tik su akcija
                        <span class="inline-flex min-w-[1.375rem] items-center justify-center rounded-full px-1.5 text-sm font-bold tabular-nums" :class="filter === 'active' ? 'bg-white/25 text-white' : 'bg-green/10 text-green'">{{ $activeCount }}</span>
                    </button>
                @endif
                @if ($storeFilterOptions->isNotEmpty())
                    <div class="relative shrink-0">
                        <select x-model="storeFilter" class="appearance-none rounded-lg border-2 py-2 pl-4 pr-8 text-base font-bold transition-colors focus:outline-none" :class="storeFilter !== '' ? 'border-green text-green' : 'border-gray-300 text-gray-700 hover:border-green hover:text-dark-green'">
                            <option value="">Visos parduotuvės</option>
                            @foreach ($storeFilterOptions as $option)
                                <option value="{{ $option['slug'] }}">{{ $option['name'] }} ({{ $option['count'] }})</option>
                            @endforeach
                        </select>
                        <x-app-icon name="chevron-down" x-show="storeFilter === ''" class="pointer-events-none absolute right-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <button type="button" x-show="storeFilter !== ''" x-cloak @click="storeFilter = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-green hover:text-dark-green">
                            <x-app-icon name="x" class="size-4" />
                        </button>
                    </div>
                @endif
                @if ($categoryFilterOptions->isNotEmpty())
                    <div class="relative shrink-0">
                        <select x-model="categoryFilter" class="appearance-none rounded-lg border-2 py-2 pl-4 pr-8 text-base font-bold transition-colors focus:outline-none" :class="categoryFilter !== '' ? 'border-green text-green' : 'border-gray-300 text-gray-700 hover:border-green hover:text-dark-green'">
                            <option value="">Visos kategorijos</option>
                            @foreach ($categoryFilterOptions as $option)
                                <option value="{{ $option['slug'] }}">{{ $option['name'] }} ({{ $option['count'] }})</option>
                            @endforeach
                        </select>
                        <x-app-icon name="chevron-down" x-show="categoryFilter === ''" class="pointer-events-none absolute right-2.5 top-1/2 size-4 -translate-y-1/2 text-gray-400" />
                        <button type="button" x-show="categoryFilter !== ''" x-cloak @click="categoryFilter = ''" class="absolute right-2 top-1/2 -translate-y-1/2 text-green hover:text-dark-green">
                            <x-app-icon name="x" class="size-4" />
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
