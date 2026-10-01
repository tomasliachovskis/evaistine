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
            :class="isShown({{ $page['page_number'] }}) ? '{{ ! empty($beta) ? 'border-action bg-action' : 'border-green bg-action' }} text-white' : '{{ ! empty($beta) ? 'border-gray-400 text-font' : 'border-gray-200 text-gray-700' }} bg-white hover:border-green/40'"
            class="relative flex items-center justify-center rounded-lg border font-bold transition-colors min-h-12 min-w-12 border px-2.5 text-lg"
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
                <span class="px-1 font-bold {{ ! empty($beta) ? 'text-lg text-gray-600' : 'text-sm text-gray-400' }}">…</span>
            </template>
            <template x-if="currentPage > 5 && currentPage < {{ $lastPage }}">
                <button
                    type="button"
                    class="flex items-center justify-center rounded-lg border font-bold text-white min-h-12 min-w-12 bg-green-soft bg-action px-2.5 text-lg"
                    x-text="currentPage"
                ></button>
            </template>
            <template x-if="!(currentPage > 5 && currentPage < {{ $lastPage }})">
                <span class="px-1 font-bold {{ ! empty($beta) ? 'text-lg text-gray-600' : 'text-sm text-gray-400' }}">…</span>
            </template>
        @endif
    @endforeach
</div>
