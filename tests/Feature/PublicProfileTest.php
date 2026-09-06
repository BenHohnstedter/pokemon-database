<?php

/**
 * Öffentliches Profil zum Teilen des Fortschritts (spec.md 2.11).
 */

use App\Models\Achievement;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->trainer = User::factory()->create(['name' => 'Ash', 'xp' => 800]);
});

it('zeigt ein freigegebenes Profil auch ohne Login', function () {
    $this->trainer->update(['profile_public' => true]);
    Pokemon::factory()->withBaseForm()->count(4)->create();

    UserPokemonForm::create([
        'user_id' => $this->trainer->id,
        'pokemon_form_id' => PokemonForm::first()->id,
        'owned' => true,
    ]);

    $this->get(route('trainer.public', $this->trainer))
        ->assertOk()
        ->assertSee('Ash')
        ->assertSee('1 / 4', false);
});

it('meldet ein nicht freigegebenes Profil als nicht vorhanden', function () {
    // 404 statt 403, damit sich "gesperrt" nicht von "gibt es nicht"
    // unterscheiden lässt.
    $this->get(route('trainer.public', $this->trainer))->assertNotFound();
});

it('gibt keine Kontodaten preis', function () {
    $this->trainer->update(['profile_public' => true]);

    $antwort = $this->get(route('trainer.public', $this->trainer))->assertOk();

    expect($antwort->getContent())->not->toContain($this->trainer->email);
});

it('zeigt freigeschaltete Orden', function () {
    $this->seed(AchievementSeeder::class);
    $this->trainer->update(['profile_public' => true]);
    $this->trainer->achievements()->attach(
        Achievement::where('key', 'first_catch')->value('id'),
        ['unlocked_at' => now()],
    );

    $this->get(route('trainer.public', $this->trainer))
        ->assertOk()
        ->assertSee('Erster Fang');
});

it('lässt sich in den Einstellungen ein- und ausschalten', function () {
    $this->actingAs($this->trainer)->patch(route('settings.update'), [
        'go_region' => 'europa',
        'theme' => 'default',
        'profile_public' => '1',
    ]);

    expect($this->trainer->fresh()->profile_public)->toBeTrue();
    $this->get(route('trainer.public', $this->trainer))->assertOk();

    $this->actingAs($this->trainer)->patch(route('settings.update'), [
        'go_region' => 'europa',
        'theme' => 'default',
    ]);

    expect($this->trainer->fresh()->profile_public)->toBeFalse();
    $this->get(route('trainer.public', $this->trainer))->assertNotFound();
});

it('zeigt den Link zum Profil nur, wenn es freigegeben ist', function () {
    $this->actingAs($this->trainer)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee('Öffentliches Profil freigeben')
        ->assertDontSee(route('trainer.public', $this->trainer));

    $this->trainer->update(['profile_public' => true]);

    $this->actingAs($this->trainer->fresh())
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertSee(route('trainer.public', $this->trainer));
});
