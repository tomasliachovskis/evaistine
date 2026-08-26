<x-layouts.app :title="$title" :robots="$robots">
    @php $euro = fn ($amount) => number_format((float) $amount, 2, ',', ' ') . ' €'; @endphp

    <section class="base-container py-8 sm:py-16">
        <h1 class="mb-4 text-2xl font-extrabold text-gray-900 sm:text-3xl">Stebimos prekės</h1>

        @if ($savingsSummary['total_savings'] > 0)
            <div class="mb-6 flex items-center gap-4 rounded-2xl border border-green/20 bg-green/5 p-4 sm:p-5">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-green/15 sm:size-14">
                    <x-app-icon name="wallet" class="size-6 text-dark-green sm:size-7" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm text-gray-600">Galite sutaupyti</p>
                    <p class="text-2xl font-bold leading-tight text-dark-green sm:text-3xl">{{ $euro($savingsSummary['total_savings']) }}</p>
                    @if ($savingsSummary['expiring_soon_count'] > 0)
                        <p class="mt-1 flex items-center gap-1 text-sm font-medium text-orange-600">
                            <x-app-icon name="clock" class="size-3.5 shrink-0" />
                            {{ $savingsSummary['expiring_soon_count'] === 1 ? '1 akcija baigiasi per 24 val.' : $savingsSummary['expiring_soon_count'] . ' akcijos baigiasi per 24 val.' }}
                        </p>
                    @endif
                </div>
            </div>
        @endif

        @if (count($storeTotals) > 0)
            <div class="mb-8">
                <h2 class="mb-4 text-lg font-semibold sm:text-xl">Parduotuvių suvestinė</h2>
                {{-- The original lets clicking a store card filter the grid below client-side
                     (React state); this Blade port keeps the summary cards read-only rather than
                     wiring a Livewire filter here — the /akcijos filter panel already covers
                     store-based filtering elsewhere in the app. --}}
                <div class="mb-8 flex flex-row flex-wrap justify-start gap-2 sm:gap-3">
                    @foreach ($storeTotals as $total)
                        <div class="w-[calc(50%-4px)] gap-0 rounded-xl border bg-card p-2 py-0 sm:w-[140px] sm:py-2">
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
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if (count($products) === 0)
            <div class="cursor-default rounded-xl border bg-card p-6">
                <p class="text-center text-gray-600">
                    Jūs dar neturite mėgstamiausių prekių. Pridėkite prekes prie mėgstamiausių, paspaudę ant širdelės ikonos.
                </p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($products as $deal)
                    <x-deal-card :deal="$deal" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
