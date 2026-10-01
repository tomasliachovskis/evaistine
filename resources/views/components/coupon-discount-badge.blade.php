@props(['label'])

{{-- Unlike <x-discount-badge>, coupon discounts are free-form labels
     ("-30%", "-200€") with no minimum-percent cutoff — kuplio shows every
     coupon's badge regardless of size, so this doesn't gate on a threshold
     the way the product-grid badge does. Sized up (larger text/padding
     than the product-grid badge) to match the rest of the coupon card —
     this section targets a 50+ audience.

     Uses dark-green (not the product-grid badge's yellow #ffdb4d) so the
     badge and the CTA button below it read as one cohesive green-branded
     pair instead of a clashing yellow+green combo — explicit user call,
     the yellow read as "primitive" here. --}}
@if (!empty($label))
    <span class="inline-flex shrink-0 items-center justify-center rounded-lg bg-action px-4 py-2.5 text-xl font-bold leading-none tabular-nums text-white">{{ $label }}</span>
@endif
