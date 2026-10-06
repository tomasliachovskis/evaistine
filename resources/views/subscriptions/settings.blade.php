@php
    // Same store list and order as the "Mano parduotuvės" picker
    // (components/my-stores-sheet.blade.php); $stores from MobileNavComposer.
    $mainStores = \App\Support\StoreListPriority::mainSlugs();
    $pickable = collect($stores ?? [])
        ->filter(fn ($s) => ($s['discounts_count'] ?? 0) > 0 || ($s['leaflets_count'] ?? 0) > 0)
        ->sortBy([
            fn ($a, $b) => (array_search($a['slug'], $mainStores, true) === false ? 99 : array_search($a['slug'], $mainStores, true))
                <=> (array_search($b['slug'], $mainStores, true) === false ? 99 : array_search($b['slug'], $mainStores, true)),
            fn ($a, $b) => ($b['discounts_count'] ?? 0) <=> ($a['discounts_count'] ?? 0),
        ])
        ->values();
    $user = $subscriber->user;
    $toggleClass = 'flex min-h-16 w-full cursor-pointer items-start gap-3 rounded-2xl border-2 bg-white p-4 transition-colors';
@endphp

<x-layouts.app title="Pranešimai el. paštu" robots="noindex, nofollow" app-title="Pranešimai" back-href="/">
    <div class="base-container flex max-w-2xl flex-col gap-6 pb-10 pt-4">
        <div>
            <h1>Pranešimai el. paštu</h1>
            <p class="mt-2 text-lg text-gray-700">Siunčiame į <span class="font-semibold break-all">{{ $subscriber->email }}</span></p>
        </div>

        @if ($justConfirmed)
            <div class="rounded-2xl border border-green/30 bg-green/5 p-4 text-lg text-gray-900" role="status">
                <p class="font-bold">Patvirtinta, ačiū!</p>
                <p class="mt-1">Žemiau galite pasirinkti parduotuves ir kokius laiškus gauti.</p>
            </div>
        @endif
        @if (session('status'))
            <div class="rounded-2xl border border-green/30 bg-green/5 p-4 text-lg font-semibold text-dark-green" role="status">{{ session('status') }}</div>
        @endif
        @if ($subscriber->unsubscribed_at)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-lg text-amber-900">Šiuo metu laiškų nesiunčiame. Pažymėkite, ko norite, ir išsaugokite, kad vėl gautumėte.</div>
        @endif

        <form method="POST" action="/pranesimai/{{ $subscriber->token }}" class="flex flex-col gap-6" x-data="{ picked: @js($subscriber->store_slugs ?? []) }">
            @csrf
            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-bold">Kokius laiškus siųsti?</h2>

                <label class="{{ $toggleClass }} {{ $user ? '' : 'opacity-60' }}" x-data="{ on: @js($user && ! $user->price_watch_unsubscribed_at) }" :class="on ? 'border-action bg-green-soft' : 'border-gray-200'">
                    <input type="checkbox" name="wants_price_drops" value="1" class="mt-1 size-6 shrink-0 accent-[#13306a]" x-model="on" @disabled(! $user)>
                    <span>
                        <span class="block text-lg font-bold text-gray-900">Atpigo sekamos prekės</span>
                        <span class="block text-base text-gray-700">
                            @if ($user)
                                Kai prekė, prie kurios paspaudėte „Sekti kainą“, atpinga.
                            @else
                                Reikia prisijungti ir prie prekių paspausti „Sekti kainą“.
                            @endif
                        </span>
                    </span>
                </label>

                <label class="{{ $toggleClass }}" x-data="{ on: @js((bool) $subscriber->wants_weekly) }" :class="on ? 'border-action bg-green-soft' : 'border-gray-200'">
                    <input type="checkbox" name="wants_weekly" value="1" class="mt-1 size-6 shrink-0 accent-[#13306a]" x-model="on">
                    <span>
                        <span class="block text-lg font-bold text-gray-900">Savaitės santrauka</span>
                        <span class="block text-base text-gray-700">Kas ketvirtadienį: nauji leidiniai ir geriausios jūsų parduotuvių akcijos.</span>
                    </span>
                </label>

                <label class="{{ $toggleClass }}" x-data="{ on: @js((bool) $subscriber->wants_new_leaflets) }" :class="on ? 'border-action bg-green-soft' : 'border-gray-200'">
                    <input type="checkbox" name="wants_new_leaflets" value="1" class="mt-1 size-6 shrink-0 accent-[#13306a]" x-model="on">
                    <span>
                        <span class="block text-lg font-bold text-gray-900">Naujas leidinys</span>
                        <span class="block text-base text-gray-700">Kai jūsų parduotuvė išleidžia naują leidinį.</span>
                    </span>
                </label>
            </section>

            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-bold">Kurių parduotuvių?</h2>
                <p class="text-base text-gray-700">Nepažymėjus jokios, siųsime Maxima, Norfa, Lidl, Rimi ir Iki.</p>
                <template x-for="slug in picked" :key="slug">
                    <input type="hidden" name="stores[]" :value="slug">
                </template>
                <div class="flex flex-wrap gap-2.5">
                    @foreach ($pickable as $store)
                        <x-store-pick-tile
                            :slug="$store['slug']"
                            :name="$store['name']"
                            checked="picked.includes('{{ $store['slug'] }}')"
                            toggle="picked.includes('{{ $store['slug'] }}') ? picked = picked.filter((s) => s !== '{{ $store['slug'] }}') : picked.push('{{ $store['slug'] }}')"
                        />
                    @endforeach
                </div>
            </section>

            <button type="submit" class="sticky bottom-24 flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-action px-6 text-lg font-bold text-white shadow-lg hover:bg-action-hover sm:bottom-4">
                <x-app-icon name="check" class="size-5" />
                Išsaugoti
            </button>
        </form>

        @unless ($subscriber->unsubscribed_at)
            <form method="POST" action="/pranesimai/{{ $subscriber->token }}/atsisakyti" class="border-t border-gray-200 pt-6" onsubmit="return confirm('Atsisakyti visų laiškų?')">
                @csrf
                <button type="submit" class="min-h-12 rounded-xl px-4 text-base font-semibold text-gray-700 underline underline-offset-4 hover:no-underline">Atsisakyti visų laiškų</button>
            </form>
        @endunless
    </div>
</x-layouts.app>
