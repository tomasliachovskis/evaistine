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
        open: false,
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
    @open-price-watch-modal.window="open = true; sent = false; errors = {}; productName = $event.detail.productName; productImage = $event.detail.productImage"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
    style="display: none;"
>
    <div @click.outside="open = false" class="relative w-full overflow-hidden rounded-xl border border-gray-200 bg-white p-6 shadow-xl sm:max-w-[440px]">
        <button type="button" @click="open = false" class="absolute right-5 top-5 text-gray-400 hover:text-gray-700" aria-label="Uždaryti">&times;</button>

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
                            <x-app-icon name="bell" class="size-6 fill-none text-green" />
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
                        <input type="email" name="email" required placeholder="jusu@email.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                    </div>
                    <button type="submit" :disabled="submitting" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        <x-app-icon name="bell" class="size-4 fill-none" x-show="!submitting" />
                        Pranešti apie naują akciją
                    </button>
                </form>

                <p class="text-center text-xs text-gray-500">Be slaptažodžio. Sekimą bet kada galėsite išjungti.</p>
            </div>
        </template>
    </div>
</div>
