{{-- Global login/register modal, toggled via the Alpine $store.authModal
     store (registered in the layout's alpine:init listener). Ported from
     discount/src/components/auth/{login,register}-dialog.tsx, then reworked
     to submit via fetch() instead of a plain form POST — a real login/
     register error used to be a full page reload (confirmed live: the whole
     page flashed white and the email/password fields collapsed back behind
     "Prisijungti su el. paštu", hiding the just-typed email along with the
     error). Now errors render inline without leaving the modal.

     x-data="{}" is required here even though this element owns no local
     store-independent state: Livewire's initial-page morph (it ships
     wire:navigate-style page tracking) only preserves/rebinds Alpine
     subtrees that are themselves an x-data root. A directive-only element
     with no x-data anywhere in its ancestor chain (this is a direct child
     of <body>) silently ends up unbound — x-show/@click/@window listeners
     never fire — even though it renders with no errors and looks identical
     in the DOM. --}}
<div
    x-data="{
        submitting: false,
        showLoginPw: false,
        showRegisterPw: false,
        loginErrors: {},
        registerErrors: {},
        firstError(errors) {
            const values = Object.values(errors);
            return values.length ? values[0][0] : null;
        },
        async submitAuthForm(form, errorsKey) {
            if (this.submitting) return;
            this.submitting = true;
            this[errorsKey] = {};
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                });
                if (res.status === 422) {
                    const data = await res.json();
                    this[errorsKey] = data.errors || {};
                    this.submitting = false;
                    return;
                }
                if (!res.ok) {
                    this.submitting = false;
                    return;
                }
                const data = await res.json();
                window.location.href = data.redirect || '/';
            } catch (e) {
                this.submitting = false;
            }
        },
    }"
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
        </div>

        <div class="space-y-4 px-6 py-5">
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

            {{-- Login: email/password always visible now — hiding it behind a
                 "Prisijungti su el. paštu" reveal button meant a failed login's
                 error banner and the field it referred to weren't visible at
                 the same time, since the fields collapsed back on reload. --}}
            <template x-if="$store.authModal.mode !== 'register'">
                <form @submit.prevent="submitAuthForm($el, 'loginErrors')" method="POST" action="/login" class="space-y-4">
                    @csrf
                    <input type="hidden" name="form" value="login">
                    <template x-if="firstError(loginErrors)">
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" x-text="firstError(loginErrors)"></p>
                    </template>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-900">El. paštas</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="jusu@pastas.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-900">Slaptažodis</label>
                        <div class="relative">
                            <input :type="showLoginPw ? 'text' : 'password'" name="password" required placeholder="••••••••" class="h-11 w-full rounded-lg border border-gray-200 px-3 pr-11 text-sm focus:border-green focus:outline-none">
                            <button type="button" @click="showLoginPw = !showLoginPw" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 hover:text-gray-600" :aria-label="showLoginPw ? 'Slėpti slaptažodį' : 'Rodyti slaptažodį'">
                                <x-app-icon :name="'eye'" class="size-4" x-show="!showLoginPw" />
                                <x-app-icon :name="'eye-off'" class="size-4" x-show="showLoginPw" x-cloak />
                            </button>
                        </div>
                    </div>
                    <button type="submit" :disabled="submitting" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Prisijungti
                    </button>
                </form>
            </template>

            {{-- Register: single password field + show/hide toggle instead of
                 a second "Pakartoti slaptažodį" field — a confirm-password
                 field doesn't meaningfully cut typo-driven failed signups
                 (Baymard/NN Group), it just adds a field before the CTA. --}}
            <template x-if="$store.authModal.mode === 'register'">
                <form @submit.prevent="submitAuthForm($el, 'registerErrors')" method="POST" action="/register" class="space-y-4">
                    @csrf
                    <input type="hidden" name="form" value="register">
                    <template x-if="firstError(registerErrors)">
                        <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" x-text="firstError(registerErrors)"></p>
                    </template>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-900">El. paštas</label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="jusu@pastas.lt" class="h-11 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium text-gray-900">Slaptažodis</label>
                        <div class="relative">
                            <input :type="showRegisterPw ? 'text' : 'password'" name="password" required minlength="8" placeholder="••••••••" class="h-11 w-full rounded-lg border border-gray-200 px-3 pr-11 text-sm focus:border-green focus:outline-none">
                            <button type="button" @click="showRegisterPw = !showRegisterPw" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-400 hover:text-gray-600" :aria-label="showRegisterPw ? 'Slėpti slaptažodį' : 'Rodyti slaptažodį'">
                                <x-app-icon :name="'eye'" class="size-4" x-show="!showRegisterPw" />
                                <x-app-icon :name="'eye-off'" class="size-4" x-show="showRegisterPw" x-cloak />
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">Bent 8 simboliai</p>
                    </div>
                    <button type="submit" :disabled="submitting" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-green text-sm font-semibold text-white shadow-sm transition-colors hover:bg-dark-green disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                        Registruotis
                    </button>
                </form>
            </template>
        </div>

        <template x-if="$store.authModal.mode !== 'register'">
            <div class="space-y-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 text-center text-sm leading-relaxed">
                <p class="text-gray-600">
                    Neturi paskyros?
                    <button type="button" @click="$store.authModal.mode = 'register'" class="font-semibold text-green transition-colors hover:text-dark-green hover:underline">Registruotis</button>
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
