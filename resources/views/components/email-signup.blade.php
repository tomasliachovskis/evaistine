@props(['title' => 'Akcijos ir nauji leidiniai el. paštu', 'store' => null, 'compact' => false])

{{-- Signup for the email notifications without an account
     (EmailSubscriptionController::subscribe). Sends the visitor's "Mano
     vaistinės" along, so the emails cover their stores from the start.
     After signup: "check your email" (double opt-in), or straight to the
     settings page when signed in with the same address. $store (a leaflet
     page's store) is added to the stores sent. --}}
<section
    x-data="{
        email: @js(auth()->user()?->email ?? ''),
        sending: false,
        sent: false,
        error: '',
        async submit() {
            if (this.sending) return;
            this.sending = true;
            this.error = '';
            try {
                const res = await fetch('/pranesimai', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ email: this.email, stores: [...new Set([...$store.myStores.slugs, ...@js($store ? [$store] : [])])] }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.settings) { location.href = data.settings; return; }
                if (res.ok) { this.sent = true; }
                else this.error = (data.errors && Object.values(data.errors)[0][0]) || (res.status === 429 ? 'Per daug bandymų. Palaukite minutę.' : 'Nepavyko. Bandykite dar kartą.');
            } catch (e) {
                this.error = 'Nepavyko. Bandykite dar kartą.';
            }
            this.sending = false;
        },
    }"
    {{ $attributes->merge(['class' => $compact ? 'rounded-xl bg-gray-50 p-4' : 'rounded-2xl border border-green/30 bg-green/5 p-5 sm:p-6']) }}
>
    @if ($compact)
        {{-- Narrow columns (the leaflet viewer's side column): the side
             menu's grey grouped panel ("Populiarios prekės"), no icon,
             shorter text, always stacked. --}}
        <h2 class="text-base font-bold leading-snug text-gray-900">{{ $title }}</h2>
        <p class="mt-1 text-sm leading-snug text-gray-700">Kas ketvirtadienį nauji leidiniai ir akcijos. Nemokamai.</p>
        <p x-show="$store.myStores.active()" x-cloak class="mt-1 text-xs text-gray-600">
            Vaistinės: <span x-text="$store.myStores.slugs.map((s) => $store.myStores.name(s)).join(', ')"></span>
        </p>
    @else
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white ring-1 ring-green/20">
                <x-app-icon name="mail" class="size-7 text-dark-green" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-bold leading-snug text-gray-900">{{ $title }}</h2>
                <p class="mt-1 text-lg leading-snug text-gray-700">
                    Kas ketvirtadienį: jūsų vaistinių nauji leidiniai ir geriausios akcijos. Nemokamai, atsisakyti galima bet kada.
                </p>
                <p x-show="$store.myStores.active()" x-cloak class="mt-1 text-base text-gray-600">
                    Vaistinės: <span x-text="$store.myStores.slugs.map((s) => $store.myStores.name(s)).join(', ')"></span>
                </p>
            </div>
        </div>
    @endif

    <form x-show="!sent" @submit.prevent="submit()" class="{{ $compact ? 'mt-3' : 'mt-4 sm:flex-row' }} flex flex-col gap-2">
        <label class="sr-only" for="email-signup-{{ $attributes->get('id', 'main') }}">El. paštas</label>
        <input
            id="email-signup-{{ $attributes->get('id', 'main') }}"
            type="email"
            x-model="email"
            required
            autocomplete="email"
            placeholder="jusu@pastas.lt"
            class="min-h-12 w-full min-w-0 flex-1 rounded-xl border border-gray-300 bg-white px-4 {{ $compact ? 'text-base' : 'text-lg' }} focus:border-dark-green focus:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
        >
        <button type="submit" :disabled="sending" class="flex min-h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-action px-6 {{ $compact ? 'text-base' : 'text-lg' }} font-bold text-white hover:bg-action-hover disabled:opacity-60">
            <span x-show="sending" x-cloak class="size-5 animate-spin rounded-full border-[3px] border-white/40 border-t-white"></span>
            Užsisakyti
        </button>
    </form>
    <p x-show="error" x-cloak class="mt-2 text-base font-semibold text-red-700" role="alert" x-text="error"></p>
    <div x-show="sent" x-cloak class="mt-4 rounded-xl bg-white p-4 text-lg text-gray-900 ring-1 ring-green/20" role="status">
        <p class="font-bold">Patikrinkite el. paštą</p>
        <p class="mt-1">Išsiuntėme laišką į <span class="font-semibold break-all" x-text="email"></span>. Paspauskite jame mygtuką „Taip, noriu gauti“.</p>
    </div>
</section>
