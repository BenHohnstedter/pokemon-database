<?php

/**
 * Integrationstest über die gesamte Kette: Import → Fundorte → Ableitung →
 * Prioritäts-Engine (spec.md 2.3, 2.7, 2.8, 4).
 *
 * Die beiden Fehleinstufungen, die beim ersten echten Testimport auffielen,
 * hätte genau dieser Test gefunden – deshalb steht er hier eigenständig neben
 * den Unit-Tests der Engine.
 */

use App\Enums\ObtainMethod;
use App\Enums\PriorityLevel;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\User;
use App\Services\PokedexQuery;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    resetDexSequence();
    config()->set('pokedex.pokeapi.cache_enabled', false);
    Http::preventStrayRequests();

    $this->seed(GameSeeder::class);
    $this->user = User::factory()->create();
    $this->user->settingsOrDefault();
});

/**
 * Baut die Bisasam-Linie so, wie sie nach einem echten Import aussieht:
 * Bisasam als Starter im noch käuflichen Let's Go, Bisaknosp und Bisaflor
 * wild nur in X (3DS, also Bank-Weg).
 */
function importiereBisasamLinie(): void
{
    $species = fn (int $id, string $slug, string $nameDe, array $evolvesTo = []) => [
        'name' => $slug,
        'names' => [['name' => $nameDe, 'language' => ['name' => 'de']]],
        'generation' => ['url' => 'https://pokeapi.co/api/v2/generation/1/'],
        'is_legendary' => false,
        'is_mythical' => false,
        'is_baby' => false,
        'evolution_chain' => ['url' => 'https://pokeapi.co/api/v2/evolution-chain/1/'],
        'varieties' => [['is_default' => true, 'pokemon' => ['name' => $slug]]],
    ];

    $detail = fn (string $slug) => [
        'name' => $slug,
        'types' => [['slot' => 1, 'type' => ['name' => 'grass']]],
        'sprites' => ['front_default' => null, 'front_shiny' => null, 'other' => []],
        'stats' => [],
    ];

    Http::fake([
        '*/pokemon-species/1' => Http::response($species(1, 'bulbasaur', 'Bisasam')),
        '*/pokemon-species/2' => Http::response($species(2, 'ivysaur', 'Bisaknosp')),
        '*/pokemon-species/3' => Http::response($species(3, 'venusaur', 'Bisaflor')),
        '*/pokemon/bulbasaur/encounters' => Http::response([]),
        '*/pokemon/ivysaur/encounters' => Http::response([[
            'location_area' => ['name' => 'friend-safari-grass'],
            'version_details' => [['version' => ['name' => 'x'], 'max_chance' => 10]],
        ]]),
        '*/pokemon/venusaur/encounters' => Http::response([]),
        '*/pokemon/bulbasaur' => Http::response($detail('bulbasaur')),
        '*/pokemon/ivysaur' => Http::response($detail('ivysaur')),
        '*/pokemon/venusaur' => Http::response($detail('venusaur')),
        '*evolution-chain*' => Http::response([
            'chain' => [
                'species' => ['name' => 'bulbasaur'],
                'evolves_to' => [[
                    'species' => ['name' => 'ivysaur'],
                    'evolution_details' => [['trigger' => ['name' => 'level-up'], 'min_level' => 16]],
                    'evolves_to' => [[
                        'species' => ['name' => 'venusaur'],
                        'evolution_details' => [['trigger' => ['name' => 'level-up'], 'min_level' => 32]],
                        'evolves_to' => [],
                    ]],
                ]],
            ],
        ]),
    ]);

    test()->artisan('pokedex:import', ['--to' => 3])->assertSuccessful();
    test()->artisan('pokedex:import-encounters')->assertSuccessful();

    // Bisasam ist Starter, kein Wildfang – kommt aus dem kuratierten Seeder.
    Obtainability::create([
        'pokemon_id' => Pokemon::where('dex_nr', 1)->value('id'),
        'game_id' => Game::where('slug', 'lets-go-pikachu')->value('id'),
        'method' => ObtainMethod::Gift->value,
        'location_detail' => 'Starter-Pokémon',
        'source' => 'curated',
    ]);

    test()->artisan('pokedex:recalculate')->assertSuccessful();
}

