<?php

/**
 * PokéAPI-Import (spec.md 4).
 *
 * Läuft gegen gefälschte HTTP-Antworten – Tests dürfen nicht von einer
 * erreichbaren API abhängen, und die PokéAPI bittet ausdrücklich darum,
 * sie nicht unnötig zu belasten.
 */

use App\Enums\FormType;
use App\Enums\Region;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    resetDexSequence();

    // Ohne das würde ein Test die auf Platte gecachte Antwort des vorherigen
    // lesen – alle Tests fragen dieselbe URL /evolution-chain/1 ab.
    config()->set('pokedex.pokeapi.cache_enabled', false);

    // Laravel führt Requests, die auf kein Fake-Muster passen, tatsächlich aus.
    // Ohne diese Zeile wäre ein Tippfehler im Muster nicht als Fehler sichtbar,
    // sondern als stiller Live-Aufruf gegen die PokéAPI.
    Http::preventStrayRequests();
});

/** Antwort für /pokemon-species/{id}. */
function speciesAntwort(int $id, string $slug, string $nameDe, array $ueberschreiben = []): array
{
    return array_merge([
        'name' => $slug,
        'names' => [
            ['name' => $nameDe, 'language' => ['name' => 'de']],
            ['name' => ucfirst($slug), 'language' => ['name' => 'en']],
        ],
        'generation' => ['url' => 'https://pokeapi.co/api/v2/generation/1/'],
        'is_legendary' => false,
        'is_mythical' => false,
        'is_baby' => false,
        'evolution_chain' => ['url' => 'https://pokeapi.co/api/v2/evolution-chain/1/'],
        'varieties' => [
            ['is_default' => true, 'pokemon' => ['name' => $slug]],
        ],
    ], $ueberschreiben);
}

/** Antwort für /pokemon/{slug}. */
function pokemonAntwort(string $slug, array $typen = ['grass'], array $ueberschreiben = []): array
{
    return array_merge([
        'name' => $slug,
        'types' => collect($typen)->map(fn ($t, $i) => [
            'slot' => $i + 1,
            'type' => ['name' => $t],
        ])->all(),
        'sprites' => [
            'front_default' => "https://sprites/{$slug}.png",
            'front_shiny' => "https://sprites/{$slug}-shiny.png",
            'other' => ['official-artwork' => ['front_default' => "https://art/{$slug}.png"]],
        ],
        'stats' => [
            ['stat' => ['name' => 'hp'], 'base_stat' => 45],
            ['stat' => ['name' => 'attack'], 'base_stat' => 49],
        ],
        'height' => 7,
        'weight' => 69,
    ], $ueberschreiben);
}

