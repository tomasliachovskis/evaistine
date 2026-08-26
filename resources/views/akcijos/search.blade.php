<x-layouts.app :title="'Paieška: ' . $query" :canonical="$canonical" :robots="$robots">
    {{-- Title block matches discounts-layout.tsx's <h1> treatment used by every
         other listing page, for visual consistency. --}}
    <div class="base-container gap-4 pb-4 pt-4 sm:pb-5">
        <h1 class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 font-semibold text-gray-900">
            <span class="text-gray-900">Paieškos rezultatai: „{{ $query }}“</span>
            @if ($total > 0)
                <span class="whitespace-nowrap tabular-nums text-gray-500">({{ number_format($total, 0, ',', ' ') }})</span>
            @endif
        </h1>

        <div class="mt-6 flex w-full flex-wrap gap-8">
            <x-deal-grid :deals="$deals" :pagination="$pagination" :base-path="$basePath" />
        </div>
    </div>
</x-layouts.app>
