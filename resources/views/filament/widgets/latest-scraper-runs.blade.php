<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-wrap gap-2">
            @foreach ($this->getRuns() as $run)
                @php
                    [$bg, $fg] = match ($run['color']) {
                        'success' => ['#dcfce7', '#15803d'],
                        'danger' => ['#fee2e2', '#b91c1c'],
                        'warning' => ['#fef3c7', '#b45309'],
                        default => ['#f3f4f6', '#4b5563'],
                    };
                @endphp
                <div
                    title="{{ $run['title'] }}"
                    style="background-color: {{ $bg }}; color: {{ $fg }};"
                    class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm"
                >
                    <span class="font-medium">{{ $run['store'] }}:</span>
                    <span class="font-semibold">{{ $run['value'] }}</span>
                    @if ($run['startedAgo'])
                        <span class="opacity-50">|</span>
                        <span class="opacity-60 text-xs">{{ $run['startedAgo'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
