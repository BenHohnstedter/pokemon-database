<?php

/**
 * Sammlungsstand exportieren und wieder einspielen.
 */

use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Services\CollectionTransfer;
use Database\Seeders\AchievementSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    resetDexSequence();
    $this->user = User::factory()->create(['name' => 'Demo Trainer']);
    $this->transfer = app(CollectionTransfer::class);
});

function markiere(User $user, PokemonForm $form, array $werte = ['owned' => true]): UserPokemonForm
{
    return UserPokemonForm::updateOrCreate(
        ['user_id' => $user->id, 'pokemon_form_id' => $form->id],
        array_merge(['owned_at' => now()], $werte),
    );
}

it('exportiert den Stand über den Form-Slug statt über interne IDs', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Bisasam']);
    $pokemon->baseForm->update(['slug' => 'bulbasaur']);
    markiere($this->user, $pokemon->baseForm->fresh());

    $daten = $this->transfer->export($this->user);

    expect($daten['format'])->toBe(CollectionTransfer::FORMAT_VERSION)
        ->and($daten['count'])->toBe(1)
        ->and($daten['entries'][0]['form'])->toBe('bulbasaur')
        ->and($daten['entries'][0]['owned'])->toBeTrue();
});

it('nimmt keine Kontodaten in den Export auf', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    markiere($this->user, $pokemon->baseForm);

    $json = $this->transfer->exportJson($this->user);
    $daten = json_decode($json, true);

    expect($json)->not->toContain($this->user->email)
        ->not->toContain('password')
        // Der Anzeigename darf drin sein, sonst nichts vom Konto.
        ->and(array_keys($daten))
        ->toBe(['format', 'app', 'exported_at', 'trainer', 'count', 'entries'])
        ->and($daten['entries'][0])->not->toHaveKey('user_id');
});

it('exportiert nur Einträge, die überhaupt etwas aussagen', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $b = Pokemon::factory()->withBaseForm()->create();
    $c = Pokemon::factory()->withBaseForm()->create();

    markiere($this->user, $a->baseForm);
    markiere($this->user, $b->baseForm, ['is_favourite' => true]);
    // c bekommt eine leere Zeile, wie sie beim Hin- und Herklicken entsteht.
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $c->baseForm->id,
        'owned' => false,
    ]);

    expect($this->transfer->export($this->user)['count'])->toBe(2);
});

it('spielt einen Export wieder ein', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    $pokemon->baseForm->update(['slug' => 'bulbasaur']);
    markiere($this->user, $pokemon->baseForm->fresh(), ['owned' => true, 'owned_shiny' => true]);

    $json = $this->transfer->exportJson($this->user);

    $anderer = User::factory()->create();
    $ergebnis = $this->transfer->import($anderer, $json);

    expect($ergebnis->successful)->toBeTrue()
        ->and($ergebnis->imported)->toBe(1);

    $eintrag = UserPokemonForm::where('user_id', $anderer->id)->first();

    expect($eintrag->owned)->toBeTrue()
        ->and($eintrag->owned_shiny)->toBeTrue();
});

it('nimmt beim Ergänzen nichts weg', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $b = Pokemon::factory()->withBaseForm()->create();
    $a->baseForm->update(['slug' => 'aaa']);
    $b->baseForm->update(['slug' => 'bbb']);

    // Der Nutzer hat b, die Datei kennt nur a.
    markiere($this->user, $b->baseForm->fresh());

    $json = json_encode([
        'format' => 1,
        'entries' => [['form' => 'aaa', 'owned' => true]],
    ]);

    $this->transfer->import($this->user, $json);

    expect(UserPokemonForm::where('user_id', $this->user->id)->where('owned', true)->count())->toBe(2);
});

it('setzt beim Ersetzen alles zurück, was die Datei nicht nennt', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $b = Pokemon::factory()->withBaseForm()->create();
    $a->baseForm->update(['slug' => 'aaa']);
    $b->baseForm->update(['slug' => 'bbb']);

    markiere($this->user, $a->baseForm->fresh());
    markiere($this->user, $b->baseForm->fresh());

    $json = json_encode([
        'format' => 1,
        'entries' => [['form' => 'aaa', 'owned' => true]],
    ]);

    $this->transfer->import($this->user, $json, ersetzen: true);

    expect(UserPokemonForm::where('user_id', $this->user->id)->where('owned', true)->count())->toBe(1)
        ->and(UserPokemonForm::whereRelation('form', 'slug', 'bbb')->value('owned'))->toBeFalse();
});

