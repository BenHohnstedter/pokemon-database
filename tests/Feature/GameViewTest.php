<?php

/**
 * Spiel-für-Spiel-Ansicht: „Ich bin jetzt in diesem Spiel – was fehlt mir hier
 * noch?" (spec.md 2.3).
 */

use App\Enums\ObtainMethod;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Factories\GameFactory;

beforeEach(function () {
    resetDexSequence();
    $this->user = User::factory()->create();
});

it('verlangt einen Login für die Spielübersicht', function () {
    $this->get(route('games.index'))->assertRedirect(route('login'));
});

it('listet die Spiele nach Generation gruppiert', function () {
    GameFactory::new()->create(['name_de' => 'Schwert', 'generation' => 8]);
    GameFactory::new()->bankOnly()->create(['name_de' => 'Schwarz 2', 'generation' => 5]);

    $this->actingAs($this->user)
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('Generation 8')
        ->assertSee('Generation 5')
        ->assertSee('Schwert')
        ->assertSee('Schwarz 2');
});

it('markiert die eigenen Spiele in der Übersicht', function () {
    $meins = GameFactory::new()->create(['name_de' => 'Karmesin']);
    GameFactory::new()->create(['name_de' => 'Purpur']);

    $this->user->games()->attach($meins);

    $this->actingAs($this->user)
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('Deins');
});

it('zählt je Spiel, wie viele Pokémon noch fehlen', function () {
    $game = GameFactory::new()->create(['name_de' => 'Schwert']);

    $fehlt = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    $habe = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Glumanda']);

    Obtainability::factory()->create(['pokemon_id' => $fehlt->id, 'game_id' => $game->id]);
    Obtainability::factory()->create(['pokemon_id' => $habe->id, 'game_id' => $game->id]);

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $habe->baseForm->id,
        'owned' => true,
    ]);

    $antwort = $this->actingAs($this->user)->get(route('games.index'))->assertOk();

    expect($antwort->viewData('offeneJeSpiel')[$game->id])->toBe(1);
    $antwort->assertSee('Pokémon fehlt Dir noch');
});

it('listet auf der Spielseite nur die fehlenden Pokémon', function () {
    $game = GameFactory::new()->create(['name_de' => 'Schwert']);

    $fehlt = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    $habe = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Glumanda']);

    Obtainability::factory()->create(['pokemon_id' => $fehlt->id, 'game_id' => $game->id]);
    Obtainability::factory()->create(['pokemon_id' => $habe->id, 'game_id' => $game->id]);

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $habe->baseForm->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Bisasam')
        ->assertDontSee('Glumanda');
});

it('zeigt mit ?offen=0 auch die schon gefangenen', function () {
    $game = GameFactory::new()->create();
    $habe = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Glumanda']);

    Obtainability::factory()->create(['pokemon_id' => $habe->id, 'game_id' => $game->id]);
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $habe->baseForm->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('games.show', [$game, 'offen' => 0]))
        ->assertOk()
        ->assertSee('Glumanda');
});

it('zeigt den Fundort aus diesem Spiel, nicht den global besten', function () {
    $hier = GameFactory::new()->bankOnly()->create(['name_de' => 'Schwarz 2']);
    $woanders = GameFactory::new()->create(['name_de' => 'Schwert']);

    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);

    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => $hier->id,
        'method' => ObtainMethod::Wild,
        'location_detail' => 'Route 14 (Schwarz 2)',
    ]);
    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => $woanders->id,
        'method' => ObtainMethod::Wild,
        'location_detail' => 'Naturzone (Schwert)',
    ]);

    $this->actingAs($this->user)
        ->get(route('games.show', $hier))
        ->assertOk()
        ->assertSee('Route 14 (Schwarz 2)')
        ->assertDontSee('Naturzone (Schwert)');
});

it('warnt auf der Seite eines Bank-Spiels vor der Frist', function () {
    $game = GameFactory::new()->bankOnly()->create(['name_de' => 'Schwarz 2']);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Pokémon Bank')
        ->assertSee('26.02.2027');
});

it('sagt bei einem HOME-Spiel, dass keine Frist droht', function () {
    $game = GameFactory::new()->modern()->create(['name_de' => 'Karmesin']);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('direkt an Pokémon HOME', escape: false)
        ->assertDontSee('26.02.2027');
});

