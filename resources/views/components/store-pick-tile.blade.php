@props(['slug', 'name', 'checked' => null, 'toggle' => null, 'href' => null, 'active' => false, 'checkbox' => true, 'note' => null])

{{-- One store tile: logo on top, the name centered under it with the
     tile's full width (side by side, names like "Thomas Philipps" broke
     mid-word). Every store picker on the site uses it, so they all look
     like the "Mano parduotuvės" one:
     - Alpine-driven ($checked: JS expression, $toggle: JS statement run on
       tap): check box tiles that update in place. With $href it stays a
       real link for crawlers and the tap is intercepted.
     - Plain link ($href, no $toggle): $active marks the current choice;
       $checkbox false drops the empty box for tiles that just open a page.
     $note: a small line under the name ("12 leidinių"). --}}
@php
    $alpine = $toggle !== null;
    $tag = $href ? 'a' : 'button';
@endphp
<{{ $tag }}
    {{ $attributes->except('class') }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    @if ($alpine)
        @if ($href) @click.prevent="{{ $toggle }}" @else @click="{{ $toggle }}" @endif
        role="checkbox"
        :aria-checked="({{ $checked }}) ? 'true' : 'false'"
        :class="({{ $checked }}) ? 'border-action bg-green-soft' : 'border-gray-200 hover:border-gray-300'"
    @elseif ($active)
        aria-current="true"
    @endif
    class="relative flex min-h-28 w-[calc(50%-0.3125rem)] flex-col items-center justify-center gap-2 rounded-2xl border-2 px-3 pb-3 pt-5 text-center transition-colors {{ $alpine ? 'bg-white' : ($active ? 'border-action bg-green-soft' : 'border-gray-200 bg-white hover:border-gray-300') }}"
>
    @if ($alpine)
        <span
            class="absolute right-2 top-2 flex size-6 items-center justify-center rounded-md border-2"
            :class="({{ $checked }}) ? 'border-action bg-action text-white' : 'border-gray-300 bg-white'"
        ><svg x-show="{{ $checked }}" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg></span>
    @elseif ($active || $checkbox)
        <span class="absolute right-2 top-2 flex size-6 items-center justify-center rounded-md border-2 {{ $active ? 'border-action bg-action text-white' : 'border-gray-300 bg-white' }}">
            @if ($active)
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            @endif
        </span>
    @endif
    <span class="flex h-9 items-center justify-center">
        <img src="/assets/stores/{{ $slug }}.svg" alt="" class="h-9 w-28 object-contain">
    </span>
    <span class="text-lg font-semibold leading-tight text-gray-900">{{ $name }}</span>
    @if ($note)
        <span class="-mt-1 text-base text-gray-500">{{ $note }}</span>
    @endif
</{{ $tag }}>
