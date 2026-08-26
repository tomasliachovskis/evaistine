@props(['productId', 'favorited' => false])

@php
    // Ported from product-price-watch-banner.tsx's variant="offersHero" — the
    // product hero's follow/price-watch banner. Two DIFFERENT buttons (not
    // one button restyled): mobile gets a full-width "Stebėkite kainą" (bell
    // icon, chevron) button, desktop gets a shrink-wrapped "Sekti kainą"
    // (heart icon) button — swapped via lg:hidden/lg:flex, not just resized.
    // Follower count sits BELOW the button (flex-col), not beside it.
    $productId = (int) $productId;
    $favoritedJs = $favorited ? 'true' : 'false';
    $toggleHandler = <<<JS
        busy: false,
        favorited: {$favoritedJs},
        toggle() {
            if (this.busy) return;
            this.busy = true;
            const prev = this.favorited;
            this.favorited = !this.favorited;
            fetch('/favorites/toggle/{$productId}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content,
                    'Accept': 'application/json',
                },
            })
                .then((r) => {
                    if (r.status === 401) {
                        this.favorited = prev;
                        window.dispatchEvent(new CustomEvent('open-auth-modal'));
                        return null;
                    }
                    return r.json();
                })
                .then((data) => {
                    if (data) {
                        this.favorited = data.favorited;
                        window.dispatchEvent(new CustomEvent('favorites-changed'));
                    }
                })
                .catch(() => { this.favorited = prev; })
                .finally(() => { this.busy = false; });
        },
    JS;
@endphp

<div class="flex w-full flex-col items-start gap-1.5 lg:w-auto lg:shrink-0" x-data="{ {{ $toggleHandler }} }">
    {{-- Mobile: full-width, bell icon, "Stebėkite kainą" / "Sekama" --}}
    <button
        type="button"
        @click="toggle()"
        class="flex w-full items-center gap-2 rounded-lg border-0 px-4 py-3 text-sm font-bold text-white shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 lg:hidden"
        :class="favorited ? 'justify-center bg-green hover:bg-dark-green' : 'justify-between bg-green hover:bg-dark-green'"
        :disabled="busy"
    >
        <template x-if="favorited">
            <span class="flex items-center gap-2">
                <x-app-icon name="check" class="size-4 text-white" style="stroke-width:2.5" />
                Sekama
            </span>
        </template>
        <template x-if="!favorited">
            <span class="flex items-center gap-2"><x-app-icon name="bell" class="size-4 fill-none text-white" /> Stebėkite kainą</span>
        </template>
        <x-app-icon name="chevron-right" class="size-4 shrink-0" x-show="!favorited" />
    </button>

    {{-- Desktop: shrink-wrapped, heart icon, "Sekti kainą" / "Sekama" --}}
    <button
        type="button"
        @click="toggle()"
        class="hidden items-center gap-2 rounded-lg border-0 px-4 py-2.5 text-sm font-bold shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 lg:flex"
        :class="favorited ? 'border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'bg-green text-white hover:bg-dark-green'"
        :disabled="busy"
    >
        <x-app-icon name="heart" class="size-4 transition-colors" x-bind:class="favorited ? 'fill-amber-500 text-amber-500' : 'fill-none text-white'" />
        <span x-text="favorited ? 'Sekama' : 'Sekti kainą'"></span>
    </button>

    <div class="flex items-center gap-1.5">
        <x-app-icon name="users" class="size-[18px] shrink-0 text-gray-500" style="stroke-width:1.75" />
        <p class="text-xs text-gray-600">{{ \App\Support\ProductPageMeta::followerLabel($productId) }}</p>
    </div>
</div>
