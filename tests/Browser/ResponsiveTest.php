<?php

/**
 * Responsive-Prüfung (spec.md 7): keine Seite darf horizontal überlaufen.
 *
 * Querlauf ist auf dem Handy der auffälligste Layoutfehler – die Seite lässt
 * sich seitlich schieben, Inhalte stehen halb außerhalb. Der Test misst
 * scrollWidth gegen clientWidth und nennt im Fehlerfall die Elemente, die
 * überstehen, damit die Ursache sofort sichtbar ist.
 */

use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\GameSeeder;
use Laravel\Dusk\Browser;

/** Breiten, die die Sprungpunkte des Layouts abdecken. */
const BREITEN = [
    'Handy' => [375, 812],
    'Handy quer' => [667, 375],
    'Tablet' => [768, 1024],
    'schmaler Desktop' => [900, 800],
    'Desktop' => [1280, 900],
];

const SEITEN = [
    'Startseite' => '/',
    'Pokédex' => '/pokedex',
    'Dashboard' => '/dashboard',
    'Statistik' => '/statistik',
    'Trainerkarte' => '/trainerkarte',
    'Bestenliste' => '/bestenliste',
    'Masseneingabe' => '/sammlung/masseneingabe',
    'Sichern' => '/sammlung/uebertragen',
    'Einstellungen' => '/einstellungen',
    'Spiele' => '/spiele',
    // Die Tabellen auf diesen beiden Seiten sind die breitesten der App und
    // damit die wahrscheinlichsten Überläufer. Die IDs stimmen, weil
    // DatabaseTruncation die Auto-Increment-Zähler zurücksetzt.
    'Spiel-Detail' => '/spiele/1',
    'Pokémon-Detail' => '/pokedex/1',
];

/**
 * Prüft, ob die Seite tatsächlich seitlich verschiebbar ist – und nennt die
 * Schuldigen.
 *
 * Absichtlich nicht über `documentElement.scrollWidth`: sobald eine Seite eine
 * breite Tabelle in einem `overflow-x-auto`-Container zeigt (Bezugsquellen,
 * Spielansicht), meldet Chrome dort die ungekürzte Inhaltsbreite, obwohl der
 * Container sauber clippt und sich nichts schieben lässt. Der Test hätte also
 * genau das angemeckert, was die richtige Lösung ist.
 *
 * Gemessen wird stattdessen das, was der Nutzer merkt: Lässt sich das Dokument
 * nach rechts scrollen? Und dazu die Elemente, die über den Rand ragen, ohne
 * dass ein Vorfahre sie clippt.
 *
 * @return array{0:int,1:string} verschiebbare Pixel, überstehende Elemente
 */
function horizontalerUeberlauf(Browser $browser): array
{
    return $browser->script(<<<'JS'
        const de = document.documentElement;
        const vorher = window.scrollX;
        window.scrollTo(9999, window.scrollY);
        const schiebbar = Math.round(window.scrollX);
        window.scrollTo(vorher, window.scrollY);

        // Ein Vorfahre mit eigenem Scrollbereich schneidet das Kind ab – das
        // ist gewollt und kein Layoutfehler.
        const geclippt = (el) => {
            for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
                if (['auto', 'hidden', 'scroll'].includes(getComputedStyle(p).overflowX)) {
                    return true;
                }
            }
            return false;
        };

        const schuldige = [...document.querySelectorAll('body *')]
            .filter(e => e.getBoundingClientRect().right > de.clientWidth + 1 && !geclippt(e))
            .slice(0, 5)
            .map(e => e.tagName.toLowerCase() + '.' + e.className.toString().slice(0, 50));

        return [schiebbar, schuldige.join(' | ') || '(keine gefunden)'];
    JS)[0];
}

it('läuft auf keiner Seite und keiner Breite horizontal über', function () {
    $this->seed(GameSeeder::class);
    $this->seed(AchievementSeeder::class);

    Pokemon::factory()->withBaseForm()->count(80)->create();

    $user = User::factory()->create(['name' => 'Responsive-Tester']);
    $user->settingsOrDefault();

    // Ohne Fundorte blieben Spiel- und Detailseite leer und der Test würde
    // genau die Tabellen nicht prüfen, wegen derer er sie besucht.
    foreach (Pokemon::limit(40)->pluck('id') as $pokemonId) {
        Obtainability::factory()->create([
            'pokemon_id' => $pokemonId,
            'game_id' => 1,
            'location_detail' => 'Route 1 mit einem betont langen Fundortnamen',
        ]);
    }

    // Etwas Bestand, damit Fortschrittsbalken und Karten echte Inhalte haben.
    foreach (PokemonForm::base()->limit(25)->pluck('id') as $formId) {
        UserPokemonForm::create([
            'user_id' => $user->id,
            'pokemon_form_id' => $formId,
            'owned' => true,
            'owned_at' => now(),
        ]);
    }

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user);

        foreach (BREITEN as $geraet => [$breite, $hoehe]) {
            $browser->resize($breite, $hoehe);

            foreach (SEITEN as $name => $pfad) {
                $browser->visit($pfad)->pause(250);

                [$schiebbar, $schuldige] = horizontalerUeberlauf($browser);

                expect($schiebbar)->toBe(
                    0,
                    "{$name} lässt sich bei {$geraet} ({$breite}px) um {$schiebbar}px "
                    ."seitlich schieben. Überstehend: {$schuldige}"
                );
            }
        }
    });
});

it('zeigt auf dem Handy das Klappmenü statt der Desktop-Navigation', function () {
    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->resize(375, 812)
            ->visit('/dashboard')
            ->assertVisible('[aria-label="Menü umschalten"]')
            ->click('[aria-label="Menü umschalten"]')
            ->waitForText('Masseneingabe')
            ->assertSee('Bestenliste');
    });
});

it('bleibt auf dem Desktop ohne Klappmenü bedienbar', function () {
    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->resize(1280, 900)
            ->visit('/dashboard')
            ->assertSee('Pokédex')
            ->assertSee('Statistik')
            ->assertMissing('[aria-label="Menü umschalten"]:not(.lg\\:hidden)');
    });
});
