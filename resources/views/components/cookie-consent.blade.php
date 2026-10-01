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
    class="fixed isolate z-[100] bottom-[calc(4.25rem+env(safe-area-inset-bottom,0px))] left-0 right-0 w-full max-w-[577px] rounded-lg border bg-white p-4 shadow-lg sm:bottom-4 sm:left-4 sm:p-6"
>
    <div class="space-y-2 sm:space-y-4">
        <h2 class="text-base font-bold sm:text-2xl">Ši svetainė naudoja slapukus</h2>
        {{-- Shorter on mobile so the banner doesn't cover most of the
             viewport (was ~41% of a 375x812 screen) — full explanation
             still shown from sm: up. --}}
        <p class="text-xs text-gray-600 sm:text-base sm:text-gray-950">
            <span class="sm:hidden">Naudojame slapukus patirčiai pagerinti. <a href="/privatumo-politika" class="underline">Skaityti daugiau.</a></span>
            <span class="hidden sm:inline">
                Šioje svetainėje naudojami slapukai, siekiant pagerinti
                vartotojo patirtį. Naudodamiesi mūsų svetaine, jūs sutinkate
                su visais slapukais pagal mūsų slapukų politiką.
                <a href="/privatumo-politika" class="underline">Skaityti išsamiau.</a>
            </span>
        </p>
        <div class="mt-3 flex flex-wrap gap-3 sm:mt-4">
            <button type="button" @click="setConsent('accepted')" class="inline-flex min-h-12 items-center rounded-xl bg-action px-5 text-base font-bold text-white hover:bg-action-hover">
                Leisti visus slapukus
            </button>
            <button type="button" @click="setConsent('necessary')" class="inline-flex min-h-12 items-center rounded-xl border border-gray-200 px-5 text-base font-semibold text-gray-900 hover:border-gray-300">
                Tik būtini slapukai
            </button>
        </div>
    </div>
</div>
