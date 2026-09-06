<?php

/**
 * Orden, XP-Vergabe und Trainer-Level (spec.md 2.9).
 */

use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Services\AchievementService;
use App\Services\OwnershipService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    resetDexSequence();
    $this->seed(AchievementSeeder::class);
    $this->service = app(AchievementService::class);
    $this->ownership = app(OwnershipService::class);
    $this->user = User::factory()->create();
});

function markiereBesessen(User $user, int $anzahl, bool $shiny = false): void
{
    foreach (PokemonForm::base()->limit($anzahl)->pluck('id') as $formId) {
        UserPokemonForm::updateOrCreate(
            ['user_id' => $user->id, 'pokemon_form_id' => $formId],
            $shiny ? ['owned_shiny' => true] : ['owned' => true],
        );
    }
}

it('schaltet den ersten Fang frei', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();
    markiereBesessen($this->user, 1);

    $neu = $this->service->sync($this->user);

    expect($neu->pluck('key'))->toContain('first_catch');
});

it('schreibt die XP-Belohnung des Ordens gut', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();
    markiereBesessen($this->user, 1);

    $this->service->sync($this->user);

    // "Erster Fang" gibt 50 XP.
    expect($this->user->fresh()->xp)->toBe(50);
});

it('schaltet denselben Orden nicht zweimal frei', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create();
    markiereBesessen($this->user, 1);

    $this->service->sync($this->user);
    $xpNachErstem = $this->user->fresh()->xp;

    $zweiterLauf = $this->service->sync($this->user->fresh());

    expect($zweiterLauf)->toBeEmpty()
        ->and($this->user->fresh()->xp)->toBe($xpNachErstem);
});

it('schaltet die Sammel-Meilensteine der Reihe nach frei', function () {
    Pokemon::factory()->withBaseForm()->count(12)->create();
    markiereBesessen($this->user, 10);

    $neu = $this->service->sync($this->user);

    expect($neu->pluck('key'))->toContain('first_catch')
        ->toContain('dex_10')
        ->not->toContain('dex_100');
});

it('erkennt eine komplett gesammelte Generation', function () {
    Pokemon::factory()->withBaseForm()->count(3)->create(['generation' => 1]);
    Pokemon::factory()->withBaseForm()->count(2)->create(['generation' => 2]);

    // Nur die drei aus Generation 1.
    $gen1Formen = PokemonForm::base()
        ->whereIn('pokemon_id', Pokemon::where('generation', 1)->select('id'))
        ->pluck('id');

    foreach ($gen1Formen as $formId) {
        UserPokemonForm::create([
            'user_id' => $this->user->id,
            'pokemon_form_id' => $formId,
            'owned' => true,
        ]);
    }

    $neu = $this->service->sync($this->user);

    expect($neu->pluck('key'))->toContain('gen_1_complete')
        ->not->toContain('gen_2_complete');
});

it('vergibt den Typensammler erst, wenn jeder Typ abgedeckt ist', function () {
    $feuer = Type::factory()->create(['slug' => 'fire']);
    $wasser = Type::factory()->create(['slug' => 'water']);

    $a = Pokemon::factory()->withBaseForm()->create();
    $b = Pokemon::factory()->withBaseForm()->create();
    $a->types()->attach($feuer, ['slot' => 1]);
    $b->types()->attach($wasser, ['slot' => 1]);

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $a->baseForm->id,
        'owned' => true,
    ]);

    expect($this->service->fulfilledKeys($this->user))->not->toContain('all_types');

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $b->baseForm->id,
        'owned' => true,
    ]);

    expect($this->service->fulfilledKeys($this->user->fresh()))->toContain('all_types');
});

it('zählt Shiny-Orden getrennt vom normalen Bestand', function () {
    Pokemon::factory()->withBaseForm()->count(12)->create();
    markiereBesessen($this->user, 10, shiny: true);

    $keys = $this->service->fulfilledKeys($this->user);

    expect($keys)->toContain('shiny_1')
        ->toContain('shiny_10')
        ->not->toContain('shiny_50')
        // Shiny allein zählt nicht als normaler Fang.
        ->not->toContain('first_catch');
});

