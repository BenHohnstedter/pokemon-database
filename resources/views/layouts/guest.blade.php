{{--
    Anmeldung, Registrierung, Passwort-Reset. Bewusst dieselbe Optik wie die
    App dahinter (spec.md 5): Wer sich einloggt, soll nicht erst eine weiße
    Seite und danach ein dunkles Programm sehen.

    Ein Theme aus den Nutzereinstellungen gibt es hier noch nicht — an dieser
    Stelle ist niemand angemeldet, also läuft alles über das Standard-Theme.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="default">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>{{ $title ?? config('app.name') }}</title>

    {{-- Icon in Tab und Lesezeichenleiste. Herkunft siehe public/icons/HERKUNFT.md. --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/pokeball.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="text-center">
            <span class="font-pixel text-lg text-dex-accent">{{ config('app.name') }}</span>
            <span class="mt-2 block text-xs uppercase tracking-widest text-dex-muted">
                Pokémon-Sammlungs-Tracker
            </span>
        </a>

        <div class="pixel-panel mt-8 w-full max-w-md px-6 py-6">
            {{ $slot }}
        </div>

        <a href="{{ route('pokedex.index') }}"
           class="mt-6 text-xs uppercase tracking-wide text-dex-muted underline hover:text-dex-accent">
            Ohne Konto im Pokédex stöbern
        </a>
    </div>
</body>
</html>
