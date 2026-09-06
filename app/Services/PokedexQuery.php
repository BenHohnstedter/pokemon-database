<?php

namespace App\Services;

use App\Enums\FormType;
use App\Models\Game;
use App\Models\GoAvailability;
use App\Models\Obtainability;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Support\EvolutionFallback;
use App\Support\PriorityContext;
use App\Support\PriorityResult;
use Illuminate\Support\Collection;

/**
 * Lädt den kompletten Pokédex einmal und bewertet ihn am Stück.
 *
 * Warum nicht pro Seite? Weil nach Dringlichkeit gefiltert und sortiert wird
 * (spec.md 2.7) – dafür muss die Stufe für *alle* Einträge bekannt sein, nicht
 * nur für die 60 auf der aktuellen Seite. Der Trick ist, die drei nötigen
 * Tabellen in drei Queries zu holen und die Relationen von Hand zu verdrahten,
 * statt Eloquent pro Zeile nachladen zu lassen (spec.md 7, Performance).
 */
class PokedexQuery
{
    /** @var array<int,Collection<int,Obtainability>>|null */
    private ?array $sourcesByPokemon = null;

    /** @var Collection<int,GoAvailability>|null */
    private ?Collection $goByPokemon = null;

    public function __construct(private readonly PriorityEngine $engine) {}

    /**
     * Alle Formen mit Bewertung, fertig zum Filtern.
     *
     * @param  array<int,FormType>  $formTypes
     * @return Collection<int,object{form:PokemonForm,owned:bool,ownedShiny:bool,favourite:bool,priority:PriorityResult}>
     */
    public function evaluate(?User $user, array $formTypes = [FormType::Base]): Collection
    {
        $context = $user ? PriorityContext::forUser($user) : PriorityContext::guest();
        $ownership = $this->ownershipMap($user);

        $this->primeLookups();

        return PokemonForm::query()
            ->with([
                'pokemon.types',
                'pokemon.evolvesFrom:id,name_de,source_pokemon_id',
                'pokemon.evolvesFrom.sourcePokemon:id,name_de',
            ])
            ->whereIn('form_type', array_map(fn (FormType $t) => $t->value, $formTypes))
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_forms.pokemon_id')
            ->orderBy('pokemon.dex_nr')
            ->orderBy('pokemon_forms.sort_order')
            ->select('pokemon_forms.*')
            ->get()
            ->map(function (PokemonForm $form) use ($context, $ownership) {
                $state = $ownership[$form->id] ?? null;
                $owned = (bool) ($state?->owned);

                return (object) [
                    'form' => $form,
                    'owned' => $owned,
                    'ownedShiny' => (bool) ($state?->owned_shiny),
                    'favourite' => (bool) ($state?->is_favourite),
                    'priority' => $this->engine->evaluate(
                        $form,
                        $context,
                        $owned,
                        $this->sourcesFor($form->pokemon_id),
                        $this->goFor($form->pokemon_id),
                        $this->fallbackFor($form),
                    ),
                ];
            });
    }

    /** Bewertung einer einzelnen Form – für die Detailseite. */
    public function evaluateForm(PokemonForm $form, ?User $user): PriorityResult
    {
        $this->primeLookups();

        $owned = $user
            ? (bool) UserPokemonForm::where('user_id', $user->id)
                ->where('pokemon_form_id', $form->id)
                ->value('owned')
            : false;

        return $this->engine->evaluate(
            $form,
            $user ? PriorityContext::forUser($user) : PriorityContext::guest(),
            $owned,
            $this->sourcesFor($form->pokemon_id),
            $this->goFor($form->pokemon_id),
            $this->fallbackFor($form),
        );
    }

    /**
     * Wie viele fehlende Pokémon hängen noch an der Bank-Deadline?
     * Speist das Countdown-Widget (spec.md 2.7).
     */
    public function bankUrgentCount(?User $user): int
    {
        return $this->evaluate($user)
            ->filter(fn (object $row) => $row->priority->isUrgent())
            ->count();
    }

    /** Bezugsquellen einer Art, inklusive verdrahteter game-Relation. */
    public function sourcesFor(int $pokemonId): Collection
    {
        // Auch als Einstiegspunkt aufrufbar (Detailseite), deshalb hier absichern.
        $this->primeLookups();

        return $this->sourcesByPokemon[$pokemonId] ?? collect();
    }

    private function goFor(int $pokemonId): ?GoAvailability
    {
        return $this->goByPokemon?->get($pokemonId);
    }

    /**
     * Der Weg über die Vorstufe, sofern es eine gibt.
     *
     * Wird für JEDE Stufe mit Vorstufe geliefert, nicht nur für die, die selbst
     * keine Quelle hat – die Engine vergleicht beide Wege und nimmt den
     * günstigeren (spec.md 2.8). `pokedex:recalculate` hat in
     * `source_pokemon_id` schon die nächste fangbare Stufe aufgelöst, deshalb
     * reicht hier ein Blick auf die direkte Vorstufe.
     */
    private function fallbackFor(PokemonForm $form): ?EvolutionFallback
    {
        $vorstufe = $form->pokemon?->evolvesFrom;

        if ($vorstufe?->source_pokemon_id === null) {
            return null;
        }

        $quelle = $vorstufe->source_pokemon_id === $vorstufe->id
            ? $vorstufe
            : $vorstufe->sourcePokemon;

        if ($quelle === null) {
            return null;
        }

        return new EvolutionFallback(
            $quelle->name_de,
            $this->sourcesFor($quelle->id),
            $form->pokemon->evolution_summary_de,
        );
    }

    /**
     * Drei Queries statt tausender: Spiele, Bezugsquellen und GO-Daten werden
     * einmal geladen und die Relationen manuell gesetzt.
     */
    private function primeLookups(): void
    {
        if ($this->sourcesByPokemon !== null) {
            return;
        }

        $games = Game::query()->get()->keyBy('id');

        $this->sourcesByPokemon = Obtainability::query()
            ->get()
            ->each(function (Obtainability $o) use ($games) {
                $o->setRelation('game', $games->get($o->game_id));
            })
            ->groupBy('pokemon_id')
            ->all();

        $this->goByPokemon = GoAvailability::query()
            ->whereNull('pokemon_form_id')
            ->get()
            ->keyBy('pokemon_id');
    }

    /** @return array<int,UserPokemonForm> */
    private function ownershipMap(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return UserPokemonForm::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('pokemon_form_id')
            ->all();
    }
}
