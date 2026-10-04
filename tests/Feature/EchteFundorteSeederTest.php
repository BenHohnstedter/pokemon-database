<?php

/**
 * Die kuratierten Echt-Fundorte ersetzen die Platzhalter von
 * `pokedex:fill-gaps` für Arten, deren Fundort die PokéAPI nicht kennt
 * (FEATURE-UPDATES.md 23).
 */

use App\Console\Commands\FillObtainabilityGapsCommand;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Database\Seeders\CuratedObtainabilitySeeder;
use Database\Seeders\GameSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->seed(GameSeeder::class);
});

function luecke(Pokemon $pokemon, string $gameSlug): void
{
    Obtainability::create([
        'pokemon_id' => $pokemon->id,
        'game_id' => Game::where('slug', $gameSlug)->value('id'),
        'method' => ObtainMethod::Wild->value,
        'location_detail' => 'Fundort noch nicht hinterlegt',
        'source' => FillObtainabilityGapsCommand::SOURCE,
    ]);
}

it('ersetzt den Platzhalter eines bekannten Fundorts durch den echten Eintrag', function () {
    $frillish = Pokemon::factory()->withBaseForm()->create(['slug' => 'frillish', 'generation' => 5]);
    luecke($frillish, 'black');

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $quelle = Obtainability::where('pokemon_id', $frillish->id)
        ->whereHas('game', fn ($q) => $q->where('slug', 'black'))
        ->first();

    expect(Obtainability::where('pokemon_id', $frillish->id)->count())->toBe(2)
        ->and($quelle->source)->toBe('curated')
        ->and($quelle->method)->toBe(ObtainMethod::Wild)
        ->and($quelle->location_detail)->toContain('Route 4')
        ->and($quelle->locationAreas())->not->toBeEmpty()
        ->and($quelle->locationWikiUrl($quelle->locationAreas()[0]['name_de']))
        ->toBe('https://www.pokewiki.de/Route_4_%28Einall%29');
});

it('räumt den Platzhalter einer Endstufe weg – Entwicklung statt Wildfang', function () {
    $frillish = Pokemon::factory()->withBaseForm()->create(['slug' => 'frillish', 'generation' => 5]);
    $jellicent = Pokemon::factory()->withBaseForm()->create([
        'slug' => 'jellicent', 'generation' => 5, 'evolves_from_id' => $frillish->id,
    ]);
    luecke($jellicent, 'black');
    luecke($jellicent, 'white');

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    expect(Obtainability::where('pokemon_id', $jellicent->id)->count())->toBe(0);
});

it('setzt die Versionsexklusive nur im richtigen Spiel und räumt das Gegenstück', function () {
    $tornadus = Pokemon::factory()->withBaseForm()->create(['slug' => 'tornadus', 'generation' => 5]);
    luecke($tornadus, 'black');
    luecke($tornadus, 'white');

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    // Tornadus steht zusätzlich in den Dyna-Raid-Abenteuern – hier zählt nur
    // die eigene Fundort-Zeile in Einall.
    expect(Obtainability::where('pokemon_id', $tornadus->id)->where('game_id', Game::where('slug', 'black')->value('id'))->count())->toBe(1)
        ->and(Obtainability::where('pokemon_id', $tornadus->id)->where('game_id', Game::where('slug', 'white')->value('id'))->count())->toBe(0);

    $quelle = Obtainability::where('pokemon_id', $tornadus->id)
        ->where('game_id', Game::where('slug', 'black')->value('id'))
        ->first();

    expect($quelle->method)->toBe(ObtainMethod::Roaming)
        ->and($quelle->source)->toBe('curated');
});

