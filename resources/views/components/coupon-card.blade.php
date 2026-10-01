@props(['coupon', 'showWebsiteLink' => true])

@php
    $isCode = $coupon->type === \App\Models\Coupon::TYPE_CODE;
    $link = $coupon->linkUrl();

    if ($coupon->valid_until === null) {
        $validityLabel = 'galioja iki pranešimo';
    } else {
        $daysLeft = now()->startOfDay()->diffInDays($coupon->valid_until, false);
        $validityLabel = $daysLeft <= 3
            ? ($daysLeft <= 0 ? 'baigiasi šiandien' : 'baigiasi po ' . $daysLeft . ' d.')
            : 'iki ' . ($coupon->valid_until->year !== now()->year ? $coupon->valid_until->year . ' m. ' : '') . \App\Support\LithuanianDate::dayMonthGenitive($coupon->valid_until);
    }
@endphp

{{-- Sized up across the board (larger type, thicker touch targets) —
     this audience skews 50+, so the compact, small-text card density used
     elsewhere on the site (product grid cards) is deliberately not reused
     here. --}}
<div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50 p-5 sm:flex-row sm:items-start" x-data="{ revealed: false, copied: false, code: @js($coupon->code), link: @js($link) }">
    <div class="flex shrink-0 items-center justify-center sm:w-32">
        @if ($coupon->website->logo_url)
            <img src="{{ $coupon->website->logo_url }}" alt="{{ $coupon->website->name }}" class="h-14 w-auto max-w-[8rem] object-contain">
        @else
            <span class="text-lg font-bold text-gray-700">{{ $coupon->website->name }}</span>
        @endif
    </div>

    <div class="min-w-0 flex-1">
        <div class="mb-1.5 flex flex-wrap items-center gap-2.5">
            <span class="text-sm font-bold uppercase tracking-wide text-gray-500">{{ $isCode ? 'Nuolaidos kodas' : 'Nuolaida' }}</span>
            @if ($coupon->is_exclusive)
                <span class="inline-flex items-center rounded-full bg-green/10 px-2.5 py-1 text-sm font-bold text-dark-green">Tik pas mus</span>
            @endif
            @if ($coupon->is_verified)
                <span class="inline-flex items-center gap-1 text-sm font-medium text-gray-500">
                    <x-app-icon name="circle-check" class="size-4.5 text-dark-green" />Patvirtinta
                </span>
            @endif
        </div>

        <h3 class="text-xl font-bold leading-snug text-gray-900">{{ $coupon->title }}</h3>

        @if ($coupon->description)
            <p class="mt-1.5 text-base leading-snug text-gray-600">{{ $coupon->description }}</p>
        @endif

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-gray-500">
            <span>{{ $validityLabel }}</span>
            @if ($coupon->usage_count > 0)
                <span>{{ number_format($coupon->usage_count, 0, ',', ' ') }}x panaudotas</span>
            @endif
            @if ($showWebsiteLink)
                <a href="/kuponai/{{ $coupon->website->slug }}" class="inline-flex min-h-12 items-center font-bold text-dark-green underline underline-offset-4 hover:no-underline">Visi kuponai iš {{ $coupon->website->name }}</a>
            @endif
        </div>

        @if ($coupon->editor_tip)
            <div class="mt-3 rounded-lg bg-white p-3.5 text-sm leading-snug text-gray-600">
                <span class="font-bold text-gray-900">Redaktoriaus patarimas.</span> {{ $coupon->editor_tip }}
            </div>
        @endif

        @if ($coupon->terms)
            <details class="mt-3 text-sm text-gray-500">
                <summary class="cursor-pointer font-bold text-gray-600 hover:text-gray-900">Naudojimo sąlygos</summary>
                <p class="mt-1.5 leading-snug">{{ $coupon->terms }}</p>
            </details>
        @endif
    </div>

    <div class="flex shrink-0 flex-col items-stretch gap-2.5 sm:w-56">
        <x-coupon-discount-badge :label="$coupon->discount_label" />

        @if ($isCode)
            <div class="relative">
                <button
                    type="button"
                    @click="
                        revealed = true;
                        if (navigator.clipboard && code) {
                            navigator.clipboard.writeText(code).then(() => { copied = true; setTimeout(() => copied = false, 2000); });
                        }
                        if (link) { window.open(link, '_blank', 'noopener,noreferrer'); }
                    "
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-green bg-white px-4 py-3.5 text-base font-bold text-dark-green transition-colors hover:bg-green/5"
                >
                    <span x-text="!revealed ? 'Rodyti kodą' : (copied ? 'Nukopijuota!' : code)"></span>
                </button>
            </div>
        @else
            <a
                href="{{ $link ?? '#' }}"
                target="_blank"
                rel="noopener noreferrer nofollow sponsored"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-action px-4 py-3.5 text-base font-bold text-white transition-colors hover:bg-action-hover"
            >
                Parodyti nuolaidą
            </a>
        @endif
    </div>
</div>
