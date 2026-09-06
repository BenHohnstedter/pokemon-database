<?php

namespace App\Enums;

/**
 * Konsolen-Plattform eines Spiels (spec.md 2.6, 3).
 */
enum Platform: string
{
    case GameBoy = 'gb';
    case GameBoyColor = 'gbc';
    case GameBoyAdvance = 'gba';
    case NintendoDs = 'nds';
    case Nintendo3ds = '3ds';
    case Switch = 'switch';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::GameBoy => 'Game Boy',
            self::GameBoyColor => 'Game Boy Color',
            self::GameBoyAdvance => 'Game Boy Advance',
            self::NintendoDs => 'Nintendo DS',
            self::Nintendo3ds => 'Nintendo 3DS',
            self::Switch => 'Nintendo Switch',
            self::Mobile => 'Smartphone',
        };
    }

    /**
     * "Alte Hardware" im Sinne der Prioritäts-Engine (spec.md 2.7, Stufe 🟠/🔴):
     * alles vor der Switch-Ära.
     */
    public function isLegacyHardware(): bool
    {
        return match ($this) {
            self::Switch, self::Mobile => false,
            default => true,
        };
    }
}