it('setzt Regieleki und Regidrago nur in der jeweils richtigen Edition', function () {
    $regidrago = Pokemon::factory()->withBaseForm()->create(['slug' => 'regidrago', 'generation' => 8]);
    luecke($regidrago, 'sword');
    luecke($regidrago, 'shield');

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $statik = Obtainability::where('pokemon_id', $regidrago->id)
        ->where('method', ObtainMethod::StaticEncounter->value)
        ->with('game')
        ->get();

    expect($statik->pluck('game.slug')->all())->toBe(['sword'])
        ->and(Obtainability::where('pokemon_id', $regidrago->id)->where('game_id', Game::where('slug', 'shield')->value('id'))->where('source', 'generation-fallback')->count())->toBe(0);
});

it('ist wiederholbar, ohne Duplikate anzulegen', function () {
    $frillish = Pokemon::factory()->withBaseForm()->create(['slug' => 'frillish', 'generation' => 5]);

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    expect(Obtainability::where('pokemon_id', $frillish->id)->count())->toBe(2);
});

it('markiert abgelaufene Event-Fundorte als solche', function () {
    $leaves = Pokemon::factory()->withBaseForm()->create(['slug' => 'iron-leaves', 'generation' => 9]);
    luecke($leaves, 'scarlet');
    luecke($leaves, 'violet');

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    $quellen = Obtainability::where('pokemon_id', $leaves->id)->with('game')->get();

    expect($quellen)->toHaveCount(1)
        ->and($quellen->first()->game->slug)->toBe('violet')
        ->and($quellen->first()->method)->toBe(ObtainMethod::Event)
        ->and($quellen->first()->event_expired)->toBeTrue();
});

it('pflegt die Paldea-Ergänzungen ein: seltene Wildfänge und reine Entwicklungen', function () {
    $frillish = Pokemon::factory()->withBaseForm()->create(['slug' => 'frillish', 'generation' => 5]);
    $bellibolt = Pokemon::factory()->withBaseForm()->create(['slug' => 'bellibolt', 'generation' => 9, 'evolves_from_id' => $frillish->id]);
    $frigibax = Pokemon::factory()->withBaseForm()->create(['slug' => 'frigibax', 'generation' => 9]);
    $arctibax = Pokemon::factory()->withBaseForm()->create(['slug' => 'arctibax', 'generation' => 9, 'evolves_from_id' => $frigibax->id]);
    $baxcalibur = Pokemon::factory()->withBaseForm()->create(['slug' => 'baxcalibur', 'generation' => 9, 'evolves_from_id' => $arctibax->id]);

    foreach (['bellibolt', 'frigibax', 'arctibax', 'baxcalibur'] as $slug) {
        $pokemon = Pokemon::where('slug', $slug)->first();
        luecke($pokemon, 'scarlet');
        luecke($pokemon, 'violet');
    }

    $this->artisan('db:seed', ['--class' => CuratedObtainabilitySeeder::class])->assertSuccessful();

    // Selten fangbare Arten: der Platzhalter wird zum echten Wildfang in beiden Editionen.
    $bellibolt = Obtainability::where('pokemon_id', $bellibolt->id)
        ->where('method', ObtainMethod::Wild->value)
        ->where('source', 'curated')
        ->with('game')
        ->get();

    expect($bellibolt->pluck('game.slug')->all())->toBe(['scarlet', 'violet'])
        ->and($bellibolt->first()->location_detail)->toContain('sehr selten');

    $frigibax = Obtainability::where('pokemon_id', $frigibax->id)->withCount('game')->get();
    expect($frigibax)->toHaveCount(2)
        ->and($frigibax->every(fn ($o) => $o->source === 'curated'))->toBeTrue();

    $arctibax = Obtainability::where('pokemon_id', $arctibax->id)->withCount('game')->get();
    expect($arctibax)->toHaveCount(2)
        ->and($arctibax->every(fn ($o) => $o->source === 'curated'))->toBeTrue();

    // Endstufe ohne eigenen Fangweg: Platzhalter werden entfernt.
    expect(Obtainability::where('pokemon_id', $baxcalibur->id)->count())->toBe(0);
});
