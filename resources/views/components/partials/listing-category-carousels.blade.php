@props(['sections', 'primarySlug' => null, 'secondarySlug' => null, 'topOffers' => [], 'availableCategories' => []])

@php
    $contextStoreSlug = $primarySlug && \App\Support\StoreDisplayMeta::isStoreSlug($primarySlug)
        ? $primarySlug
        : null;
@endphp

@if (! empty($topOffers))
    <x-landing-deals-section
        id="geriausi-pasiulymai"
        title="Geriausi savaitės pasiūlymai"
        :deals="$topOffers"
        icon="flame"
        layout="carousel"
        :context-store-slug="$contextStoreSlug"
    />
@endif

@foreach ($sections as $section)
    <x-landing-deals-section
        :id="'category-'.$section['slug']"
        :title="$section['name']"
        :deals="$section['discounts']"
        icon="shopping-basket"
        :category-slug="$section['slug']"
        layout="carousel"
        :see-all-href="$secondarySlug ? null : ($primarySlug ? '/akcijos/'.$primarySlug.'/'.$section['slug'] : '/akcijos/'.$section['slug'])"
        :see-all-count="collect($availableCategories)->firstWhere('slug', $section['slug'])['offers_count'] ?? null"
        :context-store-slug="$contextStoreSlug"
    />
@endforeach
