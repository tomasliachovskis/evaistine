@props(['eyebrow' => null, 'iconSrc' => null, 'title', 'subtitle' => null])

{{-- Shared hero band (matches the mockup's .type-hero) used on every store/
     category/leidinys page: green-tinted rounded card, icon box, eyebrow,
     title, subtitle, then a stats row (passed as the default slot so each
     caller can mix plain bold-number stats, freshness, and links) and an
     optional CTA button (named $cta slot, e.g. <x-store-subscribe-button>). --}}
<div class="flex flex-col items-start gap-4 rounded-2xl border border-green-soft-border bg-green-soft p-5 sm:flex-row sm:items-center sm:p-6">
    <div class="flex min-w-0 flex-1 items-start gap-3 sm:items-center sm:gap-4">
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
            @if ($subtitle)
                <p class="mt-1 max-w-[60ch] text-sm leading-relaxed text-gray-600 sm:text-base">{{ $subtitle }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <p class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 sm:text-sm">
                    {{ $slot }}
                </p>
            @endif
        </div>
    </div>

    @isset($cta)
        <div class="w-full shrink-0 sm:w-auto">
            {{ $cta }}
        </div>
    @endisset
</div>
