<?php

/**
 * Pokédex-Ansicht, Filter und Detailseite (spec.md 2.1–2.3, 2.7, 2.8).
 */

use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Factories\GameFactory;

beforeEach(function () {
    resetDexSequence();
    $this->user = User::factory()->create();
});

it('zeigt den Pokédex auch ohne Login', function () {
    Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);

    $this->get(route('pokedex.index'))
        ->assertOk()
        ->assertSee('Bisasam');
});

it('blendet Regionalformen standardmäßig aus', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    PokemonForm::factory()->regional()->for($pokemon)->create(['name_de' => 'Vulpix (Alola-Form)']);

    $this->get(route('pokedex.index'))
        ->assertOk()
        ->assertSee('Vulpix')
        ->assertDontSee('Alola-Form');
});

it('zeigt Regionalformen, wenn der Nutzer sie anfordert', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    PokemonForm::factory()->regional()->for($pokemon)->create(['name_de' => 'Vulpix (Alola-Form)']);

    $this->get(route('pokedex.index', ['formen' => 1]))
        ->assertOk()
        ->assertSee('Alola-Form');
});

it('filtert nach Suchbegriff', function () {
    Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    Pokemon::factory()->withBaseForm()->create(['name_de' => 'Glumanda']);

    $this->get(route('pokedex.index', ['q' => 'Glum']))
        ->assertOk()
        ->assertSee('Glumanda')
        ->assertDontSee('Bisasam');
});

it('filtert nach Dex-Nummer', function () {
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 25, 'name_de' => 'Pikachu']);
    Pokemon::factory()->withBaseForm()->create(['dex_nr' => 26, 'name_de' => 'Raichu']);

    $this->get(route('pokedex.index', ['q' => '25']))
        ->assertOk()
        ->assertSee('Pikachu')
        ->assertDontSee('Raichu');
});

it('filtert nach Generation und Typ', function () {
    $feuer = Type::factory()->create(['slug' => 'fire', 'name_de' => 'Feuer']);
    $wasser = Type::factory()->create(['slug' => 'water', 'name_de' => 'Wasser']);

    $glut = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Glumanda', 'generation' => 1]);
    $schiggy = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Schiggy', 'generation' => 1]);
    $karnimani = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Karnimani', 'generation' => 2]);

    $glut->types()->attach($feuer, ['slot' => 1]);
    $schiggy->types()->attach($wasser, ['slot' => 1]);
    $karnimani->types()->attach($wasser, ['slot' => 1]);

    $this->get(route('pokedex.index', ['typ' => 'water', 'gen' => 1]))
        ->assertOk()
        ->assertSee('Schiggy')
        ->assertDontSee('Karnimani')
        ->assertDontSee('Glumanda');
});

it('filtert nach Dringlichkeit', function () {
    $bank = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Dringend']);
    $modern = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Entspannt']);

    Obtainability::factory()->create([
        'pokemon_id' => $bank->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);
    Obtainability::factory()->create([
        'pokemon_id' => $modern->id,
        'game_id' => GameFactory::new()->modern()->create()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['prio' => 'bank_urgent']))
        ->assertOk()
        ->assertSee('Dringend')
        ->assertDontSee('Entspannt');
});

it('filtert auf fehlende und besessene Pokémon', function () {
    $besessen = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Habichschon']);
    $fehlt = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Fehltnoch']);

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $besessen->baseForm->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['status' => 'fehlend']))
        ->assertOk()
        ->assertSee('Fehltnoch')
        ->assertDontSee('Habichschon');
});

it('zeigt die Wunschliste als eigenen Filter', function () {
    $wunsch = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Wunschziel']);
    Pokemon::factory()->withBaseForm()->create(['name_de' => 'Egalmir']);

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $wunsch->baseForm->id,
        'is_favourite' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['status' => 'wunschliste']))
        ->assertOk()
        ->assertSee('Wunschziel')
        ->assertDontSee('Egalmir');
});

