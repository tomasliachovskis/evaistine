@props(['class' => ''])

{{-- Ported from store-subscribe-button.tsx — not a real per-store follow
     backend (there's no StoreFavorite model), it's just a CTA: logged-in
     users go to their existing /favorites page, guests get prompted to
     register. Reuses the shared login/register auth-modal. --}}
@auth
    <a href="/favorites" class="{{ $class }} inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white transition-colors hover:bg-dark-green">
        <x-app-icon name="bell" class="size-4 shrink-0" />
        <span class="sm:hidden">Sekti</span>
        <span class="hidden sm:inline">Sekti akcijas</span>
    </a>
@else
    <button
        type="button"
        @click="$store.authModal.open = true; $store.authModal.mode = 'register'"
        class="{{ $class }} inline-flex shrink-0 items-center gap-2 rounded-lg border-2 border-green bg-green px-4 py-2 text-base font-bold text-white transition-colors hover:bg-dark-green"
    >
        <x-app-icon name="bell" class="size-4 shrink-0" />
        <span class="sm:hidden">Sekti</span>
        <span class="hidden sm:inline">Sekti akcijas</span>
    </button>
@endauth
