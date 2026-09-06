<?php

/**
 * Tests der Prioritäts-Engine (spec.md 2.7) – die Tabelle dort ist die
 * Referenz, jeder Case hat hier mindestens einen Test.
 */

use App\Enums\Difficulty;
use App\Enums\GoRegion;
use App\Enums\PriorityLevel;
use App\Models\GoAvailability;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Services\PriorityEngine;
use App\Support\EvolutionFallback;
use App\Support\PriorityContext;
use Database\Factories\GameFactory;
use Illuminate\Support\Collection;

beforeEach(function () {
    resetDexSequence();
    $this->engine = new PriorityEngine;
});

/** Baut ein Pokémon mit Basisform und liefert die Form zurück. */
function form(array $attributes = []): PokemonForm
{
    return Pokemon::factory()->withBaseForm()->create($attributes)->baseForm;
}

/** Bezugsquellen einer Form, so wie die Engine sie erwartet (mit game-Relation). */
function sources(PokemonForm $form): Collection
{
    return Obtainability::query()
        ->with('game')
        ->where('pokemon_id', $form->pokemon_id)
        ->get();
}

it('meldet Besessenes ohne weitere Prüfung als besessen', function () {
    $form = form();

    $result = $this->engine->evaluate($form, PriorityContext::guest(), owned: true);

    expect($result)->toHavePriority(PriorityLevel::Owned)
        ->and($result->reachableWithCurrentGames())->toBeTrue();
});

it('stuft ein Pokémon als einfach ein, wenn der Nutzer ein passendes Spiel besitzt', function () {
    $form = form();
    $game = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$game->id]),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::Easy)
        ->and($result->routes)->not->toBeEmpty()
        ->and($result->reachableWithCurrentGames())->toBeTrue();
});

it('stuft ein Pokémon als kaufbar ein, wenn das Spiel noch im Handel ist', function () {
    $form = form();
    $game = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: []),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::Purchasable)
        ->and($result->reachableWithCurrentGames())->toBeFalse();
});

it('meldet Bank-Dringlichkeit, wenn der einzige Weg nach HOME über Pokémon Bank führt', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: []),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::BankUrgent)
        ->and($result->isUrgent())->toBeTrue()
        ->and($result->consoles)->toContain('Nintendo 3DS');
});

it('entschärft die Bank-Deadline, wenn das Pokémon in GO farmbar ist', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);
    $go = GoAvailability::factory()->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [], goRegion: GoRegion::Europa),
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    // GO ist weltweit verfügbar, also greift schon die "einfach"-Regel.
    expect($result)->toHavePriority(PriorityLevel::Easy)
        ->and($result->goRescuable)->toBeTrue();
});

it('bleibt bei Bank-Dringlichkeit, wenn GO das Pokémon gar nicht führt', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);
    $go = GoAvailability::factory()->notAvailable()->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext,
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    expect($result)->toHavePriority(PriorityLevel::BankUrgent);
});

it('stuft ein regional exklusives GO-Pokémon außerhalb der eigenen Region nicht als einfach ein', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);
    $go = GoAvailability::factory()
        ->exclusiveTo([GoRegion::Ozeanien])
        ->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(goRegion: GoRegion::Europa),
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    // Tausch in GO bleibt möglich, deshalb keine harte Bank-Deadline …
    expect($result)->toHavePriority(PriorityLevel::OldHardware)
        ->and($result->goRescuable)->toBeTrue();
});

it('stuft dasselbe Pokémon als einfach ein, wenn der Nutzer in der richtigen Region wohnt', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);
    $go = GoAvailability::factory()
        ->exclusiveTo([GoRegion::Ozeanien])
        ->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(goRegion: GoRegion::Ozeanien),
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    expect($result)->toHavePriority(PriorityLevel::Easy);
});

it('behandelt ein Community-Day-Pokémon nicht als verlässlichen GO-Weg', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);
    $go = GoAvailability::factory()->communityDayOnly()->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext,
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    expect($result)->toHavePriority(PriorityLevel::BankUrgent);
});

