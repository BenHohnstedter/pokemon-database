<?php

/**
 * Mehrfach-Fang-Empfehlung für Entwicklungsreihen (spec.md 2.8).
 *
 * Referenzbeispiel aus der Spec:
 * "Fange 3× Bisasam: 1× so lassen, 1× zu Bisaknosp entwickeln (und stoppen),
 *  1× bis Bisaflor weiterentwickeln."
 */

use App\Models\Pokemon;
use App\Services\MultiCatchCalculator;

beforeEach(function () {
    resetDexSequence();
    $this->calculator = new MultiCatchCalculator;
});

/**
 * Baut eine dreistufige Linie, bei der nur die erste Stufe wild fangbar ist.
 *
 * @return array{0:Pokemon,1:Pokemon,2:Pokemon}
 */
function dreistufigeLinie(): array
{
    $basis = Pokemon::factory()->create(['name_de' => 'Bisasam', 'obtainable_directly' => true]);
    $mitte = Pokemon::factory()->evolutionOnly($basis)->create(['name_de' => 'Bisaknosp']);
    $final = Pokemon::factory()->evolutionOnly($mitte)->create(['name_de' => 'Bisaflor']);

    return [$basis, $mitte, $final];
}

it('empfiehlt drei Exemplare der Basisform für eine komplett fehlende Dreierlinie', function () {
    [$basis, $mitte, $final] = dreistufigeLinie();

    $plan = $this->calculator->plan(collect([$basis, $mitte, $final]));

    expect($plan->totalCatches())->toBe(3)
        ->and($plan->steps)->toHaveCount(1)
        ->and($plan->steps[0]->origin->name_de)->toBe('Bisasam')
        ->and($plan->unreachable)->toBeEmpty();
});

it('formuliert die Empfehlung wie in der Spec', function () {
    [$basis, $mitte, $final] = dreistufigeLinie();

    $plan = $this->calculator->plan(collect([$basis, $mitte, $final]));

    expect($plan->sentences()[0])->toBe(
        'Fange 3× Bisasam: 1× so lassen, 1× zu Bisaknosp entwickeln (und stoppen), 1× bis Bisaflor weiterentwickeln.'
    );
});

it('lässt bereits besessene Stufen aus der Rechnung', function () {
    [$basis, $mitte, $final] = dreistufigeLinie();

    $plan = $this->calculator->plan(
        collect([$basis, $mitte, $final]),
        ownedDexNumbers: [$basis->dex_nr, $mitte->dex_nr],
    );

    expect($plan->totalCatches())->toBe(1)
        ->and($plan->sentences()[0])->toBe('Fange 1× Bisasam: 1× bis Bisaflor weiterentwickeln.');
});

it('gibt einen leeren Plan zurück, wenn die Linie komplett ist', function () {
    [$basis, $mitte, $final] = dreistufigeLinie();

    $plan = $this->calculator->plan(
        collect([$basis, $mitte, $final]),
        ownedDexNumbers: [$basis->dex_nr, $mitte->dex_nr, $final->dex_nr],
    );

    expect($plan->isEmpty())->toBeTrue()
        ->and($plan->totalCatches())->toBe(0);
});

it('gruppiert nach der jeweils nächsten fangbaren Vorstufe', function () {
    // Mittelstufe ist selbst wild fangbar – dann reichen zwei Exemplare
    // der Basis plus eines der Mittelstufe nicht, sondern es splittet sich auf.
    $basis = Pokemon::factory()->create(['name_de' => 'Basis', 'obtainable_directly' => true]);
    $mitte = Pokemon::factory()->create([
        'name_de' => 'Mitte',
        'evolves_from_id' => $basis->id,
        'obtainable_directly' => true,
    ]);
    $final = Pokemon::factory()->evolutionOnly($mitte)->create(['name_de' => 'Final']);

    $plan = $this->calculator->plan(collect([$basis, $mitte, $final]));

    expect($plan->steps)->toHaveCount(2)
        ->and($plan->totalCatches())->toBe(3)
        ->and($plan->steps[0]->origin->name_de)->toBe('Basis')
        ->and($plan->steps[0]->count())->toBe(1)
        ->and($plan->steps[1]->origin->name_de)->toBe('Mitte')
        ->and($plan->steps[1]->count())->toBe(2);
});

it('markiert Stufen ohne jede fangbare Vorstufe als unerreichbar', function () {
    $basis = Pokemon::factory()->create(['name_de' => 'Basis', 'obtainable_directly' => false]);
    $final = Pokemon::factory()->evolutionOnly($basis)->create(['name_de' => 'Final']);

    $plan = $this->calculator->plan(collect([$basis, $final]));

    expect($plan->steps)->toBeEmpty()
        ->and($plan->unreachable)->toHaveCount(2);
});

it('kommt mit Verzweigungen wie Evoli klar', function () {
    $evoli = Pokemon::factory()->create(['name_de' => 'Evoli', 'obtainable_directly' => true]);
    $aquana = Pokemon::factory()->evolutionOnly($evoli)->create(['name_de' => 'Aquana']);
    $blitza = Pokemon::factory()->evolutionOnly($evoli)->create(['name_de' => 'Blitza']);
    $flamara = Pokemon::factory()->evolutionOnly($evoli)->create(['name_de' => 'Flamara']);

    $plan = $this->calculator->plan(collect([$evoli, $aquana, $blitza, $flamara]));

    expect($plan->totalCatches())->toBe(4)
        ->and($plan->steps)->toHaveCount(1)
        ->and($plan->steps[0]->origin->name_de)->toBe('Evoli');
});
