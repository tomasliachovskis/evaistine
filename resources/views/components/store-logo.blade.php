@props(['slug', 'name' => '', 'size' => 'md'])

@php
    $sizeClass = match ($size) {
        'xs' => 'h-5 w-auto max-w-[3rem] sm:h-6 sm:max-w-[3.25rem]',
        'sm' => 'h-6 w-auto max-w-[4.5rem] sm:h-7 sm:max-w-[5rem]',
        'lg' => 'max-h-[56px] max-w-[120px] sm:max-h-[64px] sm:max-w-[132px]',
        default => 'h-10 w-16 sm:h-11 sm:w-[4.75rem]',
    };
@endphp

{{-- ?v= busts the CDN's 4h edge cache after a logo file changes — bump this
     whenever public/assets/stores/*.svg content changes, since the filename
     itself never does. --}}
<img src="/assets/stores/{{ $slug }}.svg?v=2" alt="{{ $name }}" class="object-contain {{ $sizeClass }} {{ $attributes->get('class') }}">
