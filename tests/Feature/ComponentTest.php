<?php

/**
 * Komponententests für die zentralen UI-Bausteine (spec.md 7):
 * Pokémon-Karte, Fortschrittsbalken, Dringlichkeits-Badge, Countdown-Widget.
 *
 * Gerendert wird jeweils isoliert über Blade::render, damit ein Fehler in der
 * Komponente nicht erst über eine ganze Seite auffällt.
 */

use App\Enums\Difficulty;
use App\Enums\PriorityLevel;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Models\User;
use App\Services\BankDeadline;
use App\Support\PriorityResult;
use App\Support\ProgressBar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => resetDexSequence());

afterEach(fn () => Carbon::setTestNow());

/** Baut die Zeilenstruktur, die PokedexQuery liefert. */
function karteZeile(PokemonForm $form, array $ueberschreiben = []): object
{
    return (object) array_merge([
        'form' => $form,
        'owned' => false,
        'ownedShiny' => false,
        'favourite' => false,
        'priority' => new PriorityResult(
            level: PriorityLevel::BankUrgent,
            difficulty: Difficulty::Schwer,
            reason: 'Testgrund',
        ),
    ], $ueberschreiben);
}

// ── Fortschrittsbalken ──────────────────────────────────────────────────────

it('rendert den Fortschrittsbalken mit Zahlen, Prozent und ARIA-Werten', function () {
    $html = Blade::render(
        '<x-progress-bar :bar="$bar" />',
        ['bar' => new ProgressBar('Nationaler Dex', 587, 1302, 'base')]
    );

    expect($html)->toContain('Nationaler Dex')
        ->toContain('587')
        ->toContain('1302')
        ->toContain('45,1')
        ->toContain('role="progressbar"')
        ->toContain('aria-valuenow="45"')
        ->toContain('width: 45.1%');
});

it('meldet einen vollständigen Balken als komplett', function () {
    $html = Blade::render(
        '<x-progress-bar :bar="$bar" />',
        ['bar' => new ProgressBar('Kanto', 151, 151)]
    );

    expect($html)->toContain('Komplett')
        ->not->toContain('Noch 0');
});

it('kommt beim leeren Balken ohne Division durch null aus', function () {
    $html = Blade::render(
        '<x-progress-bar :bar="$bar" />',
        ['bar' => new ProgressBar('Leer', 0, 0)]
    );

    expect($html)->toContain('aria-valuenow="0"')
        ->toContain('0,0');
});

// ── Dringlichkeits- und Schwierigkeits-Badge ────────────────────────────────

it('rendert die Dringlichkeitsstufe mit Icon und Beschreibung', function () {
    $html = Blade::render(
        '<x-priority-badge :priority="$p" />',
        ['p' => PriorityLevel::BankUrgent]
    );

    expect($html)->toContain('🔴')
        ->toContain('Dringend – Bank-Deadline')
        ->toContain('vor der Abschaltung erledigen');
});

it('hält das Label für Screenreader vor, auch wenn es visuell ausgeblendet ist', function () {
    $html = Blade::render(
        '<x-priority-badge :priority="$p" :show-label="false" />',
        ['p' => PriorityLevel::Easy]
    );

    expect($html)->toContain('sr-only')
        ->toContain('Einfach');
});

it('rendert das Schwierigkeits-Badge', function () {
    $html = Blade::render(
        '<x-difficulty-badge :difficulty="$d" />',
        ['d' => Difficulty::SehrSchwer]
    );

    expect($html)->toContain('Sehr schwer');
});

// ── Typ-Badge ───────────────────────────────────────────────────────────────

it('rendert das Typ-Badge in der Typenfarbe', function () {
    $html = Blade::render(
        '<x-type-badge :type="$t" />',
        ['t' => new Type(['slug' => 'fire', 'name_de' => 'Feuer', 'color' => '#EE8130'])]
    );

    expect($html)->toContain('Feuer')
        ->toContain('#EE8130');
});

// ── Pokémon-Karte ───────────────────────────────────────────────────────────

it('rendert die Karte mit Dex-Nummer, Name, Bild und Alt-Text', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create([
        'dex_nr' => 25,
        'name_de' => 'Pikachu',
    ]);
    $pokemon->baseForm->update(['artwork_url' => 'https://art/pikachu.png']);

    $html = Blade::render(
        '<x-pokemon-card :row="$row" :interactive="false" />',
        ['row' => karteZeile($pokemon->baseForm->fresh())]
    );

    expect($html)->toContain('#0025')
        ->toContain('Pikachu')
        ->toContain('https://art/pikachu.png')
        ->toContain('alt="Pikachu"')
        ->toContain('loading="lazy"');
});

