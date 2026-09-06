<?php

namespace App\Services;

use App\Models\Pokemon;
use App\Support\MultiCatchPlan;
use App\Support\MultiCatchStep;
use Illuminate\Support\Collection;

/**
 * Mehrfach-Fang-Empfehlung für Entwicklungsreihen (spec.md 2.8).
 *
 * Grundgedanke: In HOME zählt jede Entwicklungsstufe als eigener Dex-Eintrag,
 * und ein einzelnes Exemplar endet immer bei genau einer Stufe. Für jede noch
 * fehlende Stufe braucht es also ein eigenes Exemplar, gefangen bei der
 * nächstgelegenen Vorstufe, die überhaupt einen direkten Fundweg hat
 * (`obtainable_directly`).
 */
class MultiCatchCalculator
{
    /**
     * @param  Collection<int,Pokemon>  $line     alle Stufen der Entwicklungslinie
     * @param  array<int,int>  $ownedDexNumbers  bereits besessene Dex-Nummern
     */
    public function plan(Collection $line, array $ownedDexNumbers = []): MultiCatchPlan
    {
        $byId = $line->keyBy('id');
        $owned = array_flip($ownedDexNumbers);

        /** @var array<int,array<int,Pokemon>> $grouped  origin-id => fehlende Stufen */
        $grouped = [];
        $unreachable = [];

        foreach ($line as $pokemon) {
            if (isset($owned[$pokemon->dex_nr])) {
                continue;
            }

            $origin = $this->nearestObtainableAncestor($pokemon, $byId);

            if ($origin === null) {
                $unreachable[] = $pokemon;

                continue;
            }

            $grouped[$origin->id][] = $pokemon;
        }

        $steps = [];

        foreach ($grouped as $originId => $targets) {
            usort(
                $targets,
                fn (Pokemon $a, Pokemon $b) => $this->depth($a, $byId) <=> $this->depth($b, $byId)
            );

            $steps[] = new MultiCatchStep($byId->get($originId), $targets);
        }

        usort(
            $steps,
            fn (MultiCatchStep $a, MultiCatchStep $b) => $a->origin->dex_nr <=> $b->origin->dex_nr
        );

        return new MultiCatchPlan($steps, $unreachable);
    }

    /**
     * Die nächste Stufe aufwärts (inklusive der Stufe selbst), die wild fangbar,
     * ein Geschenk o.ä. ist. Gibt es keine, ist die Stufe über diese Linie nicht
     * erreichbar – dann greift die Prioritäts-Engine mit Tausch/Event.
     *
     * @param  Collection<int,Pokemon>  $byId
     */
    private function nearestObtainableAncestor(Pokemon $pokemon, Collection $byId): ?Pokemon
    {
        $current = $pokemon;
        $guard = 0;

        while ($current !== null && $guard++ < 10) {
            if ($current->obtainable_directly) {
                return $current;
            }

            $current = $current->evolves_from_id ? $byId->get($current->evolves_from_id) : null;
        }

        return null;
    }

    /** Wie viele Entwicklungsschritte liegt die Stufe hinter dem Ursprung? */
    private function depth(Pokemon $pokemon, Collection $byId): int
    {
        $depth = 0;
        $current = $pokemon;

        while ($current?->evolves_from_id && $depth < 10) {
            $current = $byId->get($current->evolves_from_id);
            $depth++;
        }

        return $depth;
    }
}
