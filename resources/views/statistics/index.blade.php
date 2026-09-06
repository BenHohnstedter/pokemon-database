@php
    use App\Enums\Difficulty;

    $verlaufMax = max(1, max($verlauf ?: [1]));
@endphp

<x-app-layout>
    <x-slot name="title">Statistik – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Statistik</h1>
        <p class="mt-2 text-sm text-dex-muted">Wie sich Deine Sammlung verteilt und entwickelt.</p>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- ── Verhältnis Basis / Regional / Shiny (spec.md 2.10) ───────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Basis, Regional, Shiny</h2>
            <div class="space-y-4">
                <x-progress-bar :bar="$basis" />
                <x-progress-bar :bar="$regional" noun="Formen" />
                <x-progress-bar :bar="$shiny" noun="Shinys" />
            </div>
        </section>

        {{-- ── Zeitlicher Verlauf ──────────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Zuwachs pro Monat</h2>

            <div class="flex h-40 items-end gap-1" role="img"
                 aria-label="Balkendiagramm der pro Monat ergänzten Pokémon">
                @foreach ($verlauf as $monat => $anzahl)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <span class="font-dex text-xs text-dex-muted">{{ $anzahl ?: '' }}</span>
                        <div class="w-full bg-dex-accent transition-all"
                             style="height: {{ max(2, ($anzahl / $verlaufMax) * 100) }}%"
                             title="{{ $monat }}: {{ $anzahl }}"></div>
                        <span class="text-[9px] text-dex-muted">{{ substr($monat, 5, 2) }}</span>
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-dex-muted">Letzte 12 Monate, Monatszahl unter dem Balken.</p>
        </section>

        {{-- ── Fortschritt nach Typ ────────────────────────────────────────── --}}
        <section class="pixel-panel p-5 lg:col-span-2">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Nach Typ</h2>

            @if ($nachTyp === [])
                <p class="text-sm text-dex-muted">Noch keine Typdaten importiert.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($nachTyp as $bar)
                        <x-progress-bar :bar="$bar" compact />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── Nach Generation ─────────────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Nach Generation</h2>
            <div class="space-y-4">
                @foreach ($nachGeneration as $bar)
                    <x-progress-bar :bar="$bar" compact />
                @endforeach
            </div>
        </section>

        {{-- ── Dringlichkeit und Schwierigkeit (spec.md 2.7, 2.8) ──────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Dringlichkeit</h2>

            <ul class="space-y-2 text-sm">
                @foreach ($nachDringlichkeit as $eintrag)
                    <li class="flex items-center justify-between gap-2 border-b border-dex-border/30 pb-1">
                        <span class="flex items-center gap-2">
                            <span aria-hidden="true">{{ $eintrag['level']->icon() }}</span>
                            {{ $eintrag['level']->label() }}
                        </span>
                        <span class="font-dex text-base">{{ $eintrag['anzahl'] }}</span>
                    </li>
                @endforeach
            </ul>

            <h3 class="mb-3 mt-6 font-pixel text-[10px] uppercase text-dex-muted">
                Fehlende nach Schwierigkeit
            </h3>

            <ul class="space-y-2 text-sm">
                @foreach (Difficulty::cases() as $stufe)
                    <li class="flex items-center justify-between gap-2 border-b border-dex-border/30 pb-1">
                        <x-difficulty-badge :difficulty="$stufe" />
                        <span class="font-dex text-base">{{ $nachSchwierigkeit[$stufe->value] ?? 0 }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-app-layout>
