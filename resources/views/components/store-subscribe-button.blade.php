@props(['slug', 'class' => ''])

{{-- On a store's own pages (/{store}, /leidinys/{store}): adds the
     store to "Mano vaistinės" or takes it off (see the myStores Alpine
     store in layouts/app.blade.php). Was a "Sekti akcijas" button that only
     linked to /favorites or the login sheet, with no per-store follow
     behind it. Works without an account. --}}
<button
    type="button"
    x-data
    @click="$store.myStores.toggle(@js($slug))"
    :aria-pressed="$store.myStores.has(@js($slug))"
    :title="$store.myStores.has(@js($slug)) ? 'Tarp mano vaistinių. Paspauskite, kad išimtumėte.' : 'Pridėti prie mano vaistinių'"
    class="{{ $class }} inline-flex min-h-12 shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-base font-bold transition-colors"
    :class="$store.myStores.has(@js($slug)) ? 'border-green-soft-border bg-green-soft text-dark-green hover:bg-green-soft-border' : 'border-green bg-action text-white hover:bg-action-hover'"
>
    <x-app-icon x-show="!$store.myStores.has('{{ $slug }}')" name="plus" class="size-5 shrink-0" />
    <x-app-icon x-show="$store.myStores.has('{{ $slug }}')" x-cloak name="check" class="size-5 shrink-0" />
    {{-- Short on every screen: the + / check icon and the green or light
         fill say whether it's added. --}}
    <span>Mano vaistinė</span>
</button>
