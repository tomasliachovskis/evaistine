@props([
    'productId',
    'favorited' => false,
    'productName' => '',
    'productImage' => null,
    'categoryName' => '',
    'variant' => 'offersHero',
])

@php
    // Ported from product-price-watch-banner.tsx — offersHero for active
    // products, noOffers when there is no live promotion.
    $productId = (int) $productId;
    $favoritedJs = $favorited ? 'true' : 'false';
    $mobileLabel = $variant === 'noOffers' ? 'Pranešti, kai bus akcija' : 'Stebėkite kainą';
    $desktopLabel = $variant === 'noOffers' ? 'Pranešti, kai bus akcija' : 'Sekti kainą';
    // Bell everywhere a "follow/notify me" action is offered (matches the
    // mobile button right above and every other Sekti* button site-wide,
    // e.g. store-subscribe-button.blade.php) — this used to show a heart
    // here specifically, the one inconsistent spot for the same action.
    $desktopIcon = 'bell';
    $gaPayload = json_encode([
        'product_id' => $productId,
        'product_name' => $productName,
        'category' => $categoryName,
        'is_logged_in' => auth()->check(),
        'position' => 'hero',
    ], JSON_UNESCAPED_UNICODE);
    $priceWatchDetail = json_encode([
        'productName' => $productName,
        'productImage' => $productImage,
    ], JSON_UNESCAPED_UNICODE);
    $toggleHandler = <<<JS
        busy: false,
        favorited: {$favoritedJs},
        toggle() {
            if (this.busy) return;
            // price_alert_subscribe in discount/src/lib/google-analytics.ts
            // — only on the subscribe transition, not on unfavoriting, and
            // fired regardless of whether the toggle itself succeeds.
            if (!this.favorited && window.trackGaEvent) {
                window.trackGaEvent('price_alert_subscribe', {$gaPayload});
            }
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
                        // So the magic-link flow that's about to open can
                        // finish this favorite once the emailed link is
                        // clicked, and land on /favorites — see
                        // AuthController::redirectAfterAuth().
                        fetch('/auth/pending-favorite', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").content,
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ product_id: {$productId} }),
                        }).catch(() => {});
                        window.dispatchEvent(new CustomEvent('open-price-watch-modal', { detail: {$priceWatchDetail} }));
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
    {{-- Mobile: full-width, same slim CTA shape as <x-leaflet-card>'s
         "Peržiūrėti" button (h-11, rounded-lg, text-base). --}}
    <button
        type="button"
        @click="toggle()"
        class="flex h-11 w-full items-center justify-center gap-2 rounded-lg border-0 bg-green text-base font-bold text-white transition-colors hover:bg-dark-green disabled:cursor-not-allowed disabled:opacity-50 lg:hidden"
        :disabled="busy"
    >
        <template x-if="favorited">
            <span class="flex items-center gap-2">
                <x-app-icon name="check" class="size-4 text-white" style="stroke-width:2.5" />
                Sekama
            </span>
        </template>
        <template x-if="!favorited">
            <span class="flex items-center gap-2"><x-app-icon name="bell" class="size-4 fill-none text-white" /> {{ $mobileLabel }}</span>
        </template>
    </button>

    {{-- Desktop: shrink-wrapped, heart icon, "Sekti kainą" / "Sekama" --}}
    <button
        type="button"
        @click="toggle()"
        class="hidden items-center gap-2 rounded-lg border-0 px-4 py-2.5 text-sm font-bold shadow-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 lg:flex"
        :class="favorited ? 'border border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-100' : 'bg-green text-white hover:bg-dark-green'"
        :disabled="busy"
    >
        <x-app-icon :name="$desktopIcon" class="size-4 transition-colors" x-bind:class="favorited ? 'fill-amber-500 text-amber-500' : 'fill-none text-white'" />
        <span x-show="favorited" x-cloak>Sekama</span>
        <span x-show="!favorited" x-cloak>{{ $desktopLabel }}</span>
    </button>
</div>
