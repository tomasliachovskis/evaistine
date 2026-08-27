<x-layouts.app :title="$title" :robots="$robots">
    @php
        $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';

        $hasActiveDiscount = fn (array $deal) => ($deal['discounted_price'] ?? 0) > 0 || !empty($deal['discount_percent']);

        // Default order: biggest price drops first (what got cheaper since
        // last time — the whole point of favoriting something), then other
        // active discounts, then no-longer-discounted products last.
        $sortedProducts = collect($products)->sortBy(function ($deal) use ($hasActiveDiscount) {
            $dropAmount = (float) ($deal['price_change_amount'] ?? 0);
            $group = $dropAmount > 0 ? 0 : ($hasActiveDiscount($deal) ? 1 : 2);

            return [$group, -$dropAmount];
        })->values();
        $activeCount = $sortedProducts->filter($hasActiveDiscount)->count();

        // Standard sort dropdown (same options/UI pattern as the /akcijos
        // listing pages) — client-side via CSS `order`, since this list is
        // small enough that a full page reload per sort isn't needed. One
        // rank map per mode, keyed by product id.
        $rankOf = fn ($collection) => $collection->values()->mapWithKeys(fn ($deal, $i) => [$deal['product']['id'] => $i])->all();
        $sortRanks = [
            'default' => $rankOf($sortedProducts),
            'price_min' => $rankOf($sortedProducts->sortBy(fn ($deal) => (float) ($deal['discounted_price'] ?: PHP_INT_MAX))),
            'price_max' => $rankOf($sortedProducts->sortByDesc(fn ($deal) => (float) ($deal['discounted_price'] ?? 0))),
            'discount_percent' => $rankOf($sortedProducts->sortByDesc(fn ($deal) => (float) ($deal['discount_percent'] ?? 0))),
        ];
        $sortOptions = [
            'default' => ['label' => 'Numatytasis', 'icon' => 'trending-up'],
            'price_min' => ['label' => 'Mažiausia kaina', 'icon' => 'arrow-down'],
            'price_max' => ['label' => 'Didžiausia kaina', 'icon' => 'arrow-up'],
            'discount_percent' => ['label' => 'Didž. nuolaida (%)', 'icon' => 'percent'],
        ];

        $dealStore = function (array $deal) {
            $offers = collect($deal['offers'] ?? []);
            $storeId = $deal['store_id'] ?? null;
            $match = $storeId ? $offers->firstWhere('store.id', $storeId) : null;

            return $match['store'] ?? $offers->first()['store'] ?? collect($deal['history'] ?? [])->first()['store'] ?? null;
        };
        $dealCategory = fn (array $deal) => $deal['product']['category'] ?? null;

        $categoryFilterOptions = $sortedProducts->map($dealCategory)->filter()->groupBy('slug')
            ->map(fn ($g) => ['slug' => $g->first()['slug'], 'name' => $g->first()['name'], 'count' => $g->count()])
            ->sortBy('name')->values();

        // Hidden for now — favorites lists are small/curated enough that the
        // store filter usually covers it; flip this back on if that changes.
        $showCategoryFilter = false;

        $allStoresTotalPrice = collect($storeTotals)->sum('total_price');

        // Shared building blocks for the "checkmark + active border" pattern
        // used across store rows and the category filter rows.
        $rowActive = 'border-green bg-green/10';
        $rowInactive = 'border-gray-200 hover:border-gray-400';
        $checkBase = 'flex size-6 shrink-0 items-center justify-center rounded-full border-2 transition-colors';
        $checkActive = 'border-green bg-green';
        $checkInactive = 'border-gray-300 bg-white';
    @endphp

    <section class="base-container pb-12 pt-4 sm:pt-6" x-data="{
        storeFilter: '', categoryFilter: '',
        categoryOpen: false, sort: 'default', sortOpen: false,
        categoryNames: { @foreach ($categoryFilterOptions as $option) '{{ $option['slug'] }}': @js($option['name']), @endforeach },
    }">
        <h1 class="mb-5 text-3xl font-extrabold text-gray-900 sm:text-4xl">Stebimos prekės</h1>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-green bg-green/10 p-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-green/20">
                    <x-app-icon name="wallet" class="size-6 text-dark-green" />
                </div>
                <div class="min-w-0">
                    <p class="text-base text-gray-700">Galite sutaupyti dabar</p>
                    <p class="text-3xl font-extrabold leading-tight text-dark-green sm:text-4xl">{{ $euro($savingsSummary['total_savings']) }}</p>
                    <p class="mt-1 text-sm text-gray-700">
                        {{ count($products) }} {{ count($products) === 1 ? 'stebima prekė' : 'stebimos prekės' }}
                        @if ($savingsSummary['expiring_soon_count'] > 0)
                            · {{ $savingsSummary['expiring_soon_count'] === 1 ? '1 akcija baigiasi per 24 val.' : $savingsSummary['expiring_soon_count'] . ' akcijos baigiasi per 24 val.' }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        @if (count($storeTotals) > 0)
            @php
                $gridCellBase = 'flex flex-col items-start gap-1 rounded-xl border-2 bg-white p-3 text-left transition-colors';
            @endphp
            <h2 class="mb-2 text-lg font-bold text-gray-900">Jūsų parduotuvės</h2>
            <div class="mb-8 grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                <button
                    type="button"
                    @click="storeFilter = ''; $nextTick(() => document.getElementById('favorites-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    class="{{ $gridCellBase }}"
                    :class="storeFilter === '' ? '{{ $rowActive }}' : '{{ $rowInactive }}'"
                >
                    <span class="flex w-full items-center gap-2">
                        <span class="{{ $checkBase }}" :class="storeFilter === '' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="storeFilter === ''" class="size-4 text-white" />
                        </span>
                        <span class="min-w-0 flex-1 truncate text-base font-bold text-gray-900">Visos</span>
                    </span>
                    <span class="text-sm text-gray-600">{{ $activeCount }} {{ $activeCount === 1 ? 'akcija' : 'akcijos' }}</span>
                    <span class="text-base font-extrabold text-green">{{ number_format($allStoresTotalPrice, 2, ',', ' ') }}€</span>
                </button>
                @foreach ($storeTotals as $total)
                    <button
                        type="button"
                        @click="storeFilter = '{{ $total['store_slug'] }}'; $nextTick(() => document.getElementById('favorites-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="{{ $gridCellBase }}"
                        :class="storeFilter === '{{ $total['store_slug'] }}' ? '{{ $rowActive }}' : '{{ $rowInactive }}'"
                    >
                        <span class="flex w-full items-center gap-2">
                            <span class="{{ $checkBase }}" :class="storeFilter === '{{ $total['store_slug'] }}' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                                <x-app-icon name="check" x-show="storeFilter === '{{ $total['store_slug'] }}'" class="size-4 text-white" />
                            </span>
                            @if ($total['store_slug'])
                                <x-store-logo :slug="$total['store_slug']" :name="$total['store_name']" size="xs" class="shrink-0 object-contain" />
                            @else
                                <span class="min-w-0 flex-1 truncate text-base font-bold text-gray-900">{{ $total['store_name'] }}</span>
                            @endif
                        </span>
                        <span class="text-sm text-gray-600">{{ $total['product_count'] }} {{ $total['product_count'] === 1 ? 'akcija' : 'akcijos' }}</span>
                        <span class="text-base font-extrabold text-green">{{ number_format($total['total_price'], 2, ',', ' ') }}€</span>
                    </button>
                @endforeach
            </div>
        @endif

        @if (count($products) === 0)
            <div class="rounded-2xl border-2 bg-card p-10 text-center">
                <x-app-icon name="heart" class="mx-auto size-10 text-gray-300" />
                <p class="mt-4 text-lg text-gray-600">
                    Jūs dar neturite mėgstamiausių prekių. Pridėkite prekes prie mėgstamiausių, paspaudę ant širdelės ikonos.
                </p>
                <a href="/akcijos" class="mt-6 inline-flex items-center gap-2 rounded-xl border-2 border-green bg-green px-5 py-3 text-base font-bold text-white transition-colors hover:bg-dark-green hover:border-dark-green">
                    Žiūrėti visas akcijas
                    <x-app-icon name="arrow-right" class="size-4" />
                </a>
            </div>
        @else
            @if ($showCategoryFilter && $categoryFilterOptions->isNotEmpty())
                @php
                    $categoryRowBase = 'flex w-full items-center gap-3 rounded-xl border-2 bg-white px-4 py-3 text-left transition-colors';
                @endphp
                <button type="button" @click="categoryOpen = !categoryOpen" class="mb-2 flex w-full items-center justify-between gap-3 rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-left">
                    <span>
                        <span class="block text-lg font-bold text-gray-900">Kategorija</span>
                        <span class="block text-sm text-gray-600" x-text="categoryFilter === '' ? 'Visos kategorijos' : (categoryNames[categoryFilter] || categoryFilter)"></span>
                    </span>
                    <x-app-icon name="chevron-down" class="size-6 shrink-0 text-gray-400 transition-transform" x-bind:class="categoryOpen ? 'rotate-180' : ''" />
                </button>
                <div x-show="categoryOpen" x-cloak class="mb-8 flex flex-col gap-2">
                    <button type="button" @click="categoryFilter = ''; categoryOpen = false" class="{{ $categoryRowBase }}" :class="categoryFilter === '' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                        <span class="{{ $checkBase }}" :class="categoryFilter === '' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="categoryFilter === ''" class="size-4 text-white" />
                        </span>
                        <span class="flex-1 text-base font-bold text-gray-900">Visos</span>
                    </button>
                    @foreach ($categoryFilterOptions as $option)
                        <button type="button" @click="categoryFilter = '{{ $option['slug'] }}'; categoryOpen = false" class="{{ $categoryRowBase }}" :class="categoryFilter === '{{ $option['slug'] }}' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                            <span class="{{ $checkBase }}" :class="categoryFilter === '{{ $option['slug'] }}' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                                <x-app-icon name="check" x-show="categoryFilter === '{{ $option['slug'] }}'" class="size-4 text-white" />
                            </span>
                            <span class="flex-1 text-base font-bold text-gray-900">{{ $option['name'] }}</span>
                            <span class="shrink-0 text-sm font-semibold text-gray-500">{{ $option['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 id="favorites-grid" class="scroll-mt-24 text-2xl font-bold text-gray-900">Prekės</h2>
                <div class="relative shrink-0" @click.outside="sortOpen = false">
                    <button type="button" @click="sortOpen = !sortOpen" class="inline-flex items-center gap-2 rounded-2xl bg-[#e8e8e8] px-3 py-2 text-base font-semibold text-gray-900 hover:bg-[#dedede]" aria-haspopup="listbox" :aria-expanded="sortOpen">
                        <x-app-icon name="arrow-down-up" class="size-4 shrink-0" />
                        <span class="hidden sm:inline">
                            @foreach ($sortOptions as $value => $option)
                                <span x-show="sort === '{{ $value }}'">{{ $option['label'] }}</span>
                            @endforeach
                        </span>
                        <x-app-icon name="chevron-down" class="size-4 shrink-0 opacity-70" />
                    </button>
                    <div x-show="sortOpen" x-cloak class="absolute right-0 top-full z-30 mt-1.5 min-w-[220px] rounded-2xl border border-gray-200 bg-white p-1.5 shadow-lg">
                        @foreach ($sortOptions as $value => $option)
                            <button type="button" @click="sort = '{{ $value }}'; sortOpen = false" class="flex w-full items-center gap-2 rounded-2xl px-3 py-2.5 text-left text-base font-semibold text-gray-900 transition-colors hover:bg-gray-100" :class="sort === '{{ $value }}' ? 'bg-gray-100' : ''">
                                <x-app-icon name="{{ $option['icon'] }}" class="size-4 shrink-0 opacity-90" />
                                {{ $option['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                @foreach ($sortedProducts as $deal)
                    @php
                        $cardStoreSlug = $dealStore($deal)['slug'] ?? '';
                        $cardCategorySlug = $dealCategory($deal)['slug'] ?? '';
                        $pid = $deal['product']['id'];
                        // Current savings (original vs discounted price right now) —
                        // not the same as price_change_amount, which only reflects a
                        // drop since our last recorded snapshot. A product can be on
                        // sale at the same price for weeks with no "change" to show.
                        $currentSavings = (float) ($deal['original_price'] ?? 0) - (float) ($deal['discounted_price'] ?? 0);
                    @endphp
                    <div
                        x-show="(storeFilter === '' || storeFilter === '{{ $cardStoreSlug }}') && (categoryFilter === '' || categoryFilter === '{{ $cardCategorySlug }}')"
                        :style="'order:' + ({ @foreach ($sortRanks as $mode => $ranks) '{{ $mode }}': {{ $ranks[$pid] ?? 999 }}, @endforeach }[sort])"
                        class="relative {{ !$hasActiveDiscount($deal) ? 'opacity-60' : '' }}"
                    >
                        @if ($currentSavings > 0)
                            <span class="absolute left-2 top-2 z-20 rounded-lg bg-green px-2 py-1 text-xs font-bold text-white">
                                −{{ $euro($currentSavings) }}
                            </span>
                        @endif
                        @unless ($hasActiveDiscount($deal))
                            <span class="absolute left-1/2 top-1 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-gray-900/80 px-3 py-1 text-sm font-semibold text-white">
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
