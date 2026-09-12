@props(['label'])

{{-- Matches the mockup's .freshness-stat exactly: small clock icon + text,
     inline with whatever other stats sit beside it. `label` is already a
     finished string (LithuanianDate::relative()) — listing_meta round-trips
     through JSON (ProductController's API layer), so a raw Carbon date
     wouldn't survive that; the caller resolves it before it gets here. --}}
@if ($label)
    <span class="inline-flex items-center gap-1">
        <x-app-icon name="clock" class="size-3.5 shrink-0 opacity-75" />
        {{ $label }}
    </span>
@endif
