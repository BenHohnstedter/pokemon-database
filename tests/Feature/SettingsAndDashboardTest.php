<?php

/**
 * Dashboard, Einstellungen, Statistik und Trainerkarte
 * (spec.md 2.2, 2.6, 2.9, 2.10).
 */

use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    resetDexSequence();
    $this->user = User::factory()->create();
});

it('zeigt das Dashboard mit Fortschritt und Countdown', function () {
    Pokemon::factory()->withBaseForm()->count(4)->create();
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => PokemonForm::first()->id,
        'owned' => true,
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Gesamtfortschritt')
        ->assertSee('Pokémon Bank')
        ->assertSee('1 / 4', false);
});

it('nennt auf dem Dashboard die Zahl der Bank-kritischen Pokémon', function () {
    $this->seed(GameSeeder::class);

    $dringend = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bankfall']);
    Obtainability::factory()->create([
        'pokemon_id' => $dringend->id,
        'game_id' => \App\Models\Game::where('slug', 'black')->value('id'),
    ]);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Vor der Bank-Abschaltung erledigen');
});

it('leitet Gäste vom Dashboard zum Login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('zeigt die Einstellungsseite mit der Spieleliste', function () {
    $this->seed(GameSeeder::class);

    $this->actingAs($this->user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Welche Spiele besitzt Du?')
        ->assertSee('Pokémon GO')
        ->assertSee('Game Boy Grün');
});

it('speichert Spielebesitz, GO-Region und Zähl-Toggles', function () {
    $this->seed(GameSeeder::class);
    $spiel = \App\Models\Game::where('slug', 'sword')->first();

    $this->actingAs($this->user)
        ->patch(route('settings.update'), [
            'go_region' => 'ozeanien',
            'theme' => 'gameboy',
            'count_regional_in_total' => '1',
            'count_shiny_in_total' => '1',
            'music_volume' => 60,
            'spiele' => [$spiel->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $settings = $this->user->fresh()->settingsOrDefault();

    expect($settings->go_region->value)->toBe('ozeanien')
        ->and($settings->theme)->toBe('gameboy')
        ->and($settings->count_regional_in_total)->toBeTrue()
        ->and($settings->count_shiny_in_total)->toBeTrue()
        ->and($settings->music_volume)->toBe(60)
        ->and($this->user->fresh()->games)->toHaveCount(1);
});

it('nimmt Häkchen zurück, wenn die Checkbox nicht mitgeschickt wird', function () {
    $this->user->settingsOrDefault()->update(['count_regional_in_total' => true]);

    $this->actingAs($this->user)->patch(route('settings.update'), [
        'go_region' => 'europa',
        'theme' => 'default',
        'music_volume' => 35,
    ]);

    expect($this->user->fresh()->settingsOrDefault()->count_regional_in_total)->toBeFalse();
});

it('lehnt eine unbekannte GO-Region ab', function () {
    $this->actingAs($this->user)
        ->patch(route('settings.update'), [
            'go_region' => 'mond',
            'theme' => 'default',
            'music_volume' => 35,
        ])
        ->assertSessionHasErrors('go_region');
});

it('wendet das gewählte Theme auf das HTML-Dokument an', function () {
    $this->user->settingsOrDefault()->update(['theme' => 'gameboy']);

    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-theme="gameboy"', false);
});

it('zeigt die Statistikseite', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();

    $this->actingAs($this->user)
        ->get(route('statistics'))
        ->assertOk()
        ->assertSee('Zuwachs pro Monat')
        ->assertSee('Nach Typ');
});

it('zeigt die Trainerkarte mit Level und Orden', function () {
    $this->seed(AchievementSeeder::class);
    $this->user->update(['xp' => 350]);

    $this->actingAs($this->user)
        ->get(route('trainer.card'))
        ->assertOk()
        ->assertSee('Trainerkarte')
        ->assertSee('Orden')
        ->assertSee('350');
});

it('zeigt die Bestenliste und den eigenen Platz', function () {
    User::factory()->create(['name' => 'Konkurrenz', 'xp' => 9999]);
    $this->user->update(['xp' => 100]);

    $this->actingAs($this->user)
        ->get(route('trainer.leaderboard'))
        ->assertOk()
        ->assertSee('Konkurrenz')
        ->assertSee('(Du)');
});

it('schaltet die Bestenliste auf Freunde um', function () {
    User::factory()->create(['name' => 'Fremder', 'xp' => 9999]);

    $this->actingAs($this->user)
        ->get(route('trainer.leaderboard', ['freunde' => 1]))
        ->assertOk()
        ->assertDontSee('Fremder');
});

it('schickt eine Freundschaftsanfrage und nimmt sie an', function () {
    $freund = User::factory()->create(['name' => 'Kumpel']);

    $this->actingAs($this->user)
        ->post(route('friends.add'), ['email' => $freund->email])
        ->assertSessionHas('status');

    $anfrage = \App\Models\Friendship::first();
    expect($anfrage->status)->toBe('pending');

    $this->actingAs($freund)
        ->post(route('friends.accept', $anfrage))
        ->assertSessionHas('status');

    expect($this->user->fresh()->friends)->toHaveCount(1)
        ->and($freund->fresh()->friends)->toHaveCount(1);
});

it('verhindert das Bestätigen fremder Anfragen', function () {
    $freund = User::factory()->create();
    $dritter = User::factory()->create();

    $anfrage = \App\Models\Friendship::create([
        'user_id' => $this->user->id,
        'friend_id' => $freund->id,
        'status' => 'pending',
    ]);

    $this->actingAs($dritter)
        ->post(route('friends.accept', $anfrage))
        ->assertForbidden();
});

it('lässt niemanden sich selbst als Freund hinzufügen', function () {
    $this->actingAs($this->user)
        ->post(route('friends.add'), ['email' => $this->user->email])
        ->assertSessionHasErrors('email');
});

it('zeigt die Startseite mit dem Bank-Countdown', function () {
    Carbon::setTestNow('2026-09-06');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Dex')
        ->assertSee('Noch')
        ->assertSee('26.02.2027');

    Carbon::setTestNow();
});