it('zeigt den Filter für unerreichbare Pokémon', function () {
    $erreichbar = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kriegich']);
    $unerreichbar = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kriegichnicht']);

    $meinSpiel = GameFactory::new()->modern()->create();
    $this->user->games()->attach($meinSpiel);

    Obtainability::factory()->create(['pokemon_id' => $erreichbar->id, 'game_id' => $meinSpiel->id]);
    Obtainability::factory()->create([
        'pokemon_id' => $unerreichbar->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['unerreichbar' => 1]))
        ->assertOk()
        ->assertSee('Kriegichnicht')
        ->assertDontSee('Kriegich"');   // exakter Treffer, nicht die Teilzeichenkette
});

it('sortiert nach Dringlichkeit', function () {
    $entspannt = Pokemon::factory()->withBaseForm()->create(['dex_nr' => 1, 'name_de' => 'Entspannt']);
    $dringend = Pokemon::factory()->withBaseForm()->create(['dex_nr' => 2, 'name_de' => 'Dringend']);

    Obtainability::factory()->create([
        'pokemon_id' => $entspannt->id,
        'game_id' => GameFactory::new()->modern()->create()->id,
    ]);
    Obtainability::factory()->create([
        'pokemon_id' => $dringend->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);

    $antwort = $this->actingAs($this->user)
        ->get(route('pokedex.index', ['sortierung' => 'dringlichkeit']))
        ->assertOk();

    // Das dringendere Pokémon muss im HTML vor dem entspannten stehen.
    $html = $antwort->getContent();
    expect(strpos($html, 'Dringend'))->toBeLessThan(strpos($html, 'Entspannt'));
});

it('zeigt die Detailseite mit Bezugsquellen und Entwicklungslinie', function () {
    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam', 'dex_nr' => 1]);
    $spiel = GameFactory::new()->modern()->create(['name_de' => 'Pokémon Karmesin']);

    Obtainability::factory()->create([
        'pokemon_id' => $basis->id,
        'game_id' => $spiel->id,
        'location_detail' => 'Route 1',
    ]);

    Pokemon::factory()->withBaseForm()->evolutionOnly($basis)->create(['name_de' => 'Bisaknosp']);

    $this->get(route('pokedex.show', $basis))
        ->assertOk()
        ->assertSee('Bisasam')
        ->assertSee('Pokémon Karmesin')
        ->assertSee('Route 1')
        ->assertSee('Bisaknosp');
});

it('zeigt die Mehrfach-Fang-Empfehlung auf der Detailseite', function () {
    $basis = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    $mitte = Pokemon::factory()->withBaseForm()->evolutionOnly($basis)->create(['name_de' => 'Bisaknosp']);
    Pokemon::factory()->withBaseForm()->evolutionOnly($mitte)->create(['name_de' => 'Bisaflor']);

    $this->actingAs($this->user)
        ->get(route('pokedex.show', $basis))
        ->assertOk()
        ->assertSee('Mehrfach-Fang-Empfehlung')
        ->assertSee('Fange 3× Bisasam');
});

it('paginiert lange Listen', function () {
    Pokemon::factory()->withBaseForm()->count(70)->create();

    $this->get(route('pokedex.index'))
        ->assertOk()
        ->assertSee('page=2');
});

it('zeigt einen Hinweis, wenn noch keine Daten importiert wurden', function () {
    $this->get(route('pokedex.index'))
        ->assertOk()
        ->assertSee('pokedex:import');
});

it('filtert auf alles, was an der Bank-Deadline hängt – auch das selbst Holbare', function () {
    $meins = GameFactory::new()->bankOnly()->create();
    $this->user->games()->attach($meins);

    $selbstHolbar = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Selbstholbar']);
    Obtainability::factory()->create(['pokemon_id' => $selbstHolbar->id, 'game_id' => $meins->id]);

    $fehltSpiel = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Spielfehlt']);
    Obtainability::factory()->create([
        'pokemon_id' => $fehltSpiel->id,
        'game_id' => GameFactory::new()->bankOnly()->create()->id,
    ]);

    $ohneFrist = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Entspannt']);
    Obtainability::factory()->create([
        'pokemon_id' => $ohneFrist->id,
        'game_id' => GameFactory::new()->modern()->create()->id,
    ]);

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['deadline' => 1]))
        ->assertOk()
        ->assertSee('Selbstholbar')
        ->assertSee('Spielfehlt')
        ->assertDontSee('Entspannt');
});

