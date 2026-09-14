@props(['eyebrow' => null, 'iconSrc' => null, 'title', 'subtitle' => null, 'hideSubtitleOnMobile' => false])

{{-- Shared hero band (matches the mockup's .type-hero) used on every store/
     category/leidinys page: green-tinted rounded card, icon box, eyebrow,
     title, subtitle, then a stats row (passed as the default slot so each
     caller can mix plain bold-number stats, freshness, and links) and an
     optional CTA button (named $cta slot, e.g. <x-store-subscribe-button>). --}}
<div class="flex flex-col items-start gap-4 rounded-2xl border border-green-soft-border bg-green-soft p-5 sm:flex-row sm:items-center sm:p-6">
    <div class="min-w-0 flex-1">
        {{-- Icon stays beside the eyebrow/title at every width — only the
             subtitle and stats row below drop out to the card's full width
             instead of being squeezed into the text column next to the icon. --}}
        <div class="flex items-center gap-3 sm:gap-4">
            @if ($iconSrc)
                <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white p-2 shadow-sm sm:size-16">
                    <img src="{{ $iconSrc }}" alt="" class="h-full w-full object-contain" onerror="this.style.visibility='hidden'">
                </span>
            @elseif (isset($icon))
                <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-white text-green shadow-sm sm:size-16">
                    {{ $icon }}
                </span>
            @endif
            <div class="min-w-0 flex-1">
                @if ($eyebrow)
                    <span class="text-xs font-extrabold uppercase tracking-wide text-green">{{ $eyebrow }}</span>
                @endif
                <h1 class="{{ $eyebrow ? 'mt-0.5' : '' }} min-w-0 text-xl font-extrabold leading-tight text-gray-900 sm:text-2xl">{{ $title }}</h1>
            </div>
        </div>
        @if ($subtitle)
            <p class="mt-2 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base {{ $hideSubtitleOnMobile ? 'hidden sm:block' : '' }}">{{ $subtitle }}</p>
        @endif
        @if ($slot->isNotEmpty())
            {{-- Vertical-bar separator before every stat but the first —
                 sm+ only. On mobile these items wrap to their own line
                 (narrower viewport, same gap-y-1 row spacing as before), and
                 a leading "|" on each wrapped line reads as a stray bullet
                 rather than a separator, so it's dropped there entirely
                 instead of trying to hide it conditionally per line. --}}
            <p class="mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600 sm:gap-x-4 sm:text-sm sm:[&>*:not(:first-child)]:before:mr-2 sm:[&>*:not(:first-child)]:before:text-gray-300 sm:[&>*:not(:first-child)]:before:content-['|']">
                {{ $slot }}
            </p>
        @endif
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
