@php
    /** @var \App\Models\UserSetting|null $dexSettings */
    $dexSettings = auth()->check() ? auth()->user()->settingsOrDefault() : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $dexSettings?->theme ?? 'default' }}"
      data-reduce-motion="{{ $dexSettings?->reduce_motion ? 'true' : 'false' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- PWA: installierbar auf dem Homescreen (spec.md 6, 7) --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    {{-- Icon in Tab und Lesezeichenleiste. Herkunft siehe public/icons/HERKUNFT.md. --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/pokeball.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased"
      x-data="dexAudio({
          music: {{ $dexSettings?->music_enabled ? 'true' : 'false' }},
          volume: {{ $dexSettings?->music_volume ?? 35 }},
          tracks: {{ Js::from(collect(config('pokedex.music', []))->map(fn (array $s) => [
              'datei' => asset($s['datei']),
              'titel' => $s['titel'],
              'urheber' => $s['urheber'],
          ])) }}
      })">

    <a href="#inhalt"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 pixel-button">
        Zum Inhalt springen
    </a>

    @include('layouts.navigation')

    @isset($header)
        <header class="border-b-2 border-dex-border/60 bg-dex-panel/40">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    <main id="inhalt" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="pixel-panel mb-5 border-dex-success/70 px-4 py-3 text-sm" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-10 border-t-2 border-dex-border/50 px-4 py-6 text-center text-xs text-dex-muted">
        <p>
            Privates, nicht-kommerzielles Fan-Projekt. Pokémon-Namen, -Sprites und -Artworks
            gehören Nintendo/Game&nbsp;Freak/The&nbsp;Pokémon&nbsp;Company.
        </p>
        <p class="mt-1">
            Daten über <a class="underline" href="https://pokeapi.co/" rel="noopener">PokéAPI</a>,
            ergänzt um Angaben aus
            <a class="underline" href="https://bulbapedia.bulbagarden.net/" rel="noopener">Bulbapedia</a>
            (CC&nbsp;BY-NC-SA).
        </p>
    </footer>

    {{-- Fehlermeldungen aus den Alpine-Toggles --}}
    <div x-data="{ meldung: '', sichtbar: false }"
         x-on:dex:fehler.window="meldung = $event.detail; sichtbar = true; setTimeout(() => sichtbar = false, 5000)"
         x-show="sichtbar"
         x-cloak
         class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2"
         role="alert">
        <div class="pixel-panel border-dex-danger px-4 py-2 text-sm" x-text="meldung"></div>
    </div>

    {{-- Neu freigeschaltete Orden (spec.md 2.9) --}}
    <div x-data="{ orden: [] }"
         x-on:dex:orden.window="orden = $event.detail; setTimeout(() => orden = [], 6000)"
         x-show="orden.length > 0"
         x-cloak
         class="fixed bottom-4 right-4 z-50 space-y-2"
         role="status"
         aria-live="polite">
        <template x-for="o in orden" :key="o.name">
            <div class="pixel-panel border-dex-accent px-4 py-3 text-sm">
                <p class="font-pixel text-[10px] uppercase text-dex-accent">Neuer Orden</p>
                <p class="mt-1">
                    <span x-text="o.icon" aria-hidden="true"></span>
                    <span x-text="o.name"></span>
                </p>
                <p class="text-xs text-dex-muted">
                    +<span x-text="o.xp"></span> XP
                </p>
            </div>
        </template>
    </div>
</body>
</html>
