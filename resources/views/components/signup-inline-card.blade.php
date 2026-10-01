{{-- Guest-only "get an email when it gets cheaper" card, sitting in the
     page flow. Replaces the old <x-signup-savings-popup>, which covered
     the page by itself after 10 seconds: a popup nobody asked for breaks
     the "nothing changes on its own" rule for older readers
     (docs/ui-older-readers.md). Opens the shared auth-modal. --}}
@guest
    <div x-data {{ $attributes->class('flex flex-col gap-3 rounded-2xl border border-green/30 bg-green/5 p-4 sm:flex-row sm:items-center sm:gap-6 sm:p-5') }}>
        <div class="min-w-0 flex-1">
            <h2 class="flex items-center gap-2 text-lg font-bold leading-snug text-gray-900">
                <x-app-icon name="bell" class="size-6 shrink-0 fill-none text-dark-green" />
                Pranešime, kai prekė atpigs
            </h2>
            <p class="mt-1 text-base leading-snug text-gray-700">Pažymėkite prekes, ir parašysime el. paštu, kai jos atpigs. Nemokamai.</p>
        </div>
        <button
            type="button"
            @click="$store.authModal.open = true"
            class="min-h-12 shrink-0 rounded-xl bg-action px-6 text-base font-bold text-white transition-colors hover:bg-action-hover"
        >
            Užsiregistruoti nemokamai
        </button>
    </div>
@endguest
