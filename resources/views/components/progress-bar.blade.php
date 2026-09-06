@props([
    'bar',
    'noun' => 'Pokémon',
    'compact' => false,
])

{{-- Fortschrittsbalken im Blockstil (spec.md 2.2, 5) --}}
<div {{ $attributes->merge(['class' => 'w-full']) }}>
    <div class="mb-1 flex items-baseline justify-between gap-2">
        <span class="font-pixel text-[10px] uppercase text-dex-muted">{{ $bar->label }}</span>
        <span class="font-dex text-sm text-dex-text">
            {{ $bar->owned }}<span class="text-dex-muted">/{{ $bar->total }}</span>
            <span class="ml-1 text-dex-accent">{{ number_format($bar->percent(), 1, ',', '.') }}&nbsp;%</span>
        </span>
    </div>

    <div class="dex-progress"
         role="progressbar"
         aria-valuenow="{{ (int) $bar->percent() }}"
         aria-valuemin="0"
         aria-valuemax="100"
         aria-label="{{ $bar->caption($noun) }}">
        <span style="width: {{ $bar->percent() }}%"></span>
    </div>

    @unless ($compact)
        <p class="mt-1 text-xs text-dex-muted">
            @if ($bar->isComplete())
                Komplett! 🎉
            @else
                Noch {{ $bar->missing() }} {{ $noun }} offen.
            @endif
        </p>
    @endunless
</div>
