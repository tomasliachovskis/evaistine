@if ($mode === 'desktop')
    {{-- Desktop: pill trigger (search-input.tsx lines 328-363, isHeader variant)
         opening a full-width overlay panel (lines 365-425). --}}
    <div x-data="{}" @keydown.escape.window="$wire.open = false">
        <button
            type="button"
            wire:click="$set('open', true)"
            class="relative flex h-12 w-full max-w-none min-w-0 items-center rounded-xl bg-gray-100 pr-4 pl-12 text-left text-base transition-colors hover:bg-gray-200"
        >
            <x-app-icon name="search" class="pointer-events-none absolute left-4 size-6 text-gray-700" />
            <span class="truncate text-gray-600">Ieškoti prekės ar vaistinės</span>
        </button>

        {{-- Teleported to <body>: <header> is `fixed` + `z-50`, its own
             stacking context, so this overlay's z-index was capped there
             and a page's z-[60] sticky filter bar (e.g. /leidiniai store
             chips) rendered on top of it. Same fix as site-header's
             Kategorijos modal/mobile menu, via Livewire's @teleport so the
             wire: bindings inside keep working. --}}
        @teleport('body')
        <div x-show="$wire.open" x-cloak class="fixed inset-0 z-[100] hidden sm:block">
            <button type="button" class="absolute inset-0 bg-black/55" aria-label="Uždaryti paiešką" wire:click="$set('open', false)"></button>
            <div class="relative bg-white shadow-xl">
                <div class="base-container py-6">
                    <div class="mb-1 flex items-center justify-between gap-4">
                        <p class="text-2xl font-bold text-gray-900">Paieška</p>
                        <button type="button" wire:click="$set('open', false)" class="sheet-close" aria-label="Uždaryti">
                            <x-app-icon name="x" class="size-7" />
                        </button>
                    </div>
                    <form @submit="window.trackGaEvent && window.trackGaEvent('search', { query: $wire.query.trim() })" wire:submit.prevent="goToResults" class="relative">
                        <x-app-icon name="search" class="pointer-events-none absolute top-1/2 left-4 h-6 w-6 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="query"
                            placeholder="Ieškoti produktų..."
                            autocomplete="off"
                            x-ref="desktopInput"
                            x-init="$watch('$wire.open', (v) => v && setTimeout(() => $refs.desktopInput.focus(), 50))"
                            class="h-16 w-full rounded-xl border border-gray-200 pr-36 pl-12 text-xl shadow-none focus:border-dark-green focus:outline-none"
                        >
                        <button type="submit" class="absolute top-1/2 right-2 flex h-12 -translate-y-1/2 items-center gap-2 rounded-xl bg-action px-5 text-lg font-bold text-white hover:bg-action-hover">
                            Ieškoti
                            <x-app-icon name="arrow-right" class="size-5" />
                        </button>
                    </form>

                    <div class="mt-4 max-h-[min(420px,calc(100vh-220px))] overflow-y-auto border-t border-gray-100 pt-4">
                        @include('components.partials.site-search-results')
                    </div>
                </div>
            </div>
        </div>
        @endteleport
    </div>
@else
    {{-- Mobile: icon-only trigger opening a bottom sheet (mobileProductToolbar
         variant, search-input.tsx lines 270-323). --}}
    <div x-data="{}">
        <button type="button" wire:click="$set('open', true)" class="relative flex size-12 items-center justify-center rounded-xl text-gray-900 transition-colors hover:bg-gray-100" aria-label="Ieškoti" title="Ieškoti">
            <x-app-icon name="search" class="size-7" />
        </button>

        {{-- Teleported for the same header stacking-context reason as the
             desktop overlay above. --}}
        @teleport('body')
        <div x-show="$wire.open" x-cloak class="fixed inset-0 z-[9999] sm:hidden">
            <button type="button" class="absolute inset-0 bg-black/55" aria-label="Uždaryti" wire:click="$set('open', false)"></button>
            <div
                x-show="$wire.open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                class="absolute inset-x-0 bottom-0 flex h-[min(640px,90vh)] flex-col overflow-hidden rounded-t-3xl bg-white shadow-xl"
            >
                <div class="flex shrink-0 justify-center pb-1 pt-2">
                    <div class="h-1 w-12 rounded-full bg-gray-300"></div>
                </div>
                <div class="flex shrink-0 items-center justify-between gap-3 border-b px-4 py-3">
                    <h2 class="text-2xl font-bold leading-tight">Paieška</h2>
                    <button type="button" wire:click="$set('open', false)" class="sheet-close" aria-label="Uždaryti">
                        <x-app-icon name="x" class="size-7" />
                    </button>
                </div>
                <form @submit="window.trackGaEvent && window.trackGaEvent('search', { query: $wire.query.trim() })" wire:submit.prevent="goToResults" class="flex shrink-0 flex-col gap-2 p-3">
                    <div class="relative">
                        <x-app-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-6 -translate-y-1/2 text-gray-600" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="query"
                            placeholder="pvz. vitaminas D, Eurovaistinė leidinys"
                            autocomplete="off"
                            inputmode="search"
                            class="h-14 w-full rounded-xl border border-gray-200 pl-12 text-lg focus:border-dark-green focus:outline-none"
                        >
                    </div>
                    <button type="submit" class="min-h-14 w-full rounded-xl bg-action text-lg font-bold text-white hover:bg-action-hover">Ieškoti</button>
                </form>
                <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                    @include('components.partials.site-search-results')
                </div>
            </div>
        </div>
        @endteleport
    </div>
@endif
