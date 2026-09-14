@props(['percent', 'size' => 'sm'])

@php
    // MIN_PROMOTION_BADGE_PERCENT in promotion-percent-badge.tsx — callers
    // don't need to repeat this check anymore.
    $roundedPercent = $percent !== null ? round($percent) : null;
@endphp

@if ($roundedPercent !== null && $roundedPercent >= 20)
    @if ($size === 'lg')
        <span class="inline-flex h-[1.6rem] items-center rounded-lg bg-[#ffdb4d] px-2.5 text-[17.6px] font-bold leading-none tabular-nums text-gray-900 sm:h-[2rem] sm:px-3 sm:text-[19px]">-{{ $roundedPercent }}%</span>
    @else
        <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-[#ffdb4d] px-2.5 py-1.5 text-sm font-bold leading-none tabular-nums text-gray-900">-{{ $roundedPercent }}%</span>
    @endif
@endif
