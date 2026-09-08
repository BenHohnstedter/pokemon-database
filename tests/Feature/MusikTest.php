<?php

/**
 * Hintergrundmusik (spec.md 2.9, FEATURE-UPDATES.md 15).
 *
 * Der Test hängt bewusst an der Konfiguration statt an festen Dateinamen: Wer
 * ein Stück ergänzt, trägt es in `config/pokedex.php` ein — und der Test prüft
 * dann automatisch mit, ob die Datei auch wirklich da liegt.
 */

use App\Models\User;

it('hat mindestens ein Stück hinterlegt, und jede Datei liegt auch da', function () {
    $stuecke = config('pokedex.music');

    expect($stuecke)->not->toBeEmpty();

    foreach ($stuecke as $stueck) {
        expect($stueck)->toHaveKeys(['datei', 'titel', 'urheber'])
            ->and(file_exists(public_path($stueck['datei'])))
            ->toBeTrue("Datei fehlt: {$stueck['datei']}");
    }
});

it('reicht die Stückliste an die Oberfläche durch', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    foreach (config('pokedex.music') as $stueck) {
        expect($html)->toContain($stueck['titel'])
            ->and($html)->toContain(basename($stueck['datei']));
    }
});

it('bietet einen Schalter zum Weiterschalten', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Nächstes Stück')
        ->and($html)->toContain('weiter()');
});

it('spielt nichts von Nintendo aus – die Stücke stehen dokumentiert unter CC0', function () {
    // Die Original-Soundtracks gehören Nintendo/Game Freak und dürfen nicht im
    // öffentlichen Repo liegen. Der Herkunftsnachweis ist deshalb Pflicht.
    $herkunft = public_path('audio/HERKUNFT.md');

    expect(file_exists($herkunft))->toBeTrue();

    $text = file_get_contents($herkunft);

    foreach (config('pokedex.music') as $stueck) {
        expect($text)->toContain(basename($stueck['datei']))
            ->and($text)->toContain($stueck['urheber']);
    }
});
