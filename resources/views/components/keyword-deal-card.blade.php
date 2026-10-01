@props(['card', 'untilMore' => false])

{{-- One keyword per card (/pradzia-beta): the keyword, a photo of its
     cheapest current deal, that price and store, and how many offers the
     keyword page compares. The whole card is one link to the keyword page.
     Sized for older readers: price 28px, a 48px bottom row. untilMore:
     hidden until the parent's Alpine `more` flag ("Rodyti daugiau") is on. --}}
@php
    use App\Support\LithuanianPlural;

    $count = (int) ($card['count'] ?? 0);
@endphp

<a
    href="{{ $card['href'] }}"
    data-ga-event="product_card_click"
    data-ga-product-id="{{ $card['product_id'] }}"
    data-ga-product-name="{{ $card['product_name'] }}"
    data-ga-source="home_beta"
    @if ($untilMore) x-show="more" x-cloak @endif
    {{ $attributes->class('flex flex-col gap-2.5 rounded-3xl bg-white p-2 shadow-[0_1px_2px_rgba(22,32,26,0.05),0_8px_24px_rgba(22,32,26,0.05)] transition-shadow hover:shadow-[0_1px_2px_rgba(22,32,26,0.08),0_12px_32px_rgba(22,32,26,0.1)] sm:gap-3.5 sm:p-2.5') }}
>
    <div class="relative flex h-32 items-center justify-center overflow-hidden rounded-2xl bg-green-soft sm:h-48">
        @if ($card['image_url'])
            <img src="{{ $card['image_url'] }}" alt="{{ $card['product_name'] }}" loading="lazy" class="max-h-28 max-w-[80%] object-contain mix-blend-multiply sm:max-h-40 sm:max-w-[70%]" onerror="this.style.display='none'">
        @endif
        <span class="absolute left-2 top-2 sm:left-3 sm:top-3">
            <x-discount-badge :percent="$card['discount_percent']" />
        </span>
    </div>

    <div class="flex flex-col gap-0.5 px-1.5 sm:gap-1.5 sm:px-2.5">
        <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between sm:gap-2">
            <span class="text-lg font-extrabold leading-tight tracking-tight text-gray-900 sm:text-xl">{{ $card['label'] }}</span>
            <span class="text-2xl font-extrabold leading-tight tracking-tight text-action tabular-nums">{{ number_format($card['price'], 2, ',', ' ') }} €</span>
        </div>
        <span class="hidden text-sm leading-snug text-gray-600 sm:block">{{ $card['product_name'] }}</span>
        @if ($card['store_slug'])
            <span class="mt-0.5 flex items-center gap-2 text-xs text-gray-700 sm:text-sm">
                <x-store-logo :slug="$card['store_slug']" :name="$card['store_name']" size="xs" />
                {{ $card['store_name'] }}
            </span>
        @endif
    </div>

    <span class="mt-auto flex min-h-12 items-center justify-between gap-1 rounded-2xl bg-green-soft px-2.5 py-1 leading-tight min-[360px]:whitespace-nowrap text-xs font-bold text-dark-green sm:px-4 sm:text-base">
        {{ LithuanianPlural::formatCount($count) }} {{ LithuanianPlural::offerWord($count) }}
        <x-app-icon name="arrow-right" class="size-4 shrink-0 sm:size-5" />
    </span>
</a>
