<?php

/**
 * Spiel-für-Spiel-Ansicht: „Ich bin jetzt in diesem Spiel – was fehlt mir hier
 * noch?" (spec.md 2.3).
 */

use App\Enums\ObtainMethod;
use App\Models\Obtainability;
use App\Models\Pokemon;
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