it('vergibt die Bank-Rettungs-Orden über die eigene Schnittstelle', function () {
    $neu = $this->service->syncBankProgress($this->user, offeneBankFaelle: 0, gerettet: 12);

    expect($neu->pluck('key'))->toContain('bank_rescue_10')
        ->toContain('bank_clear')
        ->not->toContain('bank_rescue_50');
});

it('meldet "Deadline besiegt" nicht, solange nichts gerettet wurde', function () {
    $neu = $this->service->syncBankProgress($this->user, offeneBankFaelle: 0, gerettet: 0);

    expect($neu)->toBeEmpty();
});

it('gibt für seltene Pokémon mehr XP als für gewöhnliche', function () {
    $normal = Pokemon::factory()->withBaseForm()->create();
    $legendaer = Pokemon::factory()->withBaseForm()->legendary()->create();
    $mysterioes = Pokemon::factory()->withBaseForm()->mythical()->create();

    $xpNormal = $this->ownership->xpFor($normal->baseForm);
    $xpLegendaer = $this->ownership->xpFor($legendaer->baseForm);
    $xpMysterioes = $this->ownership->xpFor($mysterioes->baseForm);

    expect($xpLegendaer)->toBeGreaterThan($xpNormal)
        ->and($xpMysterioes)->toBeGreaterThan($xpLegendaer);
});

it('gibt für Shiny ein Vielfaches der normalen XP', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    expect($this->ownership->xpFor($pokemon->baseForm, shiny: true))
        ->toBe($this->ownership->xpFor($pokemon->baseForm) * config('pokedex.xp.shiny_multiplier'));
});

it('gibt für Regionalformen einen Zuschlag', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();
    $regional = PokemonForm::factory()->regional()->for($pokemon)->create();

    expect($this->ownership->xpFor($regional))
        ->toBe($this->ownership->xpFor($pokemon->baseForm) + config('pokedex.xp.regional_bonus'));
});

it('lässt die XP beim Zurücknehmen nicht unter null fallen', function () {
    $pokemon = Pokemon::factory()->withBaseForm()->create();

    $this->ownership->set($this->user, $pokemon->baseForm, owned: false);

    expect($this->user->fresh()->xp)->toBe(0);
});

it('rechnet Regionalform-Typen nicht der Basisform an', function () {
    $normal = Type::factory()->create(['slug' => 'normal']);
    $stahl = Type::factory()->create(['slug' => 'steel']);

    // Mauzi: Basisform Normal, Galar-Form Stahl.
    $mauzi = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Mauzi']);
    $galar = PokemonForm::factory()->regional('galar')->for($mauzi)->create();
    $mauzi->types()->attach($normal, ['slot' => 1]);
    DB::table('pokemon_type')->insert([
        'pokemon_id' => $mauzi->id,
        'pokemon_form_id' => $galar->id,
        'type_id' => $stahl->id,
        'slot' => 1,
    ]);

    // Stahlos deckt Stahl als Basisform ab – damit gehört Stahl zur Zielmenge.
    $stahlos = Pokemon::factory()->withBaseForm()->create(['name_de' => 'Stahlos']);
    $stahlos->types()->attach($stahl, ['slot' => 1]);

    // Nur Mauzis Basisform im Bestand, nicht die Galar-Form.
    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $mauzi->baseForm->id,
        'owned' => true,
    ]);

    // Vor der Korrektur zählte die Stahl-Zeile der Galar-Form hier mit.
    expect($this->service->fulfilledKeys($this->user))->not->toContain('all_types');

    UserPokemonForm::create([
        'user_id' => $this->user->id,
        'pokemon_form_id' => $stahlos->baseForm->id,
        'owned' => true,
    ]);

    expect($this->service->fulfilledKeys($this->user->fresh()))->toContain('all_types');
});
