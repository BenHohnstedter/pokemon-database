<?php

/**
 * Trainer-Level und XP-Kurve (spec.md 2.9).
 */

use App\Models\User;

it('startet bei Level 1 ohne XP', function () {
    expect(User::levelForXp(0))->toBe(1)
        ->and(User::xpForLevel(1))->toBe(0);
});

it('lässt den Levelabstand mit steigendem Level wachsen', function () {
    $abstaende = [];

    for ($level = 1; $level <= 5; $level++) {
        $abstaende[] = User::xpForLevel($level + 1) - User::xpForLevel($level);
    }

    expect($abstaende)->toBe([100, 200, 300, 400, 500]);
});

it('ordnet XP dem richtigen Level zu', function (int $xp, int $erwartet) {
    expect(User::levelForXp($xp))->toBe($erwartet);
})->with([
    [0, 1],
    [99, 1],
    [100, 2],
    [299, 2],
    [300, 3],
    [600, 4],
]);

it('deckelt bei Level 100', function () {
    expect(User::levelForXp(999_999_999))->toBe(User::MAX_LEVEL);
});

it('rechnet den Fortschritt innerhalb des Levels aus', function () {
    $user = new User(['xp' => 150]);

    expect($user->level())->toBe(2)
        ->and($user->levelProgressPercent())->toBe(25.0)   // 50 von 200 XP
        ->and($user->xpToNextLevel())->toBe(150);
});

it('meldet auf Maximallevel keinen weiteren Bedarf', function () {
    $user = new User(['xp' => User::xpForLevel(User::MAX_LEVEL)]);

    expect($user->level())->toBe(User::MAX_LEVEL)
        ->and($user->levelProgressPercent())->toBe(100.0)
        ->and($user->xpToNextLevel())->toBe(0);
});
