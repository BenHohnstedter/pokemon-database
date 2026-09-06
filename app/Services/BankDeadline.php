<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Stichtag der Pokémon-Bank-Abschaltung und die Zahlen für das
 * Countdown-Widget auf dem Dashboard (spec.md 2.7).
 */
class BankDeadline
{
    public function shutdownAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(config('pokedex.bank_shutdown_at'));
    }

    public function hasPassed(): bool
    {
        return CarbonImmutable::now()->greaterThanOrEqualTo($this->shutdownAt());
    }

    /** Verbleibende volle Tage, nie negativ. */
    public function daysLeft(): int
    {
        if ($this->hasPassed()) {
            return 0;
        }

        return (int) CarbonImmutable::now()->startOfDay()->diffInDays($this->shutdownAt()->startOfDay());
    }

    /**
     * Anteil der bereits verstrichenen Zeit, gerechnet ab der offiziellen
     * Ankündigung im Sommer 2026. Speist den Fortschrittsbalken des Widgets.
     */
    public function elapsedPercent(): float
    {
        $start = CarbonImmutable::parse('2026-07-01');
        $end = $this->shutdownAt();
        $now = CarbonImmutable::now();

        if ($now->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return 100.0;
        }

        $total = $start->diffInSeconds($end);

        return $total > 0 ? round(($start->diffInSeconds($now) / $total) * 100, 1) : 0.0;
    }

    /** "Noch 173 Tage" bzw. der Hinweis, dass die Frist abgelaufen ist. */
    public function humanLabel(): string
    {
        if ($this->hasPassed()) {
            return 'Pokémon Bank ist abgeschaltet';
        }

        $days = $this->daysLeft();

        return $days === 1 ? 'Noch 1 Tag' : "Noch {$days} Tage";
    }
}
