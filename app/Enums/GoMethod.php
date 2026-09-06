<?php

namespace App\Enums;

/**
 * Bezugsweg in Pokémon GO (spec.md 2.4).
 */
enum GoMethod: string
{
    case Wild = 'wild';
    case Egg = 'egg';
    case Raid = 'raid';
    case CommunityDay = 'community_day';
    case Research = 'research';
    case EvolutionOnly = 'evolution_only';
    case NotAvailable = 'not_available';

    public function label(): string
    {
        return match ($this) {
            self::Wild => 'Wild fangbar',
            self::Egg => 'Aus Ei',
            self::Raid => 'Raid',
            self::CommunityDay => 'Community Day',
            self::Research => 'Forschung',
            self::EvolutionOnly => 'Nur durch Entwicklung',
            self::NotAvailable => 'In GO nicht verfügbar',
        };
    }

    /** Steht das Pokémon in GO überhaupt zur Verfügung? */
    public function isAvailable(): bool
    {
        return $this !== self::NotAvailable;
    }

    /**
     * Kann der Nutzer es realistisch selbst beschaffen, ohne auf ein Event zu warten?
     * Nur solche Wege entschärfen die Bank-Deadline (spec.md 2.4, letzter Punkt).
     */
    public function isReliablyFarmable(): bool
    {
        return in_array($this, [self::Wild, self::Egg, self::Raid, self::EvolutionOnly], true);
    }
}