it('weist darauf hin, wenn das Spiel gar nicht eingetragen ist', function () {
    $game = GameFactory::new()->create(['name_de' => 'Schwert']);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Du hast dieses Spiel nicht eingetragen.');
});

it('meldet ein leeres Spiel als abgeschlossen', function () {
    $game = GameFactory::new()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $pokemon->baseForm->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Hier fehlt Dir nichts mehr');
});

it('unterscheidet "alles gefangen" von "keine Fundorte hinterlegt"', function () {
    // Pokémon GO und einige Altspiele haben keine Obtainability-Zeilen. Beides
    // käme als 0 an – die Übersicht darf dafür keinen Vollzug melden.
    $leer = GameFactory::new()->create(['name_de' => 'Pokémon GO']);
    $fertig = GameFactory::new()->create(['name_de' => 'Schwert']);

    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $fertig->id]);
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $pokemon->baseForm->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('Keine Fundorte hinterlegt')
        ->assertSee('Hier fehlt Dir nichts mehr');

    $this->actingAs($this->user)
        ->get(route('games.show', $leer))
        ->assertOk()
        ->assertSee('Für dieses Spiel sind keine Fundorte hinterlegt.')
        ->assertDontSee('Hier fehlt Dir nichts mehr');
});

it('nennt bei offenen Arten auch den Gesamtbestand des Spiels', function () {
    $game = GameFactory::new()->create(['name_de' => 'Schwert']);

    foreach (Pokemon::factory()->withBaseForm()->count(3)->create() as $pokemon) {
        Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);
    }

    $this->actingAs($this->user)
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('von 3 hinterlegten');
});

/*
|--------------------------------------------------------------------------
| Regionalformen in der Spielansicht
|--------------------------------------------------------------------------
|
| Fundorte hängen bei uns an der Art, nicht an einer bestimmten Form. Eine
| Quelle "Vulpix in Rot" auf das Alola-Vulpix zu übertragen wäre eine
| Behauptung, die die Daten nicht hergeben.
*/

it('zeigt standardmäßig nur die normalen Formen', function () {
    $game = GameFactory::new()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    PokemonForm::factory()->regional()->for($pokemon)->create(['name_de' => 'Vulpix (Alola-Form)']);

    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $antwort = $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Vulpix')
        ->assertDontSee('Alola-Form');

    expect($antwort->viewData('zeilen'))->toHaveCount(1);
});

it('führt Sonderformen auch eingeschaltet nur mit eigenem Fundort auf', function () {
    $game = GameFactory::new()->create();
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    PokemonForm::factory()->regional()->for($pokemon)->create(['name_de' => 'Vulpix (Alola-Form)']);

    // Quelle auf Artebene – sie gehört zur normalen Form, nicht zur Alola-Form.
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $this->actingAs($this->user)
        ->get(route('games.show', [$game, 'formen' => 1]))
        ->assertOk()
        ->assertSee('Vulpix')
        ->assertDontSee('Alola-Form')
        ->assertSee('kein eigener Fundort hinterlegt');
});

it('zeigt eine Regionalform, sobald sie einen eigenen Fundort hat', function () {
    $game = GameFactory::new()->create(['name_de' => 'Sonne']);
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    $alola = PokemonForm::factory()->regional()->for($pokemon)->create(['name_de' => 'Vulpix (Alola-Form)']);

    Obtainability::factory()->create([
        'pokemon_id' => $pokemon->id,
        'game_id' => $game->id,
        'pokemon_form_id' => $alola->id,
        'location_detail' => 'Route 3 (Akala)',
    ]);

    $antwort = $this->actingAs($this->user)
        ->get(route('games.show', [$game, 'formen' => 1]))
        ->assertOk()
        ->assertSee('Alola-Form')
        ->assertSee('Route 3 (Akala)')
        ->assertDontSee('kein eigener Fundort hinterlegt');

    // Die normale Form hat hier keine eigene Quelle und fehlt deshalb.
    expect($antwort->viewData('zeilen'))->toHaveCount(1);
});

