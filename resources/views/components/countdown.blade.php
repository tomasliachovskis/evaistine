@props(['toDate'])

{{-- $toDate is a "Y-m-d" string (date only — Discount::end_at has no time
     component in the API payload), so this counts down to that day's
     midnight. Renders nothing if there's no end date or it's already past. --}}
@php
    $target = $toDate ? \Illuminate\Support\Carbon::parse($toDate)->endOfDay() : null;
@endphp

@if ($target && $target->isFuture())
    <div
        x-data="{
            remaining: {{ (int) now()->diffInSeconds($target) }},
            label: '',
            tick() {
                if (this.remaining <= 0) { this.label = 'Akcija baigiasi'; return; }
                const d = Math.floor(this.remaining / 86400);
                const h = Math.floor((this.remaining % 86400) / 3600);
                const m = Math.floor((this.remaining % 3600) / 60);
                this.label = d > 0 ? `${d}d ${h}val.` : (h > 0 ? `${h}val. ${m}min.` : `${m}min.`);
                this.remaining--;
            },
        }"
        x-init="tick(); setInterval(tick, 60000)"
        class="inline-flex items-center gap-1 rounded bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
    >
        <span aria-hidden="true">⏳</span>
        <span x-text="'Baigiasi po: ' + label"></span>
    </div>
@endif