it('markiert eine fehlende Karte als Schattenriss und eine besessene als besessen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    $fehlt = Blade::render(
        '<x-pokemon-card :row="$row" :interactive="false" />',
        ['row' => karteZeile($pokemon->baseForm)]
    );
    $besessen = Blade::render(
        '<x-pokemon-card :row="$row" :interactive="false" />',
        ['row' => karteZeile($pokemon->baseForm, ['owned' => true])]
    );

    expect($fehlt)->toContain('dex-card--missing')
        ->and($besessen)->toContain('dex-card--owned')
        ->not->toContain('dex-card--missing');
});

it('zeigt die Typen der Form, nicht die der Art', function () {
    $feuer = Type::factory()->create(['slug' => 'fire', 'name_de' => 'Feuer']);
    $eis = Type::factory()->create(['slug' => 'ice', 'name_de' => 'Eis']);

    $vulpix = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Vulpix']);
    $vulpix->types()->attach($feuer, ['slot' => 1]);

    $alola = PokemonForm::factory()->regional()->for($vulpix)->create([
        'name_de' => 'Vulpix (Alola-Form)',
    ]);

    // Form-Typen werden direkt geschrieben, siehe Kommentar an PokemonForm::types().
    DB::table('pokemon_type')->insert([
        'pokemon_id' => $vulpix->id,
        'pokemon_form_id' => $alola->id,
        'type_id' => $eis->id,
        'slot' => 1,
    ]);

    $html = Blade::render(
        '<x-pokemon-card :row="$row" :interactive="false" />',
        ['row' => karteZeile($alola->fresh())]
    );

    expect($html)->toContain('Eis')
        ->not->toContain('Feuer');
});

it('blendet die Klick-Schalter für Gäste aus', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    $html = Blade::render(
        '<x-pokemon-card :row="$row" :interactive="false" />',
        ['row' => karteZeile($pokemon->baseForm)]
    );

    expect($html)->not->toContain('Besitze ich')
        ->not->toContain('dexToggle(');
});

it('rendert für eingeloggte Nutzer die Klick-Schalter samt CSRF-Token', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    $html = Blade::render(
        '<x-pokemon-card :row="$row" />',
        ['row' => karteZeile($pokemon->baseForm)]
    );

    expect($html)->toContain('dexToggle(')
        ->toContain('Besitze ich')
        ->toContain(csrf_token())
        ->toContain('dusk="toggle-'.$pokemon->baseForm->id.'"');
});

// ── Countdown-Widget ────────────────────────────────────────────────────────

it('zeigt die verbleibenden Tage und die Zahl der betroffenen Pokémon', function () {
    Carbon::setTestNow('2027-02-16 12:00:00');
    config()->set('pokedex.bank_shutdown_at', '2027-02-26 23:59:59');

    $html = Blade::render(
        '<x-bank-countdown :deadline="$d" :betroffen="42" />',
        ['d' => new BankDeadline]
    );

    expect($html)->toContain('Noch 10 Tage')
        ->toContain('42')
        ->toContain('26.02.2027')
        ->toContain('Alle betroffenen ansehen');
});

it('feiert, wenn kein Pokémon mehr an der Deadline hängt', function () {
    Carbon::setTestNow('2027-02-01 12:00:00');
    config()->set('pokedex.bank_shutdown_at', '2027-02-26 23:59:59');

    $html = Blade::render(
        '<x-bank-countdown :deadline="$d" :betroffen="0" />',
        ['d' => new BankDeadline]
    );

    expect($html)->toContain('Kein Pokémon hängt mehr an der Bank-Deadline')
        ->not->toContain('Alle betroffenen ansehen');
});

it('meldet die Frist als abgelaufen', function () {
    Carbon::setTestNow('2027-03-05 12:00:00');
    config()->set('pokedex.bank_shutdown_at', '2027-02-26 23:59:59');

    $html = Blade::render(
        '<x-bank-countdown :deadline="$d" :betroffen="7" />',
        ['d' => new BankDeadline]
    );

    expect($html)->toContain('Pokémon Bank ist abgeschaltet')
        ->not->toContain('Alle betroffenen ansehen');
});

it('rendert genau ein class-Attribut am Kartenrahmen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    // Zwei class-Attribute nebeneinander verwirft der Browser stillschweigend –
    // in der Gast-Ansicht fehlte dadurch die komplette Basis-Optik.
    foreach ([false, true] as $interaktiv) {
        if ($interaktiv) {
            $this->actingAs(User::factory()->create());
        }

        $html = Blade::render(
            '<x-pokemon-card :row="$row" :interactive="$i" />',
            ['row' => karteZeile($pokemon->baseForm), 'i' => $interaktiv]
        );

        $rahmen = substr($html, 0, strpos($html, '>') + 1);

        expect(substr_count($rahmen, ' class='))->toBe(
            1,
            'Kartenrahmen hat mehr als ein class-Attribut ('.($interaktiv ? 'eingeloggt' : 'Gast').')'
        )
            ->and($rahmen)->toContain('dex-card');
    }
});
