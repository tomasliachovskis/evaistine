@if ($mode === 'desktop')
    {{-- Desktop: pill trigger (search-input.tsx lines 328-363, isHeader variant)
         opening a full-width overlay panel (lines 365-425). --}}
    <div x-data="{}" @keydown.escape.window="$wire.open = false">
        <button
            type="button"
            wire:click="$set('open', true)"
            class="relative flex h-10 w-full max-w-none min-w-0 items-center rounded-full bg-white pr-4 pl-10 text-left text-sm shadow-sm transition-opacity hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
        >
            <x-app-icon name="search" class="pointer-events-none absolute left-3.5 size-4 text-gray-400" />
            <span class="truncate text-gray-400">Ieškoti parduotuvių, leidinių, prekių...</span>
        </button>

        <div x-show="$wire.open" x-cloak class="fixed inset-0 z-[100] hidden sm:block">
            <button type="button" class="absolute inset-0 bg-black/55" aria-label="Uždaryti paiešką" wire:click="$set('open', false)"></button>
            <div class="relative bg-white shadow-xl">
                <div class="base-container py-6">
                    <div class="mb-1 flex items-center justify-between gap-4">
                        <p class="text-sm font-semibold text-gray-500">Paieška</p>
                        <button type="button" wire:click="$set('open', false)" class="rounded-full p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900" aria-label="Uždaryti">
                            <x-app-icon name="x" class="size-5" />
                        </button>
                    </div>
                    <form wire:submit.prevent="goToResults" class="relative">
                        <x-app-icon name="search" class="pointer-events-none absolute top-1/2 left-4 h-6 w-6 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="query"
                            placeholder="Ieškoti produktų..."
                            autocomplete="off"
                            x-ref="desktopInput"
                            x-init="$watch('$wire.open', (v) => v && setTimeout(() => $refs.desktopInput.focus(), 50))"
                            class="h-14 w-full rounded-xl border border-gray-200 pr-14 pl-12 text-lg shadow-none focus:border-green focus:outline-none"
                        >
                        <button type="submit" class="absolute top-1/2 right-2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-xl bg-green text-white hover:bg-green-400">
                            <x-app-icon name="arrow-right" class="size-5" />
                        </button>
                    </form>

                    <div class="mt-4 max-h-[min(420px,calc(100vh-220px))] overflow-y-auto border-t border-gray-100 pt-4">
                        @include('components.partials.site-search-results')
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Mobile: icon-only trigger opening a bottom sheet (mobileProductToolbar
         variant, search-input.tsx lines 270-323). --}}
    <div x-data="{}">
        <button type="button" wire:click="$set('open', true)" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-white hover:bg-white/10" aria-label="Ieškoti">
            <x-app-icon name="search" class="size-6" />
        </button>

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
                class="absolute inset-x-0 bottom-0 flex h-[min(560px,82vh)] flex-col overflow-hidden rounded-t-[22px] bg-white shadow-xl"
            >
                <div class="flex shrink-0 justify-center pb-1 pt-2">
                    <div class="h-1 w-12 rounded-full bg-gray-300"></div>
                </div>
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-2.5">
                    <h2 class="text-base font-bold leading-tight">Ieškoti produktų</h2>
                    <button type="button" wire:click="$set('open', false)" class="rounded-full p-0.5 text-gray-500 hover:bg-gray-100 hover:text-gray-900" aria-label="Uždaryti">
                        <x-app-icon name="x" class="size-5" />
                    </button>
                </div>
                <form wire:submit.prevent="goToResults" class="flex shrink-0 flex-col gap-2 p-3">
                    <div class="relative">
                        <x-app-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-[18px] w-[18px] -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="query"
                            placeholder="Ieškoti"
                            autocomplete="off"
                            inputmode="search"
                            class="h-11 w-full rounded-lg border border-gray-200 pl-9 text-base focus:border-green focus:outline-none"
                        >
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-green py-2.5 text-sm font-semibold text-white hover:bg-green-400">Ieškoti</button>
                </form>
                <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                    @include('components.partials.site-search-results')
                </div>
            </div>
        </div>
    </div>
@endif
