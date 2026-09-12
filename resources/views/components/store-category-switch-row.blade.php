@props(['title', 'helperText' => null, 'items' => []])

{{-- Matches the mockup's .switch-row/.switch-chip exactly: a wrapping row of
     compact vertical chips (never horizontal-scroll — the same "unbounded
     lists must wrap" rule used throughout the mockup), letting someone jump
     to the same category at another store without losing their place. --}}
@if (!empty($items))
    <div class="section-card mt-6 rounded-lg border border-gray-200 bg-white p-4 sm:p-5">
        <h2 class="text-base font-bold leading-tight text-gray-900">{{ $title }}</h2>
        @if ($helperText)
            <p class="mb-3 mt-1 text-sm text-gray-500">{{ $helperText }}</p>
        @endif
        <div class="flex flex-wrap gap-2.5">
            @foreach ($items as $item)
                @if ($item['is_current'] ?? false)
                    <div class="flex min-w-[84px] shrink-0 flex-col items-center gap-1 rounded-xl border-[2.5px] border-green bg-green/10 px-4 py-2.5">
                        <x-store-logo :slug="$item['image_slug']" :name="$item['label']" size="xs" />
                        <span class="text-[0.85rem] font-bold text-gray-900">{{ $item['label'] }}</span>
                        <span class="flex items-center gap-1 text-[0.72rem] font-extrabold text-dark-green">
                            <x-app-icon name="check" class="size-3" />
                            Esi čia
                        </span>
                    </div>
                @else
                    <a href="{{ $item['href'] }}" class="flex min-w-[84px] shrink-0 flex-col items-center gap-1 rounded-xl border-2 border-gray-200 bg-white px-4 py-2.5 transition-colors hover:border-green/40">
                        <x-store-logo :slug="$item['image_slug']" :name="$item['label']" size="xs" />
                        <span class="text-[0.85rem] font-bold text-gray-900">{{ $item['label'] }}</span>
                        <span class="text-[0.72rem] text-gray-400">{{ number_format($item['discounts_count'], 0, ',', ' ') }} {{ \App\Support\LithuanianPlural::offerWord($item['discounts_count']) }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
