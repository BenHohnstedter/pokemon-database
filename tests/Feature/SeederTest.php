<?php

/**
 * Die kuratierten Seeder hängen ihre Einträge an konkrete Pokémon. Laufen sie
 * vor dem Import, finden sie nichts – das darf nicht stillschweigend passieren
 * (spec.md 2.3, 2.4).
 */

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
