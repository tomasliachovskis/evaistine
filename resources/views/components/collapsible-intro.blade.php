{{-- Page intro text: on phones it shows the first 2 lines with a
     "Skaityti daugiau" toggle, so the content below starts sooner; from sm
     up it is always shown in full. --}}
<div x-data="{ open: false }" {{ $attributes->class(['max-w-[60ch]']) }}>
    <p class="text-sm leading-relaxed text-gray-600 sm:text-base" :class="open ? '' : 'max-sm:line-clamp-2'">
        {{ $slot }}
    </p>
    <button type="button" @click="open = !open" class="inline-flex min-h-11 items-center gap-1 text-base font-semibold text-dark-green underline underline-offset-4 sm:hidden" :aria-expanded="open">
        <span x-text="open ? 'Rodyti mažiau' : 'Skaityti daugiau'">Skaityti daugiau</span>
        <x-app-icon name="chevron-down" class="size-4 transition-transform" x-bind:class="open && 'rotate-180'" />
    </button>
</div>
