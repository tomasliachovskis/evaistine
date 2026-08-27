{{-- Ported from discount/src/components/common/signup-savings-popup.tsx —
     guest-only "don't miss the best deals" register nudge, shown once the
     page has been open 5s, dismissible for a day via a plain cookie (same
     vanilla document.cookie approach as <x-cookie-consent>, no js-cookie
     dependency needed). Reuses the shared auth-modal (open/mode store)
     instead of its own dialog. --}}
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
                setTimeout(() => { this.elapsed = true; }, 5000);
            },
            dismiss() {
                const expires = new Date(Date.now() + 86400000).toUTCString();
                document.cookie = `signup_promo_dismissed=1; expires=${expires}; path=/`;
                this.dismissed = true;
            },
        }"
        x-show="elapsed && !dismissed"
        x-cloak
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
    >
        <button type="button" class="absolute inset-0 cursor-pointer bg-black/55" aria-label="Uždaryti" @click="dismiss()"></button>
        <div class="relative w-full max-w-[480px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl">
            <button
                type="button"
                @click="dismiss()"
                class="absolute right-5 top-5 z-10 flex size-8 cursor-pointer items-center justify-center rounded-full text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                aria-label="Uždaryti"
            >
                <x-app-icon name="x" class="size-5" />
            </button>

            <div class="flex flex-col items-center gap-4 px-6 pb-2 pt-10 text-center sm:px-10">
                <div class="flex size-16 items-center justify-center rounded-full bg-green/10">
                    <x-app-icon name="bell" class="size-8 text-dark-green" />
                </div>
                <h2 class="text-2xl font-bold leading-snug text-gray-900 sm:text-[1.75rem]">Nepraleiskite geriausių akcijų</h2>
            </div>

            <div class="flex flex-col items-center gap-6 px-4 pb-8 pt-1 text-center sm:px-10">
                <p class="text-base leading-relaxed text-gray-500">
                    Išsisaugokite mėgstamas prekes ir gaukite pranešimą, kai jų kaina sumažės.
                </p>

                <div class="flex w-full flex-nowrap items-center justify-center gap-x-1.5 text-[15px] font-medium text-gray-700 sm:gap-x-2.5 sm:text-sm sm:font-normal">
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
                    @click="dismiss(); $store.authModal.open = true; $store.authModal.mode = 'register'"
                    class="h-12 w-full rounded-lg bg-green text-base font-bold text-white transition-colors hover:bg-dark-green"
                >
                    Registruotis nemokamai
                </button>
                <p class="text-sm text-gray-500">
                    Jau turite paskyrą?
                    <button
                        type="button"
                        @click="dismiss(); $store.authModal.open = true; $store.authModal.mode = 'login'"
                        class="font-medium text-green hover:text-dark-green hover:underline"
                    >
                        Prisijungti
                    </button>
                </p>
            </div>
        </div>
    </div>
@endguest
