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
    $mobileLabel = $variant === 'noOffers' ? 'Pranešti, kai bus akcija' : 'Sekti kainą';
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
    $shareTitleJs = json_encode($productName, JSON_UNESCAPED_UNICODE);
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
        copied: false,
        // Share sheet on phones; elsewhere copy the link and say so.
        async share() {
            const data = { title: {$shareTitleJs}, url: window.location.href.split('#')[0] };
            try {
                if (navigator.share) {
                    await navigator.share(data);
                    return;
                }
                await navigator.clipboard.writeText(data.url);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2500);
            } catch (e) {}
            if (window.trackGaEvent) window.trackGaEvent('share', { method: navigator.share ? 'native' : 'copy', item_id: {$productId} });
        },
    JS;
@endphp

{{-- Soft (not solid green) follow button plus a share button, owner's
     request: the solid green read as too loud next to the price. --}}
<div class="flex w-full items-stretch gap-2 lg:w-auto lg:shrink-0" x-data="{ {{ $toggleHandler }} }">
    <button
        type="button"
        @click="toggle()"
        class="flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl px-4 text-base font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50 lg:flex-none"
        :class="favorited ? 'bg-action text-white hover:bg-action-hover' : 'bg-green-soft-border text-dark-green hover:bg-[#acdfc0]'"
        :disabled="busy"
    >
        <x-app-icon x-show="!favorited" :name="$desktopIcon" class="size-5 fill-none" />
        <x-app-icon x-show="favorited" x-cloak name="check" class="size-5" style="stroke-width:2.5" />
        <span x-show="favorited" x-cloak>Sekama</span>
        <span x-show="!favorited"><span class="lg:hidden">{{ $mobileLabel }}</span><span class="hidden lg:inline">{{ $desktopLabel }}</span></span>
    </button>
    <button
        type="button"
        @click="share()"
        class="flex min-h-12 min-w-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-gray-100 px-3 text-base font-bold text-gray-900 transition-colors hover:bg-gray-200 min-[400px]:px-4"
        aria-label="Dalintis"
    >
        <x-app-icon x-show="!copied" name="share-2" class="size-5" />
        <x-app-icon x-show="copied" x-cloak name="check" class="size-5" />
        <span class="hidden min-[400px]:inline" x-text="copied ? 'Nukopijuota' : 'Dalintis'">Dalintis</span>
    </button>
</div>
