@props(['items', 'current' => null])

{{-- Ported from discount/src/components/common/breadcrumbs.tsx — same
     base-container spacing, ArrowRight separators, current-page item in
     green/font-medium. $items is the same ['name' => ..., 'href' => ...][]
     shape passed to App\Support\BreadcrumbSchema::build(). --}}
<div class="base-container pb-4 pt-3">
    <nav class="flex flex-wrap items-center gap-1.5">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <x-app-icon name="arrow-right" class="size-3.5 text-gray-300" />
            @endif
            <a
                href="{{ $item['href'] }}"
                class="text-sm text-gray-600 transition-colors hover:text-green {{ $current === $item['href'] ? 'font-medium text-green' : '' }}"
            >{{ $item['name'] }}</a>
        @endforeach
    </nav>
</div>
