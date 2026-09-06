<?php

/**
 * End-to-End-Kernflow aus spec.md 7:
 * Registrieren → Login → Pokémon als besessen markieren → Fortschritt
 * aktualisiert sich → Logout.
 *
 * Voraussetzungen (siehe README, Abschnitt "Tests"):
 *   - `npm run build` wurde ausgeführt (Dusk lädt echte Assets)
 *   - eine erreichbare Testdatenbank, konfiguriert in `.env.dusk.local`
 *   - `php artisan serve` bzw. der XAMPP-VHost läuft unter APP_URL
 */

use App\Models\Pokemon;
use App\Models\User;
use Laravel\Dusk\Browser;

it('führt den kompletten Kernflow von der Registrierung bis zum Logout durch', function () {
    Pokemon::factory()->withBaseForm()->count(4)->create();
    $erstes = Pokemon::orderBy('dex_nr')->first();

    $this->browse(function (Browser $browser) use ($erstes) {
        $browser
            // ── Registrieren ──────────────────────────────────────────────
            ->visit('/register')
            ->type('name', 'Dusk-Trainer')
            ->type('email', 'dusk-trainer@localhost')
            ->type('password', 'geheim-genug-123')
            ->type('password_confirmation', 'geheim-genug-123')
            ->press('REGISTER')
            ->waitForLocation('/dashboard')
            ->assertSee('Gesamtfortschritt')

            // ── Ausgangsstand: nichts gesammelt ───────────────────────────
            ->assertSee('0 / 4')

            // ── Pokémon als besessen markieren ────────────────────────────
            ->visit('/pokedex')
            ->waitForText($erstes->name_de)
            ->click('@toggle-'.$erstes->baseForm->id)
            ->waitUntilMissing('@toggle-'.$erstes->baseForm->id.'[disabled]')

            // ── Fortschritt hat sich aktualisiert ─────────────────────────
            ->visit('/dashboard')
            ->assertSee('1 / 4')

            // ── Logout ────────────────────────────────────────────────────
            ->click('@user-menu')
            ->waitForText('Abmelden')
            ->press('Abmelden')
            ->waitForLocation('/')
            ->assertGuest();
    });
});

it('markiert per Masseneingabe mehrere Pokémon nach bestätigter Vorschau', function () {
    Pokemon::factory()->withBaseForm()->count(10)->create();

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/sammlung/masseneingabe')
            ->type('eingabe', '1-5,8')
            ->press('Vorschau anzeigen')
            ->waitForText('bestätigen?')
            // Die Vorschau darf noch nichts gespeichert haben.
            ->assertSee('6')
            ->press('Ja, 6 Pokémon übernehmen')
            ->waitForText('als besessen markiert')
            ->visit('/dashboard')
            ->assertSee('6 / 10');
    });
});

it('filtert den Pokédex auf die dringenden Bank-Fälle', function () {
    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/pokedex')
            ->waitForText('Dringlichkeit')
            ->select('prio', 'bank_urgent')
            ->press('Filtern')
            ->waitForText('Dringend – Bank-Deadline');
    });
});

it('wechselt die Farbpalette auf den Game-Boy-Modus', function () {
    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/einstellungen')
            ->waitForText('Darstellung')
            ->select('theme', 'gameboy')
            ->press('Speichern')
            ->waitForText('Einstellungen gespeichert')
            ->assertAttribute('html', 'data-theme', 'gameboy');
    });
});
