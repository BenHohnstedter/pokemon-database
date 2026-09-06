<?php

namespace App\Enums;

/**
 * Weltregionen für die GO-Regionalexklusivität (spec.md 2.4).
 * "Weltweit" bedeutet: keine regionale Einschränkung.
 */
enum GoRegion: string
{
    case Weltweit = 'weltweit';
    case Europa = 'europa';
    case Nordamerika = 'nordamerika';
    case Suedamerika = 'suedamerika';
    case Mittelamerika = 'mittelamerika';
    case Afrika = 'afrika';
    case Ostasien = 'ostasien';
    case Suedasien = 'suedasien';
    case Suedostasien = 'suedostasien';
    case Naherosten = 'naher_osten';
    case Ozeanien = 'ozeanien';

    public function label(): string
    {
        return match ($this) {
            self::Weltweit => 'Weltweit',
            self::Europa => 'Europa',
            self::Nordamerika => 'Nordamerika',
            self::Suedamerika => 'Südamerika',
            self::Mittelamerika => 'Mittelamerika & Karibik',
            self::Afrika => 'Afrika',
            self::Ostasien => 'Ostasien',
            self::Suedasien => 'Südasien',
            self::Suedostasien => 'Südostasien',
            self::Naherosten => 'Naher Osten',
            self::Ozeanien => 'Ozeanien / Australien',
        };
    }

    /** Auswahlliste für die Nutzereinstellung "GO-Region". */
    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $r) => $r === self::Weltweit)
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
