<?php

/**
 * Fortschrittsbalken und die Zähl-Toggles aus den Einstellungen
 * (spec.md 2.2, 2.6, 2.10).
 */

use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Services\ProgressService;

beforeEach(function () {
    resetDexSequence();
    $this->service = new ProgressService;
    $this->user = User::factory()->create();
});

function besitze(User $user, PokemonForm $form, bool $shiny = false): void
{
    UserPokemonForm::updateOrCreate(
        ['user_id' => $user->id, 'pokemon_form_id' => $form->id],
        $shiny
            ? ['owned_shiny' => true, 'owned_shiny_at' => now()]
            : ['owned' => true, 'owned_at' => now()],
    );
}

it('zählt nur Basisformen in den Hauptbalken', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $b = Pokemon::factory()->withBaseForm()->create();
    PokemonForm::factory()->regional()->for($a)->create();

    besitze($this->user, $a->baseForm);

    $bar = $this->service->base($this->user);

    expect($bar->total)->toBe(2)
        ->and($bar->owned)->toBe(1)
        ->and($bar->percent())->toBe(50.0)
        ->and($bar->missing())->toBe(1);
});

it('führt Regionalformen als eigenen Balken', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $regional = PokemonForm::factory()->regional()->for($a)->create();

    besitze($this->user, $regional);

    $regionalBar = $this->service->regional($this->user);
    $baseBar = $this->service->base($this->user);

    expect($regionalBar->owned)->toBe(1)
        ->and($regionalBar->total)->toBe(1)
        ->and($baseBar->owned)->toBe(0);
});

it('lässt Regionalformen standardmäßig aus dem Gesamtfortschritt heraus', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $regional = PokemonForm::factory()->regional()->for($a)->create();

    besitze($this->user, $regional);

    $total = $this->service->total($this->user);

    expect($total->total)->toBe(1)   // nur die Basisform
        ->and($total->owned)->toBe(0);
});

it('rechnet Regionalformen ein, wenn der Nutzer den Toggle aktiviert', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $regional = PokemonForm::factory()->regional()->for($a)->create();

    besitze($this->user, $regional);
    $this->user->settingsOrDefault()->update(['count_regional_in_total' => true]);
    $this->user->refresh();

    $total = $this->service->total($this->user);

    expect($total->total)->toBe(2)
        ->and($total->owned)->toBe(1);
});

it('verdoppelt die Zielzahl, wenn Shiny mitgezählt wird', function () {
    $a = Pokemon::factory()->withBaseForm()->create();

    besitze($this->user, $a->baseForm);
    besitze($this->user, $a->baseForm, shiny: true);

    $this->user->settingsOrDefault()->update(['count_shiny_in_total' => true]);
    $this->user->refresh();

    $total = $this->service->total($this->user);

    expect($total->total)->toBe(2)   // normal + shiny
        ->and($total->owned)->toBe(2)
        ->and($total->isComplete())->toBeTrue();
});

it('zählt Shiny quer über alle Formen', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    $regional = PokemonForm::factory()->regional()->for($a)->create();

    besitze($this->user, $regional, shiny: true);

    $shiny = $this->service->shiny($this->user);

    expect($shiny->owned)->toBe(1)
        ->and($shiny->total)->toBe(2);
});

it('gruppiert den Fortschritt nach Generation', function () {
    $gen1 = Pokemon::factory()->withBaseForm()->create(['generation' => 1]);
    Pokemon::factory()->withBaseForm()->create(['generation' => 1]);
    Pokemon::factory()->withBaseForm()->create(['generation' => 2]);

    besitze($this->user, $gen1->baseForm);

    $bars = collect($this->service->byGeneration($this->user))->keyBy('key');

    expect($bars)->toHaveCount(2)
        ->and($bars['gen-1']->owned)->toBe(1)
        ->and($bars['gen-1']->total)->toBe(2)
        ->and($bars['gen-2']->owned)->toBe(0);
});

it('formuliert die Bildunterschrift wie in der Spec', function () {
    Pokemon::factory()->withBaseForm()->count(4)->create();
    $erstes = Pokemon::first();
    besitze($this->user, $erstes->baseForm);

    expect($this->service->base($this->user)->caption())
        ->toBe('1 / 4 Pokémon gesammelt (25,0 %)');
});

it('liefert einen leeren Balken ohne Division durch null', function () {
    $bar = $this->service->base($this->user);

    expect($bar->total)->toBe(0)
        ->and($bar->percent())->toBe(0.0)
        ->and($bar->isComplete())->toBeFalse();
});

it('baut eine Monatszeitreihe mit lückenlosen Monaten', function () {
    $a = Pokemon::factory()->withBaseForm()->create();
    besitze($this->user, $a->baseForm);

    $timeline = $this->service->monthlyTimeline($this->user, months: 3);

    expect($timeline)->toHaveCount(3)
        ->and($timeline[now()->format('Y-m')])->toBe(1);
});
