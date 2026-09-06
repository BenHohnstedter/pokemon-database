<?php

/**
 * End-to-End-Kernflow aus spec.md 7:
 * Registrieren → Login → Pokémon als besessen markieren → Fortschritt
 * aktualisiert sich → Logout.
 *
 * Wichtig für alle Textprüfungen hier: Selenium liefert den **gerenderten**
 * Text zurück, und das Retro-Design setzt auf Überschriften und Schaltflächen
 * `text-transform: uppercase`. "Filtern" steht im DOM, ankommen tut "FILTERN".
 * Deshalb laufen Interaktionen über `dusk`-Attribute und Prüfungen über Text,
 * der nicht großgeschrieben wird (Fließtext, Zahlen).
 *
 * Voraussetzungen (siehe README, Abschnitt "Tests"):
 *   - `npm run build` wurde ausgeführt
 *   - `.env.dusk.local` zeigt auf eine eigene Testdatenbank
 *   - ein Server läuft unter der dort gesetzten APP_URL
 */

use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\GameSeeder;
use Laravel\Dusk\Browser;

it('führt den kompletten Kernflow von der Registrierung bis zum Logout durch', function () {
    $this->seed(AchievementSeeder::class);
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

            // ── Ausgangsstand: nichts gesammelt ───────────────────────────
            ->assertSee('0/4')
            ->assertSee('Noch 4 Pokémon offen.')

            // ── Pokémon als besessen markieren ────────────────────────────
            ->visit('/pokedex')
            ->waitFor('@toggle-'.$erstes->baseForm->id)
            ->click('@toggle-'.$erstes->baseForm->id)
            ->pause(1500)

            // ── Fortschritt hat sich aktualisiert ─────────────────────────
            ->visit('/dashboard')
            ->assertSee('1/4')
            ->assertSee('Noch 3 Pokémon offen.')

            // ── Logout ────────────────────────────────────────────────────
            ->click('@user-menu')
            ->waitFor('@abmelden')
            ->clickAndWaitForReload('@abmelden')
            ->assertGuest();
    });

    expect(User::where('email', 'dusk-trainer@localhost')->exists())->toBeTrue()
        ->and(UserPokemonForm::where('owned', true)->count())->toBe(1);
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
            ->click('@vorschau-anzeigen')
            ->waitForText('bestätigen?')
            // Die Vorschau darf noch nichts gespeichert haben.
            ->assertSee('Das markiert')
            ->click('@massen-uebernehmen')
            ->waitForText('als besessen markiert')
            ->visit('/dashboard')
            ->assertSee('6/10');
    });

    expect(UserPokemonForm::where('owned', true)->count())->toBe(6);
});

it('filtert den Pokédex auf die dringenden Bank-Fälle', function () {
    $this->seed(GameSeeder::class);

    $dringend = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bankfall']);
    Obtainability::factory()->create([
        'pokemon_id' => $dringend->id,
        'game_id' => Game::where('slug', 'black')->value('id'),
    ]);

    $entspannt = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Entspannt']);
    Obtainability::factory()->create([
        'pokemon_id' => $entspannt->id,
        'game_id' => Game::where('slug', 'scarlet')->value('id'),
    ]);

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/pokedex')
            ->waitFor('@filter-anwenden')
            ->select('prio', 'bank_urgent')
            ->click('@filter-anwenden')
            ->waitForText('Bankfall')
            ->assertDontSee('Entspannt');
    });
});

it('zeigt auch das an, was man selbst holen kann, aber vor der Deadline muss', function () {
    $this->seed(GameSeeder::class);

    $meins = Game::where('slug', 'black')->first();

    $selbstHolbar = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Selbstholbar']);
    Obtainability::factory()->create([
        'pokemon_id' => $selbstHolbar->id,
        'game_id' => $meins->id,
    ]);

    $user = User::factory()->create();
    $user->settingsOrDefault();
    $user->games()->attach($meins);

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/dashboard')
            // Trotz 🟢 "einfach" hängt es an der Frist – genau der Fall, der
            // vorher unsichtbar war.
            ->waitForText('KANNST DU SELBST HOLEN')
            ->assertSee('Selbstholbar')
            ->visit('/pokedex?deadline=1')
            ->waitForText('Selbstholbar');
    });
});

it('wechselt die Farbpalette auf den Game-Boy-Modus', function () {
    $this->seed(GameSeeder::class);

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/einstellungen')
            ->waitFor('@einstellungen-speichern')
            ->select('theme', 'gameboy')
            ->click('@einstellungen-speichern')
            ->waitForText('Einstellungen gespeichert');

        // assertAttribute sucht innerhalb von body – das <html>-Element liegt
        // darüber und ist nur per Script erreichbar.
        $theme = $browser->script('return document.documentElement.dataset.theme;')[0];

        expect($theme)->toBe('gameboy');
    });

    expect($user->fresh()->settingsOrDefault()->theme)->toBe('gameboy');
});

it('stellt die Zahl der Einträge pro Seite um', function () {
    Pokemon::factory()->withBaseForm()->count(40)->create();

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/pokedex')
            ->waitFor('@filter-anwenden')
            ->select('pro_seite', '30')
            ->click('@filter-anwenden')
            ->pause(800);

        $karten = $browser->script('return document.querySelectorAll(".dex-card").length;')[0];

        expect($karten)->toBe(30);
    });
});

it('exportiert den Sammlungsstand und zeigt die Kennzahlen', function () {
    Pokemon::factory()->withBaseForm()->count(5)->create();

    $user = User::factory()->create();
    $user->settingsOrDefault();

    foreach (PokemonForm::base()->limit(3)->pluck('id') as $formId) {
        UserPokemonForm::create([
            'user_id' => $user->id,
            'pokemon_form_id' => $formId,
            'owned' => true,
            'owned_at' => now(),
        ]);
    }

    $this->browse(function (Browser $browser) use ($user) {
        $browser
            ->loginAs($user)
            ->visit('/sammlung/uebertragen')
            ->waitForText('gefahrlos weitergeben')
            ->assertSee('3')
            ->assertSee('Ergänzen')
            ->assertSee('Ersetzen');
    });
});
