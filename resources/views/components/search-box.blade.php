@props(['value' => '', 'placeholder' => 'Vitaminas D, ibuprofenas, kremas nuo saulės…', 'autofocus' => false, 'size' => 'md'])

{{-- The home page's search pill, shared with the search pages so a reader
     can fix a typo or search again right where the results are. size="lg"
     is the larger hero version (/pradzia-beta): a search icon, bigger text
     and a 56px button. --}}
@php
    $isLarge = $size === 'lg';
@endphp
<form
    method="get"
    action=""
    {{ $attributes->class($isLarge
        ? 'flex w-full max-w-3xl items-center gap-3 rounded-3xl bg-white p-2 pl-4 shadow-[0_1px_2px_rgba(22,32,26,0.06),0_12px_32px_rgba(22,32,26,0.09)] sm:p-2.5 sm:pl-6'
        : 'flex w-full max-w-xl items-center gap-2 rounded-full border border-gray-200 bg-white p-1.5 pl-5 shadow-sm') }}
    x-data="{ q: @js($value) }"
    @submit.prevent="if (q.trim()) window.location = '/paieska/' + encodeURIComponent(q.trim())"
>
    @if ($isLarge)
        <x-app-icon name="search" class="size-6 shrink-0 text-gray-500" />
    @endif
    <input
        type="search"
        name="q"
        x-model="q"
        placeholder="{{ $placeholder }}"
        aria-label="Ieškoti prekių"
        class="min-w-0 flex-1 border-none bg-transparent outline-none {{ $isLarge ? 'text-lg sm:text-xl' : 'text-base sm:text-lg' }}"
        @if ($autofocus) autofocus @endif
    >
    <button type="submit" class="shrink-0 bg-action font-bold text-white transition-colors hover:bg-action-hover {{ $isLarge ? 'min-h-14 rounded-2xl px-5 text-lg sm:px-8 sm:text-xl' : 'rounded-full px-5 py-3 text-base' }}">
        Ieškoti
    </button>
</form>
