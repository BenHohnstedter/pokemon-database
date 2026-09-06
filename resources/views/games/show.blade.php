<x-app-layout>
    <x-slot name="title">{{ $game->name_de }} – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="font-pixel text-[10px] uppercase text-dex-muted">
                    {{ $game->platform->label() }}
                    @if ($besitztSpiel) · <span class="text-dex-success">Dein Spiel</span> @endif
                </p>
                <h1 class="mt-1 font-pixel text-sm text-dex-accent sm:text-base">{{ $game->name_de }}</h1>
                <p class="mt-2 text-sm text-dex-muted">
                    {{ $nurOffene ? 'Dir fehlen hier noch' : 'Hier gibt es insgesamt' }}
                    <strong class="font-dex text-base text-dex-text">{{ $zeilen->count() }}</strong>
                    von {{ $gesamtImSpiel }} Pokémon.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('games.show', [$game, 'offen' => $nurOffene ? 0 : 1, 'formen' => $zeigtFormen ? 1 : 0]) }}"
                   dusk="spiel-offen-umschalten"
                   class="pixel-button-ghost">
                    {{ $nurOffene ? 'Alle anzeigen' : 'Nur fehlende' }}
                </a>
                <a href="{{ route('games.show', [$game, 'offen' => $nurOffene ? 1 : 0, 'formen' => $zeigtFormen ? 0 : 1]) }}"
                   dusk="spiel-formen-umschalten"
                   @class(['pixel-button-ghost', 'border-dex-accent text-dex-accent' => $zeigtFormen])>
                    {{ $zeigtFormen ? 'Nur normale Formen' : 'Regionalformen mit anzeigen' }}
                </a>
                <a href="{{ route('games.index') }}" class="pixel-button-ghost">← Alle Spiele</a>
            </div>
        </div>
    </x-slot>

    {{-- Wie kommt das Gefangene nach HOME? Der wichtigste Kontext beim Abarbeiten. --}}
    <div @class([
        'pixel-panel mb-6 p-4 text-sm',
        'border-dex-danger' => $game->bank_only,
        'border-dex-success/70' => $game->home_compatible && ! $game->bank_only,
    ])>
        @if ($game->home_compatible && ! $game->bank_only)
            <p class="text-dex-success">
                Dieses Spiel hängt direkt an Pokémon HOME – keine Frist, kein Umweg.
            </p>
        @elseif ($game->bank_only)
            <p class="text-dex-danger">
                Alles aus diesem Spiel muss über <strong>Pokémon Bank</strong> nach HOME –
                und das geht nur noch bis zum 26.02.2027.
            </p>
        @else
            <p class="text-dex-muted">
                Für diesen Titel gibt es keinen Transferweg nach HOME.
            </p>
        @endif

        @if ($game->note)
            <p class="mt-1 text-xs text-dex-muted">{{ $game->note }}</p>
        @endif

        @if ($formenOhneFundort)
            {{-- Die Fundorte der PokéAPI hängen an der Art, nicht an der Form.
                 Eine Quelle "Vulpix in Rot" auf das Alola-Vulpix zu übertragen
                 wäre eine Behauptung, die die Daten nicht hergeben. --}}
            <p class="mt-2 text-xs text-dex-muted">
                Für Sonderformen ist in diesem Spiel kein eigener Fundort hinterlegt –
                die Liste zeigt deshalb weiterhin nur die normalen Formen. Deine
                Regionalformen findest Du im
                <a href="{{ route('pokedex.index', ['formen' => 1]) }}" class="underline">Pokédex</a>.
            </p>
        @endif

        @unless ($besitztSpiel)
            <p class="mt-2 text-xs text-dex-muted">
                Du hast dieses Spiel nicht eingetragen.
                <a href="{{ route('settings.edit') }}" class="underline">In den Einstellungen ändern</a>.
            </p>
        @endunless
    </div>

    @if ($zeilen->isEmpty())
        <div class="pixel-panel p-8 text-center">
            @if ($gesamtImSpiel === 0)
                {{-- Nicht mit "nichts mehr offen" verwechseln: für Pokémon GO und
                     einige Altspiele sind schlicht keine Fundorte hinterlegt. --}}
                <p class="font-pixel text-xs text-dex-muted">
                    Für dieses Spiel sind keine Fundorte hinterlegt.
                </p>
            @else
                <p class="font-pixel text-xs text-dex-success">Hier fehlt Dir nichts mehr. 🎉</p>
            @endif
        </div>
    @else
        <div class="pixel-panel pixel-scroll-x p-4">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="border-b-2 border-dex-border text-[10px] uppercase text-dex-muted">
                    <tr>
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">Pokémon</th>
                        <th class="py-2 pr-3">Methode</th>
                        <th class="py-2 pr-3">Ort / Detail</th>
                        <th class="py-2 pr-3">Stufe</th>
                        <th class="py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-dex-border/40">
                    @foreach ($zeilen as $row)
                        <tr @class(['opacity-60' => $row->owned])>
                            <td class="py-2 pr-3 font-dex text-dex-muted">
                                {{ $row->form->pokemon->dex_label }}
                            </td>
                            <td class="py-2 pr-3">
                                <a href="{{ route('pokedex.show', $row->form->pokemon) }}"
                                   class="flex items-center gap-2 hover:text-dex-accent">
                                    @if ($row->form->displayImage())
                                        <img src="{{ $row->form->displayImage() }}"
                                             alt="" aria-hidden="true" loading="lazy"
                                             width="32" height="32"
                                             class="pixelated h-8 w-8 shrink-0 object-contain">
                                    @endif
                                    <span>{{ $row->form->name_de }}</span>
                                </a>
                            </td>
                            <td class="py-2 pr-3">{{ $row->quelle->method->label() }}</td>
                            <td class="py-2 pr-3 text-dex-muted">
                                {{ $row->quelle->location_detail ?: '—' }}
                            </td>
                            <td class="py-2 pr-3">
                                <x-priority-badge :priority="$row->priority->level" :show-label="false" />
                            </td>
                            <td class="py-2">
                                @auth
                                    <div x-data="dexToggle({
                                            url: '{{ route('collection.toggle', $row->form) }}',
                                            token: '{{ csrf_token() }}',
                                            owned: {{ $row->owned ? 'true' : 'false' }},
                                            shiny: {{ $row->ownedShiny ? 'true' : 'false' }},
                                            favourite: {{ $row->favourite ? 'true' : 'false' }},
                                            sound: {{ auth()->user()->settingsOrDefault()->sound_effects_enabled ? 'true' : 'false' }},
                                         })">
                                        <button type="button"
                                                x-on:click="umschalten('normal')"
                                                :disabled="busy"
                                                dusk="spiel-toggle-{{ $row->form->id }}"
                                                class="pixel-button-ghost py-1 text-[10px] disabled:opacity-50"
                                                :class="owned ? 'border-dex-success text-dex-success' : ''">
                                            <span x-text="owned ? '✔ Habe ich' : 'Als gefangen'"></span>
                                        </button>
                                    </div>
                                @endauth
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
