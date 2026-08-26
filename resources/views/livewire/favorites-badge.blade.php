<span>
    @if ($count > 0)
        <span class="absolute -right-2 -top-1.5 flex size-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-0.5 text-[9px] font-bold text-white">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</span>
