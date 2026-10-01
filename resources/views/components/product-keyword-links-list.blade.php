{{-- Same grouped panel as <x-category-links-list> in the side menu. --}}
<div class="mb-1 mt-1 flex flex-col rounded-xl bg-gray-50 p-1.5">
    @foreach (config('header_nav.product_keyword_items') as $item)
        <a href="/akcijos/{{ $item['slug'] }}" class="flex min-h-12 w-full items-center rounded-lg px-3 text-base font-semibold text-gray-900 transition-colors hover:bg-white">
            <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
        </a>
    @endforeach
</div>
