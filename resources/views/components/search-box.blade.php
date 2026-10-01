@props(['value' => '', 'placeholder' => 'Pienas, kava, kiaulienos nugarinė…', 'autofocus' => false])

{{-- The home page's search pill, shared with the search pages so a reader
     can fix a typo or search again right where the results are. --}}
<form
    method="get"
    action=""
    {{ $attributes->class('flex w-full max-w-xl items-center gap-2 rounded-full border border-gray-200 bg-white p-1.5 pl-5 shadow-sm') }}
    x-data="{ q: @js($value) }"
    @submit.prevent="if (q.trim()) window.location = '/akcijos/paieska/' + encodeURIComponent(q.trim())"
>
    <input
        type="search"
        name="q"
        x-model="q"
        placeholder="{{ $placeholder }}"
        aria-label="Ieškoti prekių"
        class="min-w-0 flex-1 border-none bg-transparent text-base outline-none sm:text-lg"
        @if ($autofocus) autofocus @endif
    >
    <button type="submit" class="shrink-0 rounded-full bg-action px-5 py-3 text-base font-bold text-white transition-colors hover:bg-action-hover">
        Ieškoti
    </button>
</form>
