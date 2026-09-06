@php
    $dexLabel = $basis->caption();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $trainer->name }} hat {{ $dexLabel }}">
    <meta name="theme-color" content="#0f172a">
    <title>{{ $trainer->name }} – {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

    <header class="border-b-4 border-dex-border bg-dex-panel">
        <div class="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-4">
            <a href="{{ route('home') }}" class="font-pixel text-xs text-dex-accent sm:text-sm">
                DEX&#8209;RESCUE
            </a>
            <a href="{{ route('pokedex.index') }}" class="pixel-button-ghost">Pokédex ansehen</a>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">

        <div class="pixel-panel scanlines relative overflow-hidden p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-pixel text-[10px] uppercase text-dex-muted">Trainer</p>
                    <h1 class="mt-1 font-pixel text-sm sm:text-lg">{{ $trainer->name }}</h1>
                    <p class="mt-2 text-xs text-dex-muted">
                        Dabei seit {{ $trainer->created_at->format('m/Y') }}
                    </p>
                </div>

                <div class="text-right">
                    <p class="font-pixel text-[10px] uppercase text-dex-muted">Level</p>
                    <p class="font-dex text-4xl text-dex-accent">{{ $trainer->level() }}</p>
                    <p class="text-xs text-dex-muted">
                        {{ number_format($trainer->xp, 0, ',', '.') }} XP
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <x-progress-bar :bar="$basis" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-progress-bar :bar="$regional" compact noun="Formen" />
                    <x-progress-bar :bar="$shiny" compact noun="Shinys" />
                </div>
            </div>
        </div>

        <section class="pixel-panel mt-6 p-5">
            <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">Nach Generation</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($generationen as $bar)
                    <x-progress-bar :bar="$bar" compact />
                @empty
                    <p class="text-sm text-dex-muted">Noch keine Daten.</p>
                @endforelse
            </div>
        </section>

        @if ($orden->isNotEmpty())
            <section class="pixel-panel mt-6 p-5">
                <h2 class="mb-4 font-pixel text-xs uppercase text-dex-accent">
                    Orden ({{ $orden->count() }})
                </h2>

                <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($orden as $achievement)
                        <li class="pixel-panel-soft border-dex-accent/60 p-2 text-center"
                            title="{{ $achievement->description }}">
                            <span class="block text-xl" aria-hidden="true">{{ $achievement->icon }}</span>
                            <span class="mt-1 block text-[10px] leading-tight">{{ $achievement->name }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="mt-6 text-center text-xs text-dex-muted">
            Willst Du Deinen eigenen Fortschritt tracken?
            <a href="{{ route('register') }}" class="underline">Hier geht's los.</a>
        </p>
    </main>

    <footer class="mt-10 border-t-2 border-dex-border/50 px-4 py-6 text-center text-xs text-dex-muted">
        <p>
            Privates, nicht-kommerzielles Fan-Projekt. Pokémon-Namen, -Sprites und -Artworks
            gehören Nintendo/Game&nbsp;Freak/The&nbsp;Pokémon&nbsp;Company.
        </p>
    </footer>
</body>
</html>
