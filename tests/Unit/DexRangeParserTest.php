<?php

/**
 * Freitext-Masseneingabe (spec.md 2.5): "1,15,700" oder "1-50,60-63".
 */

use App\Services\DexRangeParser;

beforeEach(fn () => $this->parser = new DexRangeParser);

it('liest einzelne Nummern', function () {
    $result = $this->parser->parse('1,15,700');

    expect($result->dexNumbers)->toBe([1, 15, 700])
        ->and($result->count())->toBe(3)
        ->and($result->hasInvalid())->toBeFalse();
});

it('liest Bereiche auf', function () {
    $result = $this->parser->parse('1-5,60-63');

    expect($result->dexNumbers)->toBe([1, 2, 3, 4, 5, 60, 61, 62, 63])
        ->and($result->count())->toBe(9);
});

it('entfernt Duplikate und sortiert aufsteigend', function () {
    $result = $this->parser->parse('10,5,5,1-3,2');

    expect($result->dexNumbers)->toBe([1, 2, 3, 5, 10]);
});

it('akzeptiert Leerzeichen, Semikolon und Zeilenumbrüche als Trenner', function () {
    $result = $this->parser->parse("1; 2\n3\t4  5");

    expect($result->dexNumbers)->toBe([1, 2, 3, 4, 5]);
});

it('dreht vertauschte Bereichsgrenzen um', function () {
    $result = $this->parser->parse('5-1');

    expect($result->dexNumbers)->toBe([1, 2, 3, 4, 5])
        ->and($result->hasInvalid())->toBeFalse();
});

it('sammelt ungültige Einträge, statt abzubrechen', function () {
    $result = $this->parser->parse('1,abc,5,,7-,9');

    expect($result->dexNumbers)->toBe([1, 5, 9])
        ->and($result->invalidTokens)->toBe(['abc', '7-'])
        ->and($result->hasInvalid())->toBeTrue();
});

it('lehnt Nummern außerhalb des Dex-Bereichs ab', function () {
    $result = $this->parser->parse('0,1,1302,9999', minDex: 1, maxDex: 1302);

    expect($result->dexNumbers)->toBe([1, 1302])
        ->and($result->invalidTokens)->toBe(['0', '9999']);
});

it('lehnt absurd große Bereiche ab', function () {
    $result = $this->parser->parse('1-99999');

    expect($result->isEmpty())->toBeTrue()
        ->and($result->invalidTokens)->toBe(['1-99999']);
});

it('kommt mit leerer Eingabe klar', function () {
    expect($this->parser->parse(null)->isEmpty())->toBeTrue()
        ->and($this->parser->parse('   ')->isEmpty())->toBeTrue();
});

it('fasst das Ergebnis für die Vorschau wieder zu Bereichen zusammen', function () {
    $result = $this->parser->parse('1-50,60-63,700');

    expect($result->summary())->toBe('1–50, 60–63, 700')
        ->and($result->count())->toBe(55);
});

it('zeigt Einzelnummern in der Zusammenfassung nicht als Bereich', function () {
    expect($this->parser->parse('1,3,5')->summary())->toBe('1, 3, 5');
});
