<?php

namespace App\Enums;

/**
 * Art einer Pokémon-Form.
 *
 * Wichtig (spec.md 2.2): Mega-Entwicklungen und Gigadynamax werden bewusst NICHT
 * getrackt – das sind temporäre Kampfzustände, die HOME nicht dauerhaft speichert.
 */
enum FormType: string
{
    /** Klassische Basisform – zählt in den Hauptfortschrittsbalken. */
    case Base = 'base';

    /** Regionalform (Alola, Galar, Hisui, Paldea …) – eigener Fortschrittsbalken. */
    case Regional = 'regional';

    /** Sonstige dauerhaft speicherbare Form (z.B. Rotom-Formen, Deoxys-Formen). */
    case Other = 'other';

    public function isBase(): bool
    {
        return $this === self::Base;
    }

    public function label(): string
    {
        return match ($this) {
            self::Base => 'Basisform',
            self::Regional => 'Regionalform',
            self::Other => 'Sonderform',
        };
    }
}
