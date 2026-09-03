@props(['productId', 'productName' => '', 'productImage' => null, 'variant' => 'icon', 'favorited' => false])

@php
    $productId = (int) $productId;
    $productNameJs = json_encode($productName, JSON_UNESCAPED_UNICODE);
    $productImageJs = json_encode($productImage);
@endphp

@if ($variant === 'button')
    {{-- Ported from discount/src/components/product/product-save-button.tsx:
         solid green pill on the product hero, label flips once favorited. --}}
    <button
        type="button"
        x-data="favoriteButton({{ $productId }}, {{ $favorited ? 'true' : 'false' }}, {{ $productNameJs }}, {{ $productImageJs }})"
        @click.stop.prevent="toggle()"
        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-colors"
        :class="favorited ? 'bg-dark-green' : 'bg-green hover:bg-dark-green'"
    >
        <x-app-icon name="heart" class="size-4" x-bind:class="favorited ? 'fill-white' : 'fill-none'" />
        <span x-text="favorited ? 'Sekama' : 'Sekti kainą'"></span>
    </button>
@else
    {{-- Ported from discount/src/components/common/favorite-button.tsx (default,
         non-"header" variant): ghost icon button, no circular backdrop — the
         heart itself fills red when favorited. --}}
    {{-- Visual mobile SEO audit finding: this was h-auto w-auto p-0, so its
         tap target was exactly the 22px icon — well under the ~44px
         guidance, and repeated on every card in every grid. Sizing the
         button itself to 44px (icon stays visually 22px, just centered in
         a bigger invisible hit area) fixes that without changing how the
         icon looks. --}}
    <button
        type="button"
        x-data="favoriteButton({{ $productId }}, {{ $favorited ? 'true' : 'false' }}, {{ $productNameJs }}, {{ $productImageJs }})"
        @click.stop.prevent="toggle()"
        class="flex h-11 w-11 items-center justify-center hover:bg-transparent"
        x-bind:aria-label="favorited ? 'Pašalinti iš stebimų' : 'Pridėti į stebimas'"
    >
        <x-app-icon
            name="heart"
            class="size-[22px] transition-colors drop-shadow-none"
            x-bind:class="favorited ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400 hover:text-red-500'"
        />
    </button>
@endif
