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
    /** Wie weit die Entwicklungskette nach oben verfolgt wird. */
    private const MAX_EVOLUTION_DEPTH = 4;

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
                // Zwei Ebenen decken jede reguläre Entwicklungslinie ab
                // (Basis → Mitte → Endstufe).
                'pokemon.evolvesFrom:id,name_de,evolves_from_id',
                'pokemon.evolvesFrom.evolvesFrom:id,name_de,evolves_from_id',
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
                        $this->fallbacksFor($form),
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
            $this->fallbacksFor($form),
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
     * Die Wege über sämtliche Vorstufen der Linie (spec.md 2.8).
     *
     * Bewusst die ganze Kette und nicht nur die direkte Vorstufe: Bisaflor ist
     * über Bisaknosp *und* über Bisasam erreichbar, und wenn Bisaknosp wild nur
     * in einem Bank-Spiel vorkommt, ist der längere Weg über Bisasam der
     * bessere. Die Engine vergleicht alle Kandidaten.
     *
     * @return array<int,EvolutionFallback>
     */
    private function fallbacksFor(PokemonForm $form): array
    {
        $fallbacks = [];
        $vorstufe = $form->pokemon?->evolvesFrom;
        $schritte = 1;

        while ($vorstufe !== null && $schritte <= self::MAX_EVOLUTION_DEPTH) {
            $quellen = $this->sourcesFor($vorstufe->id);

            if ($quellen->isNotEmpty()) {
                $fallbacks[] = new EvolutionFallback(
                    ancestorName: $vorstufe->name_de,
                    sources: $quellen,
                    evolutionSummary: $schritte === 1 ? $form->pokemon->evolution_summary_de : null,
                    steps: $schritte,
                );
            }

            $vorstufe = $vorstufe->evolvesFrom;
            $schritte++;
        }

        return $fallbacks;
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
