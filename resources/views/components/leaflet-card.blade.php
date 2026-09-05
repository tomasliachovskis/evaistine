@props(['leaflet'])

@php
    $daysWord = fn ($n) => match (true) {
        $n === 1 => 'diena',
        $n % 10 >= 2 && $n % 10 <= 9 && !($n % 100 >= 11 && $n % 100 <= 19) => 'dienas',
        default => 'dienų',
    };
    $isReady = empty($leaflet['processing_status']) || $leaflet['processing_status'] === 'ready';
    $isExpired = $leaflet['status'] === 'expired';
    $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
    $dateRange = !empty($leaflet['valid_from']) && !empty($leaflet['valid_to'])
        ? \Illuminate\Support\Carbon::parse($leaflet['valid_from'])->format('Y.m.d') . ' – ' . \Illuminate\Support\Carbon::parse($leaflet['valid_to'])->format('Y.m.d')
        : null;
@endphp

{{-- Mobile gets a horizontal row (thumbnail left, info right) instead of the
     sm:+ vertical grid card — a full-width vertical card wastes most of its
     height on empty space at one-per-row mobile width (same treatment as
     leaflets/hub.blade.php's inline cards). No padding around the mobile
     thumbnail either — it fills the card's full height edge-to-edge
     (article's own overflow-hidden + rounded-2xl clips its left corners)
     instead of sitting inset with wasted space around it. --}}
<article {{ $attributes->merge(['class' => 'group flex flex-row gap-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-all sm:flex-col sm:gap-0 sm:hover:-translate-y-0.5 sm:hover:shadow-lg']) }}>
    <a href="{{ $href }}" class="relative block w-32 shrink-0 overflow-hidden bg-gray-50 sm:aspect-[6/5] sm:w-full">
        @if (!empty($leaflet['thumbnail_url'] ?? $leaflet['image_url'] ?? null))
            <img
                src="{{ $leaflet['thumbnail_url'] ?? $leaflet['image_url'] }}"
                alt="{{ $leaflet['title'] ?? $leaflet['store_name'] }}"
                loading="lazy"
                class="h-full w-full object-cover object-top transition-transform duration-300 sm:group-hover:scale-[1.03] {{ $isExpired ? 'grayscale' : '' }}"
            >
        @else
            <div class="flex h-full items-center justify-center px-2 text-center text-xs text-gray-500">{{ $leaflet['title'] ?? '' }}</div>
        @endif
        <div class="absolute right-1.5 top-1.5 sm:right-3 sm:top-3">
            @if (!$isReady)
                <span class="inline-flex items-center rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm sm:px-3 sm:py-1 sm:text-xs">Ruošiama</span>
            @elseif ($leaflet['status'] === 'expired')
                <span class="inline-flex items-center rounded-full bg-gray-900/75 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm sm:px-3 sm:py-1 sm:text-xs">Nebegalioja</span>
            @elseif ($leaflet['status'] === 'new')
                <span class="inline-flex items-center gap-1 rounded-full bg-green px-2 py-0.5 text-[10px] font-bold text-white shadow-sm sm:px-3 sm:py-1 sm:text-xs">
                    <x-app-icon name="flame" class="size-3 sm:size-3.5" />
                    Naujas
                </span>
            @endif
        </div>
    </a>
    <div class="flex flex-1 flex-col gap-1.5 py-3 pr-3 sm:gap-3 sm:p-5">
        <div class="flex items-center gap-1.5 sm:gap-2.5">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-100 bg-white p-1 shadow-sm sm:h-12 sm:w-12">
                <img src="/assets/stores/{{ $leaflet['store_slug'] }}.svg?v=2" alt="" class="h-full w-full object-contain">
            </span>
            <span class="truncate text-sm font-extrabold text-gray-900 sm:text-lg">{{ $leaflet['store_name'] }}</span>
        </div>

        @if ($dateRange)
            {{-- A real, always-available identifier for every card — some
                 leaflets carry a themed campaign name instead of a
                 sequential number, so the validity date range stands in
                 consistently for both. --}}
            <p class="text-xs font-medium text-gray-500 sm:-mt-1.5">{{ $dateRange }}</p>
        @endif

        @if ($leaflet['status'] === 'expired')
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 sm:text-sm">
                <x-app-icon name="clock" class="size-3.5 sm:size-4" />
                Nebegalioja
            </span>
        @else
            @php $days = $leaflet['days_remaining'] ?? null; @endphp
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold sm:text-sm {{ $days !== null && $days <= 2 ? 'text-red-600' : ($days !== null && $days <= 5 ? 'text-amber-600' : 'text-dark-green') }}">
                <x-app-icon name="clock" class="size-3.5 sm:size-4" />
                {{ $days !== null ? "Galioja dar {$days} {$daysWord($days)}" : 'Galioja' }}
            </span>
        @endif

        <a href="{{ $href }}" class="mt-1 inline-flex h-8 w-fit items-center justify-center rounded-lg bg-green px-4 text-xs font-bold text-white transition-colors hover:bg-dark-green sm:mt-auto sm:h-11 sm:w-full sm:text-base">
            Peržiūrėti
        </a>
    </div>
</article>
