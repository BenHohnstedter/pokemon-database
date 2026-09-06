@props([
    'row',          // object{form, owned, ownedShiny, favourite, priority}
    'interactive' => true,
])

@php
    $form = $row->form;
    $pokemon = $form->pokemon;
    $bild = $form->displayImage();
@endphp

{{-- Eine Karte im Pokédex-Raster (spec.md 2.5, 5) --}}
{{--
    Achtung: die Klassen müssen in EIN class-Attribut. Ein zweites daneben
    (etwa aus @class) wird vom Browser stillschweigend verworfen – dann fehlt
    der Karte in der Gast-Ansicht die komplette Basis-Optik.
--}}
<div
    @if ($interactive)
        x-data="dexToggle({
            url: '{{ route('collection.toggle', $form) }}',
            token: '{{ csrf_token() }}',
            owned: {{ $row->owned ? 'true' : 'false' }},
            shiny: {{ $row->ownedShiny ? 'true' : 'false' }},
            favourite: {{ $row->favourite ? 'true' : 'false' }},
            sound: {{ auth()->check() && auth()->user()->settingsOrDefault()->sound_effects_enabled ? 'true' : 'false' }},
        })"
        :class="{ 'dex-card--owned': owned, 'dex-card--missing': ! owned, 'animate-pixel-pop': pop }"
        class="dex-card"
    @else
        @class([
            'dex-card',
            'dex-card--owned' => $row->owned,
            'dex-card--missing' => ! $row->owned,
        ])
    @endif
>
    <div class="flex w-full items-start justify-between gap-1">
        <span class="font-dex text-xs text-dex-muted">{{ $pokemon->dex_label }}</span>

        @if ($interactive)
            <button type="button"
                    x-on:click.stop="umschalten('favorit')"
                    class="text-xs leading-none transition-transform hover:scale-125"
                    :aria-pressed="favourite ? 'true' : 'false'"
                    :title="favourite ? 'Von der Wunschliste nehmen' : 'Auf die Wunschliste setzen'">
                <span x-show="favourite" class="text-dex-accent">★</span>
                <span x-show="! favourite" class="text-dex-muted">☆</span>
                <span class="sr-only">Wunschliste</span>
            </button>
        @elseif ($row->favourite)
            <span class="text-xs text-dex-accent" title="Auf der Wunschliste">★</span>
        @endif
    </div>

    <a href="{{ route('pokedex.show', $pokemon) }}" class="block">
        @if ($bild)
            <img src="{{ $bild }}"
                 alt="{{ $form->name_de }}"
                 loading="lazy"
                 width="96"
                 height="96"
                 class="pixelated mx-auto h-20 w-20 object-contain">
        @else
            <div class="mx-auto flex h-20 w-20 items-center justify-center text-2xl text-dex-muted"
                 aria-hidden="true">?</div>
        @endif
    </a>

    <a href="{{ route('pokedex.show', $pokemon) }}"
       class="text-xs font-semibold leading-tight hover:text-dex-accent">
        {{ $form->name_de }}
    </a>

    {{-- Die Form kann eigene Typen haben: Alola-Vulpix ist Eis, nicht Feuer. --}}
    <div class="flex flex-wrap justify-center gap-0.5">
        @foreach ($form->displayTypes() as $type)
            <x-type-badge :type="$type" />
        @endforeach
    </div>

    <div class="mt-0.5 flex flex-wrap justify-center gap-1">
        <x-priority-badge :priority="$row->priority->level" :show-label="false" />

        {{-- Hängt an der Bank-Frist, ist aber nicht 🔴 – sonst stünde es doppelt. --}}
        @if ($row->priority->affectedByBankDeadline() && ! $row->priority->isUrgent())
            <span class="inline-flex items-center border border-dex-danger/60 bg-dex-danger/10 px-1.5 py-0.5 text-[10px] uppercase text-dex-danger"
                  title="Der Weg nach HOME führt über Pokémon Bank – vor der Abschaltung übertragen.">
                ⏳<span class="sr-only">Bank-Deadline</span>
            </span>
        @endif
    </div>

    @if ($interactive)
        <div class="mt-1 flex w-full gap-1">
            <button type="button"
                    x-on:click="umschalten('normal')"
                    :disabled="busy"
                    dusk="toggle-{{ $form->id }}"
                    class="pixel-button-ghost flex-1 justify-center py-1 text-[10px] disabled:opacity-50"
                    :class="owned ? 'border-dex-success text-dex-success' : ''">
                <span x-text="owned ? '✔ Besitze ich' : 'Fehlt mir'"></span>
            </button>

            <button type="button"
                    x-on:click="umschalten('shiny')"
                    :disabled="busy"
                    class="pixel-button-ghost justify-center px-2 py-1 text-[10px] disabled:opacity-50"
                    :class="shiny ? 'border-dex-accent text-dex-accent' : ''"
                    :title="shiny ? 'Shiny im Bestand' : 'Shiny fehlt noch'">
                ✨<span class="sr-only">Shiny umschalten</span>
            </button>
        </div>
    @endif
</div>
