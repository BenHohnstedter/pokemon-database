@php
    $deadline = app(\App\Services\BankDeadline::class);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tracke, welche Pokémon Dir in Pokémon HOME noch fehlen – und was davon vor der Abschaltung von Pokémon Bank dringend ist.">
    <meta name="theme-color" content="#0f172a">
    <title>{{ config('app.name') }} – Pokémon-Sammlungs-Tracker</title>

    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

    <header class="border-b-4 border-dex-border bg-dex-panel">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <span class="font-pixel text-xs text-dex-accent sm:text-sm">DEX&#8209;RESCUE</span>

            <nav class="flex gap-2">
                <a href="{{ route('pokedex.index') }}" class="pixel-button-ghost">Pokédex</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="pixel-button">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="pixel-button-ghost">Anmelden</a>
                    <a href="{{ route('register') }}" class="pixel-button">Registrieren</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-12">

        <section class="text-center">
            <h1 class="font-pixel text-lg leading-relaxed text-dex-accent sm:text-2xl">
                Welche Pokémon<br>musst Du jetzt retten?
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-dex-muted">
                Dex&#8209;Rescue zeigt Dir nicht nur, was in Deiner Pokémon-HOME-Sammlung fehlt,
                sondern vor allem, <strong class="text-dex-text">was davon eine tickende Uhr hat</strong>.
                Denn mit dem Ende von Pokémon Bank schließt sich der Transferweg für viele
                ältere Pokémon für immer.
            </p>

            <div class="pixel-panel scanlines relative mx-auto mt-8 max-w-md p-5">
                <p class="font-pixel text-[10px] uppercase text-dex-muted">Pokémon Bank</p>
                <p class="mt-2 font-pixel text-xl text-dex-danger">{{ $deadline->humanLabel() }}</p>
                <p class="mt-2 text-xs text-dex-muted">
                    Abschaltung am {{ $deadline->shutdownAt()->format('d.m.Y') }}
                </p>
                <div class="dex-progress mt-3">
                    <span style="width: {{ $deadline->elapsedPercent() }}%"></span>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @guest
                    <a href="{{ route('register') }}" class="pixel-button">Kostenlos loslegen</a>
                @endguest
                <a href="{{ route('pokedex.index') }}" class="pixel-button-ghost">Pokédex ansehen</a>
            </div>
        </section>

        <section class="mt-16 grid gap-4 sm:grid-cols-3">
            <div class="pixel-panel p-5">
                <h2 class="font-pixel text-[10px] uppercase text-dex-accent">Priorisiert</h2>
                <p class="mt-3 text-sm text-dex-muted">
                    Sechs Dringlichkeitsstufen von „kein Handlungsdruck" bis
                    „🔴 vor der Bank-Abschaltung erledigen" – berechnet aus Deinem Spielebesitz,
                    Deiner GO-Region und den echten Transferwegen nach HOME.
                </p>
            </div>

            <div class="pixel-panel p-5">
                <h2 class="font-pixel text-[10px] uppercase text-dex-accent">Konkret</h2>
                <p class="mt-3 text-sm text-dex-muted">
                    Pro Pokémon: welches Spiel, welche Route, welche Konsole. Inklusive
                    Mehrfach-Fang-Empfehlung für Entwicklungsreihen – damit Du weißt, wie oft
                    Du die Vorstufe fangen musst.
                </p>
            </div>

            <div class="pixel-panel p-5">
                <h2 class="font-pixel text-[10px] uppercase text-dex-accent">Motivierend</h2>
                <p class="mt-3 text-sm text-dex-muted">
                    Trainer-Level, Orden, Fortschrittsbalken je Region und Typ, Bestenliste
                    unter Freunden – und eine Trainerkarte im GameBoy-Stil.
                </p>
            </div>
        </section>
    </main>

    <footer class="border-t-2 border-dex-border/50 px-4 py-8 text-center text-xs text-dex-muted">
        <p>
            Privates, nicht-kommerzielles Fan-Projekt. Pokémon-Namen, -Sprites und -Artworks
            gehören Nintendo/Game&nbsp;Freak/The&nbsp;Pokémon&nbsp;Company.
        </p>
        <p class="mt-1">
            Daten über <a class="underline" href="https://pokeapi.co/" rel="noopener">PokéAPI</a>
            und <a class="underline" href="https://bulbapedia.bulbagarden.net/" rel="noopener">Bulbapedia</a>.
        </p>
    </footer>
</body>
</html>
