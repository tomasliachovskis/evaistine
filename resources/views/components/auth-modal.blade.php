{{-- Global login modal, toggled via the Alpine $store.authModal store
     (registered in the layout's alpine:init listener). Passwordless: email
     magic link + Google/Facebook OAuth, no password field, no separate
     registration flow — a new account is created automatically the first
     time someone clicks their emailed link.

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
        sent: false,
        errors: {},
        // The address the email went to, for the code step and 'send again'.
        email: '',
        code: '',
        codeErrors: {},
        checking: false,
        resent: false,
        firstError(errors) {
            const values = Object.values(errors);
            return values.length ? values[0][0] : null;
        },
        async submitMagicLinkForm(form) {
            if (this.submitting) return;
            this.submitting = true;
            this.errors = {};
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                });
                if (res.status === 422) {
                    const data = await res.json();
                    this.errors = data.errors || {};
                    this.submitting = false;
                    return;
                }
                if (!res.ok) {
                    this.submitting = false;
                    return;
                }
                this.email = new FormData(form).get('email');
                this.code = '';
                this.codeErrors = {};
                this.submitting = false;
                this.sent = true;
                this.$nextTick(() => this.$refs.codeInput?.focus());
            } catch (e) {
                this.submitting = false;
            }
        },
        // The 6-digit code from the same email, entered here so the login
        // happens in this window (the link often opens in another browser
        // on phones). Using it also uses the link, and vice versa.
        async submitCode() {
            if (this.checking) return;
            this.checking = true;
            this.codeErrors = {};
            this.resent = false;
            try {
                const res = await fetch('/login/code', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ email: this.email, code: this.code }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                this.codeErrors = data.errors || { code: [res.status === 429 ? 'Per daug bandymų. Palaukite minutę.' : 'Nepavyko. Bandykite dar kartą.'] };
            } catch (e) {
                this.codeErrors = { code: ['Nepavyko. Bandykite dar kartą.'] };
            }
            this.checking = false;
        },
        async resend() {
            if (this.submitting) return;
            this.submitting = true;
            this.codeErrors = {};
            try {
                const body = new FormData();
                body.append('email', this.email);
                const res = await fetch('/login', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body,
                });
                this.resent = res.ok;
                if (!res.ok) this.codeErrors = { code: [res.status === 429 ? 'Per daug laiškų. Palaukite minutę.' : 'Nepavyko išsiųsti. Bandykite dar kartą.'] };
                this.code = '';
            } catch (e) {}
            this.submitting = false;
        },
    }"
    x-show="$store.authModal.open"
    x-cloak
    @keydown.escape.window="$store.authModal.open = false"
    @open-auth-modal.window="$store.authModal.open = true; sent = false; errors = {}; codeErrors = {}; resent = false"
    x-back-closes="$store.authModal.open"
    class="sheet-backdrop"
    style="display: none;"