it('respektiert die gewählte Seitengröße', function () {
    Pokemon::factory()->withBaseForm()->count(40)->create();

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['pro_seite' => 30]))
        ->assertOk()
        ->assertSee('page=2');

    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['pro_seite' => 60]))
        ->assertOk()
        ->assertDontSee('page=2');
});

it('nimmt die Seitengröße aus den Einstellungen, wenn keine im Link steht', function () {
    Pokemon::factory()->withBaseForm()->count(40)->create();
    $this->user->settingsOrDefault()->update(['per_page' => 30]);

    $this->actingAs($this->user->fresh())
        ->get(route('pokedex.index'))
        ->assertOk()
        ->assertSee('page=2');
});

it('ignoriert eine unsinnige Seitengröße', function () {
    Pokemon::factory()->withBaseForm()->count(40)->create();

    // 99999 steht nicht in den erlaubten Werten – es bleibt beim Standard.
    $this->actingAs($this->user)
        ->get(route('pokedex.index', ['pro_seite' => 99999]))
        ->assertOk()
        ->assertDontSee('page=2');
});

/*
|--------------------------------------------------------------------------
| Bezugsquellen nach eigenen Spielen gruppiert (spec.md 2.3)
|--------------------------------------------------------------------------
|
| Eine flache Liste aus sechs Titeln beantwortet die eigentliche Frage nicht:
| "Komme ich da mit dem ran, was ich habe?"
*/

it('stellt die eigenen Spiele auf der Detailseite nach vorne', function () {
    $meins = GameFactory::new()->create(['name_de' => 'Pokémon X']);
    $fremd = GameFactory::new()->bankOnly()->create(['name_de' => 'HeartGold']);

    $this->user->games()->attach($meins);

    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $meins->id]);
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $fremd->id]);

    $antwort = $this->actingAs($this->user)
        ->get(route('pokedex.show', $pokemon))
        ->assertOk()
        ->assertSee('In Deinen Spielen')
        ->assertSee('Außerdem in diesen Spielen');

    expect($antwort->viewData('quellenInMeinenSpielen')->pluck('game_id')->all())->toBe([$meins->id])
        ->and($antwort->viewData('quellenAndereSpiele')->pluck('game_id')->all())->toBe([$fremd->id]);

    // Der eigene Titel steht im Markup vor den fremden.
    $html = $antwort->getContent();
    expect(strpos($html, 'Pokémon X'))->toBeLessThan(strpos($html, 'HeartGold'));
});

it('sagt deutlich, wenn es das Pokémon in keinem eigenen Spiel gibt', function () {
    $meins = GameFactory::new()->create(['name_de' => 'Karmesin']);
    $fremd = GameFactory::new()->bankOnly()->create(['name_de' => 'HeartGold']);

    $this->user->games()->attach($meins);

    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $fremd->id]);

    $this->actingAs($this->user)
        ->get(route('pokedex.show', $pokemon))
        ->assertOk()
        ->assertSee('Nur in Spielen, die Du nicht hast')
        ->assertDontSee('In Deinen Spielen');
});

it('bittet Gäste ohne eingetragene Spiele um ihre Spieleliste', function () {
    $game = GameFactory::new()->create(['name_de' => 'Karmesin']);
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Kapilz']);
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $this->actingAs($this->user)
        ->get(route('pokedex.show', $pokemon))
        ->assertOk()
        ->assertSee('In diesen Spielen')
        ->assertSee('Trag Deine Spiele ein');
});

it('verlinkt Bezugsquellen für angemeldete Nutzer auf die Spielseite', function () {
    $game = GameFactory::new()->create(['name_de' => 'Karmesin']);
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $this->actingAs($this->user)
        ->get(route('pokedex.show', $pokemon))
        ->assertOk()
        ->assertSee(route('games.show', $game), escape: false);
});

it('verlinkt für Gäste nicht auf die Spielseite', function () {
    // Ohne Login gibt es die Spiel-Ansicht nicht – der Name bleibt reiner Text.
    $game = GameFactory::new()->create(['name_de' => 'Karmesin']);
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    Obtainability::factory()->create(['pokemon_id' => $pokemon->id, 'game_id' => $game->id]);

    $this->get(route('pokedex.show', $pokemon))
        ->assertOk()
        ->assertSee('Karmesin')
        ->assertDontSee(route('games.show', $game), escape: false);
});
