<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pokémon-Bank-Abschaltung
    |--------------------------------------------------------------------------
    |
    | Stichtag für die Prioritäts-Engine und das Countdown-Widget (spec.md 2.7).
    | Quellen nennen den 26./27.02.2027 je nach Zeitzone – wir rechnen mit dem
    | frühesten Zeitpunkt, damit die App eher zu früh als zu spät warnt.
    |
    */
    'bank_shutdown_at' => env('POKEMON_BANK_SHUTDOWN_AT', '2027-02-26 23:59:59'),

    /*
    |--------------------------------------------------------------------------
    | Hintergrundmusik
    |--------------------------------------------------------------------------
    |
    | Ruhige Stücke zum Durchschalten, standardmäßig aus (spec.md 2.9).
    |
    | Ausschließlich frei lizenziertes Material: Die Original-Soundtracks der
    | Spiele gehören Nintendo/Game Freak/The Pokémon Company und dürfen hier
    | nicht liegen. Alle Stücke unten stehen unter CC0 und stammen von
    | OpenGameArt; Herkunft und Urheber siehe public/audio/HERKUNFT.md.
    |
    | Weitere Stücke: Datei nach public/audio legen, hier eintragen, in
    | HERKUNFT.md dokumentieren. Mehr ist nicht nötig.
    |
    */
    'music' => [
        [
            'datei' => 'audio/ruhig-zuhause.ogg',
            'titel' => 'A Place I Call Home',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/ruhig-friedliche-tage.ogg',
            'titel' => 'Peaceful Days',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/stadt-heimatstadt.ogg',
            'titel' => 'Home Town',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/town-theme.mp3',
            'titel' => 'Town Theme',
            'urheber' => 'cynicmusic',
        ],
        [
            'datei' => 'audio/ruhig-sommererinnerungen.ogg',
            'titel' => 'Summer Memories',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/stadt-stillstand.ogg',
            'titel' => 'Where Time Stands Still',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/neue-stadt.mp3',
            'titel' => 'A New Town',
            'urheber' => 'cynicmusic',
        ],
        [
            'datei' => 'audio/ruhig-kindheitsfreunde.ogg',
            'titel' => 'Childhood Friends',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/stadt-sonnenkueste.ogg',
            'titel' => 'Sunshine Coast',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/ruhig-sandburgen.ogg',
            'titel' => 'Sand Castles',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/stadtbummel.ogg',
            'titel' => 'Exploring Town',
            'urheber' => 'Julie Damsgaard',
        ],
        [
            'datei' => 'audio/ruhig-unschuld.ogg',
            'titel' => 'Innocence',
            'urheber' => 'Juhani Junkala',
        ],
        [
            'datei' => 'audio/stadt-basar.ogg',
            'titel' => 'Bazaar',
            'urheber' => 'Juhani Junkala',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PokéAPI
    |--------------------------------------------------------------------------
    */
    'pokeapi' => [
        'base_url' => rtrim(env('POKEAPI_BASE_URL', 'https://pokeapi.co/api/v2'), '/'),
        'timeout' => (int) env('POKEAPI_TIMEOUT', 30),
        'retries' => (int) env('POKEAPI_RETRIES', 3),
        'retry_delay_ms' => (int) env('POKEAPI_RETRY_DELAY', 500),
        // Antworten werden auf Platte gecacht, damit ein erneuter Import nicht
        // wieder tausende Requests auslöst (spec.md 4).
        'cache_enabled' => (bool) env('POKEAPI_CACHE', true),
        'cache_dir' => storage_path('app/pokeapi-cache'),
        'cache_ttl_days' => (int) env('POKEAPI_CACHE_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anzeige
    |--------------------------------------------------------------------------
    */
    'per_page' => 60,

    /*
    |--------------------------------------------------------------------------
    | XP-Vergabe (spec.md 2.9)
    |--------------------------------------------------------------------------
    */
    'xp' => [
        'base' => 10,
        'difficulty_bonus' => [
            'leicht' => 0,
            'mittel' => 5,
            'schwer' => 15,
            'sehr_schwer' => 30,
        ],
        'legendary_bonus' => 25,
        'mythical_bonus' => 40,
        'shiny_multiplier' => 3,
        'regional_bonus' => 5,
    ],
];
