{{-- Shared between leaflets/show.blade.php's two pager placements (desktop:
     in the rail below the date range; mobile: below the image instead) —
     same buttons/click handlers either way, reading/writing the enclosing
     x-data's currentPage (lifted up to the outer grid so both placements
     and the viewer share one Alpine scope). --}}
@php $lastPage = count($pages); @endphp
<div class="flex flex-wrap items-center gap-1.5">
    @foreach ($pages as $page)
        @continue($page['page_number'] > 5 && $page['page_number'] !== $lastPage)
        <button
            type="button"
            @click="currentPage = {{ $page['page_number'] }}"
            :class="isShown({{ $page['page_number'] }}) ? 'border-green bg-green text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-green/40'"
            class="relative flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-2 text-sm font-bold transition-colors"
        >{{ $page['page_number'] }}@if (! empty($beta))<span
                x-show="filtering() && pageMatchCount({{ $page['page_number'] }}) > 0"
                x-cloak
                class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-[#ffdb4d] px-1 text-xs font-bold leading-5 text-gray-900"
                x-text="pageMatchCount({{ $page['page_number'] }})"
            ></span>@endif</button>

        @if ($page['page_number'] === 5 && $lastPage > 6)
            {{-- Prev/next arrows, swipe, and the keyboard shortcuts can land
                 currentPage anywhere in the truncated gap (6..lastPage-1),
                 not just on one of the statically-rendered 1-5/last buttons
                 — confirmed live 2026-09-18: arriving on e.g. page 23 left
                 no button highlighted at all, since 23 was never rendered.
                 Reactive middle button (Alpine, not Blade — currentPage is
                 client-only state): shows "… N …" for whichever page that
                 actually is, or just the plain "…" when currentPage is
                 already one of the static buttons. --}}
            <template x-if="currentPage > 5 && currentPage < {{ $lastPage }}">
                <span class="px-1 text-sm font-bold text-gray-400">…</span>
            </template>
            <template x-if="currentPage > 5 && currentPage < {{ $lastPage }}">
                <button
                    type="button"
                    class="flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-green bg-green px-2 text-sm font-bold text-white"
                    x-text="currentPage"
                ></button>
            </template>
            <template x-if="!(currentPage > 5 && currentPage < {{ $lastPage }})">
                <span class="px-1 text-sm font-bold text-gray-400">…</span>
            </template>
        @endif
    @endforeach
</div>
