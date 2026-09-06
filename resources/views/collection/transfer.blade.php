<x-app-layout>
    <x-slot name="title">Sichern &amp; Übertragen – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Sichern &amp; Übertragen</h1>
        <p class="mt-2 text-sm text-dex-muted">
            Deinen Sammlungsstand als Datei sichern – und auf einem anderen Rechner
            oder nach einem Neuaufsetzen wieder einspielen.
        </p>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-2">

        {{-- ── Export ──────────────────────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Exportieren</h2>

            <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="pixel-panel-soft p-3">
                    <dt class="text-[11px] uppercase text-dex-muted">Besessen</dt>
                    <dd class="font-dex text-2xl">{{ $besessen }}</dd>
                </div>
                <div class="pixel-panel-soft p-3">
                    <dt class="text-[11px] uppercase text-dex-muted">Shiny</dt>
                    <dd class="font-dex text-2xl">{{ $shinys }}</dd>
                </div>
                <div class="pixel-panel-soft p-3">
                    <dt class="text-[11px] uppercase text-dex-muted">Wunschliste</dt>
                    <dd class="font-dex text-2xl">{{ $favoriten }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-dex-muted">
                Die Datei enthält nur Deinen Sammlungsstand – keine E-Mail, kein Passwort,
                keine Kontodaten. Du kannst sie also gefahrlos weitergeben.
            </p>

            <a href="{{ route('collection.export') }}" class="pixel-button mt-5">
                ⬇ Als JSON herunterladen
            </a>

            <p class="mt-2 text-xs text-dex-muted">
                Dateiname: <span class="font-dex">{{ $dateiname }}</span>
            </p>
        </section>

        {{-- ── Import ──────────────────────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Importieren</h2>

            <form method="POST" action="{{ route('collection.import') }}"
                  enctype="multipart/form-data" class="mt-4">
                @csrf

                <label for="datei" class="mb-1 block text-xs uppercase text-dex-muted">
                    Export-Datei
                </label>
                <input type="file" id="datei" name="datei" accept=".json,application/json"
                       required
                       class="pixel-input file:mr-3 file:border-0 file:bg-dex-soft file:px-3 file:py-1 file:text-dex-text">
                <x-input-error :messages="$errors->get('datei')" class="mt-2" />

                <fieldset class="mt-5">
                    <legend class="mb-2 font-pixel text-[10px] uppercase text-dex-muted">Modus</legend>

                    <label class="flex items-start gap-3 text-sm">
                        <input type="radio" name="modus" value="ergaenzen" checked
                               class="mt-0.5 border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                        <span>
                            Ergänzen
                            <span class="block text-xs text-dex-muted">
                                Fügt die Einträge zu Deinem Stand hinzu. Was Du schon hast,
                                bleibt – ein älterer Export kann Dir also nichts wegnehmen.
                            </span>
                        </span>
                    </label>

                    <label class="mt-3 flex items-start gap-3 text-sm">
                        <input type="radio" name="modus" value="ersetzen"
                               class="mt-0.5 border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                        <span>
                            Ersetzen
                            <span class="block text-xs text-dex-danger">
                                Setzt Deinen Stand exakt auf die Datei. Alles, was dort nicht
                                steht, gilt danach als nicht besessen.
                            </span>
                        </span>
                    </label>
                </fieldset>

                <button type="submit" class="pixel-button mt-5">Datei einspielen</button>
            </form>

            <div class="pixel-panel-soft mt-5 p-3 text-xs text-dex-muted">
                <p class="font-pixel text-[10px] uppercase">Gut zu wissen</p>
                <ul class="mt-2 space-y-1">
                    <li>
                        Zugeordnet wird über den Formnamen (z.B. <span class="font-dex">bulbasaur</span>,
                        <span class="font-dex">vulpix-alola</span>), nicht über interne IDs – die Datei
                        funktioniert deshalb auch auf einer frisch aufgesetzten Datenbank.
                    </li>
                    <li>
                        Formen, die es hier noch nicht gibt, werden übersprungen und gemeldet,
                        statt den Import abzubrechen.
                    </li>
                    <li>Vor einer großen Massenaktion lohnt sich ein Export als Sicherung.</li>
                </ul>
            </div>
        </section>
    </div>
</x-app-layout>
