<?php

/**
 * CSV-Import für kuratierte Bezugsquellen und GO-Daten (spec.md 2.3, 2.4, 4).
 */

use App\Enums\Difficulty;
use App\Enums\GoMethod;
use App\Enums\GoRegion;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\GoAvailability;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Database\Seeders\GameSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->seed(GameSeeder::class);

    $this->csv = function (string $inhalt): string {
        $pfad = tempnam(sys_get_temp_dir(), 'dex').'.csv';
        file_put_contents($pfad, $inhalt);

        return $pfad;
    };
});

it('importiert Bezugsquellen aus einer CSV', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1, 'name_de' => 'Bisasam']);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    1;;sword;gift;Vom Professor;leicht;0;Starter
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $datei])->assertSuccessful();

    $quelle = Obtainability::first();

    expect(Obtainability::count())->toBe(1)
        ->and($quelle->method)->toBe(ObtainMethod::Gift)
        ->and($quelle->location_detail)->toBe('Vom Professor')
        ->and($quelle->difficulty)->toBe(Difficulty::Leicht)
        ->and($quelle->note)->toBe('Starter')
        ->and($quelle->source)->toBe('csv');
});

it('meldet unbekannte Dex-Nummern, Spiele und Methoden, statt abzubrechen', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    999;;sword;wild;;;;
    1;;gibtsnicht;wild;;;;
    1;;sword;quatsch;;;;
    1;;sword;wild;Route 1;;;
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $datei])
        ->expectsOutputToContain('Dex-Nummer unbekannt')
        ->expectsOutputToContain('Spiel unbekannt')
        ->expectsOutputToContain('Methode unbekannt')
        ->assertSuccessful();

    expect(Obtainability::count())->toBe(1);
});

it('erkennt abgelaufene Events aus der CSV', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 151]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    151;;emerald;event;Verteilung 2005;sehr_schwer;1;
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $datei])->assertSuccessful();

    expect(Obtainability::first()->event_expired)->toBeTrue();
});

it('ist wiederholbar, ohne Duplikate anzulegen', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    1;;sword;wild;Route 1;;;
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $datei])->assertSuccessful();
    $this->artisan('pokedex:import-sources', ['datei' => $datei])->assertSuccessful();

    expect(Obtainability::count())->toBe(1);
});

it('räumt frühere CSV-Einträge auf Wunsch weg', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1]);

    $alt = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    1;;sword;wild;Alte Route;;;
    CSV);
    $neu = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    1;;shield;wild;Neue Route;;;
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $alt])->assertSuccessful();
    $this->artisan('pokedex:import-sources', ['datei' => $neu, '--ersetzen' => true])->assertSuccessful();

    expect(Obtainability::count())->toBe(1)
        ->and(Obtainability::first()->location_detail)->toBe('Neue Route');
});

it('lässt Einträge aus dem Seeder beim Ersetzen unangetastet', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1]);
    Obtainability::factory()->create([
        'pokemon_id' => Pokemon::first()->id,
        'game_id' => Game::where('slug', 'red')->value('id'),
        'source' => 'curated',
    ]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
    1;;sword;wild;Route 1;;;
    CSV);

    $this->artisan('pokedex:import-sources', ['datei' => $datei, '--ersetzen' => true])->assertSuccessful();

    expect(Obtainability::where('source', 'curated')->count())->toBe(1)
        ->and(Obtainability::count())->toBe(2);
});

it('bricht bei fehlender Datei ab', function () {
    $this->artisan('pokedex:import-sources', ['datei' => 'gibt/es/nicht.csv'])
        ->expectsOutputToContain('Datei nicht gefunden')
        ->assertFailed();
});

it('importiert GO-Daten inklusive Regionalexklusivität', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 122, 'name_de' => 'Pantimos']);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;method;regions;transferable;note
    122;wild;europa;1;Regional exklusiv
    CSV);

    $this->artisan('pokedex:import-go', ['datei' => $datei])->assertSuccessful();

    $go = GoAvailability::first();

    expect($go->method)->toBe(GoMethod::Wild)
        ->and($go->regions)->toBe(['europa'])
        ->and($go->isRegionExclusive())->toBeTrue()
        ->and($go->availableInRegion(GoRegion::Europa))->toBeTrue()
        ->and($go->availableInRegion(GoRegion::Ozeanien))->toBeFalse();
});

it('behandelt eine leere Regionsangabe als weltweit', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 25]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;method;regions;transferable;note
    25;wild;;1;
    CSV);

    $this->artisan('pokedex:import-go', ['datei' => $datei])->assertSuccessful();

    expect(GoAvailability::first()->regions)->toBe(['weltweit'])
        ->and(GoAvailability::first()->isRegionExclusive())->toBeFalse();
});

it('nimmt mehrere Regionen und meldet unbekannte', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 83]);

    $datei = ($this->csv)(<<<'CSV'
    dex_nr;method;regions;transferable;note
    83;wild;ostasien,mordor,europa;1;
    CSV);

    $this->artisan('pokedex:import-go', ['datei' => $datei])
        ->expectsOutputToContain('Region unbekannt')
        ->assertSuccessful();

    expect(GoAvailability::first()->regions)->toBe(['ostasien', 'europa']);
});

it('überschreibt einen vorhandenen GO-Eintrag, statt einen zweiten anzulegen', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 25]);

    $erst = ($this->csv)("dex_nr;method;regions;transferable;note\n25;wild;;1;\n");
    $dann = ($this->csv)("dex_nr;method;regions;transferable;note\n25;raid;europa;1;\n");

    $this->artisan('pokedex:import-go', ['datei' => $erst])->assertSuccessful();
    $this->artisan('pokedex:import-go', ['datei' => $dann])->assertSuccessful();

    expect(GoAvailability::count())->toBe(1)
        ->and(GoAvailability::first()->method)->toBe(GoMethod::Raid);
});
