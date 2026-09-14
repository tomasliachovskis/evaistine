@props(['items', 'current' => null])

{{-- Ported from discount/src/components/common/breadcrumbs.tsx. $items is an
     ordered list of ['name' => ..., 'href' => ...]; $current marks which
     href is the active page (bold, non-link styling). --}}
<div class="base-container pb-4 pt-3">
    <nav aria-label="breadcrumb">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm break-words text-gray-600 sm:gap-2.5">
            @foreach ($items as $index => $item)
                @php $isCurrent = $current !== null && $current === $item['href']; @endphp
                <li class="inline-flex items-center gap-1.5">
                    <a
                        href="{{ $item['href'] }}"
                        class="text-sm transition-colors hover:text-green {{ $isCurrent ? 'font-medium text-green' : 'text-gray-600' }}"
                    >{{ $item['name'] }}</a>
                </li>
                @unless ($loop->last)
                    <li role="presentation" aria-hidden="true" class="[&>svg]:size-3.5">
                        <x-app-icon name="arrow-right" class="size-3.5 text-gray-300" />
                    </li>
                @endunless
            @endforeach
        </ol>
    </nav>
</div>
