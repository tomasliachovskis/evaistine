{{-- Ported from discount/src/components/common/signup-savings-popup.tsx —
     guest-only "don't miss the best deals" register nudge, shown once the
     page has been open 10s, dismissible for a day via a plain cookie (same
     vanilla document.cookie approach as <x-cookie-consent>, no js-cookie
     dependency needed). Opens the shared auth-modal on its CTA click, and
     stays hidden (even after its own 10s timer elapses) while auth-modal or
     price-watch-modal is already open — both are z-[60], this is z-[110],
     so without this check it would silently render on top of whichever one
     the guest is already looking at.

     Shell/close-button match <x-auth-modal> exactly (same backdrop technique,
     same sm:max-w-[440px], close button inline in a header row, not a
     floating absolutely-positioned circle) — first version used its own
     max-w-[480px] shell, which silently rendered edge-to-edge on desktop
     (the class was never in the built CSS, only added after the last
     `npm run build`). Reusing only already-compiled classes here rules
     that class of bug out entirely. --}}
@guest
    <div
        x-data="{
            elapsed: false,
            dismissed: false,
            init() {
                if (document.cookie.split('; ').some((c) => c.startsWith('signup_promo_dismissed='))) {
                    this.dismissed = true;
                    return;
                }
                setTimeout(() => { this.elapsed = true; }, 10000);
            },
            dismiss() {
                const expires = new Date(Date.now() + 86400000).toUTCString();
                document.cookie = `signup_promo_dismissed=1; expires=${expires}; path=/`;
                this.dismissed = true;
            },
        }"
        x-show="elapsed && !dismissed && !$store.authModal.open && !$store.priceWatchModal.open"
        x-cloak
        @click.self="dismiss()"
        class="fixed inset-0 z-[110] flex items-center justify-center bg-black/50 p-4"
    >
        <div class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl sm:max-w-[440px]">
            <div class="flex items-center justify-end px-5 pt-3">
                <button type="button" @click="dismiss()" class="text-gray-400 hover:text-gray-700" aria-label="Uždaryti">&times;</button>
            </div>

            <div class="flex flex-col items-center gap-4 px-6 pb-2 pt-2 text-center sm:px-10">
                <div class="flex size-16 items-center justify-center rounded-full bg-green/10">
                    <x-app-icon name="bell" class="size-8 text-dark-green" />
                </div>
                <h2 class="text-2xl font-bold leading-snug text-gray-900">Nepraleiskite geriausių akcijų</h2>
            </div>

            <div class="flex flex-col items-center gap-6 px-4 pb-8 pt-1 text-center sm:px-10">
                <p class="text-base leading-relaxed text-gray-500">
                    Išsisaugokite mėgstamas prekes ir gaukite pranešimą, kai jų kaina sumažės.
                </p>

                <div class="flex w-full flex-nowrap items-center justify-center gap-x-1.5 text-sm font-medium text-gray-700 sm:gap-x-2.5 sm:font-normal">
                    @foreach ([['short' => 'Pranešimai', 'full' => 'Kainų pranešimai'], ['short' => 'Mėgstami', 'full' => 'Išsaugotos prekės'], ['short' => 'Nemokamai', 'full' => 'Nemokamai']] as $index => $feature)
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($index > 0)
                                <span class="hidden h-3 w-px shrink-0 bg-gray-200 sm:block"></span>
                            @endif
                            <span class="flex items-center gap-1 whitespace-nowrap">
                                <x-app-icon name="check" class="size-3.5 shrink-0 text-green sm:size-3" style="stroke-width:3" />
                                <span class="sm:hidden">{{ $feature['short'] }}</span>
                                <span class="hidden sm:inline">{{ $feature['full'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>

                <button
                    type="button"
                    @click="dismiss(); $store.authModal.open = true"
                    class="h-12 w-full rounded-lg bg-green text-base font-bold text-white transition-colors hover:bg-dark-green"
                >
                    Prisijungti nemokamai
                </button>
            </div>
        </div>
    </div>
@endguest
