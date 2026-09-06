<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

/**
 * Achievements/Badges der Trainer-Karte (spec.md 2.9).
 *
 * Die Bedingungen selbst stecken in App\Services\AchievementService – hier
 * stehen nur Beschreibung, Icon und XP-Belohnung.
 */
class AchievementSeeder extends Seeder
{
    /** key, name, beschreibung, icon, kategorie, xp, sortierung */
    public const ACHIEVEMENTS = [
        ['first_catch', 'Erster Fang', 'Markiere Dein erstes Pokémon als besessen.', '🎣', 'sammlung', 50, 10],
        ['dex_10', 'Sammler', '10 Pokémon in der Sammlung.', '📗', 'sammlung', 100, 20],
        ['dex_100', 'Fortgeschritten', '100 Pokémon in der Sammlung.', '📘', 'sammlung', 250, 30],
        ['dex_500', 'Veteran', '500 Pokémon in der Sammlung.', '📙', 'sammlung', 750, 40],
        ['dex_1000', 'Dex-Meister', '1.000 Pokémon in der Sammlung.', '📕', 'sammlung', 2000, 50],
        ['dex_complete', 'Vollständig', 'Der komplette nationale Dex ist voll.', '🏆', 'sammlung', 5000, 60],

        ['gen_1_complete', 'Kanto-Dex komplett', 'Alle Pokémon der ersten Generation.', '🔴', 'region', 500, 110],
        ['gen_2_complete', 'Johto-Dex komplett', 'Alle Pokémon der zweiten Generation.', '🟡', 'region', 500, 120],
        ['gen_3_complete', 'Hoenn-Dex komplett', 'Alle Pokémon der dritten Generation.', '🟢', 'region', 500, 130],
        ['gen_4_complete', 'Sinnoh-Dex komplett', 'Alle Pokémon der vierten Generation.', '💠', 'region', 500, 140],
        ['gen_5_complete', 'Einall-Dex komplett', 'Alle Pokémon der fünften Generation.', '⚫', 'region', 500, 150],
        ['gen_6_complete', 'Kalos-Dex komplett', 'Alle Pokémon der sechsten Generation.', '🔷', 'region', 500, 160],
        ['gen_7_complete', 'Alola-Dex komplett', 'Alle Pokémon der siebten Generation.', '🌺', 'region', 500, 170],
        ['gen_8_complete', 'Galar-Dex komplett', 'Alle Pokémon der achten Generation.', '⚔️', 'region', 500, 180],
        ['gen_9_complete', 'Paldea-Dex komplett', 'Alle Pokémon der neunten Generation.', '🟣', 'region', 500, 190],

        ['all_types', 'Typensammler', 'Von jedem der 18 Typen mindestens ein Pokémon.', '🌈', 'typen', 400, 210],

        ['shiny_1', 'Shiny-Hunter I', 'Das erste Shiny.', '✨', 'shiny', 150, 310],
        ['shiny_10', 'Shiny-Hunter II', '10 Shinys.', '✨', 'shiny', 300, 320],
        ['shiny_50', 'Shiny-Hunter III', '50 Shinys.', '✨', 'shiny', 600, 330],
        ['shiny_100', 'Shiny-Hunter IV', '100 Shinys.', '✨', 'shiny', 1200, 340],
        ['shiny_250', 'Shiny-Hunter V', '250 Shinys.', '🌟', 'shiny', 2500, 350],

        ['regional_25', 'Regionalforscher', '25 Regionalformen gesammelt.', '🗺️', 'formen', 400, 410],
        ['regional_all', 'Formenmeister', 'Alle Regionalformen gesammelt.', '🧭', 'formen', 1500, 420],

        ['bank_rescue_10', 'Bank-Retter I', '10 Pokémon mit Bank-Deadline gerettet.', '🛟', 'rettung', 500, 510],
        ['bank_rescue_50', 'Bank-Retter II', '50 Pokémon mit Bank-Deadline gerettet.', '🛟', 'rettung', 1200, 520],
        ['bank_clear', 'Deadline besiegt', 'Kein Pokémon mit Bank-Deadline mehr offen.', '⏱️', 'rettung', 3000, 530],

        ['streak_7', 'Wochenstreak', 'An 7 Tagen in Folge eingeloggt.', '🔥', 'aktivitaet', 200, 610],
        ['streak_30', 'Monatsstreak', 'An 30 Tagen in Folge eingeloggt.', '🔥', 'aktivitaet', 800, 620],
    ];

    public function run(): void
    {
        foreach (self::ACHIEVEMENTS as [$key, $name, $description, $icon, $category, $xp, $sort]) {
            Achievement::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'category' => $category,
                    'xp_reward' => $xp,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
