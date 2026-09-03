{{-- Global price-watch capture modal: opened instead of <x-auth-modal> when a
     guest clicks "Sekti kainą" on a product (see product-price-watch-banner's
     toggle()). Email-only, no password/registration — reuses the same
     magic-link endpoint (POST /login) as <x-auth-modal>, the pending-favorite
     stash (POST /auth/pending-favorite, called by the banner before this
     opens) completes the favorite once the emailed link is clicked, same as
     the generic login flow. No browser-push option — email only. --}}
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
    <div @click.outside="open = false" class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl sm:max-w-[440px]">
        <div class="relative px-6 pb-2 pt-6">
            <button type="button" @click="open = false" class="absolute right-5 top-5 text-gray-400 hover:text-gray-700" aria-label="Uždaryti">&times;</button>

            <template x-if="!sent">
                <div>
                    <div class="flex items-start gap-3">
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-green/10">
                            <template x-if="productImage">
                                <img :src="productImage" :alt="productName" class="h-full w-full object-contain p-1.5">
                            </template>
                            <template x-if="!productImage">
                                <x-app-icon name="bell" class="size-6 fill-none text-green" />
                            </template>
                        </div>
                        <div class="min-w-0 pt-0.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-dark-green">Kainos pranešimas</p>
                            <h2 class="text-xl font-bold text-gray-900">Sekti šią prekę?</h2>
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-relaxed text-gray-600">
                        Pranešime, kai „<span x-text="productName"></span>“ vėl bus akcijoje arba kainuos mažiau.
                    </p>

                    <div class="mt-4 space-y-2.5">
                        <div class="flex items-center gap-2.5 text-sm text-gray-700">
                            <x-app-icon name="bell" class="size-4 shrink-0 fill-none text-dark-green" />
                            Tik apie šios prekės kainą — be kasdienių reklamų
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-gray-700">
                            <x-app-icon name="user" class="size-4 shrink-0 text-dark-green" />
                            Jokios registracijos formos ar slaptažodžio
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="sent">
                <div class="py-2">
                    <div class="flex items-start gap-3">
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-green/10">
                            <x-app-icon name="check" class="size-6 text-dark-green" style="stroke-width:2.5" />
                        </div>
                        <div class="min-w-0 pt-0.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-dark-green">Kainos pranešimas</p>
                            <h2 class="text-xl font-bold text-gray-900">Nuoroda išsiųsta!</h2>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-gray-600">
                        Patikrinkite savo el. paštą ir paspauskite nuorodą — tada sekimas šiai prekei bus įjungtas. Nuoroda galioja 30 minučių.
                    </p>
                </div>
            </template>
        </div>

        <template x-if="!sent">
            <div class="px-6 pb-6 pt-2">
                <form @submit.prevent="submit($el)" method="POST" action="/login" class="space-y-3">
                    @csrf
                    <template x-if="firstError(errors)">
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" x-text="firstError(errors)"></p>
                    </template>
                    <label class="block text-sm font-semibold text-gray-900">Kur atsiųsti pranešimą?</label>
                    <input type="email" name="email" required placeholder="jusu@email.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                    <button type="submit" :disabled="submitting" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-green text-sm font-bold text-white shadow-sm transition-colors hover:bg-dark-green disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        <x-app-icon name="mail" class="size-4" x-show="!submitting" />
                        Pranešti, kai atpigs
                    </button>
                </form>

                <p class="mt-4 text-center text-xs text-gray-500">Paskyra sukuriama automatiškai. Sekimą galėsite išjungti vienu paspaudimu.</p>
            </div>
        </template>
    </div>
</div>
