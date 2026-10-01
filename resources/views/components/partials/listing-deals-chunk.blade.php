{{-- Width classes must exactly match the main grid's own
     <x-deal-card> call (discount-filters.blade.php) — infinite-scroll
     appends these into the same flex-wrap container, and a flex item
     with no explicit width just grows to fill leftover row space
     instead of matching its siblings' size. --}}
@foreach ($deals as $deal)
    <x-deal-card
        :deal="$deal"
        :stretch="false"
        :context-store-slug="$contextStoreSlug"
        class="deal-card-width"
    />
@endforeach
