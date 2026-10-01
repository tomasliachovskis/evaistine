<x-layouts.app
    app-title="Pranešimai"
    back-href="/" title="Atsisakyta kainų priminimų" :robots="'noindex, nofollow'">
    <div class="base-container">
        <div class="mx-auto flex max-w-md flex-col items-center gap-4 pt-12 pb-16 text-center sm:pt-16">
            <h1 class="text-xl font-bold text-gray-900">Atsisakyta</h1>
            <p class="text-base text-gray-600">
                Daugiau nebegausite el. laiškų apie sekamų prekių kainų pokyčius. Jūsų sekamų prekių sąrašas
                lieka nepakeistas — jį galite peržiūrėti čia:
            </p>
            <a href="/favorites" class="rounded-full bg-action px-6 py-3 text-base font-bold text-white transition-colors hover:bg-action-hover">
                Peržiūrėti sekamas prekes
            </a>
        </div>
    </div>
</x-layouts.app>
