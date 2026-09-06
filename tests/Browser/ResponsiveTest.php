<?php

/**
 * Responsive-Prüfung (spec.md 7): keine Seite darf horizontal überlaufen.
 *
 * Querlauf ist auf dem Handy der auffälligste Layoutfehler – die Seite lässt
 * sich seitlich schieben, Inhalte stehen halb außerhalb. Der Test misst
 * scrollWidth gegen clientWidth und nennt im Fehlerfall die Elemente, die
 * überstehen, damit die Ursache sofort sichtbar ist.
 */

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
];

/** Liefert die Elemente, die über den rechten Rand ragen – für die Fehlermeldung. */
function ueberstehendeElemente(Browser $browser): string
{
    $treffer = $browser->script(<<<'JS'
        const de = document.documentElement;
        return [...document.querySelectorAll('body *')]
            .filter(e => e.getBoundingClientRect().right > de.clientWidth + 1)
            .slice(0, 5)
            .map(e => e.tagName.toLowerCase() + '.' + e.className.toString().slice(0, 50));
    JS)[0];

    return $treffer === [] ? '(keine gefunden)' : implode(' | ', $treffer);
}

it('läuft auf keiner Seite und keiner Breite horizontal über', function () {
    $this->seed(GameSeeder::class);
    $this->seed(AchievementSeeder::class);

    Pokemon::factory()->withBaseForm()->count(80)->create();

    $user = User::factory()->create(['name' => 'Responsive-Tester']);
    $user->settingsOrDefault();

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

                [$scrollWidth, $clientWidth] = $browser->script(
                    'return [document.documentElement.scrollWidth, document.documentElement.clientWidth];'
                )[0];

                expect($scrollWidth)->toBeLessThanOrEqual(
                    $clientWidth,
                    "{$name} läuft bei {$geraet} ({$breite}px) über: "
                    ."scrollWidth {$scrollWidth} > clientWidth {$clientWidth}. "
                    .'Überstehend: '.ueberstehendeElemente($browser)
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
