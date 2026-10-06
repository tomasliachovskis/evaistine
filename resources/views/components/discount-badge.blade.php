@props(['percent', 'size' => 'sm'])

@php
    // MIN_PROMOTION_BADGE_PERCENT in promotion-percent-badge.tsx — callers
    // don't need to repeat this check anymore.
    $roundedPercent = $percent !== null ? round($percent) : null;
@endphp

@if ($roundedPercent !== null && $roundedPercent >= 20)
    @if ($size === 'lg')
        <span class="inline-flex h-[1.6rem] items-center rounded-lg bg-deal px-2.5 text-base font-bold leading-none tabular-nums text-deal-foreground sm:h-[2rem] sm:px-3 sm:text-lg">-{{ $roundedPercent }}%</span>
    @else
        <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-deal px-2.5 py-1.5 text-sm font-bold leading-none tabular-nums text-deal-foreground">-{{ $roundedPercent }}%</span>
    @endif
@endif
