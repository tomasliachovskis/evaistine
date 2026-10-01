<x-layouts.app
    app-title="Pranešimai"
    back-href="/" title="Atsisakyti kainų priminimų" :robots="'noindex, nofollow'">
    <div class="base-container">
        <div class="mx-auto flex max-w-md flex-col items-center gap-4 pt-12 pb-16 text-center sm:pt-16">
            <h1 class="text-xl font-bold text-gray-900">Atsisakyti kainų priminimų?</h1>
            <p class="text-base text-gray-600">
                Nebegausite el. laiškų, kai sekamos prekės atpinga. Jūsų sekamų prekių sąrašas
                (<a href="/favorites" class="text-dark-green underline">/favorites</a>) nebus pakeistas — jį galėsite
                peržiūrėti kaip ir anksčiau, tiesiog priminimai apie kainų pokyčius nebebus siunčiami.
            </p>

            <form method="POST" action="{{ url()->full() }}" class="mt-2">
                @csrf
                <button type="submit" class="rounded-full bg-action px-6 py-3 text-base font-bold text-white transition-colors hover:bg-action-hover">
                    Taip, atsisakyti priminimų
                </button>
            </form>

            <a href="/" class="text-sm text-gray-500 underline hover:text-gray-700">Ne, palikti kaip yra</a>
        </div>
    </div>
</x-layouts.app>
