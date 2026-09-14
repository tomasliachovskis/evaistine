@props(['match', 'store', 'unitLabel' => null, 'isCheapest' => false, 'highlight' => true])

@php
    // Shared by /pigiausios-prekes and the homepage's comparison teaser (same
    // underlying PriceIndexService::getPageData() match shape) — reuses the
    // real <x-deal-card> instead of a bespoke card, per explicit product
    // decision (the earlier bespoke .pp-store-card broke on narrow mobile
    // widths; the standard card is already responsive everywhere else).
    $deal = [
        'store_id' => $match['store_id'],
        'discounted_price' => $match['raw_price'],
        'original_price' => $match['original_price'] ?? 0,
        'discount_percent' => $match['discount_percent'] ?? null,
        'info' => $unitLabel ? (number_format($match['price'], 2, ',', ' ') . ' ' . $unitLabel) : null,
        'product' => [
            'id' => $match['product_id'] ?? null,
            'name' => $match['product_name'],
            'image_url' => $match['product_image_url'],
            'full_slug' => $match['product_full_slug'] ?? null,
        ],
        'offers' => [
            ['store' => ['id' => $match['store_id'], 'slug' => $store['slug'], 'name' => $store['name']]],
        ],
    ];
@endphp

<div class="relative">
    {{-- Highlight (badge + yellow-featured card) is opt-out: the homepage
         teaser passes highlight="false" per explicit product decision — a
         whole row of small comparison cards doesn't need a standout, that's
         reserved for /pigiausios-prekes' full listing. --}}
    @if ($isCheapest && $highlight)
        <span class="absolute left-2 top-2 z-20 inline-flex items-center rounded-md bg-[#ffdb4d] px-1.5 py-0.5 text-[0.65rem] font-extrabold uppercase tracking-wide text-gray-900">Gera kaina</span>
    @endif
    <x-deal-card :deal="$deal" source="pigiausios-prekes" :featured="$isCheapest && $highlight" />
</div>