it('behält den Formen-Schalter beim Umschalten auf "Alle anzeigen"', function () {
    $game = GameFactory::new()->create();

    $this->actingAs($this->user)
        ->get(route('games.show', [$game, 'formen' => 1]))
        ->assertOk()
        // Im Markup steht das & escaped – geprüft wird der Link, wie er im HTML landet.
        ->assertSee(e(route('games.show', [$game, 'offen' => 0, 'formen' => 1])), escape: false);
});

/*
|--------------------------------------------------------------------------
| Poké Transporter in der Spielansicht
|--------------------------------------------------------------------------
*/

it('nennt ein Gen-5-Spiel ohne Transporter eine Sackgasse statt einer Frist', function () {
    $game = GameFactory::new()->needsTransporter()->create(['name_de' => 'Schwarz 2']);
    $this->user->settingsOrDefault()->update(['has_poke_transporter' => false]);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('Poké Transporter')
        ->assertSee('gar nicht', escape: false)
        ->assertDontSee('26.02.2027');
});

it('behält die Frist, wenn der Transporter vorhanden ist', function () {
    $game = GameFactory::new()->needsTransporter()->create(['name_de' => 'Schwarz 2']);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        ->assertSee('26.02.2027')
        ->assertSee('Poké Transporter');
});

it('lässt Gen-6-Titel von der Transporter-Frage unberührt', function () {
    $game = GameFactory::new()->bankOnly()->create(['name_de' => 'X']);
    $this->user->settingsOrDefault()->update(['has_poke_transporter' => false]);

    $this->actingAs($this->user)
        ->get(route('games.show', $game))
        ->assertOk()
        // X lädt selbst zu Bank hoch – die Frist gilt weiter.
        ->assertSee('26.02.2027')
        ->assertDontSee('Poké Transporter');
});

it('markiert Sackgassen auch in der Spielübersicht', function () {
    GameFactory::new()->needsTransporter()->create(['name_de' => 'Schwarz 2']);
    $this->user->settingsOrDefault()->update(['has_poke_transporter' => false]);

    $this->actingAs($this->user)
        ->get(route('games.index'))
        ->assertOk()
        ->assertSee('Sackgasse')
        ->assertDontSee('Transfer über Pokémon Bank');
});

it('zeigt auch, was sich hier aus einer Vorstufe entwickeln lässt', function () {
    // Vom Nutzer gemeldet: Legenden: Arceus listete Feurigel, nicht aber
    // Igelavar und Tornupto — dabei ist die Linie mit dem Starter in der Hand
    // komplett abarbeitbar (FEATURE-UPDATES.md 21).
    $spiel = GameFactory::new()->create(['name_de' => 'Legenden: Arceus', 'generation' => 8]);

    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Feurigel']);
    $stufe2 = Pokemon::factory()->withBaseForm()->create([
        'name_de' => 'Igelavar',
        'obtainable_directly' => false,
        'source_pokemon_id' => $basis->id,
    ]);

    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => $spiel->id,
        'method' => ObtainMethod::Gift->value,
        'location_detail' => 'Starter-Pokémon zu Spielbeginn',
    ]);

    $this->actingAs($this->user)
        ->get(route('games.show', $spiel))
        ->assertOk()
        ->assertSee('Feurigel')
        ->assertSee('Igelavar')
        ->assertSee('Entwicklung aus Feurigel');

    expect($stufe2->fresh()->obtainable_directly)->toBeFalse();
});

it('führt eine Art nicht doppelt, wenn sie hier auch selbst vorkommt', function () {
    $spiel = GameFactory::new()->create(['name_de' => 'Schwert', 'generation' => 8]);

    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Feurigel']);
    $stufe2 = Pokemon::factory()->withBaseForm()->create([
        'name_de' => 'Igelavar',
        'obtainable_directly' => false,
        'source_pokemon_id' => $basis->id,
    ]);

    foreach ([$basis, $stufe2] as $art) {
        Obtainability::factory()->create([
            'pokemon_id' => $art->id,
            'game_id' => $spiel->id,
            'method' => ObtainMethod::Wild->value,
            'location_detail' => 'Route 1',
        ]);
    }

    $html = $this->actingAs($this->user)->get(route('games.show', $spiel))->assertOk()->getContent();

    expect(substr_count($html, 'Igelavar'))->toBe(substr_count($html, 'Feurigel'))
        ->and($html)->not->toContain('Entwicklung aus');
});
