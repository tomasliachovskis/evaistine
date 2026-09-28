@props(['iconSrc' => null, 'title', 'subtitle' => null, 'hideSubtitleOnMobile' => false])

{{-- Plain hero band used on every store/category/leidinys page — same
     bare title + gray stats-line look as the /akcijos hub's own header
     (no green card, no bordered icon box), just with an optional small
     store/category icon, eyebrow label, and a $cta slot the hub doesn't
     need. h1 gets no size classes here — it inherits the site's global h1
     rule (resources/css/app.css) same as the hub's plain heading. --}}
<div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 sm:gap-2.5">
            @if ($iconSrc)
                <img src="{{ $iconSrc }}" alt="" class="hidden size-8 shrink-0 object-contain sm:block sm:size-9" onerror="this.style.visibility='hidden'">
            @elseif (isset($icon))
                <span class="hidden size-8 shrink-0 items-center justify-center text-green sm:flex sm:size-9">
                    {{ $icon }}
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="min-w-0">{{ $title }}</h1>
            </div>
        </div>
        @if ($subtitle)
            <p class="mt-1.5 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base {{ $hideSubtitleOnMobile ? 'hidden sm:block' : '' }}">{{ $subtitle }}</p>
        @endif
        {{-- Stats/freshness render outside this component, as
             <x-hero-stats> right after it. --}}
    </div>

    @isset($cta)
        {{-- Hidden on mobile: with the stats row above now packed onto one
             line to fit small screens, a full-width follow button underneath
             just adds height back — kept for sm+ where it sits inline beside
             the hero instead of stacked below it. --}}
        <div class="hidden w-full shrink-0 sm:block sm:w-auto">
            {{ $cta }}
        </div>
    @endisset
</div>
