<?php

/**
 * Fundort-Import und die daraus abgeleiteten Felder (spec.md 2.3, 2.8, 4).
 */

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    resetDexSequence();
    config()->set('pokedex.pokeapi.cache_enabled', false);
    Http::preventStrayRequests();
});

/** Antwort für /pokemon/{slug}/encounters. */
function begegnung(string $gebiet, array $versionen): array
{
    return [
        'location_area' => ['name' => $gebiet],
        'version_details' => collect($versionen)->map(fn (int $chance, string $version) => [
            'version' => ['name' => $version],
            'max_chance' => $chance,
        ])->values()->all(),
    ];
}

it('legt pro Spiel einen Fundort an', function () {
    $this->seed(GameSeeder::class);
    $pokemon = Pokemon::factory()->withBaseForm()->create(['slug' => 'pikachu']);

    Http::fake([
        '*/pokemon/pikachu/encounters' => Http::response([
            begegnung('kanto-route-2-south-towards-viridian-city', ['red' => 20, 'blue' => 20]),
        ]),
    ]);

    $this->artisan('pokedex:import-encounters')->assertSuccessful();

    expect(Obtainability::count())->toBe(2)
        ->and(Obtainability::first()->method)->toBe(ObtainMethod::Wild)
        ->and(Obtainability::first()->source)->toBe('pokeapi')
        ->and(Obtainability::first()->location_detail)
        ->toBe('Kanto Route 2 South Towards Viridian City');
});

it('ordnet die DLC-Gebiete dem jeweiligen Hauptspiel zu', function () {
    $this->seed(GameSeeder::class);
    $pokemon = Pokemon::factory()->withBaseForm()->create(['slug' => 'galarian-slowpoke']);

    Http::fake([
        '*encounters' => Http::response([
            begegnung('isle-of-armor-fields-of-honor', ['the-isle-of-armor' => 15]),
        ]),
    ]);

    $this->artisan('pokedex:import-encounters')->assertSuccessful();

    expect(Obtainability::first()->game->slug)->toBe('sword');
});

it('ignoriert Nebenreihen-Titel ohne HOME-Bezug', function () {
    $this->seed(GameSeeder::class);
    Pokemon::factory()->withBaseForm()->create(['slug' => 'plusle']);

    Http::fake([
        '*encounters' => Http::response([
            begegnung('phenac-city', ['colosseum' => 100, 'xd' => 100]),
        ]),
    ]);

    $this->artisan('pokedex:import-encounters')->assertSuccessful();

    expect(Obtainability::count())->toBe(0);
});

it('fasst viele Gebiete zu einer lesbaren Angabe zusammen', function () {
    $this->seed(GameSeeder::class);
    Pokemon::factory()->withBaseForm()->create(['slug' => 'zubat']);

    Http::fake([
        '*encounters' => Http::response(
            collect(range(1, 8))
                ->map(fn (int $i) => begegnung("hoehle-{$i}", ['red' => 10]))
                ->all()
        ),
    ]);

    $this->artisan('pokedex:import-encounters')->assertSuccessful();

    $detail = Obtainability::first()->location_detail;

    // Höchstens vier Gebiete, danach ein Hinweis statt einer endlosen Liste.
    expect(substr_count($detail, ','))->toBe(3)
        ->and($detail)->toEndWith('u.a.');
});

it('bricht ohne importierte Pokémon mit einem Hinweis ab', function () {
    $this->seed(GameSeeder::class);

    $this->artisan('pokedex:import-encounters')
        ->expectsOutputToContain('pokedex:import')
        ->assertFailed();
});

it('bricht ohne Spiele in der Datenbank mit einem Hinweis ab', function () {
    Pokemon::factory()->withBaseForm()->create();

    $this->artisan('pokedex:import-encounters')
        ->expectsOutputToContain('db:seed')
        ->assertFailed();
});

it('leitet Fangbarkeit und Beschaffungsweg aus den Quellen ab', function () {
    $this->seed(GameSeeder::class);
    $spiel = Game::where('slug', 'red')->first();

    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => $spiel->id,
        'method' => ObtainMethod::Wild,
    ]);

    $mitte = Pokemon::factory()->withBaseForm()->create([
        'name_de' => 'Bisaknosp',
        'evolves_from_id' => $basis->id,
    ]);
    $ende = Pokemon::factory()->withBaseForm()->create([
        'name_de' => 'Bisaflor',
        'evolves_from_id' => $mitte->id,
    ]);

    $this->artisan('pokedex:recalculate')->assertSuccessful();

    expect($basis->fresh()->obtainable_directly)->toBeTrue()
        ->and($basis->fresh()->source_pokemon_id)->toBe($basis->id)
        ->and($mitte->fresh()->obtainable_directly)->toBeFalse()
        ->and($mitte->fresh()->source_pokemon_id)->toBe($basis->id)
        ->and($ende->fresh()->source_pokemon_id)->toBe($basis->id);
});

it('macht jede nötige Entwicklung eine Stufe schwerer', function () {
    $this->seed(GameSeeder::class);

    $basis = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => Game::where('slug', 'red')->value('id'),
        'method' => ObtainMethod::Wild,
    ]);

    $mitte = Pokemon::factory()->withBaseForm()->create(['evolves_from_id' => $basis->id]);
    $ende = Pokemon::factory()->withBaseForm()->create(['evolves_from_id' => $mitte->id]);

    $this->artisan('pokedex:recalculate')->assertSuccessful();

    expect($basis->fresh()->difficulty)->toBe(Difficulty::Leicht)
        ->and($mitte->fresh()->difficulty)->toBe(Difficulty::Mittel)
        ->and($ende->fresh()->difficulty)->toBe(Difficulty::Schwer);
});

it('stuft mysteriöse Pokémon immer als sehr schwer ein', function () {
    $this->seed(GameSeeder::class);

    $mew = Pokemon::factory()->withBaseForm()->mythical()->create();
    Obtainability::factory()->create([
        'pokemon_id' => $mew->id,
        'game_id' => Game::where('slug', 'emerald')->value('id'),
        'method' => ObtainMethod::Wild,
    ]);

    $this->artisan('pokedex:recalculate')->assertSuccessful();

    expect($mew->fresh()->difficulty)->toBe(Difficulty::SehrSchwer);
});

it('meldet Arten ganz ohne Fundweg', function () {
    Pokemon::factory()->withBaseForm()->create();

    $this->artisan('pokedex:recalculate')
        ->expectsOutputToContain('1 Arten haben aktuell gar keinen hinterlegten Fundweg')
        ->assertSuccessful();

    expect(Pokemon::first()->source_pokemon_id)->toBeNull();
});
