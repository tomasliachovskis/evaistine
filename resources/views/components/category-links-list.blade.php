@props(['categories'])

<div class="space-y-3">
    @foreach ($categories as $category)
        <a href="/akcijos/{{ $category['slug'] }}" class="flex w-full items-center gap-3 rounded-lg py-1.5 text-sm font-semibold text-gray-900 transition-colors hover:bg-gray-50">
            <img src="/assets/categories/{{ $category['slug'] }}.svg" alt="" class="h-5 w-5 shrink-0 opacity-70" onerror="this.style.display='none'">
            <span class="min-w-0 flex-1">{{ $category['name'] }}</span>
            <span class="text-sm font-normal tabular-nums text-gray-400">{{ $category['discounts_count'] ?? 0 }}</span>
        </a>
    @endforeach
</div>
