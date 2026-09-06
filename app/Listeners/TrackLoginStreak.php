<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AchievementService;
use Illuminate\Auth\Events\Login;

/**
 * Tages-Login-Streak (spec.md 2.9).
 *
 * Zählt nur den Kalendertag, nicht die Anzahl der Logins: wer sich fünfmal am
 * Tag anmeldet, bekommt trotzdem nur einen Streak-Tag.
 */
class TrackLoginStreak
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $heute = now()->startOfDay();
        $zuletzt = $user->last_login_date?->startOfDay();

        if ($zuletzt !== null && $zuletzt->equalTo($heute)) {
            return; // Heute schon gezählt.
        }

        $user->forceFill([
            // Genau ein Tag Abstand setzt die Serie fort, jede größere Lücke bricht sie.
            'login_streak' => $zuletzt !== null && $zuletzt->equalTo($heute->copy()->subDay())
                ? $user->login_streak + 1
                : 1,
            'last_login_date' => $heute,
        ])->save();

        $this->achievements->sync($user);
    }
}
