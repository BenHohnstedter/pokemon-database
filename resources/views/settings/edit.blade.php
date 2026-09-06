<x-app-layout>
    <x-slot name="title">Einstellungen – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Einstellungen</h1>
        <p class="mt-2 text-sm text-dex-muted">
            Spielebesitz und GO-Region bestimmen, wie dringend ein fehlendes Pokémon eingestuft wird.
        </p>
    </x-slot>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PATCH')

        {{-- ── Spielebesitz (spec.md 2.6, Anhang A) ────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Welche Spiele besitzt Du?</h2>
            <p class="mt-2 text-sm text-dex-muted">
                Alles, was in einem dieser Spiele vorkommt, gilt als 🟢 einfach erreichbar.
            </p>

            @foreach ($spiele as $generation => $titel)
                <fieldset class="mt-5">
                    <legend class="font-pixel text-[10px] uppercase text-dex-muted">
                        {{ $generation === 0 ? 'Nebenreihe' : "Generation {$generation}" }}
                    </legend>

                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($titel as $spiel)
                            <label class="pixel-panel-soft flex items-start gap-2 p-2 text-sm">
                                <input type="checkbox" name="spiele[]" value="{{ $spiel->id }}"
                                       @checked(in_array($spiel->id, $besesseneSpiele, true))
                                       class="mt-0.5 border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                                <span>
                                    <span class="block">{{ $spiel->name_de }}</span>
                                    <span class="block text-[10px] text-dex-muted">
                                        {{ $spiel->platform->label() }}
                                        @if ($spiel->bank_only) · über Bank @endif
                                        @if ($spiel->still_purchasable) · im Handel @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </section>

        {{-- ── GO-Region (spec.md 2.4) ─────────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Pokémon GO</h2>

            <label for="go_region" class="mt-4 block text-sm">
                <span class="mb-1 block text-xs uppercase text-dex-muted">Deine Weltregion</span>
                <select id="go_region" name="go_region" class="pixel-input max-w-sm">
                    @foreach ($regionen as $wert => $label)
                        <option value="{{ $wert }}" @selected($settings->go_region?->value === $wert)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </label>

            <p class="mt-2 text-xs text-dex-muted">
                Regional exklusive Pokémon zeigt die App danach als „spawnt bei Dir" oder
                „nur per Reise/Tausch erreichbar" an.
            </p>
        </section>

        {{-- ── Zähl-Toggles (spec.md 2.2) ──────────────────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Was zählt in den Hauptbalken?</h2>

            <div class="mt-4 space-y-3 text-sm">
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="count_regional_in_total" value="1"
                           @checked($settings->count_regional_in_total)
                           class="mt-0.5 border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    <span>
                        Regionalformen in den Gesamtfortschritt einrechnen
                        <span class="block text-xs text-dex-muted">
                            Standard: aus. Regionalformen haben einen eigenen Balken.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input type="checkbox" name="count_shiny_in_total" value="1"
                           @checked($settings->count_shiny_in_total)
                           class="mt-0.5 border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    <span>
                        Shiny in den Gesamtfortschritt einrechnen
                        <span class="block text-xs text-dex-muted">
                            Verdoppelt die Zielzahl – jede Form zählt dann normal und shiny.
                        </span>
                    </span>
                </label>
            </div>

            <h3 class="mt-6 font-pixel text-[10px] uppercase text-dex-muted">Konsolenbesitz</h3>
            <div class="mt-2 flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="owns_3ds" value="1" @checked($settings->owns_3ds)
                           class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    Nintendo 3DS vorhanden
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="owns_switch" value="1" @checked($settings->owns_switch)
                           class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    Nintendo Switch vorhanden
                </label>
            </div>
        </section>

        {{-- ── Darstellung und Sound (spec.md 2.9, 5) ──────────────────────── --}}
        <section class="pixel-panel p-5">
            <h2 class="font-pixel text-xs uppercase text-dex-accent">Darstellung &amp; Sound</h2>

            <label for="theme" class="mt-4 block text-sm">
                <span class="mb-1 block text-xs uppercase text-dex-muted">Farbpalette</span>
                <select id="theme" name="theme" class="pixel-input max-w-sm">
                    @foreach ($themes as $wert => $label)
                        <option value="{{ $wert }}" @selected($settings->theme === $wert)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label for="per_page" class="mt-4 block text-sm">
                <span class="mb-1 block text-xs uppercase text-dex-muted">Pokémon pro Seite</span>
                <select id="per_page" name="per_page" class="pixel-input max-w-sm">
                    @foreach (\App\Support\PokedexFilter::PER_PAGE_OPTIONS as $wert)
                        <option value="{{ $wert }}" @selected($settings->per_page === $wert)>{{ $wert }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs text-dex-muted">
                    Gilt für das Pokédex-Raster. Mehr pro Seite heißt weniger Blättern,
                    aber längere Ladezeit.
                </span>
            </label>

            <div class="mt-5 space-y-3 text-sm">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="sound_effects_enabled" value="1"
                           @checked($settings->sound_effects_enabled)
                           class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    8-Bit-Soundeffekte beim Fangen
                </label>

                <label class="flex items-center gap-3">
                    <input type="checkbox" name="music_enabled" value="1" @checked($settings->music_enabled)
                           class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    Chiptune-Hintergrundmusik (Standard: aus)
                </label>

                <label for="music_volume" class="block max-w-sm">
                    <span class="mb-1 block text-xs uppercase text-dex-muted">Lautstärke</span>
                    <input type="range" id="music_volume" name="music_volume" min="0" max="100"
                           value="{{ $settings->music_volume }}" class="w-full accent-dex-accent">
                </label>

                <label class="flex items-center gap-3">
                    <input type="checkbox" name="reduce_motion" value="1" @checked($settings->reduce_motion)
                           class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                    Animationen reduzieren
                </label>
            </div>
        </section>

        <button type="submit" dusk="einstellungen-speichern" class="pixel-button">Speichern</button>
    </form>
</x-app-layout>