it('meldet ein abgelaufenes Event als nur noch per Tausch erreichbar', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->expiredEvent()->create([
        'pokemon_id' => $form->pokemon_id,
        'game_id' => $game->id,
    ]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext,
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::TradeOnly)
        ->and($result->difficulty)->toBe(Difficulty::SehrSchwer)
        ->and($result->obtainableAtAll)->toBeFalse();
});

it('zählt reine Transfer-Einträge nicht als Bezugsquelle', function () {
    $form = form();
    $game = GameFactory::new()->modern()->create();
    Obtainability::factory()->transferOnly()->create([
        'pokemon_id' => $form->pokemon_id,
        'game_id' => $game->id,
    ]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$game->id]),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::TradeOnly);
});

it('erbt den Weg der fangbaren Vorstufe, wenn die Stufe nur per Entwicklung erreichbar ist', function () {
    $basis = Pokemon::factory()->withBaseForm()->create();
    $game = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $basis->id, 'game_id' => $game->id]);

    $entwicklung = Pokemon::factory()->withBaseForm()->evolutionOnly($basis)->create();

    $result = $this->engine->evaluate(
        $entwicklung->baseForm,
        new PriorityContext(ownedGameIds: [$game->id]),
        owned: false,
        obtainabilities: collect(),
        fallbacks: [new EvolutionFallback(
            $basis->name_de,
            Obtainability::with('game')->where('pokemon_id', $basis->id)->get(),
        )],
    );

    expect($result)->toHavePriority(PriorityLevel::Easy)
        // Erst fangen, dann entwickeln – eine Stufe schwerer als der reine Fang.
        ->and($result->difficulty)->toBe(Difficulty::Mittel)
        ->and($result->routes[0])->toContain('über Entwicklung aus '.$basis->name_de);
});

it('meldet eine Stufe ohne jede Quelle und ohne fangbare Vorstufe als Tauschfall', function () {
    $form = form();

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext,
        owned: false,
        obtainabilities: collect(),
    );

    expect($result)->toHavePriority(PriorityLevel::TradeOnly)
        ->and($result->obtainableAtAll)->toBeFalse();
});

it('bevorzugt den einfachsten Weg, wenn mehrere Quellen existieren', function () {
    $form = form();
    $besessen = GameFactory::new()->bankOnly()->create();
    $modern = GameFactory::new()->modern()->create();

    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $besessen->id]);
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $modern->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: []),
        owned: false,
        obtainabilities: sources($form),
    );

    // Das kaufbare Spiel gewinnt gegen die Bank-Route.
    expect($result)->toHavePriority(PriorityLevel::Purchasable);
});

it('meldet alte Hardware ohne Bank-Zwang als orange statt rot', function () {
    $form = form();
    $game = GameFactory::new()->legacyWithoutBank()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext,
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::OldHardware)
        ->and($result->isUrgent())->toBeFalse();
});

it('sortiert die Stufen nach Dringlichkeit', function () {
    $sorted = array_map(
        fn (PriorityLevel $l) => $l->value,
        PriorityLevel::byUrgencyDesc(),
    );

    expect($sorted[0])->toBe(PriorityLevel::BankUrgent->value)
        ->and(end($sorted))->toBe(PriorityLevel::Owned->value);
});

it('zieht den Umweg über die Vorstufe auch dann heran, wenn die Stufe eine eigene Quelle hat', function () {
    // Bisaknosp-Fall: wild nur in einem Bank-Spiel, aber aus einem Bisasam
    // eines noch käuflichen Spiels entwickelbar.
    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    $kaufbar = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $basis->id, 'game_id' => $kaufbar->id]);

    $mitte = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisaknosp']);
    Obtainability::factory()->create([
        'pokemon_id' => $mitte->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);

    $result = $this->engine->evaluate(
        $mitte->baseForm,
        new PriorityContext(ownedGameIds: []),
        owned: false,
        obtainabilities: sources($mitte->baseForm),
        fallbacks: [new EvolutionFallback(
            $basis->name_de,
            Obtainability::with('game')->where('pokemon_id', $basis->id)->get(),
        )],
    );

    expect($result)->toHavePriority(PriorityLevel::Purchasable)
        ->and($result->isUrgent())->toBeFalse()
        ->and($result->routes[0])->toContain('über Entwicklung aus Bisasam');
});

