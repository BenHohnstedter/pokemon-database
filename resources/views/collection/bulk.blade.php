<x-app-layout>
    <x-slot name="title">Masseneingabe – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Freitext-Masseneingabe</h1>
        <p class="mt-2 text-sm text-dex-muted">
            Mehrere Dex-Nummern auf einmal als besessen markieren – oder wieder entfernen.
        </p>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('collection.bulk.preview') }}" class="pixel-panel p-5">
                @csrf

                <label for="eingabe" class="mb-2 block font-pixel text-[10px] uppercase text-dex-muted">
                    Dex-Nummern
                </label>

                <textarea id="eingabe" name="eingabe" rows="5" class="pixel-input font-dex text-base"
                          placeholder="1,15,700 oder 1-50,60-63"
                          required>{{ old('eingabe', $eingabe ?? '') }}</textarea>

                <x-input-error :messages="$errors->get('eingabe')" class="mt-2" />

                <fieldset class="mt-4">
                    <legend class="mb-2 font-pixel text-[10px] uppercase text-dex-muted">Aktion</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="aktion" value="besitzen"
                                   @checked(($aktion ?? 'besitzen') === 'besitzen')
                                   class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                            Als besessen markieren
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="aktion" value="entfernen"
                                   @checked(($aktion ?? '') === 'entfernen')
                                   class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                            Als nicht besessen markieren
                        </label>
                    </div>
                </fieldset>

                <button type="submit" class="pixel-button mt-5">Vorschau anzeigen</button>
            </form>

            {{-- ── Vorschau vor dem Übernehmen (spec.md 2.5) ───────────────── --}}
            @isset($ergebnis)
                <div class="pixel-panel mt-5 p-5 {{ $ergebnis->hasInvalid() ? 'border-dex-danger' : 'border-dex-success' }}">
                    <h2 class="font-pixel text-xs uppercase text-dex-accent">Vorschau</h2>

                    @if (count($treffer) === 0)
                        <p class="mt-3 text-sm text-dex-danger">
                            Keine gültige Dex-Nummer erkannt – nichts zu tun.
                        </p>
                    @else
                        <p class="mt-3 text-sm">
                            Das markiert <strong class="font-dex text-lg text-dex-accent">{{ count($treffer) }}</strong>
                            Pokémon als
                            <strong>{{ $aktion === 'besitzen' ? 'besessen' : 'nicht besessen' }}</strong>
                            – bestätigen?
                        </p>

                        <p class="mt-2 break-words font-dex text-sm text-dex-muted">
                            {{ $ergebnis->summary() }}
                        </p>

                        @if ($namen->isNotEmpty())
                            <p class="mt-2 text-xs text-dex-muted">
                                Darunter:
                                {{ $namen->map(fn ($name, $nr) => "#{$nr} {$name}")->join(', ') }}@if (count($treffer) > $namen->count()) … @endif
                            </p>
                        @endif
                    @endif

                    @if ($ergebnis->hasInvalid())
                        <p class="mt-3 text-sm text-dex-danger">
                            Nicht verwertbar: <span class="font-dex">{{ $ergebnis->invalidSummary() }}</span>
                        </p>
                    @endif

                    @if (count($ergebnis->dexNumbers) > count($treffer))
                        <p class="mt-2 text-xs text-dex-muted">
                            {{ count($ergebnis->dexNumbers) - count($treffer) }} Nummern liegen im gültigen
                            Bereich, sind aber noch nicht importiert – sie werden übersprungen.
                        </p>
                    @endif

                    @if (count($treffer) > 0)
                        <form method="POST" action="{{ route('collection.bulk.apply') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="eingabe" value="{{ $eingabe }}">
                            <input type="hidden" name="aktion" value="{{ $aktion }}">
                            <button type="submit" class="pixel-button">
                                Ja, {{ count($treffer) }} Pokémon übernehmen
                            </button>
                        </form>
                    @endif
                </div>
            @endisset
        </div>

        <aside class="pixel-panel h-fit p-5 text-sm">
            <h2 class="font-pixel text-[10px] uppercase text-dex-muted">So funktioniert's</h2>

            <ul class="mt-3 space-y-2 text-dex-muted">
                <li><span class="font-dex text-dex-text">1,15,700</span> – einzelne Nummern</li>
                <li><span class="font-dex text-dex-text">1-50</span> – ein Bereich</li>
                <li><span class="font-dex text-dex-text">1-50,60-63,700</span> – gemischt</li>
                <li>Trenner dürfen Komma, Semikolon, Leerzeichen oder Zeilenumbruch sein.</li>
                <li>Vertauschte Grenzen wie <span class="font-dex">50-1</span> werden automatisch gedreht.</li>
            </ul>

            <p class="mt-4 text-xs text-dex-muted">
                Gültiger Bereich aktuell: 1–{{ $maxDex }}.
                Die Eingabe wirkt immer auf die Basisform; Regional- und Shiny-Bestände
                bleiben unangetastet.
            </p>
        </aside>
    </div>
</x-app-layout>
