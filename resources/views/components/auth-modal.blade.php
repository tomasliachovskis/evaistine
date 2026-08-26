{{-- Global login/register modal, toggled via the Alpine $store.authModal
     store (registered in the layout's alpine:init listener). Ported from
     discount/src/components/auth/{login,register}-dialog.tsx — plain POST
     forms with full-page redirect-back-with-errors so it degrades without
     JS, unlike the original's client-side next-auth signIn() calls. The
     store's initial `open`/`mode` are seeded from session validation state
     so a failed submit reopens the right tab after reload.

     x-data="{}" is required here even though this element owns no local
     store-independent state: Livewire's initial-page morph (it ships
     wire:navigate-style page tracking) only preserves/rebinds Alpine
     subtrees that are themselves an x-data root. A directive-only element
     with no x-data anywhere in its ancestor chain (this is a direct child
     of <body>) silently ends up unbound — x-show/@click/@window listeners
     never fire — even though it renders with no errors and looks identical
     in the DOM. --}}
<div
    x-data="{ showEmailForm: false }"
    x-show="$store.authModal.open"
    x-cloak
    @keydown.escape.window="$store.authModal.open = false"
    @open-auth-modal.window="$store.authModal.open = true; $store.authModal.mode = 'login'"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
    style="display: none;"
>
    <div @click.outside="$store.authModal.open = false" class="w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl sm:max-w-[440px]">
        <div class="space-y-2 border-b border-gray-100 px-5 py-3 text-left">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900" x-text="$store.authModal.mode === 'register' ? 'Registruotis' : 'Prisijunkite'"></h2>
                <button type="button" @click="$store.authModal.open = false" class="text-gray-400 hover:text-gray-700" aria-label="Uždaryti">&times;</button>
            </div>
            <div class="rounded-lg border border-green/20 bg-green/5 px-3.5 py-3 text-sm leading-relaxed text-gray-700">
                <template x-if="$store.authModal.mode === 'register'">
                    <div>
                        <p>Sukurkite naują paskyrą ir prisijunkite prie sumanių pirkėjų bendruomenės!</p>
                        <p class="mt-1">Registruojantis galėsite žymėtis norimas prekes.</p>
                    </div>
                </template>
                <template x-if="$store.authModal.mode !== 'register'">
                    <div>
                        <p>Prisijunkite prie sumanių pirkėjų bendruomenės!</p>
                        <p class="mt-1">Prisijungę galėsite žymėtis norimas prekes.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="space-y-4 px-6 py-5">
            {{-- $errors is only auto-shared by ShareErrorsFromSession, which runs on
                 matched routes — a 404 (no route matched) renders this layout without
                 it ever having bound, so guard with isset() rather than assuming it's
                 always present. --}}
            @if (isset($errors) && $errors->any())
                <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">{{ $errors->first() }}</p>
            @endif

            <div class="space-y-2.5">
                <a href="/auth/facebook/redirect" class="flex h-11 w-full cursor-pointer items-center justify-center gap-3 rounded-lg border border-[#1877F2]/25 bg-white text-sm font-semibold text-gray-900 transition-colors hover:border-[#1877F2]/40 hover:bg-[#1877F2]/5">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" /></svg>
                    <span x-text="$store.authModal.mode === 'register' ? 'Registruotis su Facebook' : 'Prisijungti su Facebook'"></span>
                </a>
                <a href="/auth/google/redirect" class="flex h-11 w-full cursor-pointer items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white text-sm font-semibold text-gray-900 transition-colors hover:border-gray-300 hover:bg-gray-50/80">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    <span x-text="$store.authModal.mode === 'register' ? 'Registruotis su Google' : 'Prisijungti su Google'"></span>
                </a>
            </div>

            <div class="relative py-1">
                <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-gray-200"></span></div>
                <div class="relative flex justify-center"><span class="bg-white px-3 text-xs font-medium uppercase tracking-wide text-gray-500">Arba</span></div>
            </div>

            {{-- Login: social-first, email form revealed on demand (mirrors LoginDialog's showEmailForm state). --}}
            <template x-if="$store.authModal.mode !== 'register'">
                <div>
                    <button type="button" x-show="!showEmailForm" @click="showEmailForm = true" class="h-11 w-full rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green">
                        Prisijungti su el. paštu
                    </button>

                    <form x-show="showEmailForm" method="POST" action="/login" class="space-y-4">
                        @csrf
                        <input type="hidden" name="form" value="login">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-900">El. paštas</label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="jusu@pastas.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-900">Slaptažodis</label>
                            <input type="password" name="password" required placeholder="••••••••" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                        </div>
                        <button type="submit" class="h-11 w-full rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green">Prisijungti</button>
                        <button type="button" @click="showEmailForm = false" class="h-11 w-full rounded-lg border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50">Atgal</button>
                    </form>
                </div>
            </template>

            {{-- Register: email form always visible. --}}
            <form x-show="$store.authModal.mode === 'register'" method="POST" action="/register" class="space-y-4">
                @csrf
                <input type="hidden" name="form" value="register">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-900">El. paštas</label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="jusu@pastas.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-900">Slaptažodis</label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-gray-900">Pakartoti slaptažodį</label>
                    <input type="password" name="password_confirmation" required minlength="8" placeholder="••••••••" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                </div>
                <button type="submit" class="h-11 w-full rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green">Registruotis</button>
            </form>
        </div>

        <template x-if="$store.authModal.mode !== 'register'">
            <div x-show="!showEmailForm" class="space-y-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 text-center text-sm leading-relaxed">
                <p class="text-gray-600">
                    Nenaudoji Facebook arba Gmail? Ir neturi paskyros?
                    <button type="button" @click="$store.authModal.mode = 'register'" class="font-semibold text-green transition-colors hover:text-dark-green hover:underline">Registruotis su el. paštu</button>
                </p>
            </div>
        </template>
        <template x-if="$store.authModal.mode === 'register'">
            <div class="border-t border-gray-100 bg-gray-50/80 px-6 py-4 text-center text-sm">
                <span class="text-gray-600">Jau turite paskyrą? </span>
                <button type="button" @click="$store.authModal.mode = 'login'" class="font-semibold text-green transition-colors hover:text-dark-green hover:underline">Prisijungti</button>
            </div>
        </template>
    </div>
</div>
