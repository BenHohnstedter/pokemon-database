@props([
    'deadline',
    'betroffen' => 0,
])

{{--
    Countdown-Widget (spec.md 2.7): "Noch X Tage bis Pokémon Bank abgeschaltet
    wird – Y Pokémon sind noch betroffen."
--}}
<div class="pixel-panel scanlines relative overflow-hidden p-4 {{ $betroffen > 0 && ! $deadline->hasPassed() ? 'border-dex-danger' : '' }}">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="font-pixel text-[10px] uppercase text-dex-muted">Pokémon Bank</p>
            <p class="mt-1 font-pixel text-lg {{ $deadline->hasPassed() ? 'text-dex-muted' : 'text-dex-danger' }}">
                {{ $deadline->humanLabel() }}
            </p>
            <p class="mt-1 text-xs text-dex-muted">
                Abschaltung am {{ $deadline->shutdownAt()->format('d.m.Y') }}
            </p>
        </div>

        <div class="text-right">
            <p class="font-dex text-3xl {{ $betroffen > 0 ? 'text-dex-danger' : 'text-dex-success' }}">
                {{ $betroffen }}
            </p>
            <p class="text-xs text-dex-muted">
                {{ $betroffen === 1 ? 'Pokémon betroffen' : 'Pokémon betroffen' }}
            </p>
        </div>
    </div>

    <div class="dex-progress mt-3">
        <span style="width: {{ $deadline->elapsedPercent() }}%"></span>
    </div>

    @if ($betroffen > 0 && ! $deadline->hasPassed())
        <a href="{{ route('pokedex.index', ['prio' => 'bank_urgent', 'status' => 'fehlend', 'sortierung' => 'dringlichkeit']) }}"
           class="pixel-button mt-4 w-full">
            Dringende Fälle ansehen
        </a>
    @elseif (! $deadline->hasPassed())
        <p class="mt-3 text-xs text-dex-success">
            Kein Pokémon hängt mehr an der Bank-Deadline. 🎉
        </p>
    @endif
</div>
