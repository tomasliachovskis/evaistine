<span>
    @if ($count > 0)
        <span class="absolute -right-2 -top-2 flex size-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold leading-none text-white">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</span>
