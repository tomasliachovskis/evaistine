<div class="space-y-3">
    @foreach (config('header_nav.product_keyword_items') as $item)
        <a href="/akcijos/{{ $item['slug'] }}" class="flex w-full items-center gap-3 rounded-lg py-1.5 text-sm font-semibold text-gray-900 transition-colors hover:bg-gray-50 min-h-12">
            <span class="min-w-0 flex-1">{{ $item['label'] }}</span>
        </a>
    @endforeach
</div>
