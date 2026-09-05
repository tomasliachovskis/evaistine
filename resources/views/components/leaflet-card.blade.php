@props(['leaflet'])

@php
    $daysWord = fn ($n) => match (true) {
        $n === 1 => 'diena',
        $n % 10 >= 2 && $n % 10 <= 9 && !($n % 100 >= 11 && $n % 100 <= 19) => 'dienas',
        default => 'dienų',
    };
    $isExpired = $leaflet['status'] === 'expired';
    $href = $leaflet['view_url'] ?? "/leidinys/{$leaflet['store_slug']}";
    $days = $leaflet['days_remaining'] ?? null;
    $dateRange = !empty($leaflet['valid_from']) && !empty($leaflet['valid_to'])
        ? \Illuminate\Support\Carbon::parse($leaflet['valid_from'])->format('Y.m.d') . ' – ' . \Illuminate\Support\Carbon::parse($leaflet['valid_to'])->format('Y.m.d')
        : null;
@endphp

{{-- Same card design as leaflets/hub.blade.php's (/leidinys/{store}) — kept
     identical on purpose so /leidiniai (all stores) and a single store's own
     hub page don't look like two different products, right down to showing
     the leaflet's own title (not the store name) as the bold line. --}}
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
            <div class="flex h-full items-center justify-center px-2 text-center text-xs text-gray-500">{{ $leaflet['title'] ?? $leaflet['store_name'] }}</div>
        @endif
    </a>
    <div class="flex flex-1 flex-col gap-1.5 py-3 pr-3 sm:gap-2 sm:p-4">
        <p class="line-clamp-2 text-sm font-semibold text-gray-900">{{ $leaflet['title'] ?? '' }}</p>
        @if ($dateRange)
            {{-- A consistent, real identifier for every card — some
                 leaflets carry a themed campaign name instead of a
                 sequential number (e.g. "Skonių dienos"), so a fixed
                 "Nr. X" can't be shown for all of them without
                 fabricating one; the validity date range is always
                 real and always available. --}}
            <p class="text-xs font-medium text-gray-500">{{ $dateRange }}</p>
        @endif
        <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $isExpired ? 'text-gray-400' : ($days !== null && $days <= 2 ? 'text-red-600' : 'text-dark-green') }}">
            <x-app-icon name="clock" class="size-3.5" />
            {{ $isExpired ? 'Nebegalioja' : ($days !== null ? "Galioja dar {$days} {$daysWord($days)}" : 'Galioja') }}
        </span>
        <a href="{{ $href }}" class="mt-1 inline-flex h-8 w-fit items-center justify-center rounded-lg bg-green px-4 text-xs font-bold text-white hover:bg-dark-green sm:mt-auto sm:h-9 sm:w-full sm:text-sm">Peržiūrėti</a>
    </div>
</article>
