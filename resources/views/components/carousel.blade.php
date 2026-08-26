{{-- Plain CSS scroll-snap + Alpine prev/next, no carousel library needed. --}}
<div x-data="{
    scrollBy(amount) { this.$refs.track.scrollBy({ left: amount, behavior: 'smooth' }); },
}" class="relative">
    <div x-ref="track" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {{ $slot }}
    </div>

    <button type="button" @click="scrollBy(-320)" aria-label="Slinkti kairėn"
        class="absolute -left-3 top-1/2 hidden -translate-y-1/2 rounded-full border border-gray-200 bg-white p-2 shadow-md hover:bg-gray-50 sm:block">
        ‹
    </button>
    <button type="button" @click="scrollBy(320)" aria-label="Slinkti dešinėn"
        class="absolute -right-3 top-1/2 hidden -translate-y-1/2 rounded-full border border-gray-200 bg-white p-2 shadow-md hover:bg-gray-50 sm:block">
        ›
    </button>
</div>