it('importiert Arten mit deutschem Namen, Typen und Artwork', function () {
    Http::fake([
        '*/pokemon-species?limit=1' => Http::response(['count' => 1]),
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'bulbasaur', 'Bisasam')),
        '*/pokemon/bulbasaur' => Http::response(pokemonAntwort('bulbasaur', ['grass', 'poison'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'bulbasaur'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    $pokemon = Pokemon::first();

    expect($pokemon->name_de)->toBe('Bisasam')
        ->and($pokemon->dex_nr)->toBe(1)
        ->and($pokemon->generation)->toBe(1)
        ->and($pokemon->types->pluck('slug')->all())->toBe(['grass', 'poison'])
        ->and($pokemon->baseForm->artwork_url)->toBe('https://art/bulbasaur.png')
        ->and($pokemon->baseForm->shiny_sprite_url)->toBe('https://sprites/bulbasaur-shiny.png')
        ->and($pokemon->base_stats)->toBe(['hp' => 45, 'attack' => 49]);
});

it('legt die 18 Typen an, auch ohne API-Aufruf dafür', function () {
    Http::fake(['*' => Http::response([], 404)]);

    $this->artisan('pokedex:import', ['--to' => 0])->assertSuccessful();

    expect(Type::count())->toBe(18)
        ->and(Type::where('slug', 'fire')->value('name_de'))->toBe('Feuer');
});

it('erkennt Regionalformen am Varietätsnamen', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'vulpix', 'Vulpix', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'vulpix']],
                ['is_default' => false, 'pokemon' => ['name' => 'vulpix-alola']],
            ],
        ])),
        '*/pokemon/vulpix-alola' => Http::response(pokemonAntwort('vulpix-alola', ['ice'])),
        '*/pokemon/vulpix' => Http::response(pokemonAntwort('vulpix', ['fire'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'vulpix'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    $formen = PokemonForm::orderBy('sort_order')->get();

    expect($formen)->toHaveCount(2)
        ->and($formen[0]->form_type)->toBe(FormType::Base)
        ->and($formen[1]->form_type)->toBe(FormType::Regional)
        ->and($formen[1]->region)->toBe(Region::Alola)
        ->and($formen[1]->name_de)->toBe('Vulpix (Alola-Form)');
});

it('überspringt Mega- und Gigadynamax-Formen', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'charizard', 'Glurak', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'charizard']],
                ['is_default' => false, 'pokemon' => ['name' => 'charizard-mega-x']],
                ['is_default' => false, 'pokemon' => ['name' => 'charizard-gmax']],
            ],
        ])),
        '*/pokemon/charizard' => Http::response(pokemonAntwort('charizard', ['fire', 'flying'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'charizard'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    expect(PokemonForm::count())->toBe(1);
});

it('legt Sonderformen nur mit der passenden Option an', function () {
    $antworten = [
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'rotom', 'Rotom', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'rotom']],
                ['is_default' => false, 'pokemon' => ['name' => 'rotom-heat']],
            ],
        ])),
        '*/pokemon/rotom-heat' => Http::response(pokemonAntwort('rotom-heat', ['fire'])),
        '*/pokemon/rotom' => Http::response(pokemonAntwort('rotom', ['electric'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'rotom'], 'evolves_to' => []]]),
    ];

    Http::fake($antworten);
    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();
    expect(PokemonForm::count())->toBe(1);

    Http::fake($antworten);
    $this->artisan('pokedex:import', ['--to' => 1, '--include-other-forms' => true])->assertSuccessful();
    expect(PokemonForm::count())->toBe(2)
        ->and(PokemonForm::where('slug', 'rotom-heat')->first()->form_type)->toBe(FormType::Other);
});

it('verknüpft die Entwicklungskette und formuliert sie auf Deutsch', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'bulbasaur', 'Bisasam')),
        '*/pokemon-species/2' => Http::response(speciesAntwort(2, 'ivysaur', 'Bisaknosp')),
        '*/pokemon/bulbasaur' => Http::response(pokemonAntwort('bulbasaur')),
        '*/pokemon/ivysaur' => Http::response(pokemonAntwort('ivysaur')),
        '*evolution-chain*' => Http::response([
            'chain' => [
                'species' => ['name' => 'bulbasaur'],
                'evolves_to' => [[
                    'species' => ['name' => 'ivysaur'],
                    'evolution_details' => [[
                        'trigger' => ['name' => 'level-up'],
                        'min_level' => 16,
                    ]],
                    'evolves_to' => [],
                ]],
            ],
        ]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 2])->assertSuccessful();

    $bisaknosp = Pokemon::where('dex_nr', 2)->first();

    expect($bisaknosp->evolves_from_id)->toBe(Pokemon::where('dex_nr', 1)->value('id'))
        ->and($bisaknosp->evolution_trigger)->toBe('level-up')
        ->and($bisaknosp->evolution_conditions)->toBe(['min_level' => 16])
        ->and($bisaknosp->evolution_summary_de)->toBe('aus Bisasam ab Level 16');
});

it('beschreibt Tausch- und Item-Entwicklungen im Klartext', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'machoke', 'Maschock')),
        '*/pokemon-species/2' => Http::response(speciesAntwort(2, 'machamp', 'Machomei')),
        '*/pokemon/machoke' => Http::response(pokemonAntwort('machoke')),
        '*/pokemon/machamp' => Http::response(pokemonAntwort('machamp')),
        '*evolution-chain*' => Http::response([
            'chain' => [
                'species' => ['name' => 'machoke'],
                'evolves_to' => [[
                    'species' => ['name' => 'machamp'],
                    'evolution_details' => [[
                        'trigger' => ['name' => 'trade'],
                        'held_item' => ['name' => 'linking-cord'],
                    ]],
                    'evolves_to' => [],
                ]],
            ],
        ]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 2])->assertSuccessful();

    expect(Pokemon::where('dex_nr', 2)->value('evolution_summary_de'))
        ->toContain('durch Tausch mit Item')
        ->toContain('linking cord');
});