it('stuft eine Entwicklungsstufe nicht als Bank-kritisch ein, wenn die Vorstufe käuflich ist', function () {
    importiereBisasamLinie();

    $rows = app(PokedexQuery::class)->evaluate($this->user)->keyBy(fn ($r) => $r->form->pokemon->dex_nr);

    // Bisasam: Geschenk in Let's Go, noch im Handel.
    expect($rows[1]->priority->level)->toBe(PriorityLevel::Purchasable);

    // Bisaknosp: wild nur in X (Bank-Weg), aber aus Bisasam entwickelbar.
    expect($rows[2]->priority->level)->toBe(PriorityLevel::Purchasable)
        ->and($rows[2]->priority->isUrgent())->toBeFalse();

    // Bisaflor: gar kein eigener Fundweg, läuft über dieselbe Linie.
    expect($rows[3]->priority->level)->toBe(PriorityLevel::Purchasable)
        ->and($rows[3]->priority->isUrgent())->toBeFalse();
});

it('nennt für die Entwicklungsstufen den Weg über die Vorstufe', function () {
    importiereBisasamLinie();

    $rows = app(PokedexQuery::class)->evaluate($this->user)->keyBy(fn ($r) => $r->form->pokemon->dex_nr);

    expect($rows[3]->priority->routes[0])->toContain('über Entwicklung aus Bisasam')
        ->and($rows[3]->priority->routes[0])->toContain("Let's Go");
});

it('macht die ganze Linie einfach, sobald der Nutzer das Spiel besitzt', function () {
    importiereBisasamLinie();

    $this->user->games()->attach(Game::where('slug', 'lets-go-pikachu')->value('id'));

    $rows = app(PokedexQuery::class)->evaluate($this->user->fresh())
        ->keyBy(fn ($r) => $r->form->pokemon->dex_nr);

    expect($rows[1]->priority->level)->toBe(PriorityLevel::Easy)
        ->and($rows[2]->priority->level)->toBe(PriorityLevel::Easy)
        ->and($rows[3]->priority->level)->toBe(PriorityLevel::Easy);
});

it('meldet die Linie als Bank-kritisch, wenn auch die Vorstufe nur über Bank läuft', function () {
    importiereBisasamLinie();

    // Das käufliche Spiel aus dem Spiel nehmen: jetzt bleibt nur noch X.
    Obtainability::where('source', 'curated')->delete();
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $rows = app(PokedexQuery::class)->evaluate($this->user)->keyBy(fn ($r) => $r->form->pokemon->dex_nr);

    expect($rows[2]->priority->level)->toBe(PriorityLevel::BankUrgent)
        ->and($rows[3]->priority->level)->toBe(PriorityLevel::BankUrgent)
        // Bisasam hat nun gar keine Quelle mehr.
        ->and($rows[1]->priority->level)->toBe(PriorityLevel::TradeOnly);
});

it('zeigt die richtige Zahl im Countdown-Widget auf dem Dashboard', function () {
    importiereBisasamLinie();
    Obtainability::where('source', 'curated')->delete();
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Vor der Bank-Abschaltung erledigen')
        ->assertSee('Bisaknosp');
});

it('empfiehlt auf der Detailseite die passende Anzahl Fänge', function () {
    importiereBisasamLinie();

    // Die Empfehlung ist bewusst spielunabhängig (spec.md 2.8, Datengrundlage
    // `obtainable_directly`): Bisaknosp ist in dieser Datenlage wild fangbar,
    // also splittet der Plan statt alles aus Bisasam zu entwickeln.
    $this->actingAs($this->user)
        ->get(route('pokedex.show', Pokemon::where('dex_nr', 1)->first()))
        ->assertOk()
        ->assertSee('Mehrfach-Fang-Empfehlung')
        ->assertSee('Fange 1× Bisasam')
        ->assertSee('Fange 2× Bisaknosp');
});

it('empfiehlt drei Fänge der Basis, wenn keine Zwischenstufe fangbar ist', function () {
    importiereBisasamLinie();

    // Den Wildfang von Bisaknosp entfernen: jetzt führt alles über Bisasam.
    Obtainability::where('source', 'pokeapi')->delete();
    $this->artisan('pokedex:recalculate')->assertSuccessful();

    $this->actingAs($this->user)
        ->get(route('pokedex.show', Pokemon::where('dex_nr', 1)->first()))
        ->assertOk()
        ->assertSee('Fange 3× Bisasam');
});
