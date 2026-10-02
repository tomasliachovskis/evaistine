{{-- The light green navigation bar above offer and leaflet lists: labelled
     <x-nav-bar-button>s that say in words what is shown. One component so
     /akcijos listings, search, /leidiniai and /leidinys/{store} can't drift
     apart. Deliberately not pinned (owner's decision, 2026-10-02): with
     labels it is ~95px tall and covered too much of the list. --}}
<div {{ $attributes->merge(['class' => 'relative z-30 mb-4 flex w-full flex-col gap-3 rounded-2xl border border-green/30 bg-green/5 p-3 sm:flex-row sm:items-end sm:gap-4 sm:px-4']) }}>
    {{ $slot }}
</div>
