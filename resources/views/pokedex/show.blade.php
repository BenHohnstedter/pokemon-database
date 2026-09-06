<x-app-layout>
    <x-slot name="title">{{ $pokemon->name_de }} – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-dex text-sm text-dex-muted">{{ $pokemon->dex_label }}</p>
                <h1 class="font-pixel text-sm text-dex-accent sm:text-lg">{{ $pokemon->name_de }}</h1>
                <p class="mt-1 text-xs text-dex-muted">
                    {{ $pokemon->name_en }} · Generation {{ $pokemon->generation }}
                    @if ($pokemon->is_legendary) · Legendär @endif
                    @if ($pokemon->is_mythical) · Mysteriös @endif
                </p>
            </div>

            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('pokedex.index') }}"
               class="pixel-button-ghost">
                ← Zurück
            </a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ── Formen mit Besitz-Toggles (spec.md 2.2, 2.5) ────────────────── --}}
        <div class="space-y-4">
            @foreach ($formen as $eintrag)
                @php $form = $eintrag->form; @endphp

                <div class="pixel-panel p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-pixel text-[10px] uppercase text-dex-muted">
                                {{ $form->form_type->label() }}
                            </h2>
                            <p class="mt-1 text-sm font-semibold">{{ $form->name_de }}</p>
                        </div>
                        <x-priority-badge :priority="$eintrag->priority->level" :show-label="false" />
                    </div>

                    <div class="mt-3 flex items-center justify-center gap-4">
                        @if ($form->displayImage())
                            <figure class="text-center">
                                <img src="{{ $form->displayImage() }}" alt="{{ $form->name_de }}"
                                     class="pixelated h-24 w-24 object-contain" width="96" height="96">
                                <figcaption class="text-[10px] text-dex-muted">Normal</figcaption>
                            </figure>
                        @endif

                        @if ($form->shiny_sprite_url)
                            <figure class="text-center">
                                <img src="{{ $form->shiny_sprite_url }}" alt="{{ $form->name_de }} (Shiny)"
                                     class="pixelated h-24 w-24 object-contain" width="96" height="96">
                                <figcaption class="text-[10px] text-dex-accent">Shiny</figcaption>
                            </figure>
                        @endif
                    </div>

                    @auth
                        <div x-data="dexToggle({
                                url: '{{ route('collection.toggle', $form) }}',
                                token: '{{ csrf_token() }}',
                                owned: {{ $eintrag->ownership?->owned ? 'true' : 'false' }},
                                shiny: {{ $eintrag->ownership?->owned_shiny ? 'true' : 'false' }},
                                favourite: {{ $eintrag->ownership?->is_favourite ? 'true' : 'false' }},
                                sound: {{ auth()->user()->settingsOrDefault()->sound_effects_enabled ? 'true' : 'false' }},
                             })"
                             class="mt-4 grid grid-cols-3 gap-2">
                            <button type="button" x-on:click="umschalten('normal')" :disabled="busy"
                                    class="pixel-button-ghost justify-center py-2 text-[10px]"
                                    :class="owned ? 'border-dex-success text-dex-success' : ''">
                                <span x-text="owned ? '✔ Besessen' : 'Fehlt'"></span>
                            </button>
                            <button type="button" x-on:click="umschalten('shiny')" :disabled="busy"
                                    class="pixel-button-ghost justify-center py-2 text-[10px]"
                                    :class="shiny ? 'border-dex-accent text-dex-accent' : ''">
                                <span x-text="shiny ? '✨ Shiny da' : '✨ Shiny'"></span>
                            </button>
                            <button type="button" x-on:click="umschalten('favorit')" :disabled="busy"
                                    class="pixel-button-ghost justify-center py-2 text-[10px]"
                                    :class="favourite ? 'border-dex-accent text-dex-accent' : ''">
                                <span x-text="favourite ? '★ Wunsch' : '☆ Wunsch'"></span>
                            </button>
                        </div>
                    @endauth

                    <div class="mt-3 flex flex-wrap justify-center gap-1">
                        @foreach ($form->displayTypes() as $type)
                            <x-type-badge :type="$type" />
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs text-dex-muted">{{ $eintrag->priority->reason }}</p>
                </div>
            @endforeach

            {{-- Typen und Basiswerte --}}
            <div class="pixel-panel p-4">
                <h2 class="font-pixel text-[10px] uppercase text-dex-muted">Typen der Basisform</h2>
                <div class="mt-2 flex flex-wrap gap-1">
                    @foreach ($pokemon->types as $type)
                        <x-type-badge :type="$type" />
                    @endforeach
                </div>

                @if ($pokemon->base_stats)
                    <h2 class="mt-4 font-pixel text-[10px] uppercase text-dex-muted">Basiswerte</h2>
                    <dl class="mt-2 space-y-1 text-xs">
                        @foreach ($pokemon->base_stats as $name => $wert)
                            <div class="flex items-center gap-2">
                                <dt class="w-28 shrink-0 text-dex-muted">{{ __('stats.'.$name) }}</dt>
                                <dd class="flex-1">
                                    <div class="dex-progress h-2">
                                        <span style="width: {{ min(100, $wert / 2) }}%"></span>
                                    </div>
                                </dd>
                                <dd class="font-dex w-8 text-right">{{ $wert }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>

        {{-- ── Bezugsquellen (spec.md 2.3) ─────────────────────────────────── --}}
        <div class="space-y-4 lg:col-span-2">
            <div class="pixel-panel p-4">
                <h2 class="font-pixel text-xs uppercase text-dex-accent">Wo bekomme ich es?</h2>

                @php $ersteForm = $formen->first(); @endphp

                @if ($ersteForm)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-priority-badge :priority="$ersteForm->priority->level" />
                        <x-difficulty-badge :difficulty="$ersteForm->priority->difficulty" />
                        @if ($ersteForm->priority->goRescuable)
                            <span class="border border-dex-success/50 bg-dex-success/10 px-2 py-0.5 text-[10px] uppercase text-dex-success">
                                Über GO ohne Bank-Deadline
                            </span>
                        @endif
                    </div>

                    @if ($ersteForm->priority->consoles)
                        <p class="mt-3 text-sm">
                            <span class="text-dex-muted">Benötigte Hardware:</span>
                            {{ implode(', ', $ersteForm->priority->consoles) }}
                        </p>
                    @endif
                @endif

                @if ($quellen->isEmpty())
                    <p class="mt-4 text-sm text-dex-muted">
                        Für dieses Pokémon ist noch keine Bezugsquelle hinterlegt.
                        @if ($pokemon->onlyViaEvolution() && $pokemon->sourcePokemon)
                            Es wird über {{ $pokemon->sourcePokemon->name_de }} beschafft – siehe
                            Entwicklungslinie rechts.
                        @endif
                    </p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="border-b-2 border-dex-border text-[10px] uppercase text-dex-muted">
                                <tr>
                                    <th class="py-2 pr-3">Spiel</th>
                                    <th class="py-2 pr-3">Methode</th>
                                    <th class="py-2 pr-3">Ort / Detail</th>
                                    <th class="py-2">Weg nach HOME</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dex-border/40">
                                @foreach ($quellen as $quelle)
                                    <tr @class(['opacity-50' => $quelle->event_expired])>
                                        <td class="py-2 pr-3">
                                            <span class="font-semibold">{{ $quelle->game->name_de }}</span>
                                            <span class="block text-[10px] text-dex-muted">
                                                {{ $quelle->game->platform->label() }}
                                            </span>
                                        </td>
                                        <td class="py-2 pr-3">
                                            {{ $quelle->method->label() }}
                                            @if ($quelle->event_expired)
                                                <span class="block text-[10px] text-dex-danger">Event vorbei</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-3 text-dex-muted">{{ $quelle->location_detail ?: '—' }}</td>
                                        <td class="py-2 text-[11px]">
                                            @if ($quelle->game->home_compatible && ! $quelle->game->bank_only)
                                                <span class="text-dex-success">direkt an HOME</span>
                                            @elseif ($quelle->game->bank_only)
                                                <span class="text-dex-danger">über Pokémon Bank</span>
                                            @else
                                                <span class="text-dex-muted">kein Transferweg</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ── Pokémon GO (spec.md 2.4) ────────────────────────────────── --}}
            <div class="pixel-panel p-4">
                <h2 class="font-pixel text-xs uppercase text-dex-accent">Pokémon GO</h2>

                @if ($pokemon->goAvailability)
                    @php $go = $pokemon->goAvailability; @endphp
                    <p class="mt-3 text-sm">{{ $go->method->label() }}</p>

                    @if ($go->isRegionExclusive())
                        @php
                            $eigeneRegion = auth()->check()
                                ? auth()->user()->settingsOrDefault()->go_region
                                : null;
                            $verfuegbar = $go->availableInRegion($eigeneRegion);
                        @endphp

                        <p class="mt-2 text-sm">
                            <span class="text-dex-muted">Regional exklusiv:</span> {{ $go->regionLabels() }}
                        </p>

                        @auth
                            <p class="mt-2 text-sm {{ $verfuegbar ? 'text-dex-success' : 'text-dex-danger' }}">
                                {{ $verfuegbar
                                    ? 'Spawnt in Deiner Region ('.$eigeneRegion->label().').'
                                    : 'Spawnt nicht in Deiner Region ('.$eigeneRegion->label().') – Reise oder GO-Tausch nötig.' }}
                            </p>
                        @endauth
                    @else
                        <p class="mt-2 text-sm text-dex-success">Weltweit verfügbar.</p>
                    @endif

                    @if ($go->note)
                        <p class="mt-2 text-xs text-dex-muted">{{ $go->note }}</p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-dex-muted">
                        Keine GO-Daten hinterlegt. Die Prioritäts-Engine rechnet deshalb
                        vorsichtshalber ohne GO-Rettungsweg.
                    </p>
                @endif
            </div>

            {{-- ── Entwicklungslinie und Mehrfach-Fang (spec.md 2.1, 2.8) ──── --}}
            <div class="pixel-panel p-4">
                <h2 class="font-pixel text-xs uppercase text-dex-accent">Entwicklungslinie</h2>

                @if ($pokemon->evolution_summary_de)
                    <p class="mt-2 text-sm text-dex-muted">
                        Entwickelt sich {{ $pokemon->evolution_summary_de }}.
                    </p>
                @endif

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($linie as $stufe)
                        <a href="{{ route('pokedex.show', $stufe) }}"
                           @class([
                               'pixel-panel-soft flex items-center gap-2 px-3 py-2 text-xs transition-colors hover:border-dex-accent',
                               'border-dex-accent' => $stufe->is($pokemon),
                           ])>
                            <span class="font-dex text-dex-muted">{{ $stufe->dex_label }}</span>
                            <span>{{ $stufe->name_de }}</span>
                            @unless ($stufe->obtainable_directly)
                                <span class="text-[10px] text-dex-muted" title="Nur durch Entwicklung erreichbar">⟳</span>
                            @endunless
                        </a>
                    @endforeach
                </div>

                @unless ($fangplan->isEmpty())
                    <div class="pixel-panel-soft mt-4 p-3">
                        <h3 class="font-pixel text-[10px] uppercase text-dex-muted">
                            Mehrfach-Fang-Empfehlung
                        </h3>

                        @if ($fangplan->steps)
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($fangplan->sentences() as $satz)
                                    <li>{{ $satz }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs text-dex-muted">
                                Insgesamt {{ $fangplan->totalCatches() }} Exemplare fangen, dann ist die
                                Linie komplett.
                            </p>
                        @endif

                        @if ($fangplan->unreachable)
                            <p class="mt-2 text-xs text-dex-danger">
                                Ohne hinterlegten Fundweg:
                                {{ collect($fangplan->unreachable)->pluck('name_de')->join(', ') }}
                            </p>
                        @endif
                    </div>
                @endunless
            </div>
        </div>
    </div>
</x-app-layout>
