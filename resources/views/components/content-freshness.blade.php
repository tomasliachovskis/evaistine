@props(['label'])

{{-- `label` is already a finished string (LithuanianDate::relative()) —
     listing_meta round-trips through JSON (ProductController's API layer),
     so a raw Carbon date wouldn't survive that; the caller resolves it
     before it gets here. No icon — reads as just another stat in the same
     "|"-separated row as the other x-type-hero stats. --}}
@if ($label)
    <span class="whitespace-nowrap">
        {{ $label }}
    </span>
@endif
