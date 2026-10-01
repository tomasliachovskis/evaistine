@props(['items', 'current' => null])

{{-- Ported from discount/src/components/common/breadcrumbs.tsx — same
     base-container spacing, ArrowRight separators, current-page item in
     green/font-medium. $items is the same ['name' => ..., 'href' => ...][]
     shape passed to App\Support\BreadcrumbSchema::build(). --}}
<div class="base-container pb-2 pt-3 lg:pb-4">
    <nav class="flex flex-wrap items-center gap-x-2" aria-label="Naršymo kelias">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <x-app-icon name="arrow-right" class="crumb-sep" />
            @endif
            <a
                href="{{ $item['href'] }}"
                class="crumb-link {{ $current === $item['href'] ? 'crumb-current' : '' }}"
            >{{ $item['name'] }}</a>
        @endforeach
    </nav>
</div>