it('ist wiederholbar und legt keine Duplikate an', function () {
    $antworten = [
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'bulbasaur', 'Bisasam')),
        '*/pokemon/bulbasaur' => Http::response(pokemonAntwort('bulbasaur')),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'bulbasaur'], 'evolves_to' => []]]),
    ];

    Http::fake($antworten);
    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    Http::fake($antworten);
    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    expect(Pokemon::count())->toBe(1)
        ->and(PokemonForm::count())->toBe(1)
        ->and(DB::table('pokemon_type')->count())->toBe(1);
});

it('bricht bei einer kaputten Art nicht ab, sondern überspringt sie', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'bulbasaur', 'Bisasam')),
        '*/pokemon-species/2' => Http::response([], 500),
        '*/pokemon/bulbasaur' => Http::response(pokemonAntwort('bulbasaur')),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'bulbasaur'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 2])->assertSuccessful();

    expect(Pokemon::count())->toBe(1);
});

it('hält Regionalform-Typen aus der Basisform heraus', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'meowth', 'Mauzi', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'meowth']],
                ['is_default' => false, 'pokemon' => ['name' => 'meowth-galar']],
                ['is_default' => false, 'pokemon' => ['name' => 'meowth-alola']],
            ],
        ])),
        '*/pokemon/meowth-galar' => Http::response(pokemonAntwort('meowth-galar', ['steel'])),
        '*/pokemon/meowth-alola' => Http::response(pokemonAntwort('meowth-alola', ['dark'])),
        '*/pokemon/meowth' => Http::response(pokemonAntwort('meowth', ['normal'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'meowth'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    $mauzi = Pokemon::first();

    // Die Art selbst ist nur Normal – Stahl und Unlicht gehören den Formen.
    expect($mauzi->types->pluck('slug')->all())->toBe(['normal'])
        ->and($mauzi->baseForm->displayTypes()->pluck('slug')->all())->toBe(['normal'])
        ->and(PokemonForm::where('slug', 'meowth-galar')->first()->displayTypes()->pluck('slug')->all())
        ->toBe(['steel']);
});

it('überspringt Mützen- und Trance-Formen trotz Regionsnamen im Slug', function () {
    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'pikachu', 'Pikachu', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'pikachu']],
                ['is_default' => false, 'pokemon' => ['name' => 'pikachu-alola-cap']],
            ],
        ])),
        '*/pokemon/pikachu' => Http::response(pokemonAntwort('pikachu', ['electric'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'pikachu'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    expect(PokemonForm::count())->toBe(1)
        ->and(Pokemon::first()->types->pluck('slug')->all())->toBe(['electric']);
});

it('räumt eine früher fälschlich importierte Form beim erneuten Lauf weg', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['slug' => 'pikachu', 'dex_nr' => 1]);
    PokemonForm::factory()->regional()->for($pokemon)->create(['slug' => 'pikachu-alola-cap']);

    expect(PokemonForm::count())->toBe(2);

    Http::fake([
        '*/pokemon-species/1' => Http::response(speciesAntwort(1, 'pikachu', 'Pikachu', [
            'varieties' => [
                ['is_default' => true, 'pokemon' => ['name' => 'pikachu']],
                ['is_default' => false, 'pokemon' => ['name' => 'pikachu-alola-cap']],
            ],
        ])),
        '*/pokemon/pikachu' => Http::response(pokemonAntwort('pikachu', ['electric'])),
        '*evolution-chain*' => Http::response(['chain' => ['species' => ['name' => 'pikachu'], 'evolves_to' => []]]),
    ]);

    $this->artisan('pokedex:import', ['--to' => 1])->assertSuccessful();

    expect(PokemonForm::count())->toBe(1)
        ->and(PokemonForm::where('slug', 'pikachu-alola-cap')->exists())->toBeFalse();
});
