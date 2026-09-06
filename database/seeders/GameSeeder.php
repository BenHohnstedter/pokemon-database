<?php

namespace Database\Seeders;

use App\Enums\Platform;
use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * Mainline-Spiele als Vorlage für die Einstellungs-Checkliste (spec.md Anhang A)
 * und als Datengrundlage der Prioritäts-Engine (spec.md 2.7).
 *
 * Die drei Flags entscheiden über die Dringlichkeitsstufe:
 *
 * - home_compatible  … das Spiel hängt direkt an Pokémon HOME
 * - bank_only        … der einzige Weg nach HOME führt über Pokémon Bank
 *                      (gilt auch für Titel, die erst über eine Transferkette
 *                      – Pal Park, Poké-Transfer – bei Bank landen)
 * - still_purchasable … regulär neu im Handel/eShop erhältlich
 *
 * WICHTIG (spec.md 4): Diese Liste ist der Wissensstand beim Anlegen und sollte
 * beim Datenimport gegen Bulbapedia/Serebii gegengeprüft werden, bevor sie als
 * endgültig gilt – gerade bei den Flags "still_purchasable" und bei Titeln, die
 * nach dem Projektstart erschienen sind.
 */
class GameSeeder extends Seeder
{
    /**
     * slug, name_de, name_en, gen, platform, jahr,
     * home_compatible, bank_only, still_purchasable, note
     */
    public const GAMES = [
        // ── Gen 1 (als 3DS-Virtual-Console an Bank angebunden) ────────────────
        ['red', 'Rot', 'Red', 1, Platform::Nintendo3ds, 2016, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],
        ['blue', 'Blau', 'Blue', 1, Platform::Nintendo3ds, 2016, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],
        ['green', 'Grün', 'Green', 1, Platform::GameBoy, 1996, false, false, false, 'Nur Japan, kein Transferweg nach HOME'],
        ['yellow', 'Gelb', 'Yellow', 1, Platform::Nintendo3ds, 2016, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],

        // ── Gen 2 ────────────────────────────────────────────────────────────
        ['gold', 'Gold', 'Gold', 2, Platform::Nintendo3ds, 2017, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],
        ['silver', 'Silber', 'Silver', 2, Platform::Nintendo3ds, 2017, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],
        ['crystal', 'Kristall', 'Crystal', 2, Platform::Nintendo3ds, 2018, false, true, false, 'Virtual Console (3DS); eShop geschlossen'],

        // ── Gen 3 (nur über Pal Park + Transferkette) ─────────────────────────
        ['ruby', 'Rubin', 'Ruby', 3, Platform::GameBoyAdvance, 2003, false, true, false, 'Nur via Pal Park → Gen 4 → Gen 5 → Bank'],
        ['sapphire', 'Saphir', 'Sapphire', 3, Platform::GameBoyAdvance, 2003, false, true, false, 'Nur via Pal Park → Gen 4 → Gen 5 → Bank'],
        ['emerald', 'Smaragd', 'Emerald', 3, Platform::GameBoyAdvance, 2005, false, true, false, 'Nur via Pal Park → Gen 4 → Gen 5 → Bank'],
        ['firered', 'Feuerrot', 'FireRed', 3, Platform::GameBoyAdvance, 2004, false, true, false, 'Nur via Pal Park → Gen 4 → Gen 5 → Bank'],
        ['leafgreen', 'Blattgrün', 'LeafGreen', 3, Platform::GameBoyAdvance, 2004, false, true, false, 'Nur via Pal Park → Gen 4 → Gen 5 → Bank'],

        // ── Gen 4 ────────────────────────────────────────────────────────────
        ['diamond', 'Diamant', 'Diamond', 4, Platform::NintendoDs, 2007, false, true, false, 'Nur via Poké-Transfer → Gen 5 → Bank'],
        ['pearl', 'Perl', 'Pearl', 4, Platform::NintendoDs, 2007, false, true, false, 'Nur via Poké-Transfer → Gen 5 → Bank'],
        ['platinum', 'Platin', 'Platinum', 4, Platform::NintendoDs, 2009, false, true, false, 'Nur via Poké-Transfer → Gen 5 → Bank'],
        ['heartgold', 'HeartGold', 'HeartGold', 4, Platform::NintendoDs, 2010, false, true, false, 'Nur via Poké-Transfer → Gen 5 → Bank'],
        ['soulsilver', 'SoulSilver', 'SoulSilver', 4, Platform::NintendoDs, 2010, false, true, false, 'Nur via Poké-Transfer → Gen 5 → Bank'],

        // ── Gen 5 (erste Generation mit direktem Bank-Anschluss) ──────────────
        ['black', 'Schwarz', 'Black', 5, Platform::NintendoDs, 2011, false, true, false, 'Direkt an Pokémon Bank'],
        ['white', 'Weiß', 'White', 5, Platform::NintendoDs, 2011, false, true, false, 'Direkt an Pokémon Bank'],
        ['black-2', 'Schwarz 2', 'Black 2', 5, Platform::NintendoDs, 2012, false, true, false, 'Direkt an Pokémon Bank'],
        ['white-2', 'Weiß 2', 'White 2', 5, Platform::NintendoDs, 2012, false, true, false, 'Direkt an Pokémon Bank'],

        // ── Gen 6 ────────────────────────────────────────────────────────────
        ['x', 'X', 'X', 6, Platform::Nintendo3ds, 2013, false, true, false, 'Direkt an Pokémon Bank'],
        ['y', 'Y', 'Y', 6, Platform::Nintendo3ds, 2013, false, true, false, 'Direkt an Pokémon Bank'],
        ['omega-ruby', 'Omega Rubin', 'Omega Ruby', 6, Platform::Nintendo3ds, 2014, false, true, false, 'Direkt an Pokémon Bank'],
        ['alpha-sapphire', 'Alpha Saphir', 'Alpha Sapphire', 6, Platform::Nintendo3ds, 2014, false, true, false, 'Direkt an Pokémon Bank'],

        // ── Gen 7 ────────────────────────────────────────────────────────────
        ['sun', 'Sonne', 'Sun', 7, Platform::Nintendo3ds, 2016, false, true, false, 'Direkt an Pokémon Bank'],
        ['moon', 'Mond', 'Moon', 7, Platform::Nintendo3ds, 2016, false, true, false, 'Direkt an Pokémon Bank'],
        ['ultra-sun', 'Ultrasonne', 'Ultra Sun', 7, Platform::Nintendo3ds, 2017, false, true, false, 'Direkt an Pokémon Bank'],
        ['ultra-moon', 'Ultramond', 'Ultra Moon', 7, Platform::Nintendo3ds, 2017, false, true, false, 'Direkt an Pokémon Bank'],
        ['lets-go-pikachu', "Let's Go, Pikachu!", "Let's Go, Pikachu!", 7, Platform::Switch, 2018, true, false, true, 'Direkt an HOME'],
        ['lets-go-eevee', "Let's Go, Evoli!", "Let's Go, Eevee!", 7, Platform::Switch, 2018, true, false, true, 'Direkt an HOME'],

        // ── Gen 8 ────────────────────────────────────────────────────────────
        ['sword', 'Schwert', 'Sword', 8, Platform::Switch, 2019, true, false, true, 'Direkt an HOME, inkl. DLC Rüstungsinsel/Kronen-Schneeland'],
        ['shield', 'Schild', 'Shield', 8, Platform::Switch, 2019, true, false, true, 'Direkt an HOME, inkl. DLC Rüstungsinsel/Kronen-Schneeland'],
        ['brilliant-diamond', 'Strahlender Diamant', 'Brilliant Diamond', 8, Platform::Switch, 2021, true, false, true, 'Direkt an HOME'],
        ['shining-pearl', 'Leuchtende Perle', 'Shining Pearl', 8, Platform::Switch, 2021, true, false, true, 'Direkt an HOME'],
        ['legends-arceus', 'Legenden: Arceus', 'Legends: Arceus', 8, Platform::Switch, 2022, true, false, true, 'Direkt an HOME'],

        /*
         * Switch-Neuauflage von Feuerrot/Blattgrün, laut spec.md 1 ab Oktober
         * 2026 an HOME angebunden. Der offizielle Titel stand beim Anlegen noch
         * nicht fest – Name und Erscheinungsjahr gehören gegengeprüft, sobald er
         * bekannt ist. Der Artenbestand wird per RemakeObtainabilitySeeder aus
         * den GBA-Originalen übernommen, inklusive Ho-Oh und Lugia (Eiland 9).
         *
         * Warum das wichtig ist: Über diesen Weg kommen die Kanto-Arten ohne
         * Pokémon Bank nach HOME. Fehlte der Eintrag, stünden sie alle auf 🔴.
         */
        ['firered-switch', 'Feuerrot (Switch)', 'FireRed (Switch)', 3, Platform::Switch, 2026, true, false, true, 'Neuauflage – direkt an HOME. Titel und Erscheinungsdatum noch gegenzuprüfen.'],
        ['leafgreen-switch', 'Blattgrün (Switch)', 'LeafGreen (Switch)', 3, Platform::Switch, 2026, true, false, true, 'Neuauflage – direkt an HOME. Titel und Erscheinungsdatum noch gegenzuprüfen.'],

        // ── Gen 9 ────────────────────────────────────────────────────────────
        ['scarlet', 'Karmesin', 'Scarlet', 9, Platform::Switch, 2022, true, false, true, 'Direkt an HOME, inkl. DLC Die Schatzkammer von Zone Null'],
        ['violet', 'Purpur', 'Violet', 9, Platform::Switch, 2022, true, false, true, 'Direkt an HOME, inkl. DLC Die Schatzkammer von Zone Null'],

        // ── Nebenreihe mit HOME-Anbindung ────────────────────────────────────
        ['go', 'Pokémon GO', 'Pokémon GO', 0, Platform::Mobile, 2016, true, false, true, 'Per GO-Transporter an HOME – hebt die Bank-Deadline auf'],
    ];

    public function run(): void
    {
        foreach (self::GAMES as $index => $game) {
            [$slug, $nameDe, $nameEn, $gen, $platform, $year, $home, $bank, $buy, $note] = $game;

            Game::updateOrCreate(
                ['slug' => $slug],
                [
                    'name_de' => $nameDe,
                    'name_en' => $nameEn,
                    'generation' => $gen,
                    'platform' => $platform,
                    'release_year' => $year,
                    'home_compatible' => $home,
                    'bank_only' => $bank,
                    'still_purchasable' => $buy,
                    'note' => $note,
                    'sort_order' => $index,
                ],
            );
        }
    }
}
