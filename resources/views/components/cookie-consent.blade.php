{{-- Ported near-verbatim from discount/src/components/common/cookie-consent.tsx
     (className strings, copy, and the js-cookie 365-day cookie behavior). --}}
<div
    x-data="{
        open: false,
        init() { this.open = !document.cookie.split('; ').some(c => c.startsWith('cookie_consent=')); },
        setConsent(value) {
            const expires = new Date(Date.now() + 365 * 86400000).toUTCString();
            document.cookie = `cookie_consent=${value}; expires=${expires}; path=/`;
            this.open = false;
        },
    }"
    x-show="open"
    x-cloak
    class="fixed isolate z-[100] bottom-[calc(4.25rem+env(safe-area-inset-bottom,0px))] left-0 right-0 w-full max-w-[577px] rounded-lg border bg-white p-6 shadow-lg sm:bottom-4 sm:left-4"
>
    <div class="space-y-4">
        <h2 class="text-2xl font-bold">Ši svetainė naudoja slapukus</h2>
        <p class="text-base">
            Šioje svetainėje naudojami slapukai, siekiant pagerinti
            vartotojo patirtį. Naudodamiesi mūsų svetaine, jūs sutinkate
            su visais slapukais pagal mūsų slapukų politiką.
            <a href="/privatumo-politika" class="underline">Skaityti išsamiau.</a>
        </p>
        <div class="mt-4 flex gap-4">
            <button type="button" @click="setConsent('accepted')" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-800">
                Leisti visus slapukus
            </button>
            <button type="button" @click="setConsent('necessary')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50">
                Tik būtini slapukai
            </button>
        </div>
    </div>
</div>
