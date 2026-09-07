<?php

/**
 * Spiel-für-Spiel abarbeiten im Browser (spec.md 2.3, 7).
 *
 * Der Ablauf, den der Nutzer beschrieben hat: Konsole in der Hand, Spiel
 * auswählen, Liste durchgehen, Gefangenes direkt abhaken – ohne dafür jedes
 * Mal auf die Detailseite zu wechseln.
 *
 * Zu Großschreibung und `dusk`-Attributen siehe Kopf von CollectionFlowTest.
 */

use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\GameSeeder;
use Laravel\Dusk\Browser;

it('führt von der Spielübersicht zur Liste und hakt dort direkt ab', function () {
    $this->seed(GameSeeder::class);
    $this->seed(AchievementSeeder::class);

    $spiel = Game::where('slug', 'sword')->firstOrFail();

    $fehlt = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Zamazenta']);
    $auchOffen = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Zacian']);

    foreach ([$fehlt, $auchOffen] as $pokemon) {
        Obtainability::factory()->create([
            'pokemon_id' => $pokemon->id,
            'game_id' => $spiel->id,
            'location_detail' => 'Turm des Anfangs',
        ]);
    }

    $user = User::factory()->create();
    $user->settingsOrDefault();
    $user->games()->attach($spiel);

    $this->browse(function (Browser $browser) use ($user, $spiel, $fehlt) {
        $browser->loginAs($user)
            ->visit('/spiele')
            // Der Zähler in der Übersicht nennt die offenen Arten dieses Spiels.
            ->assertSee('Pokémon fehlen Dir noch')
            ->clickLink($spiel->name_de)
            ->waitForText('Turm des Anfangs')

            // Die Liste zeigt nur, was hier noch fehlt.
            ->assertSee('Zamazenta')
            ->assertSee('Zacian')
            ->assertSee('Dir fehlen hier noch')

            // Abhaken ohne Seitenwechsel. Geprüft wird die Zustandsklasse und
            // nicht der Text: das Retro-CSS schreibt Schaltflächen groß, und
            // Selenium liefert den gerenderten Text zurück.
            ->click("[dusk=\"spiel-toggle-{$fehlt->baseForm->id}\"]")
            ->waitFor("[dusk=\"spiel-toggle-{$fehlt->baseForm->id}\"].border-dex-success")
            // Die Anzeige springt sofort um, gespeichert wird erst danach. Der
            // Knopf ist so lange deaktiviert – erst wenn das vorbei ist, darf
            // die Datenbank geprüft werden.
            ->waitUntilMissing("[dusk=\"spiel-toggle-{$fehlt->baseForm->id}\"][disabled]");

        expect(UserPokemonForm::where('user_id', $user->id)
            ->where('pokemon_form_id', $fehlt->baseForm->id)
            ->value('owned'))->toBeTrue();

        // Nach dem Neuladen ist es aus der Restliste verschwunden.
        $browser->visit('/spiele/'.$spiel->id)
            ->waitForText('Zacian')
            ->assertDontSee('Zamazenta')
            ->assertSee('Dir fehlen hier noch');
    });
});

it('nennt die Bank-Frist auf der Seite eines Altspiels', function () {
    $this->seed(GameSeeder::class);

    $spiel = Game::where('slug', 'heartgold')->firstOrFail();
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);

    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => $spiel->id,
        'location_detail' => 'Route 34',
    ]);

    $user = User::factory()->create();
    $user->settingsOrDefault();

    $this->browse(function (Browser $browser) use ($user, $spiel) {
        $browser->loginAs($user)
            ->visit('/spiele/'.$spiel->id)
            ->waitForText('Route 34')
            ->assertSee('26.02.2027')
            ->assertSee('Du hast dieses Spiel nicht eingetragen.');
    });
});

it('zeigt ein Gen-5-Spiel ohne Poké Transporter als Sackgasse', function () {
    $this->seed(GameSeeder::class);

    $spiel = Game::where('slug', 'black-2')->firstOrFail();
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);

    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => $spiel->id,
        'location_detail' => 'Route 14',
    ]);

    $user = User::factory()->create();
    $user->settingsOrDefault()->update(['has_poke_transporter' => false]);
    $user->games()->attach($spiel);

    $this->browse(function (Browser $browser) use ($user, $spiel) {
        $browser->loginAs($user)
            ->visit('/spiele')
            // Keine Frist, wo der Weg ohnehin verschlossen ist …
            ->waitForText('Poké Transporter fehlt Dir')
            // … für Gen 6 und 7 gilt sie dagegen weiter: die laden selbst zu
            // Bank hoch. Beide Zustände stehen hier auf derselben Seite.
            ->assertSee('Transfer über Pokémon Bank')

            ->visit('/spiele/'.$spiel->id)
            ->waitForText('Route 14')
            ->assertSee('Poké Transporter')
            ->assertDontSee('26.02.2027');
    });
});
