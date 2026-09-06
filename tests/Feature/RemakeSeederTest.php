<?php

/**
 * Bezugsquellen der Originalspiele auf ihre Remakes übernehmen (spec.md 2.3, 4).
 */

use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\User;
use App\Services\PokedexQuery;
use Database\Seeders\GameSeeder;
use Database\Seeders\RemakeObtainabilitySeeder;

beforeEach(function () {
    resetDexSequence();
    $this->seed(GameSeeder::class);
});

it('überträgt die Quellen von Diamant auf Strahlender Diamant', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);

    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'diamond')->value('id'),
        'method' => ObtainMethod::Wild,
        'location_detail' => 'Route 201',
        'source' => 'pokeapi',
    ]);

    $this->seed(RemakeObtainabilitySeeder::class);

    $remake = Obtainability::where('game_id', Game::where('slug', 'brilliant-diamond')->value('id'))->first();

    expect($remake)->not->toBeNull()
        ->and($remake->pokemon_id)->toBe($pokemon->id)
        ->and($remake->location_detail)->toBe('Route 201')
        ->and($remake->source)->toBe('remake:diamond')
        ->and($remake->note)->toContain('Fundort kann abweichen');
});

it('legt die Switch-Neuauflage von Feuerrot und Blattgrün an', function () {
    $frs = Game::where('slug', 'firered-switch')->first();

    expect($frs)->not->toBeNull()
        ->and($frs->home_compatible)->toBeTrue()
        ->and($frs->bank_only)->toBeFalse()
        ->and($frs->platform->label())->toBe('Nintendo Switch');
});

it('macht Ho-Oh über die Switch-Neuauflage ohne Bank erreichbar', function () {
    // Ho-Oh gibt es in den GBA-Originalen auf Eiland 9 – im Remake also auch.
    $hooh = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Ho-Oh', 'is_legendary' => true]);

    Obtainability::factory()->create([
        'pokemon_id' => $hooh->id,
        'game_id' => Game::where('slug', 'firered')->value('id'),
        'method' => ObtainMethod::StaticEncounter,
        'location_detail' => 'Eiland 9',
    ]);

    $this->seed(RemakeObtainabilitySeeder::class);
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $ergebnis = app(PokedexQuery::class)->evaluateForm($hooh->baseForm, $user);

    // Vorher: nur GBA → 🔴. Jetzt gibt es einen Weg ohne Bank.
    expect($ergebnis->affectedByBankDeadline())->toBeFalse()
        ->and($ergebnis->isUrgent())->toBeFalse();
});

it('gibt Mew und Jirachi in den Sinnoh-Remakes ohne Event frei', function () {
    Pokemon::factory()->withBaseForm()->create(['slug' => 'mew', 'name_de' => 'Mew']);
    Pokemon::factory()->withBaseForm()->create(['slug' => 'jirachi', 'name_de' => 'Jirachi']);

    $this->seed(RemakeObtainabilitySeeder::class);

    $mew = Obtainability::whereRelation('pokemon', 'slug', 'mew')->get();

    expect($mew)->toHaveCount(2)
        ->and($mew->pluck('location_detail')->first())->toContain('Speicherstand')
        ->and(Obtainability::whereRelation('pokemon', 'slug', 'jirachi')->count())->toBe(2);
});

it('überträgt abgelaufene Events nicht mit', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    Obtainability::factory()->expiredEvent()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'diamond')->value('id'),
    ]);

    $this->seed(RemakeObtainabilitySeeder::class);

    // Ein Event, das damals lief, gibt es im Remake nicht automatisch.
    expect(Obtainability::where('source', 'remake:diamond')->count())->toBe(0);
});

it('ist wiederholbar, ohne Duplikate anzulegen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'pearl')->value('id'),
    ]);

    $this->seed(RemakeObtainabilitySeeder::class);
    $this->seed(RemakeObtainabilitySeeder::class);

    expect(Obtainability::where('source', 'remake:pearl')->count())->toBe(1);
});

it('warnt, wenn es vor dem Fundort-Import läuft', function () {
    $this->artisan('db:seed', ['--class' => RemakeObtainabilitySeeder::class])
        ->expectsOutputToContain('nichts übernommen')
        ->assertSuccessful();
});

it('lässt sich über die Quellenkennzeichnung wieder entfernen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', 'diamond')->value('id'),
        'source' => 'pokeapi',
    ]);

    $this->seed(RemakeObtainabilitySeeder::class);
    $vorher = Obtainability::count();

    Obtainability::where('source', 'like', 'remake:%')->delete();

    expect(Obtainability::count())->toBeLessThan($vorher)
        ->and(Obtainability::where('source', 'pokeapi')->count())->toBe(1);
});
