@props(['items', 'current' => null])

{{-- Ported from discount/src/components/common/breadcrumbs.tsx. $items is an
     ordered list of ['name' => ..., 'href' => ...]; $current marks which
     href is the active page (bold, non-link styling). --}}
<div class="base-container pb-2 pt-3 lg:pb-4">
    <nav aria-label="Naršymo kelias">
        <ol class="flex flex-wrap items-center gap-x-2 break-words">
            @foreach ($items as $index => $item)
                @php $isCurrent = $current !== null && $current === $item['href']; @endphp
                <li class="inline-flex items-center gap-1.5">
                    <a
                        href="{{ $item['href'] }}"
                        class="crumb-link {{ $isCurrent ? 'crumb-current' : '' }}"
                    >{{ $item['name'] }}</a>
                </li>
                @unless ($loop->last)
                    <li role="presentation" aria-hidden="true" class="inline-flex">
                        <x-app-icon name="arrow-right" class="crumb-sep" />
                    </li>
                @endunless
            @endforeach
        </ol>
    </nav>
</div>
