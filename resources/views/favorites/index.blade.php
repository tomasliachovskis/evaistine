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
    @endphp

    <section class="base-container py-8 sm:py-16" x-data="{ filter: 'all' }">
        <h1 class="mb-4 text-2xl font-extrabold text-gray-900 sm:text-3xl">Stebimos prekės</h1>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-green to-dark-green p-4 text-white sm:p-5">
                <p class="text-sm text-white/80">Galite sutaupyti dabar</p>
                <p class="text-2xl font-bold leading-tight sm:text-3xl">{{ $euro($savingsSummary['total_savings']) }}</p>
                <p class="mt-2 text-sm text-white/80">
                    {{ count($products) }} {{ count($products) === 1 ? 'stebima prekė' : 'stebimos prekės' }}
                    @if ($savingsSummary['expiring_soon_count'] > 0)
                        · {{ $savingsSummary['expiring_soon_count'] === 1 ? '1 akcija baigiasi per 24 val.' : $savingsSummary['expiring_soon_count'] . ' akcijos baigiasi per 24 val.' }}
                    @endif
                </p>
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
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold sm:text-xl">Parduotuvių suvestinė</h2>
                <div class="mb-8 flex flex-row flex-wrap justify-start gap-2 sm:gap-3">
                    @foreach ($storeTotals as $total)
                        <a
                            href="{{ $total['store_slug'] ? '/akcijos/' . $total['store_slug'] : '#' }}"
                            class="w-[calc(50%-4px)] gap-0 rounded-xl border bg-card p-2 py-0 transition-colors hover:border-green/40 sm:w-[140px] sm:py-2"
                        >
                            <div class="flex flex-col items-center gap-0 px-2 pb-1 sm:items-start">
                                @if ($total['store_slug'])
                                    <img
                                        src="/assets/stores/{{ $total['store_slug'] }}.svg"
                                        alt="{{ $total['store_name'] }}"
                                        class="mb-1 h-[57px] w-auto max-w-[100px] object-contain object-left"
                                    >
                                @endif
                            </div>
                            <div class="px-2 pb-1 pt-0">
                                <div class="flex flex-col gap-1">
                                    <div class="text-xs text-gray-600 sm:text-sm">
                                        <span class="font-medium">{{ $total['product_count'] }} {{ $total['product_count'] === 1 ? 'prekė' : 'prekės' }}</span>
                                    </div>
                                    <div class="text-base font-bold text-green sm:text-lg">{{ number_format($total['total_price'], 2) }}€</div>
                                </div>
                            </div>
                        </a>
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
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($products as $deal)
                    <div
                        x-show="filter === 'all' || (filter === 'drops' && {{ ($deal['price_change_amount'] ?? 0) > 0 ? 'true' : 'false' }}) || (filter === 'expiring' && {{ $isExpiringSoon($deal) ? 'true' : 'false' }})"
                    >
                        <x-deal-card :deal="$deal" />
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
