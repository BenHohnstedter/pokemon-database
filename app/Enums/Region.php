<?php

namespace App\Enums;

/**
 * Spielwelt-Region einer Regionalform (spec.md 2.2).
 */
enum Region: string
{
    case Kanto = 'kanto';
    case Johto = 'johto';
    case Hoenn = 'hoenn';
    case Sinnoh = 'sinnoh';
    case Einall = 'unova';
    case Kalos = 'kalos';
    case Alola = 'alola';
    case Galar = 'galar';
    case Hisui = 'hisui';
    case Paldea = 'paldea';

    public function label(): string
    {
        return match ($this) {
            self::Kanto => 'Kanto',
            self::Johto => 'Johto',
            self::Hoenn => 'Hoenn',
            self::Sinnoh => 'Sinnoh',
            self::Einall => 'Einall',
            self::Kalos => 'Kalos',
            self::Alola => 'Alola',
            self::Galar => 'Galar',
            self::Hisui => 'Hisui',
            self::Paldea => 'Paldea',
        };
    }
}