>
    <div @click.outside="$store.authModal.open = false" class="sheet-panel">
        <div class="sheet-handle"></div>
        <div class="sheet-head border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900">Prisijungimas</h2>
            <button type="button" @click="$store.authModal.open = false" class="sheet-close" aria-label="Uždaryti">
                <x-app-icon name="x" class="size-7" />
            </button>
        </div>

        <div class="space-y-4 px-6 py-5">
            <template x-if="sent">
                <div class="space-y-4">
                    <div class="space-y-1 rounded-lg border border-green/30 bg-green/5 px-4 py-4">
                        <p class="text-lg font-semibold text-gray-900">Laiškas išsiųstas</p>
                        <p class="text-base text-gray-700">Išsiuntėme laišką į <span class="font-semibold break-all" x-text="email"></span>. Jame yra 6 skaitmenų kodas ir nuoroda. Jie galioja 30 minučių.</p>
                    </div>

                    <form @submit.prevent="submitCode()" class="space-y-3">
                        <label for="login-code" class="block text-lg font-semibold text-gray-900">Įveskite kodą iš laiško</label>
                        <input
                            id="login-code"
                            x-ref="codeInput"
                            x-model="code"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="7"
                            placeholder="000 000"
                            class="min-h-14 w-full rounded-lg border border-gray-300 px-3 text-center text-3xl font-bold tracking-[0.3em] tabular-nums placeholder:text-gray-300 focus:border-dark-green focus:outline-none focus-visible:ring-4 focus-visible:ring-dark-green/30"
                        >
                        <template x-if="firstError(codeErrors)">
                            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-base text-red-700" role="alert" x-text="firstError(codeErrors)"></p>
                        </template>
                        <p x-show="resent" class="rounded-lg bg-green-soft px-3 py-2.5 text-base font-semibold text-dark-green" role="status">Naujas laiškas išsiųstas. Įveskite naują kodą.</p>
                        <button type="submit" :disabled="checking" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-action text-lg font-bold text-white shadow-sm transition-colors hover:bg-action-hover disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="checking" class="size-4 shrink-0 animate-spin rounded-full border border-white/40 border-t-white"></span>
                            Prisijungti
                        </button>
                    </form>

                    <p class="text-base text-gray-600">Arba tiesiog paspauskite nuorodą laiške.</p>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="resend()" :disabled="submitting" class="min-h-12 rounded-lg px-3 text-base font-semibold text-dark-green underline underline-offset-4 hover:no-underline disabled:opacity-60">Siųsti naują laišką</button>
                        <button type="button" @click="sent = false; codeErrors = {}; resent = false" class="min-h-12 rounded-lg px-3 text-base font-semibold text-dark-green underline underline-offset-4 hover:no-underline">Pakeisti el. paštą</button>
                    </div>
                </div>
            </template>

            <template x-if="!sent">
                <div class="space-y-4">
                    <div class="space-y-2.5">
                        <a href="/auth/google/redirect" class="flex min-h-12 w-full cursor-pointer items-center justify-center gap-3 rounded-lg border border-gray-200 bg-white text-sm font-semibold text-gray-900 transition-colors hover:border-gray-300 hover:bg-gray-50/80">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                            </svg>
                            <span>Tęsti su Google</span>
                        </a>
                        <a href="/auth/facebook/redirect" class="flex min-h-12 w-full cursor-pointer items-center justify-center gap-3 rounded-lg border border-[#1877F2]/25 bg-white text-sm font-semibold text-gray-900 transition-colors hover:border-[#1877F2]/40 hover:bg-[#1877F2]/5">
                            <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" /></svg>
                            <span>Tęsti su Facebook</span>
                        </a>
                    </div>

                    <div class="relative py-1">
                        <div class="absolute inset-0 flex items-center"><span class="w-full border-t border-gray-200"></span></div>
                        <div class="relative flex justify-center"><span class="bg-white px-3 text-xs font-medium uppercase tracking-wide text-gray-500">Arba</span></div>
                    </div>

                    <form @submit.prevent="submitMagicLinkForm($el)" method="POST" action="/login" class="space-y-4">
                        @csrf
                        <template x-if="firstError(errors)">
                            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" x-text="firstError(errors)"></p>
                        </template>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-900">El. paštas</label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="jusu@pastas.lt" class="min-h-12 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-green focus:outline-none">
                        </div>
                        <button type="submit" :disabled="submitting" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg bg-action text-sm font-semibold text-white shadow-sm transition-colors hover:bg-action-hover disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="submitting" class="size-4 shrink-0 animate-spin rounded-full border border-white/40 border-t-white"></span>
                            <x-app-icon name="mail" class="size-4" x-show="!submitting" />
                            Atsiųsti prisijungimo laišką
                        </button>
                    </form>
                </div>
            </template>
        </div>

        <template x-if="!sent">
            <div class="space-y-2 border-t border-gray-100 bg-gray-50/80 px-6 py-4 text-center text-sm leading-relaxed">
                <p class="text-gray-600">Paskyra sukuriama automatiškai, be papildomų formų.</p>
            </div>
        </template>
    </div>
</div>