it('überspringt unbekannte Formen und meldet sie', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $a->baseForm->update(['slug' => 'aaa']);

    $json = json_encode([
        'format' => 1,
        'entries' => [
            ['form' => 'aaa', 'owned' => true],
            ['form' => 'gibtsnicht', 'owned' => true],
            ['form' => 'auchnicht', 'owned' => true],
        ],
    ]);

    $ergebnis = $this->transfer->import($this->user, $json);

    expect($ergebnis->successful)->toBeTrue()
        ->and($ergebnis->imported)->toBe(1)
        ->and($ergebnis->unknownSlugs)->toBe(['gibtsnicht', 'auchnicht'])
        ->and($ergebnis->summary())->toContain('2 unbekannte Formen übersprungen');
});

it('lehnt kaputtes JSON mit einer verständlichen Meldung ab', function () {
    $ergebnis = $this->transfer->import($this->user, '{kein json');

    expect($ergebnis->successful)->toBeFalse()
        ->and($ergebnis->summary())->toContain('kein gültiges JSON');
});

it('lehnt eine Datei ohne entries-Feld ab', function () {
    $ergebnis = $this->transfer->import($this->user, json_encode(['irgendwas' => 1]));

    expect($ergebnis->successful)->toBeFalse()
        ->and($ergebnis->summary())->toContain('entries');
});

it('lehnt ein neueres Dateiformat ab, statt es falsch zu deuten', function () {
    $ergebnis = $this->transfer->import($this->user, json_encode([
        'format' => 99,
        'entries' => [],
    ]));

    expect($ergebnis->successful)->toBeFalse()
        ->and($ergebnis->summary())->toContain('Format 99');
});

it('schaltet nach dem Import fällige Orden frei', function () {
    $this->seed(AchievementSeeder::class);
    Pokemon::factory()->withBaseForm()->count(5)->create();
    PokemonForm::first()->update(['slug' => 'aaa']);

    $this->transfer->import($this->user, json_encode([
        'format' => 1,
        'entries' => [['form' => 'aaa', 'owned' => true]],
    ]));

    expect($this->user->fresh()->achievements()->pluck('key'))->toContain('first_catch');
});

// ── Über die Weboberfläche ──────────────────────────────────────────────────

it('lädt den Export als JSON-Datei herunter', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    markiere($this->user, $pokemon->baseForm);

    $antwort = $this->actingAs($this->user)
        ->get(route('collection.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/json; charset=utf-8');

    expect($antwort->headers->get('content-disposition'))->toContain('dex-rescue-demo-trainer-');
});

it('zeigt die Übertragungsseite mit den Kennzahlen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    markiere($this->user, $pokemon->baseForm, ['owned' => true, 'owned_shiny' => true]);

    $this->actingAs($this->user)
        ->get(route('collection.transfer'))
        ->assertOk()
        ->assertSee('Exportieren')
        ->assertSee('Importieren');
});

it('nimmt einen Upload entgegen und übernimmt ihn', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    $pokemon->baseForm->update(['slug' => 'bulbasaur']);

    $json = json_encode(['format' => 1, 'entries' => [['form' => 'bulbasaur', 'owned' => true]]]);
    $datei = UploadedFile::fake()->createWithContent('stand.json', $json);

    $this->actingAs($this->user)
        ->post(route('collection.import'), ['datei' => $datei, 'modus' => 'ergaenzen'])
        ->assertRedirect(route('collection.transfer'))
        ->assertSessionHas('status');

    expect(UserPokemonForm::where('user_id', $this->user->id)->where('owned', true)->count())->toBe(1);
});

it('weist eine kaputte Datei im Formular zurück', function () {
    $datei = UploadedFile::fake()->createWithContent('stand.json', '{kaputt');

    $this->actingAs($this->user)
        ->post(route('collection.import'), ['datei' => $datei, 'modus' => 'ergaenzen'])
        ->assertSessionHasErrors('datei');
});

it('verlangt für Export und Import einen Login', function () {
    $this->get(route('collection.export'))->assertRedirect(route('login'));
    $this->get(route('collection.transfer'))->assertRedirect(route('login'));
    $this->post(route('collection.import'))->assertRedirect(route('login'));
});
