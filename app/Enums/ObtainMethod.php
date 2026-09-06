<?php

namespace App\Enums;

/**
 * Wie ein Pokémon in einem konkreten Spiel zu bekommen ist (spec.md 2.3).
 */
enum ObtainMethod: string
{
    case Wild = 'wild';
    case Gift = 'gift';
    case Trade = 'trade';
    case Egg = 'egg';
    case Evolution = 'evolution';
    case Event = 'event';
    case Raid = 'raid';
    case Fossil = 'fossil';
    case HoneyTree = 'honey_tree';
    case Roaming = 'roaming';
    case StaticEncounter = 'static';
    /** Kommt im Spiel selbst nicht vor, nur per Transfer aus älteren Titeln. */
    case TransferOnly = 'transfer_only';

    public function label(): string
    {
        return match ($this) {
            self::Wild => 'Wildfang',
            self::Gift => 'Geschenk',
            self::Trade => 'Tausch (im Spiel)',
            self::Egg => 'Ei / Zucht',
            self::Evolution => 'Entwicklung',
            self::Event => 'Event (zeitlich begrenzt)',
            self::Raid => 'Raid / Dyna-Raid',
            self::Fossil => 'Fossil-Wiederbelebung',
            self::HoneyTree => 'Honigbaum',
            self::Roaming => 'Wanderndes Pokémon',
            self::StaticEncounter => 'Fester Encounter',
            self::TransferOnly => 'Nur per Transfer',
        };
    }

    /**
     * Zählt diese Methode als "direkter Fundweg"? Wichtig für die
     * Mehrfach-Fang-Empfehlung (spec.md 2.8): Entwicklung ist kein direkter Weg.
     */
    public function isDirect(): bool
    {
        return match ($this) {
            self::Evolution, self::TransferOnly => false,
            default => true,
        };
    }

    /** Basis-Schwierigkeit, wenn an der Bezugsquelle nichts Genaueres hinterlegt ist. */
    public function baseDifficulty(): Difficulty
    {
        return match ($this) {
            self::Wild, self::Gift, self::StaticEncounter, self::Fossil => Difficulty::Leicht,
            self::Egg, self::Trade, self::Roaming, self::HoneyTree, self::Raid => Difficulty::Mittel,
            self::Evolution => Difficulty::Mittel,
            self::Event, self::TransferOnly => Difficulty::Schwer,
        };
    }
}
