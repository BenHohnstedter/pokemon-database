<?php

/**
 * Anmeldung, Registrierung und Profil laufen im selben dunklen Theme wie der
 * Rest der App (FEATURE-UPDATES.md 11).
 *
 * Der Test greift bewusst die *Klassen* ab statt eines Screenshots: Das Weiß
 * kam nicht aus einer einzelnen Seite, sondern aus den Breeze-Bausteinen
 * (`bg-white`, `text-gray-700`). Solange keine Seite diese Klassen mehr
 * ausliefert, kann es auch nicht zurückkommen.
 */

use App\Models\User;

/** Klassen, an denen das ungestylte Breeze-Gerüst zu erkennen war. */
function helleBreezeKlassen(): array
{
    return ['bg-white', 'text-gray-', 'border-gray-', 'text-indigo-', 'bg-gray-500'];
}

it('rendert die Anmeldung dunkel', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('pixel-panel');

    foreach (helleBreezeKlassen() as $klasse) {
        expect($html)->not->toContain($klasse);
    }
});

it('rendert die Registrierung dunkel', function () {
    $html = $this->get(route('register'))->assertOk()->getContent();

    expect($html)->toContain('pixel-panel');

    foreach (helleBreezeKlassen() as $klasse) {
        expect($html)->not->toContain($klasse);
    }
});

it('rendert die Profilseite dunkel', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertOk()
        ->getContent();

    expect($html)->toContain('pixel-panel');

    foreach (helleBreezeKlassen() as $klasse) {
        expect($html)->not->toContain($klasse);
    }
});

it('gibt den Eingabefeldern das Pixel-Theme statt der Breeze-Optik', function () {
    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)->toContain('class="pixel-input')
        ->and($html)->toContain('pixel-button');
});

it('verlinkt ein Favicon – angemeldet wie abgemeldet', function () {
    $gast = $this->get(route('login'))->assertOk()->getContent();
    $angemeldet = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    foreach ([$gast, $angemeldet] as $html) {
        expect($html)->toContain('rel="icon"')
            ->and($html)->toContain('icons/favicon-32.png');
    }
});
