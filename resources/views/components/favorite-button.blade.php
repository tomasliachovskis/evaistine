@props(['productId', 'variant' => 'icon', 'favorited' => false])

@php
    // Plain Alpine widget, not a Livewire component — a deal-card grid embeds
    // dozens of these per page, and one Livewire component boot (unique
    // wire:id, signed snapshot/checksum) per card was the actual cost, not
    // the favorited-status query itself (already memoized, see
    // App\Support\FavoritedProducts). Posts to a plain JSON endpoint
    // (FavoritesController::toggle) instead of a Livewire action.
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
                        // So the login/register/OAuth flow that's about to
                        // open can finish this favorite for them and land on
                        // /favorites — see AuthController::redirectAfterAuth().
                        fetch('/auth/pending-favorite', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ product_id: {$productId} }),
                        }).catch(() => {});
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

@if ($variant === 'button')
    {{-- Ported from discount/src/components/product/product-save-button.tsx:
         solid green pill on the product hero, label flips once favorited. --}}
    <button
        type="button"
        x-data="{ {{ $toggleHandler }} }"
        @click.stop.prevent="toggle()"
        class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-colors"
        :class="favorited ? 'bg-dark-green' : 'bg-green hover:bg-dark-green'"
    >
        <x-app-icon name="heart" class="size-4" x-bind:class="favorited ? 'fill-white' : 'fill-none'" />
        <span x-text="favorited ? 'Sekama' : 'Sekti kainą'"></span>
    </button>
@else
    {{-- Ported from discount/src/components/common/favorite-button.tsx (default,
         non-"header" variant): ghost icon button, no circular backdrop — the
         heart itself fills red when favorited. --}}
    <button
        type="button"
        x-data="{ {{ $toggleHandler }} }"
        @click.stop.prevent="toggle()"
        class="h-auto w-auto p-0 hover:bg-transparent"
        x-bind:aria-label="favorited ? 'Pašalinti iš stebimų' : 'Pridėti į stebimas'"
    >
        <x-app-icon
            name="heart"
            class="size-[22px] transition-colors drop-shadow-none"
            x-bind:class="favorited ? 'fill-red-500 text-red-500' : 'fill-none text-gray-400 hover:text-red-500'"
        />
    </button>
@endif
