@props(['sections', 'primarySlug' => null, 'secondarySlug' => null])

@php
    $contextStoreSlug = $primarySlug && \App\Support\StoreDisplayMeta::isStoreSlug($primarySlug)
        ? $primarySlug
        : null;
@endphp

@foreach ($sections as $section)
    <x-landing-deals-section
        :id="'category-'.$section['slug']"
        :title="$section['name']"
        :deals="$section['discounts']"
        icon="shopping-basket"
        :category-slug="$section['slug']"
        layout="carousel"
        :see-all-href="$secondarySlug ? null : ($primarySlug ? '/akcijos/'.$primarySlug.'/'.$section['slug'] : '/akcijos/'.$section['slug'])"
        :context-store-slug="$contextStoreSlug"
    />
@endforeach
