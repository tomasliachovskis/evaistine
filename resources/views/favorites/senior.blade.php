<x-layouts.app :title="$title" :robots="$robots">
    @php
        $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €';

        // price_change_amount is already computed server-side per product
        // (Api\ProductController::getFavoriteProducts() ->
        // HomePageSectionsService::resolvePriceChangeAmount()).
        $dropped = collect($products)
            ->filter(fn ($deal) => ($deal['price_change_amount'] ?? 0) > 0)
            ->sortByDesc('price_change_amount')
            ->take(8)
            ->values();

        $today = \Illuminate\Support\Carbon::today();
        $isExpiringSoon = function (array $deal) use ($today) {
            if (empty($deal['to_date'])) {
                return false;
            }
            $end = \Illuminate\Support\Carbon::parse($deal['to_date'])->endOfDay();
            $daysLeft = (int) ceil(($end->timestamp - $today->timestamp) / 86400);

            return $daysLeft >= 0 && $daysLeft <= 1;
        };

        $hasActiveDiscount = fn (array $deal) => ($deal['discounted_price'] ?? 0) > 0 || !empty($deal['discount_percent']);
        $sortedProducts = collect($products)->sortBy(fn ($deal) => $hasActiveDiscount($deal) ? 0 : 1)->values();
        $activeCount = $sortedProducts->filter($hasActiveDiscount)->count();

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

        $allStoresTotalPrice = collect($storeTotals)->sum('total_price');

        // Shared building blocks for the "big row item with a checkmark" pattern
        // used everywhere on this page (store rows, status filters, category
        // filters) — a single visual language instead of cards/chips/dropdowns
        // mixed together like the regular /favorites page.
        $rowBase = 'flex w-full items-center gap-4 rounded-2xl border-2 bg-white px-5 py-4 text-left transition-colors';
        $rowActive = 'border-green bg-green/10';
        $rowInactive = 'border-gray-200 hover:border-gray-400';
        $checkBase = 'flex size-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors';
        $checkActive = 'border-green bg-green';
        $checkInactive = 'border-gray-300 bg-white';
    @endphp

    <section class="base-container pb-12 pt-4 sm:pt-6" x-data="{ filter: 'all', storeFilter: '', categoryFilter: '' }">
        <div class="mb-5 flex items-center justify-between gap-3">
            <h1 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">Stebimos prekės</h1>
            <a href="/favorites" class="shrink-0 text-base font-semibold text-gray-500 underline decoration-gray-300 underline-offset-4 hover:text-dark-green">
                Įprastas rodinys
            </a>
        </div>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-8 flex items-center gap-5 rounded-2xl border-2 border-green bg-green/10 p-6">
                <div class="flex size-16 shrink-0 items-center justify-center rounded-full bg-green/20">
                    <x-app-icon name="wallet" class="size-8 text-dark-green" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg text-gray-700">Galite sutaupyti dabar</p>
                    <p class="text-4xl font-extrabold leading-tight text-dark-green sm:text-5xl">{{ $euro($savingsSummary['total_savings']) }}</p>
                    <p class="mt-1 text-base text-gray-700">
                        {{ count($products) }} {{ count($products) === 1 ? 'stebima prekė' : 'stebimos prekės' }}
                        @if ($savingsSummary['expiring_soon_count'] > 0)
                            · {{ $savingsSummary['expiring_soon_count'] === 1 ? '1 akcija baigiasi per 24 val.' : $savingsSummary['expiring_soon_count'] . ' akcijos baigiasi per 24 val.' }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        @if (count($storeTotals) > 0)
            <h2 class="mb-4 text-2xl font-bold text-gray-900">Jūsų parduotuvės</h2>
            <div class="mb-10 flex flex-col gap-3">
                <button
                    type="button"
                    @click="storeFilter = ''; $nextTick(() => document.getElementById('favorites-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    class="{{ $rowBase }}"
                    :class="storeFilter === '' ? '{{ $rowActive }}' : '{{ $rowInactive }}'"
                >
                    <span class="{{ $checkBase }}" :class="storeFilter === '' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                        <x-app-icon name="check" x-show="storeFilter === ''" class="size-5 text-white" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-gray-900">Visos parduotuvės</span>
                        <span class="block text-base text-gray-600">{{ $activeCount }} {{ $activeCount === 1 ? 'akcija' : 'akcijos' }}</span>
                    </span>
                    <span class="shrink-0 text-xl font-extrabold text-green">{{ number_format($allStoresTotalPrice, 2, ',', ' ') }}€</span>
                </button>
                @foreach ($storeTotals as $total)
                    <button
                        type="button"
                        @click="storeFilter = '{{ $total['store_slug'] }}'; $nextTick(() => document.getElementById('favorites-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="{{ $rowBase }}"
                        :class="storeFilter === '{{ $total['store_slug'] }}' ? '{{ $rowActive }}' : '{{ $rowInactive }}'"
                    >
                        <span class="{{ $checkBase }}" :class="storeFilter === '{{ $total['store_slug'] }}' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="storeFilter === '{{ $total['store_slug'] }}'" class="size-5 text-white" />
                        </span>
                        <span class="flex min-w-0 flex-1 items-center gap-3">
                            @if ($total['store_slug'])
                                <x-store-logo :slug="$total['store_slug']" :name="$total['store_name']" size="sm" class="shrink-0 object-contain" />
                            @else
                                <span class="text-lg font-bold text-gray-900">{{ $total['store_name'] }}</span>
                            @endif
                            <span class="block text-base text-gray-600">{{ $total['product_count'] }} {{ $total['product_count'] === 1 ? 'akcija' : 'akcijos' }}</span>
                        </span>
                        <span class="shrink-0 text-xl font-extrabold text-green">{{ number_format($total['total_price'], 2, ',', ' ') }}€</span>
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
            </div>
        @else
            <h2 id="favorites-grid" class="mb-4 scroll-mt-24 text-2xl font-bold text-gray-900">Rodyti</h2>
            <div class="mb-6 flex flex-col gap-3">
                <button type="button" @click="filter = 'all'" class="{{ $rowBase }}" :class="filter === 'all' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                    <span class="{{ $checkBase }}" :class="filter === 'all' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                        <x-app-icon name="check" x-show="filter === 'all'" class="size-5 text-white" />
                    </span>
                    <span class="flex-1 text-lg font-bold text-gray-900">Visos prekės</span>
                    <span class="shrink-0 text-lg font-semibold text-gray-500">{{ count($products) }}</span>
                </button>
                @if ($dropped->isNotEmpty())
                    <button type="button" @click="filter = 'drops'" class="{{ $rowBase }}" :class="filter === 'drops' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                        <span class="{{ $checkBase }}" :class="filter === 'drops' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="filter === 'drops'" class="size-5 text-white" />
                        </span>
                        <span class="flex-1 text-lg font-bold text-gray-900">Krenta kaina</span>
                        <span class="shrink-0 text-lg font-semibold text-gray-500">{{ $dropped->count() }}</span>
                    </button>
                @endif
                @if ($savingsSummary['expiring_soon_count'] > 0)
                    <button type="button" @click="filter = 'expiring'" class="{{ $rowBase }}" :class="filter === 'expiring' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                        <span class="{{ $checkBase }}" :class="filter === 'expiring' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="filter === 'expiring'" class="size-5 text-white" />
                        </span>
                        <span class="flex-1 text-lg font-bold text-gray-900">Baigiasi greitai</span>
                        <span class="shrink-0 text-lg font-semibold text-gray-500">{{ $savingsSummary['expiring_soon_count'] }}</span>
                    </button>
                @endif
                @if ($activeCount < count($sortedProducts))
                    <button type="button" @click="filter = 'active'" class="{{ $rowBase }}" :class="filter === 'active' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                        <span class="{{ $checkBase }}" :class="filter === 'active' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="filter === 'active'" class="size-5 text-white" />
                        </span>
                        <span class="flex-1 text-lg font-bold text-gray-900">Tik su akcija</span>
                        <span class="shrink-0 text-lg font-semibold text-gray-500">{{ $activeCount }}</span>
                    </button>
                @endif
            </div>

            @if ($categoryFilterOptions->isNotEmpty())
                <h2 class="mb-4 text-2xl font-bold text-gray-900">Kategorija</h2>
                <div class="mb-10 flex flex-col gap-3">
                    <button type="button" @click="categoryFilter = ''" class="{{ $rowBase }}" :class="categoryFilter === '' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                        <span class="{{ $checkBase }}" :class="categoryFilter === '' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                            <x-app-icon name="check" x-show="categoryFilter === ''" class="size-5 text-white" />
                        </span>
                        <span class="flex-1 text-lg font-bold text-gray-900">Visos kategorijos</span>
                    </button>
                    @foreach ($categoryFilterOptions as $option)
                        <button type="button" @click="categoryFilter = '{{ $option['slug'] }}'" class="{{ $rowBase }}" :class="categoryFilter === '{{ $option['slug'] }}' ? '{{ $rowActive }}' : '{{ $rowInactive }}'">
                            <span class="{{ $checkBase }}" :class="categoryFilter === '{{ $option['slug'] }}' ? '{{ $checkActive }}' : '{{ $checkInactive }}'">
                                <x-app-icon name="check" x-show="categoryFilter === '{{ $option['slug'] }}'" class="size-5 text-white" />
                            </span>
                            <span class="flex-1 text-lg font-bold text-gray-900">{{ $option['name'] }}</span>
                            <span class="shrink-0 text-lg font-semibold text-gray-500">{{ $option['count'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <h2 class="mb-4 text-2xl font-bold text-gray-900">Prekės</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
