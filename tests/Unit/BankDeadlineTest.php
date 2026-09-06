<?php

/**
 * Countdown-Widget zur Pokémon-Bank-Abschaltung (spec.md 2.7).
 */

use App\Services\BankDeadline;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set('pokedex.bank_shutdown_at', '2027-02-26 23:59:59');
    $this->deadline = new BankDeadline;
});

afterEach(fn () => Carbon::setTestNow());

it('zählt die verbleibenden Tage bis zur Abschaltung', function () {
    Carbon::setTestNow('2027-02-16 12:00:00');

    expect($this->deadline->daysLeft())->toBe(10)
        ->and($this->deadline->hasPassed())->toBeFalse()
        ->and($this->deadline->humanLabel())->toBe('Noch 10 Tage');
});

it('benutzt die Einzahl beim letzten Tag', function () {
    Carbon::setTestNow('2027-02-25 08:00:00');

    expect($this->deadline->humanLabel())->toBe('Noch 1 Tag');
});

it('meldet die Frist als abgelaufen und gibt nie negative Tage zurück', function () {
    Carbon::setTestNow('2027-03-01 00:00:00');

    expect($this->deadline->hasPassed())->toBeTrue()
        ->and($this->deadline->daysLeft())->toBe(0)
        ->and($this->deadline->humanLabel())->toBe('Pokémon Bank ist abgeschaltet')
        ->and($this->deadline->elapsedPercent())->toBe(100.0);
});

it('liefert den verstrichenen Anteil zwischen Ankündigung und Stichtag', function () {
    Carbon::setTestNow('2026-07-01 00:00:00');
    expect($this->deadline->elapsedPercent())->toBe(0.0);

    Carbon::setTestNow('2026-11-14 12:00:00');
    expect($this->deadline->elapsedPercent())->toBeGreaterThan(40.0)
        ->and($this->deadline->elapsedPercent())->toBeLessThan(60.0);
});

it('respektiert einen abweichenden Stichtag aus der Konfiguration', function () {
    config()->set('pokedex.bank_shutdown_at', '2027-02-27 12:00:00');
    Carbon::setTestNow('2027-02-20 00:00:00');

    expect((new BankDeadline)->daysLeft())->toBe(7);
});
