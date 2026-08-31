@foreach ($deals as $deal)
    <x-deal-card :deal="$deal" class="h-full" :context-store-slug="$contextStoreSlug" />
@endforeach
