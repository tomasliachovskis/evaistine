@props(['categories'])

{{-- Categories inside the side menu's "Kategorijos" section: one grouped,
     lightly tinted panel with compact 48px rows, so the list reads as part
     of that section instead of loose, widely spaced lines. --}}
<div class="mb-1 mt-1 flex flex-col rounded-xl bg-gray-50 p-1.5">
    @foreach ($categories as $category)
        <a href="/{{ $category['slug'] }}" class="flex min-h-12 w-full items-center gap-3 rounded-lg px-3 text-base font-semibold text-gray-900 transition-colors hover:bg-white">
            <span class="min-w-0 flex-1 truncate">{{ $category['name'] }}</span>
            <span class="shrink-0 text-sm tabular-nums text-gray-500">{{ \App\Support\LithuanianPlural::formatCount((int) ($category['discounts_count'] ?? 0)) }}</span>
        </a>
    @endforeach
</div>
