<x-app-layout>
    <x-slot name="title">Dashboard – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-pixel text-sm text-dex-accent sm:text-base">
                    Hallo, {{ auth()->user()->name }}
                </h1>
                <p class="mt-2 text-sm text-dex-muted">
                    Trainer-Level {{ auth()->user()->level() }} ·
                    {{ number_format(auth()->user()->xp, 0, ',', '.') }} XP ·
                    noch {{ number_format(auth()->user()->xpToNextLevel(), 0, ',', '.') }} XP bis Level
                    {{ auth()->user()->level() + 1 }}
                </p>
            </div>

            <a href="{{ route('trainer.card') }}" class="pixel-button-ghost">Trainerkarte</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Fortschritt (spec.md 2.2) ───────────────────────────────────── --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="pixel-panel p-5">
                <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Deine Sammlung</h2>
                <x-progress-bar :bar="$gesamt" />

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <x-progress-bar :bar="$basis" compact />
                    <x-progress-bar :bar="$regional" compact noun="Formen" />
                    <x-progress-bar :bar="$shiny" compact noun="Shinys" />
                </div>

                <p class="mt-4 text-xs text-dex-muted">
                    Regional- und Shiny-Fortschritt laufen getrennt.
                    <a href="{{ route('settings.edit') }}" class="underline">In den Einstellungen</a>
                    lassen sie sich in den Hauptbalken einrechnen.
                </p>
            </div>

            {{-- Fortschritt je Generation --}}
            <div class="pixel-panel p-5">
                <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Nach Generation</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($generationen as $bar)
                        <x-progress-bar :bar="$bar" compact />
                    @empty
                        <p class="text-sm text-dex-muted">
                            Noch keine Daten – führe <code class="bg-dex-bg px-1">php artisan pokedex:import</code> aus.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Countdown und Verteilung (spec.md 2.7) ──────────────────────── --}}
        <div class="space-y-4">
            <x-bank-countdown :deadline="$deadline" :betroffen="$betroffenAnzahl" />

            <div class="pixel-panel p-5">
                <h2 class="mb-3 font-pixel text-xs uppercase text-dex-accent">Was fehlt Dir noch?</h2>

                <ul class="space-y-2 text-sm">
                    @foreach ($verteilung as $eintrag)
                        <li>
                            <a href="{{ route('pokedex.index', ['prio' => $eintrag['level']->value, 'status' => 'fehlend']) }}"
                               class="flex items-center justify-between gap-2 border-b border-dex-border/30 pb-1 hover:text-dex-accent">
                                <span class="flex items-center gap-2">
                                    <span aria-hidden="true">{{ $eintrag['level']->icon() }}</span>
                                    {{ $eintrag['level']->label() }}
                                </span>
                                <span class="font-dex text-base">{{ $eintrag['anzahl'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if ($naechsteZiele->isNotEmpty())
                <div class="pixel-panel p-5">
                    <h2 class="mb-3 font-pixel text-xs uppercase text-dex-accent">Deine Wunschliste</h2>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($naechsteZiele as $row)
                            <x-pokemon-card :row="$row" :interactive="false" />
                        @endforeach
                    </div>
                    <a href="{{ route('pokedex.index', ['status' => 'wunschliste']) }}"
                       class="mt-3 inline-block text-xs underline text-dex-muted">
                        Ganze Wunschliste ansehen
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{--
        ── Bank-Deadline, in zwei Gruppen (spec.md 2.7) ─────────────────────────
        Beide hängen an derselben Frist, brauchen aber völlig verschiedene
        Handlungen: einmal etwas beschaffen, einmal nur noch übertragen.
    --}}
    @if ($selbstHolbarTop->isNotEmpty())
        <div class="pixel-panel mt-6 border-dex-accent p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-pixel text-xs uppercase text-dex-accent">
                        ⏳ Kannst Du selbst holen – aber vor der Deadline
                    </h2>
                    <p class="mt-2 text-sm text-dex-muted">
                        Du besitzt ein passendes Spiel. Fangen und
                        <strong class="text-dex-text">vor dem {{ $deadline->shutdownAt()->format('d.m.Y') }}</strong>
                        über Pokémon Bank nach HOME übertragen.
                    </p>
                </div>
                <a href="{{ route('pokedex.index', ['deadline' => 1, 'prio' => 'easy', 'status' => 'fehlend']) }}"
                   class="pixel-button-ghost">
                    Alle {{ $selbstHolbarAnzahl }} ansehen
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($selbstHolbarTop as $row)
                    <x-pokemon-card :row="$row" />
                @endforeach
            </div>
        </div>
    @endif

    @if ($dringendTop->isNotEmpty())
        <div class="pixel-panel mt-6 border-dex-danger p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="font-pixel text-xs uppercase text-dex-danger">
                        🔴 Dafür fehlt Dir noch Spiel oder Konsole
                    </h2>
                    <p class="mt-2 text-sm text-dex-muted">
                        Erst beschaffen, dann übertragen – und beides vor der Abschaltung.
                    </p>
                </div>
                <a href="{{ route('pokedex.index', ['prio' => 'bank_urgent', 'status' => 'fehlend', 'sortierung' => 'dringlichkeit']) }}"
                   class="pixel-button-ghost">
                    Alle {{ $dringendAnzahl }} ansehen
                </a>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                @foreach ($dringendTop as $row)
                    <x-pokemon-card :row="$row" />
                @endforeach
            </div>
        </div>
    @endif
</x-app-layout>
