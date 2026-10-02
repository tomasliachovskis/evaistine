@props(['label', 'icon' => null, 'href' => null, 'valueText' => null, 'wrapperClass' => 'flex min-w-0 flex-col gap-1 sm:flex-1'])

{{-- One labelled button in <x-nav-bar>: the label above, a white button
     with the current value in words below it. A <button> opening a sheet
     or list (chevron), or with $href a link to another page (arrow).
     $valueText: an Alpine expression that keeps the value text live.
     Slots: $leading replaces the icon (e.g. a spinner swap), $after goes
     under the button inside the same wrapper (e.g. a dropdown). Use
     x-on:/x-bind: attributes, not the @/: shorthands, on this tag. --}}
<div class="{{ $wrapperClass }}">
    <span class="text-base font-semibold text-gray-700">{{ $label }}</span>
    @if ($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex min-h-12 w-full min-w-0 items-center gap-2.5 rounded-xl border border-green-soft-border bg-white px-4 text-left text-lg font-bold text-gray-900 transition-colors hover:border-green']) }}>
    @else
        <button type="button" {{ $attributes->merge(['class' => 'flex min-h-12 w-full min-w-0 cursor-pointer items-center gap-2.5 rounded-xl border border-green-soft-border bg-white px-4 text-left text-lg font-bold text-gray-900 transition-colors hover:border-green']) }}>
    @endif
        @if (isset($leading))
            {{ $leading }}
        @elseif ($icon)
            <x-app-icon :name="$icon" class="size-6 shrink-0 text-dark-green" />
        @endif
        <span class="min-w-0 flex-1 truncate" @if ($valueText) x-text="{{ $valueText }}" @endif>{{ $slot }}</span>
        <x-app-icon :name="$href ? 'arrow-right' : 'chevron-down'" class="size-5 shrink-0 text-gray-500" />
    @if ($href)
        </a>
    @else
        </button>
    @endif
    {{ $after ?? '' }}
</div>
