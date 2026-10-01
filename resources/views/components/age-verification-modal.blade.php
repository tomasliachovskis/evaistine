{{-- Legal age gate for the alkoholiniai-gerimai category (LT law: alcohol
     deals can't be shown before confirming the visitor is 20+). Same
     mechanism as <x-cookie-consent>/<x-signup-savings-popup> — plain Alpine
     x-data, persisted via vanilla document.cookie, safe under
     PageHtmlCache since the markup is identical for every visitor and only
     Alpine (client-side) decides whether to show it. Unlike those two,
     this one is NOT dismissible by clicking the backdrop or an X — the
     visitor must pick one of the two buttons. --}}
<div
    x-data="{
        verified: false,
        init() { this.verified = document.cookie.split('; ').some(c => c.startsWith('age_verified=')); },
        verify() {
            const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
            document.cookie = `age_verified=1; expires=${expires}; path=/`;
            this.verified = true;
        },
        deny() { window.location.href = '/'; },
    }"
    x-show="!verified"
    x-cloak
    class="fixed inset-0 z-[130] flex items-center justify-center bg-black/70 p-4"
>
    <div class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white p-6 text-center shadow-xl sm:max-w-[420px] sm:p-8">
        <h2 class="text-xl font-bold text-gray-900 sm:text-2xl">Turite patvirtinti amžių</h2>
        <p class="mt-3 text-sm text-gray-500 sm:text-base">
            Alkoholinius gėrimus gali įsigyti tik asmenys, kuriems yra ne mažiau kaip 20 metų.
        </p>
        <div class="mt-6 flex flex-col gap-3">
            <button type="button" @click="verify()" class="h-12 w-full rounded-lg bg-action text-base font-bold text-white transition-colors hover:bg-action-hover">
                MAN YRA 20 METŲ
            </button>
            <button type="button" @click="deny()" class="h-12 w-full rounded-lg border border-gray-300 text-base font-semibold text-gray-900 hover:bg-gray-50">
                MAN NĖRA 20 METŲ
            </button>
        </div>
    </div>
</div>
