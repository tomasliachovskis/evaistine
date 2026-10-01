{{-- Global price-watch capture modal: opened instead of <x-auth-modal> when a
     guest clicks "Sekti kainą" on a product (see product-price-watch-banner's
     toggle()). Email-only, no password/registration — reuses the same
     magic-link endpoint (POST /login) as <x-auth-modal>, the pending-favorite
     stash (POST /auth/pending-favorite, called by the banner before this
     opens) completes the favorite once the emailed link is clicked, same as
     the generic login flow. No browser-push option — email only.

     Kept deliberately short: thumbnail + question + one line of copy, no
     separate "Kainos pranešimas" eyebrow/header bar and no feature-bullet
     list — those made this modal much longer than <x-auth-modal>'s. The
     "sent" confirmation box still mirrors auth-modal's for a consistent
     login-vs-price-watch success experience. --}}
<div
    x-data="{
        sent: false,
        submitting: false,
        errors: {},
        productName: '',
        productImage: null,
        firstError(errors) {
            const values = Object.values(errors);
            return values.length ? values[0][0] : null;
        },
        async submit(form) {
            if (this.submitting) return;
            this.submitting = true;
            this.errors = {};
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                });
                if (res.status === 422) {
                    const data = await res.json();
                    this.errors = data.errors || {};
                    this.submitting = false;
                    return;
                }
                if (!res.ok) {
                    this.submitting = false;
                    return;
                }
                this.submitting = false;
                this.sent = true;
            } catch (e) {
                this.submitting = false;
            }
        },
    }"
    @open-price-watch-modal.window="$store.priceWatchModal.open = true; sent = false; errors = {}; productName = $event.detail.productName; productImage = $event.detail.productImage"
    x-show="$store.priceWatchModal.open"
    x-cloak
    @keydown.escape.window="$store.priceWatchModal.open = false"
    x-back-closes="$store.priceWatchModal.open"
    class="sheet-backdrop"
    style="display: none;"
>
    <div @click.outside="$store.priceWatchModal.open = false" class="sheet-panel">
        <div class="sheet-handle"></div>
        <div class="sheet-head">
            <h2 class="text-2xl font-bold text-gray-900">Sekti kainą</h2>
            <button type="button" @click="$store.priceWatchModal.open = false" class="sheet-close" aria-label="Uždaryti">
                <x-app-icon name="x" class="size-7" />
            </button>
        </div>
        <div class="px-5 pb-6 sm:px-6">

        <template x-if="sent">
            <div class="space-y-2 rounded-lg border border-green/30 bg-green/5 px-4 py-4 text-center">
                <p class="text-sm font-semibold text-gray-900">Nuoroda išsiųsta!</p>
                <p class="text-sm text-gray-600">Patikrinkite savo el. paštą ir paspauskite nuorodą — tada sekimas šiai prekei bus įjungtas. Ji galioja 30 minučių.</p>
            </div>
        </template>

        <template x-if="!sent">
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-green/10">
                        <template x-if="productImage">
                            <img :src="productImage" :alt="productName" class="h-full w-full object-contain p-1.5">
                        </template>
                        <template x-if="!productImage">
                            <x-app-icon name="bell" class="size-6 fill-none text-dark-green" />
                        </template>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold text-gray-900">Sekti šią prekę?</h2>
                        <p class="truncate text-sm text-gray-500" x-text="productName"></p>
                    </div>
                </div>

                <p class="text-sm text-gray-600">Pranešime, kai vėl atsiras šios prekės akcija.</p>

                <form @submit.prevent="submit($el)" method="POST" action="/login" class="space-y-3">
                    @csrf
                    <template x-if="firstError(errors)">
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" x-text="firstError(errors)"></p>
                    </template>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-900">El. paštas</label>
                        <input type="email" name="email" required placeholder="jusu@email.lt" class="min-h-12 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                    </div>
                    <button type="submit" :disabled="submitting" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-action text-sm font-semibold text-white shadow-sm transition-colors hover:bg-action-hover disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border border-white/40 border-t-white"></span>
                        <x-app-icon name="bell" class="size-4 fill-none" x-show="!submitting" />
                        Pranešti apie naują akciją
                    </button>
                </form>

                <div class="relative py-1">
                    <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-gray-200"></span></div>
                    <div class="relative flex justify-center"><span class="bg-white px-3 text-xs font-medium uppercase tracking-wide text-gray-500">Arba</span></div>
                </div>

                <a href="/auth/google/redirect" class="flex min-h-12 w-full cursor-pointer items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white text-sm font-semibold text-gray-900 transition-colors hover:border-gray-300 hover:bg-gray-50/80">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    <span>Tęsti su Google</span>
                </a>

                <p class="text-center text-xs text-gray-500">Be slaptažodžio. Sekimą bet kada galėsite išjungti.</p>
            </div>
        </template>
    </div>
    </div>
</div>
