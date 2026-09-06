<?php

/**
 * Tages-Login-Streak (spec.md 2.9).
 */

use App\Listeners\TrackLoginStreak;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

function meldeAn(User $user): void
{
    app(TrackLoginStreak::class)->handle(new Login('web', $user, false));
}

it('startet die Serie beim ersten Login', function () {
    Carbon::setTestNow('2026-09-06 10:00:00');
    $user = User::factory()->create();

    meldeAn($user);

    expect($user->fresh()->login_streak)->toBe(1)
        ->and($user->fresh()->last_login_date->toDateString())->toBe('2026-09-06');
});

it('zählt einen weiteren Tag nur einmal, egal wie oft man sich anmeldet', function () {
    Carbon::setTestNow('2026-09-06 10:00:00');
    $user = User::factory()->create();

    meldeAn($user);
    meldeAn($user->fresh());
    meldeAn($user->fresh());

    expect($user->fresh()->login_streak)->toBe(1);
});

it('verlängert die Serie am Folgetag', function () {
    Carbon::setTestNow('2026-09-06 10:00:00');
    $user = User::factory()->create();
    meldeAn($user);

    Carbon::setTestNow('2026-09-07 08:00:00');
    meldeAn($user->fresh());

    expect($user->fresh()->login_streak)->toBe(2);
});

it('setzt die Serie nach einer Lücke zurück', function () {
    Carbon::setTestNow('2026-09-06 10:00:00');
    $user = User::factory()->create();
    meldeAn($user);

    Carbon::setTestNow('2026-09-07 10:00:00');
    meldeAn($user->fresh());

    Carbon::setTestNow('2026-09-10 10:00:00');
    meldeAn($user->fresh());

    expect($user->fresh()->login_streak)->toBe(1);
});

it('schaltet den Wochenstreak-Orden frei', function () {
    $this->seed(AchievementSeeder::class);

    $user = User::factory()->create();

    for ($tag = 6; $tag <= 12; $tag++) {
        Carbon::setTestNow("2026-09-{$tag} 10:00:00");
        meldeAn($user->fresh());
    }

    expect($user->fresh()->login_streak)->toBe(7)
        ->and($user->fresh()->achievements()->pluck('key'))->toContain('streak_7');
});
