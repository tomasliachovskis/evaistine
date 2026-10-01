@props(['count', 'color' => 'gray'])

@php
    $colorClass = match ($color) {
        'white' => 'bg-white/25 text-white',
        'green' => 'bg-green/10 text-dark-green',
        default => 'bg-gray-200 text-gray-600',
    };
@endphp

@if (!empty($count))
    <span class="inline-flex min-w-[1.375rem] items-center justify-center rounded-full px-1.5 text-sm font-bold tabular-nums {{ $colorClass }}">{{ number_format($count, 0, ',', ' ') }}</span>
@endif
