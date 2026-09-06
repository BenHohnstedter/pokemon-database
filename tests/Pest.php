<?php

use App\Enums\PriorityLevel;
use Database\Factories\PokemonFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Auch die Unit-Tests bekommen TestCase + RefreshDatabase: die Geschäftslogik
| dieses Projekts (Prioritäts-Engine, Fortschritt, Mehrfach-Fang) arbeitet auf
| Eloquent-Modellen, und SQLite in-memory ist schnell genug dafür.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Ohne das bräuchte jeder View-Test ein gebautes Vite-Manifest unter
    // public/build – Tests sollen aber ohne vorherigen npm-Build laufen.
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toHavePriority', function (PriorityLevel $expected) {
    expect($this->value->level)->toBe(
        $expected,
        "Erwartet: {$expected->label()}, bekommen: {$this->value->level->label()} ({$this->value->reason})"
    );

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Dex-Nummern der Factory zurücksetzen, damit jeder Test bei #1 beginnt.
 * RefreshDatabase leert die Tabellen, aber nicht den statischen Zähler.
 */
function resetDexSequence(): void
{
    PokemonFactory::resetSequence();
}
