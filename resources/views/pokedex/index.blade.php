@php
    use App\Support\PokedexFilter;
@endphp

<x-app-layout>
    <x-slot name="title">Pokédex – {{ config('app.name') }}</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-pixel text-sm text-dex-accent sm:text-base">Pokédex</h1>
                <p class="mt-2 text-sm text-dex-muted">
                    {{ number_format($gefunden, 0, ',', '.') }} von
                    {{ number_format($gesamt, 0, ',', '.') }} Einträgen
                    @if ($filter->isActive())
                        · <a href="{{ route('pokedex.index') }}" class="underline">Filter zurücksetzen</a>
                    @endif
                </p>
            </div>

            @auth
                <a href="{{ route('collection.bulk') }}" class="pixel-button-ghost">
                    Masseneingabe
                </a>
            @endauth
        </div>
    </x-slot>

    {{-- ── Filterleiste (spec.md 2.5, 2.7, 2.8) ───────────────────────────── --}}
    <form method="GET" action="{{ route('pokedex.index') }}" class="pixel-panel mb-6 p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="q" class="mb-1 block text-xs uppercase text-dex-muted">Suche</label>
                <input type="search" id="q" name="q" value="{{ $filter->search }}"
                       placeholder="Name oder Dex-Nummer" class="pixel-input">
            </div>

            <div>
                <label for="status" class="mb-1 block text-xs uppercase text-dex-muted">Status</label>
                <select id="status" name="status" class="pixel-input">
                    @foreach (PokedexFilter::statusOptions() as $wert => $label)
                        <option value="{{ $wert }}" @selected($filter->status === $wert)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="sortierung" class="mb-1 block text-xs uppercase text-dex-muted">Sortierung</label>
                <select id="sortierung" name="sortierung" class="pixel-input">
                    @foreach (PokedexFilter::sortOptions() as $wert => $label)
                        <option value="{{ $wert }}" @selected($filter->sort === $wert)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="gen" class="mb-1 block text-xs uppercase text-dex-muted">Generation</label>
                <select id="gen" name="gen" class="pixel-input">
                    <option value="">Alle</option>
                    @foreach ($generationen as $generation)
                        <option value="{{ $generation }}" @selected($filter->generation === (int) $generation)>
                            Gen {{ $generation }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="typ" class="mb-1 block text-xs uppercase text-dex-muted">Typ</label>
                <select id="typ" name="typ" class="pixel-input">
                    <option value="">Alle</option>
                    @foreach ($typen as $typ)
                        <option value="{{ $typ->slug }}" @selected($filter->type === $typ->slug)>
                            {{ $typ->name_de }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="prio" class="mb-1 block text-xs uppercase text-dex-muted">Dringlichkeit</label>
                <select id="prio" name="prio" class="pixel-input">
                    <option value="">Alle</option>
                    @foreach ($prioritaeten as $stufe)
                        <option value="{{ $stufe->value }}" @selected($filter->priority === $stufe)>
                            {{ $stufe->icon() }} {{ $stufe->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="schwierigkeit" class="mb-1 block text-xs uppercase text-dex-muted">Schwierigkeit</label>
                <select id="schwierigkeit" name="schwierigkeit" class="pixel-input">
                    <option value="">Alle</option>
                    @foreach ($schwierigkeiten as $stufe)
                        <option value="{{ $stufe->value }}" @selected($filter->difficulty === $stufe)>
                            {{ $stufe->label() }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            {{-- Der kombinierte Filter aus spec.md 2.8 --}}
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="unerreichbar" value="1" @checked($filter->onlyUnreachable)
                       class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                Nur was ich mit meinen Spielen nicht bekommen kann
            </label>

            {{-- Alles mit Bank-Frist, auch das selbst Holbare (spec.md 2.7) --}}
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="deadline" value="1" @checked($filter->onlyBankDeadline)
                       class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                Nur was an der Bank-Deadline hängt
            </label>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="formen" value="1" @checked($zeigtFormen)
                       class="border-2 border-dex-border bg-dex-bg text-dex-accent focus:ring-0">
                Regionalformen mit anzeigen
            </label>

            <label for="pro_seite" class="flex items-center gap-2 text-sm">
                <span class="text-dex-muted">Pro Seite</span>
                <select id="pro_seite" name="pro_seite" class="pixel-input w-auto py-1">
                    @foreach (PokedexFilter::PER_PAGE_OPTIONS as $wert)
                        <option value="{{ $wert }}" @selected($proSeite === $wert)>{{ $wert }}</option>
                    @endforeach
                </select>
            </label>

            <div class="ml-auto flex gap-2">
                <button type="submit" class="pixel-button">Filtern</button>
                @if ($filter->isActive())
                    <a href="{{ route('pokedex.index') }}" class="pixel-button-ghost">Zurücksetzen</a>
                @endif
            </div>
        </div>
    </form>

    {{-- ── Schnellzugriff auf die Dringlichkeitsstufen ─────────────────────── --}}
    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ($prioritaeten as $stufe)
            <a href="{{ route('pokedex.index', array_merge($filter->toQuery(), ['prio' => $stufe->value, 'page' => null])) }}"
               @class([
                   'inline-flex items-center gap-1 border px-2 py-1 text-[10px] uppercase tracking-wide transition-opacity',
                   $stufe->badgeClasses(),
                   'opacity-100 ring-2 ring-dex-accent' => $filter->priority === $stufe,
                   'opacity-70 hover:opacity-100' => $filter->priority !== $stufe,
               ])>
                {{ $stufe->icon() }} {{ $stufe->label() }}
            </a>
        @endforeach
    </div>

    {{-- ── Raster ─────────────────────────────────────────────────────────── --}}
    @if ($paginator->isEmpty())
        <div class="pixel-panel p-8 text-center">
            <p class="font-pixel text-xs text-dex-muted">Nichts gefunden.</p>
            <p class="mt-3 text-sm text-dex-muted">
                @if ($gesamt === 0)
                    Die Datenbank ist noch leer – führe
                    <code class="bg-dex-bg px-1">php artisan pokedex:import</code> aus.
                @else
                    Versuch es mit weniger Filtern.
                @endif
            </p>
        </div>
    @else
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
            @foreach ($paginator as $row)
                <x-pokemon-card :row="$row" :interactive="auth()->check()" />
            @endforeach
        </div>

        <div class="mt-8">
            {{ $paginator->links() }}
        </div>
    @endif
</x-app-layout>
