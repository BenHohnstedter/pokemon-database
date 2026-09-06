<?php

/**
 * Sammlungsstand: Einzel-Toggle, Wunschliste und Masseneingabe (spec.md 2.5).
 */

use App\Models\Pokemon;
use App\Models\User;
use App\Models\UserPokemonForm;
use Database\Seeders\AchievementSeeder;

beforeEach(function () {
    resetDexSequence();
    $this->user = User::factory()->create();
});

it('verlangt einen Login für das Umschalten', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    $this->post(route('collection.toggle', $pokemon->baseForm))
        ->assertRedirect(route('login'));
});

it('schaltet den Besitz um und wieder zurück', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    $form = $pokemon->baseForm;

    $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $form))
        ->assertOk()
        ->assertJson(['status' => true]);

    expect(UserPokemonForm::where('pokemon_form_id', $form->id)->value('owned'))->toBeTrue();

    $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $form))
        ->assertOk()
        ->assertJson(['status' => false]);

    expect(UserPokemonForm::where('pokemon_form_id', $form->id)->value('owned'))->toBeFalse();
});

it('setzt beim Markieren einen Zeitstempel für die Verlaufsstatistik', function () {
    $form = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $this->actingAs($this->user)->postJson(route('collection.toggle', $form));

    expect(UserPokemonForm::where('pokemon_form_id', $form->id)->value('owned_at'))->not->toBeNull();
});

it('führt Shiny getrennt vom normalen Bestand', function () {
    $form = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $form), ['variante' => 'shiny'])
        ->assertOk();

    $eintrag = UserPokemonForm::where('pokemon_form_id', $form->id)->first();

    expect($eintrag->owned_shiny)->toBeTrue()
        ->and($eintrag->owned)->toBeFalse();
});

it('schaltet die Wunschliste unabhängig vom Besitz um', function () {
    $form = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $form), ['variante' => 'favorit'])
        ->assertOk()
        ->assertJson(['status' => true]);

    $eintrag = UserPokemonForm::where('pokemon_form_id', $form->id)->first();

    expect($eintrag->is_favourite)->toBeTrue()
        ->and($eintrag->owned)->toBeFalse();
});

it('vergibt XP beim Markieren und nimmt sie beim Zurücknehmen zurück', function () {
    $form = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $this->actingAs($this->user)->postJson(route('collection.toggle', $form));
    $nachher = $this->user->fresh()->xp;

    expect($nachher)->toBeGreaterThan(0);

    $this->actingAs($this->user)->postJson(route('collection.toggle', $form));

    expect($this->user->fresh()->xp)->toBe(0);
});

it('zeigt eine Vorschau vor der Masseneingabe', function () {
    Pokemon::factory()->withBaseForm()->count(5)->create();

    $this->actingAs($this->user)
        ->post(route('collection.bulk.preview'), [
            'eingabe' => '1-3,5',
            'aktion' => 'besitzen',
        ])
        ->assertOk()
        ->assertSee('4')
        ->assertSee('bestätigen?', false);

    // Die Vorschau darf noch nichts gespeichert haben.
    expect(UserPokemonForm::count())->toBe(0);
});

it('markiert Bereiche per Masseneingabe als besessen', function () {
    Pokemon::factory()->withBaseForm()->count(10)->create();

    $this->actingAs($this->user)
        ->post(route('collection.bulk.apply'), [
            'eingabe' => '1-5,8',
            'aktion' => 'besitzen',
        ])
        ->assertRedirect(route('collection.bulk'))
        ->assertSessionHas('status');

    expect(UserPokemonForm::where('owned', true)->count())->toBe(6);
});

it('nimmt eine Massenmarkierung wieder zurück', function () {
    Pokemon::factory()->withBaseForm()->count(10)->create();

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-5',
        'aktion' => 'besitzen',
    ]);

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-3',
        'aktion' => 'entfernen',
    ]);

    expect(UserPokemonForm::where('owned', true)->count())->toBe(2);
});

it('ignoriert Nummern, die es noch nicht gibt', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-3,900',
        'aktion' => 'besitzen',
    ]);

    expect(UserPokemonForm::where('owned', true)->count())->toBe(3);
});

it('meldet, wenn die Masseneingabe nichts verändert', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-3',
        'aktion' => 'besitzen',
    ]);

    $this->actingAs($this->user)
        ->post(route('collection.bulk.apply'), [
            'eingabe' => '1-3',
            'aktion' => 'besitzen',
        ])
        ->assertSessionHas('status', 'Nichts zu tun – die Auswahl war bereits so gesetzt.');
});

it('schreibt bei einem zweiten identischen Durchlauf keine XP gut', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-3',
        'aktion' => 'besitzen',
    ]);

    $nachErstemLauf = $this->user->fresh()->xp;

    $this->actingAs($this->user)->post(route('collection.bulk.apply'), [
        'eingabe' => '1-3',
        'aktion' => 'besitzen',
    ]);

    expect($this->user->fresh()->xp)->toBe($nachErstemLauf);
});

it('lehnt eine leere Masseneingabe ab', function () {
    $this->actingAs($this->user)
        ->post(route('collection.bulk.apply'), ['eingabe' => '', 'aktion' => 'besitzen'])
        ->assertSessionHasErrors('eingabe');
});

it('meldet neu freigeschaltete Orden in der Antwort zurück', function () {
    $this->seed(AchievementSeeder::class);
    $form = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $antwort = $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $form))
        ->assertOk();

    // Der erste Fang schaltet "Erster Fang" frei – das UI feiert das.
    expect(collect($antwort->json('orden'))->pluck('name'))->toContain('Erster Fang');
});

it('meldet beim zweiten Fang keinen Orden mehr', function () {
    $this->seed(AchievementSeeder::class);

    // Genug Arten, damit der Dex nach zwei Fängen nicht schon komplett ist –
    // sonst fiele hier zu Recht der Orden "Vollständig".
    Pokemon::factory()->withBaseForm()->count(8)->create();
    $erste = Pokemon::factory()->withBaseForm()->create()->baseForm;
    $zweite = Pokemon::factory()->withBaseForm()->create()->baseForm;

    $this->actingAs($this->user)->postJson(route('collection.toggle', $erste));

    $antwort = $this->actingAs($this->user)
        ->postJson(route('collection.toggle', $zweite))
        ->assertOk();

    expect($antwort->json('orden'))->toBe([]);
});

it('drosselt die Registrierung', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post(route('register'), [
            'name' => "Trainer {$i}",
            'email' => "trainer{$i}@example.com",
            'password' => 'geheim-genug-123',
            'password_confirmation' => 'geheim-genug-123',
        ]);
        auth()->logout();
    }

    $this->post(route('register'), [
        'name' => 'Einer zu viel',
        'email' => 'zuviel@example.com',
        'password' => 'geheim-genug-123',
        'password_confirmation' => 'geheim-genug-123',
    ])->assertStatus(429);
});
