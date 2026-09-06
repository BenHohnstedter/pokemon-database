<?php

/**
 * Lückenfüller für die fehlenden Fundortdaten der PokéAPI (spec.md 4).
 */

use App\Console\Commands\FillObtainabilityGapsCommand;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Database\Seeders\GameSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->seed(GameSeeder::class);
});

it('trägt für eine Art ohne jeden Weg einen Wildfang im Hauptspiel ihrer Generation nach', function () {
    Pokemon::factory()->withBaseForm()->create(['generation' => 9, 'name_de' => 'Felori']);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps')->assertSuccessful();

    $quellen = Obtainability::with('game')->get();

    expect($quellen)->toHaveCount(2)
        ->and($quellen->pluck('game.slug')->sort()->values()->all())->toBe(['scarlet', 'violet'])
        ->and($quellen->first()->source)->toBe(FillObtainabilityGapsCommand::SOURCE)
        ->and($quellen->first()->location_detail)->toBe('Fundort noch nicht hinterlegt')
        ->and($quellen->first()->note)->toContain('Angenommen');
});

it('lässt Arten in Ruhe, die über eine Vorstufe erreichbar sind', function () {
    $basis = Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => Game::where('slug', 'scarlet')->value('id'),
    ]);

    $endstufe = Pokemon::factory()->withBaseForm()->create([
        'generation' => 9,
        'evolves_from_id' => $basis->id,
    ]);

    $this->artisan('pokedex:recalculate')->assertSuccessful();
    $this->artisan('pokedex:fill-gaps')->assertSuccessful();

    // Der Endstufe einen Wildfang anzudichten wäre falsch – sie entsteht
    // durch Entwicklung.
    expect(Obtainability::where('pokemon_id', $endstufe->id)->count())->toBe(0);
});

it('lässt mysteriöse Pokémon standardmäßig aus', function () {
    Pokemon::factory()->withBaseForm()->mythical()->create(['generation' => 9]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps')->assertSuccessful();

    expect(Obtainability::count())->toBe(0);
});

it('behandelt mysteriöse Pokémon auf Wunsch mit', function () {
    Pokemon::factory()->withBaseForm()->mythical()->create(['generation' => 9]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps', ['--with-mythical' => true])->assertSuccessful();

    expect(Obtainability::count())->toBe(2);
});

it('schreibt im Trockenlauf nichts', function () {
    Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps', ['--dry-run' => true])
        ->expectsOutputToContain('Trockenlauf')
        ->assertSuccessful();

    expect(Obtainability::count())->toBe(0);
});

it('entfernt die Lückenfüller wieder, ohne echte Quellen anzutasten', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'sword')->value('id'),
        'source' => 'pokeapi',
    ]);

    $lueckenhaft = Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();
    $this->artisan('pokedex:fill-gaps')->assertSuccessful();

    expect(Obtainability::where('source', FillObtainabilityGapsCommand::SOURCE)->count())->toBe(2);

    $this->artisan('pokedex:fill-gaps', ['--remove' => true])->assertSuccessful();

    expect(Obtainability::where('source', FillObtainabilityGapsCommand::SOURCE)->count())->toBe(0)
        ->and(Obtainability::where('source', 'pokeapi')->count())->toBe(1);
});

it('meldet, wenn es nichts zu füllen gibt', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'scarlet')->value('id'),
    ]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps')
        ->expectsOutputToContain('Keine Lücken')
        ->assertSuccessful();
});

it('ist wiederholbar, ohne Duplikate anzulegen', function () {
    Pokemon::factory()->withBaseForm()->create(['generation' => 9]);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->artisan('pokedex:fill-gaps')->assertSuccessful();
    $this->artisan('pokedex:recalculate')->assertSuccessful();
    $this->artisan('pokedex:fill-gaps')->assertSuccessful();

    expect(Obtainability::count())->toBe(2);
});
