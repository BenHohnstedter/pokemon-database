<?php

/**
 * Die kuratierten Seeder hängen ihre Einträge an konkrete Pokémon. Laufen sie
 * vor dem Import, finden sie nichts – das darf nicht stillschweigend passieren
 * (spec.md 2.3, 2.4).
 */

use App\Models\Game;
use App\Models\GoAvailability;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Database\Seeders\CuratedObtainabilitySeeder;
use Database\Seeders\GameSeeder;
use Database\Seeders\GoAvailabilitySeeder;

beforeEach(function () {
    resetDexSequence();
    $this->seed(GameSeeder::class);
});

it('warnt, wenn die Bezugsquellen vor dem Import geseedet werden', function () {
    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])
        ->expectsOutputToContain('nach `pokedex:import` erneut ausführen')
        ->assertSuccessful();

    expect(Obtainability::count())->toBe(0);
});

it('warnt, wenn die GO-Daten vor dem Import geseedet werden', function () {
    $this->artisan('db:seed', ['--class' => GoAvailabilitySeeder::class])
        ->expectsOutputToContain('übersprungen')
        ->assertSuccessful();

    expect(GoAvailability::count())->toBe(0);
});

it('legt die Einträge an, sobald die Arten importiert sind', function () {
    Pokemon::factory()->withBaseForm()->create(['slug' => 'bulbasaur', 'name_de' => 'Bisasam']);
    Pokemon::factory()->withBaseForm()->create(['slug' => 'mr-mime', 'name_de' => 'Pantimos']);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => GoAvailabilitySeeder::class])->assertSuccessful();

    expect(Obtainability::where('source', 'curated')->count())->toBeGreaterThan(0)
        ->and(GoAvailability::count())->toBe(1)
        ->and(GoAvailability::first()->regions)->toBe(['europa']);
});

it('ist wiederholbar, ohne Duplikate anzulegen', function () {
    Pokemon::factory()->withBaseForm()->create(['slug' => 'mr-mime']);

    $this->artisan('db:seed', ['--class' => GoAvailabilitySeeder::class])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => GoAvailabilitySeeder::class])->assertSuccessful();

    expect(GoAvailability::count())->toBe(1);
});

it('entfernt die Starterwahl, die als Wildfang in den Daten steht', function () {
    // Die PokéAPI führt die Übergabe des Starters als Encounter im Startort:
    // Chelast steht dadurch mit „Wildfang, Lake Verity" in Diamant, obwohl es
    // dort niemand fangen kann (FEATURE-UPDATES.md 12).
    $chelast = Pokemon::factory()->withBaseForm()->create(['slug' => 'turtwig', 'name_de' => 'Chelast']);
    $diamant = Game::where('slug', 'diamond')->firstOrFail();

    Obtainability::create([
        'pokemon_id' => $chelast->id,
        'game_id' => $diamant->id,
        'method' => 'wild',
        'location_detail' => 'Lake Verity Before Galactic Intervention',
        'source' => 'pokeapi',
    ]);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $quellen = Obtainability::where('pokemon_id', $chelast->id)->where('game_id', $diamant->id)->get();

    expect($quellen)->toHaveCount(1)
        ->and($quellen->first()->method->value)->toBe('gift');
});

it('lässt echte Wildfänge stehen, wo der Starter wirklich herumläuft', function () {
    // In Let's Go laufen Bisasam, Glumanda und Schiggy tatsächlich in der Welt
    // herum. Erkennbar ist das an mehreren Fundorten – die Starterwahl hat nur
    // einen einzigen.
    $bisasam = Pokemon::factory()->withBaseForm()->create(['slug' => 'bulbasaur', 'name_de' => 'Bisasam']);
    $letsGo = Game::where('slug', 'lets-go-pikachu')->firstOrFail();

    Obtainability::create([
        'pokemon_id' => $bisasam->id,
        'game_id' => $letsGo->id,
        'method' => 'wild',
        'location_detail' => 'Cerulean City Area, Viridian Forest Area',
        'source' => 'pokeapi',
    ]);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    expect(Obtainability::where('pokemon_id', $bisasam->id)->where('game_id', $letsGo->id)->where('method', 'wild')->exists())
        ->toBeTrue();
});

it('kennt Cosmog als Geschenk aus den Kronen-Schneelanden', function () {
    // Ohne diesen Eintrag hängt die ganze Linie an Sonne/Mond und damit an der
    // Bank-Frist, obwohl sie in Schwert/Schild direkt an HOME zu holen ist.
    $cosmog = Pokemon::factory()->withBaseForm()->create(['slug' => 'cosmog', 'name_de' => 'Cosmog']);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $quellen = Obtainability::where('pokemon_id', $cosmog->id)->with('game')->get();

    expect($quellen->pluck('game.slug')->sort()->values()->all())->toBe(['shield', 'sword'])
        ->and($quellen->first()->method->value)->toBe('gift')
        ->and($quellen->first()->note)->toContain('Erweiterungspass');
});

it('kennt die nachträglichen Starter-Geschenke aus Omega Rubin und Alpha Saphir', function () {
    // Prof. Birk verschenkt nach der Story Johto-, Einall- und Sinnoh-Starter.
    // Die PokéAPI führt alle neun als Wildfang auf Route 101 – dort laufen
    // aber nur Zigzachs, Waumpel und Fiffyen herum (FEATURE-UPDATES.md 14).
    $chelast = Pokemon::factory()->withBaseForm()->create(['slug' => 'turtwig', 'name_de' => 'Chelast']);
    $oras = Game::where('slug', 'alpha-sapphire')->firstOrFail();

    Obtainability::create([
        'pokemon_id' => $chelast->id,
        'game_id' => $oras->id,
        'method' => 'wild',
        'location_detail' => 'Hoenn Route 101 Area',
        'source' => 'pokeapi',
    ]);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $quellen = Obtainability::where('pokemon_id', $chelast->id)->where('game_id', $oras->id)->get();

    expect($quellen)->toHaveCount(1)
        ->and($quellen->first()->method->value)->toBe('gift')
        ->and($quellen->first()->location_detail)->toContain('Prof. Birk')
        ->and($quellen->first()->note)->toContain('Sinnoh-Starter');
});
