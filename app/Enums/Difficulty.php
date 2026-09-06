<?php

namespace App\Enums;

/**
 * Schwierigkeitsgrad der Beschaffung, unabhängig vom Spielebesitz des Nutzers
 * (spec.md 2.8).
 */
enum Difficulty: string
{
    case Leicht = 'leicht';
    case Mittel = 'mittel';
    case Schwer = 'schwer';
    case SehrSchwer = 'sehr_schwer';

    public function label(): string
    {
        return match ($this) {
            self::Leicht => 'Leicht',
            self::Mittel => 'Mittel',
            self::Schwer => 'Schwer',
            self::SehrSchwer => 'Sehr schwer / kaum noch möglich',
        };
    }

    /** Zum Sortieren: je höher, desto schwieriger. */
    public function weight(): int
    {
        return match ($this) {
            self::Leicht => 1,
            self::Mittel => 2,
            self::Schwer => 3,
            self::SehrSchwer => 4,
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Leicht => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40',
            self::Mittel => 'bg-amber-500/15 text-amber-300 border-amber-500/40',
            self::Schwer => 'bg-orange-500/15 text-orange-300 border-orange-500/40',
            self::SehrSchwer => 'bg-rose-500/15 text-rose-300 border-rose-500/40',
        };
    }
}
