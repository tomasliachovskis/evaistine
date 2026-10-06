{{-- The light green navigation bar above offer and leaflet lists: labelled
     <x-nav-bar-button>s that say in words what is shown. One component so
     /akcijos listings, search and /leidiniai can't drift apart.
     Deliberately not pinned (owner's decision, 2026-10-02): with labels it
     is ~95px tall and covered too much of the list.

     Phones: the stacked buttons take ~290px, so a "Filtrai" header folds
     them away (owner's request). Folded, it shows the chosen values in one
     line ("Norfa · Mėsa ir žuvis · Populiariausi"), read from the buttons'
     data-nav-value spans. The choice is remembered in localStorage. Desktop
     is one row and always open. --}}
<div {{ $attributes->merge(['class' => 'relative z-30 mb-4 w-full rounded-2xl border border-green/30 bg-green/5 p-3 sm:px-4']) }}>
    <div
        x-data="{
            navFolded: false,
            navSummary: '',
            init() {
                try { this.navFolded = localStorage.getItem('evaistine_filtrai_suskleisti') === '1'; } catch (e) {}
                this.$nextTick(() => this.readSummary());
                // The values change after load (saved stores applied, a new
                // sort): keep the folded line in step.
                new MutationObserver(() => this.readSummary())
                    .observe(this.$root, { subtree: true, childList: true, characterData: true });
            },
            readSummary() {
                this.navSummary = [...this.$root.querySelectorAll('[data-nav-value]')]
                    .map((el) => el.textContent.replace(/\s+/g, ' ').trim())
                    .filter(Boolean)
                    .join(' · ');
            },
            toggleNav() {
                this.readSummary();
                this.navFolded = !this.navFolded;
                try { localStorage.setItem('evaistine_filtrai_suskleisti', this.navFolded ? '1' : '0'); } catch (e) {}
            },
        }"
    >
        <button
            type="button"
            @click="toggleNav()"
            :aria-expanded="!navFolded"
            class="flex min-h-12 w-full items-center gap-2.5 text-left sm:hidden"
            :class="navFolded ? '' : 'mb-2'"
        >
            <x-app-icon name="filter" class="size-6 shrink-0 text-dark-green" />
            <span class="min-w-0 flex-1">
                <span class="block text-lg font-bold text-gray-900">Filtrai</span>
                <span x-show="navFolded" x-cloak class="line-clamp-2 text-base leading-snug text-gray-700" x-text="navSummary"></span>
            </span>
            <span class="inline-flex shrink-0 items-center gap-1 text-base font-semibold text-dark-green">
                <span x-text="navFolded ? 'Išskleisti' : 'Suskleisti'">Suskleisti</span>
                <x-app-icon name="chevron-down" class="size-5 transition-transform" x-bind:class="navFolded ? '' : 'rotate-180'" />
            </span>
        </button>
        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-end sm:gap-4" :class="navFolded ? 'max-sm:hidden' : ''">
            {{ $slot }}
        </div>
    </div>
</div>
