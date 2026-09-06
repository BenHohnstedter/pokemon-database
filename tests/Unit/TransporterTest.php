<?php

/**
 * Poké Transporter als eigene Hürde vor Pokémon Bank (spec.md 2.7).
 *
 * Gen 6 und 7 laden selbst zu Bank hoch. Alles Ältere braucht die 3DS-App:
 * Gen 1/2 aus der Virtual Console und Gen 5 direkt, Gen 3 und 4 am Ende ihrer
 * Transferkette. Wer sie nicht hat, kommt aus diesen Titeln überhaupt nicht
 * nach HOME – dann ist nicht die Frist das Problem, sondern die Stufe davor.
 */

use App\Enums\PriorityLevel;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\User;
use App\Services\PriorityEngine;
use App\Support\PriorityContext;
use App\Support\PriorityResult;
use Database\Factories\GameFactory;
use Database\Seeders\GameSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->engine = app(PriorityEngine::class);
});

function bewerte(Pokemon $pokemon, PriorityContext $context): PriorityResult
{
    return test()->engine->evaluate($pokemon->baseForm, $context, owned: false);
}

it('markiert im Seeder genau die Titel vor Generation 6', function () {
    test()->seed(GameSeeder::class);

    $mit = Game::where('needs_transporter', true)->pluck('slug');
    $ohne = Game::where('needs_transporter', false)->pluck('slug');

    expect($mit)->toContain('black-2', 'red', 'firered', 'heartgold')
        // Gen 6/7 laden direkt zu Bank hoch.
        ->and($ohne)->toContain('x', 'omega-ruby', 'ultra-sun', 'sword')
        // Grün hat gar keinen Transferweg – Transporter ändert daran nichts.
        ->and($ohne)->toContain('green');
});

it('bleibt mit Transporter ein dringender Bank-Fall', function () {
    $game = GameFactory::new()->needsTransporter()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $ergebnis = bewerte($pokemon, new PriorityContext(hasTransporter: true));

    expect($ergebnis)->toHavePriority(PriorityLevel::BankUrgent)
        ->and($ergebnis->affectedByBankDeadline())->toBeTrue()
        ->and($ergebnis->transporterMissing)->toBeFalse();
});

it('wird ohne Transporter unerreichbar statt dringend', function () {
    $game = GameFactory::new()->needsTransporter()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $ergebnis = bewerte($pokemon, new PriorityContext(hasTransporter: false));

    expect($ergebnis)->toHavePriority(PriorityLevel::TradeOnly)
        // Keine Frist, wo der Weg ohnehin verschlossen ist.
        ->and($ergebnis->affectedByBankDeadline())->toBeFalse()
        ->and($ergebnis->transporterMissing)->toBeTrue()
        ->and($ergebnis->reason)->toContain('Poké Transporter');
});

it('lässt Gen-6-Titel auch ohne Transporter als Bank-Fall stehen', function () {
    // X/Y und ORAS laden selbst zu Bank hoch – die App ist dafür nicht nötig.
    $game = GameFactory::new()->bankOnly()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $ergebnis = bewerte($pokemon, new PriorityContext(hasTransporter: false));

    expect($ergebnis)->toHavePriority(PriorityLevel::BankUrgent)
        ->and($ergebnis->affectedByBankDeadline())->toBeTrue();
});

it('hilft ein besessenes Spiel nicht, wenn der Transporter fehlt', function () {
    // Schwarz im Regal, aber kein Weg nach HOME: "einfach" wäre gelogen.
    $game = GameFactory::new()->needsTransporter()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $ergebnis = bewerte($pokemon, new PriorityContext(
        ownedGameIds: [$game->id],
        hasTransporter: false,
    ));

    expect($ergebnis)->toHavePriority(PriorityLevel::TradeOnly)
        ->and($ergebnis->transporterMissing)->toBeTrue();
});

it('nimmt weiterhin den Weg über ein Spiel ohne Transporter-Zwang', function () {
    $alt = GameFactory::new()->needsTransporter()->create();
    $neu = GameFactory::new()->modern()->create();

    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $alt->id]);
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $neu->id]);

    $ergebnis = bewerte($pokemon, new PriorityContext(hasTransporter: false));

    expect($ergebnis)->toHavePriority(PriorityLevel::Purchasable)
        ->and($ergebnis->affectedByBankDeadline())->toBeFalse();
});

it('ist standardmäßig vorhanden, damit sich für Bestandsnutzer nichts ändert', function () {
    expect((new PriorityContext)->hasTransporter)->toBeTrue()
        ->and(User::factory()->create()->settingsOrDefault()->has_poke_transporter)
        ->toBeTrue();
});
