{{-- Shared between site-search's desktop and mobile modes (search-input.tsx's makeResultsList). --}}
@if (mb_strlen(trim($query)) >= 2)
    @if (count($results))
        <ul class="flex flex-col gap-1">
            @foreach ($results as $item)
                <li>
                    <a href="{{ $item['href'] }}" class="hover:text-dark-green block rounded-lg px-2 py-3 text-base font-medium text-gray-900 transition-colors hover:bg-gray-50">{{ $item['name'] }}</a>
                </li>
            @endforeach
        </ul>
    @else
        <p class="px-2 py-3 text-base text-gray-500">Nieko nerasta</p>
    @endif
@else
    @if (count($popular))
        <p class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Populiariausi</p>
        <ul class="flex flex-col gap-1">
            @foreach ($popular as $page)
                <li>
                    <a href="{{ $page['href'] }}" class="hover:text-dark-green flex items-center gap-2 rounded-lg px-2 py-3 text-base font-medium text-gray-900 transition-colors hover:bg-gray-50">
                        @if ($page['emoji'])<span aria-hidden="true">{{ $page['emoji'] }}</span>@endif
                        {{ $page['title'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endif