it('behält den eigenen Weg, wenn er günstiger ist als der über die Vorstufe', function () {
    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vorstufe']);
    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);

    $stufe = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Endstufe']);
    $meins = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $stufe->id, 'game_id' => $meins->id]);

    $result = $this->engine->evaluate(
        $stufe->baseForm,
        new PriorityContext(ownedGameIds: [$meins->id]),
        owned: false,
        obtainabilities: sources($stufe->baseForm),
        fallbacks: [new EvolutionFallback(
            $basis->name_de,
            Obtainability::with('game')->where('pokemon_id', $basis->id)->get(),
        )],
    );

    expect($result)->toHavePriority(PriorityLevel::Easy)
        ->and($result->routes[0])->not->toContain('über Entwicklung');
});

it('lässt die Schwierigkeit nie unter den Wert am Pokémon fallen', function () {
    // Mew-Fall: formal ein Wildfang, in Wahrheit ein abgelaufenes Event.
    $pokemon = Pokemon::factory()->withBaseForm()->mythical()->create([
        'difficulty' => Difficulty::SehrSchwer,
    ]);
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => GameFactory::new()->modern()->create()->id,
    ]);

    $result = $this->engine->evaluate(
        $pokemon->baseForm,
        new PriorityContext,
        owned: false,
        obtainabilities: sources($pokemon->baseForm),
    );

    expect($result->difficulty)->toBe(Difficulty::SehrSchwer)
        ->and($result)->toHavePriority(PriorityLevel::Purchasable);
});

it('meldet die Bank-Frist auch dann, wenn der Nutzer das Spiel besitzt', function () {
    // Der Fall, der vorher komplett aus dem Countdown verschwand: Du kommst
    // problemlos an das Pokémon, musst es aber trotzdem über Bank übertragen.
    $form = form();
    $meins = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $meins->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$meins->id]),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result)->toHavePriority(PriorityLevel::Easy)
        ->and($result->affectedByBankDeadline())->toBeTrue()
        ->and($result->bankDeadlineButReachable())->toBeTrue()
        // Die Stufe bleibt grün – 🔴 heißt weiterhin "Dir fehlt noch etwas".
        ->and($result->isUrgent())->toBeFalse()
        ->and($result->reason)->toContain('Vor der Abschaltung');
});

it('meldet keine Bank-Frist, wenn das besessene Spiel direkt an HOME hängt', function () {
    $form = form();
    $meins = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $meins->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$meins->id]),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result->affectedByBankDeadline())->toBeFalse();
});

it('meldet keine Bank-Frist, wenn eines der besessenen Spiele ohne Bank auskommt', function () {
    $form = form();
    $alt = GameFactory::new()->bankOnly()->create();
    $neu = GameFactory::new()->modern()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $alt->id]);
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $neu->id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$alt->id, $neu->id]),
        owned: false,
        obtainabilities: sources($form),
    );

    expect($result->affectedByBankDeadline())->toBeFalse();
});

it('hebt die Bank-Frist auf, wenn GO das Pokémon führt', function () {
    $form = form();
    $meins = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $meins->id]);
    $go = GoAvailability::factory()->create(['pokemon_id' => $form->pokemon_id]);

    $result = $this->engine->evaluate(
        $form,
        new PriorityContext(ownedGameIds: [$meins->id]),
        owned: false,
        obtainabilities: sources($form),
        go: $go,
    );

    expect($result->affectedByBankDeadline())->toBeFalse();
});

it('markiert 🔴 weiterhin als von der Frist betroffen', function () {
    $form = form();
    $game = GameFactory::new()->bankOnly()->create();
    Obtainability::factory()->create(['pokemon_id' => $form->pokemon_id, 'game_id' => $game->id]);

    $result = $this->engine->evaluate($form, new PriorityContext, owned: false, obtainabilities: sources($form));

    expect($result)->toHavePriority(PriorityLevel::BankUrgent)
        ->and($result->affectedByBankDeadline())->toBeTrue()
        // Diese Gruppe kann man NICHT selbst holen – dafür fehlt das Spiel.
        ->and($result->bankDeadlineButReachable())->toBeFalse();
});
