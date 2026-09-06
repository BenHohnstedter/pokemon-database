<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\GoMethod;
use App\Enums\PriorityLevel;
use App\Models\GoAvailability;
use App\Models\Obtainability;
use App\Models\PokemonForm;
use App\Support\EvolutionFallback;
use App\Support\PriorityContext;
use App\Support\PriorityResult;
use Illuminate\Support\Collection;

/**
 * Berechnet die Dringlichkeitsstufe für ein fehlendes Pokémon (spec.md 2.7).
 *
 * Die Reihenfolge der Prüfungen ist die Logik selbst und deshalb bewusst linear
 * und kommentiert gehalten — sie ist der am dichtesten getestete Teil der App
 * (spec.md Abschnitt 7).
 */
class PriorityEngine
{
    /**
     * @param  Collection<int,Obtainability>|null  $obtainabilities  Bezugsquellen der Form,
     *                                                               mit geladener game-Relation
     * @param  EvolutionFallback|null  $fallback  Weg der nächsten fangbaren Vorstufe,
     *                                            falls die Form selbst keinen hat
     */
    public function evaluate(
        PokemonForm $form,
        PriorityContext $context,
        bool $owned,
        ?Collection $obtainabilities = null,
        ?GoAvailability $go = null,
        ?EvolutionFallback $fallback = null,
    ): PriorityResult {
        if ($owned) {
            return new PriorityResult(
                level: PriorityLevel::Owned,
                difficulty: Difficulty::Leicht,
                reason: 'Schon in Deiner Sammlung.',
            );
        }

        $sources = $this->usableSources($obtainabilities ?? $this->loadSources($form));

        // Nur per Entwicklung erreichbar: Der Weg der Vorstufe ist der Weg
        // dieser Stufe, nur einen Schritt aufwendiger (spec.md 2.8).
        $viaEvolution = false;

        if ($sources->isEmpty() && $fallback?->isUsable()) {
            $sources = $this->usableSources($fallback->sources);
            $viaEvolution = $sources->isNotEmpty();
        }

        $difficulty = $this->difficultyFrom($sources, $viaEvolution);
        $consoles = $this->consolesFrom($sources);
        $prefix = $viaEvolution ? $fallback->label().' · ' : '';

        // 🟢 Einfach: Es gibt eine Quelle in einem Spiel, das der Nutzer besitzt.
        $ownedGameSources = $sources->filter(
            fn (Obtainability $o) => $context->ownsGame($o->game_id)
        );

        if ($ownedGameSources->isNotEmpty()) {
            return new PriorityResult(
                level: PriorityLevel::Easy,
                difficulty: $this->difficultyFrom($ownedGameSources, $viaEvolution),
                reason: 'Du besitzt bereits ein Spiel, in dem es vorkommt.',
                routes: $this->routeLabels($ownedGameSources, $prefix),
                consoles: $this->consolesFrom($ownedGameSources),
                goRescuable: $this->goRescuable($go),
            );
        }

        // 🟢 Einfach: In GO in der eigenen Region farmbar und nach HOME übertragbar.
        if ($this->goEasy($go, $context)) {
            return new PriorityResult(
                level: PriorityLevel::Easy,
                difficulty: Difficulty::Leicht,
                reason: 'In Pokémon GO in Deiner Region verfügbar – per GO-Transporter nach HOME.',
                routes: [$this->goRouteLabel($go)],
                consoles: ['Smartphone (Pokémon GO)'],
                goRescuable: true,
            );
        }

        // ⚪ Kein regulärer Fangweg mehr (abgelaufene Events, reine Transfer-Einträge).
        if ($sources->isEmpty()) {
            $inGo = $this->goAvailableAnywhere($go);

            return new PriorityResult(
                level: PriorityLevel::TradeOnly,
                difficulty: Difficulty::SehrSchwer,
                reason: $inGo
                    ? 'Kein regulärer Fangweg mehr – in GO nur außerhalb Deiner Region oder über Events.'
                    : 'Event ist vorbei, kein regulärer Fangweg mehr – nur über Tauschbörsen/Community.',
                routes: $inGo ? [$this->goRouteLabel($go)] : [],
                goRescuable: $this->goRescuable($go),
                obtainableAtAll: $inGo,
            );
        }

        // 🟡 Kaufbar: Ein passendes Spiel ist noch regulär im Handel.
        $purchasable = $sources->filter(fn (Obtainability $o) => (bool) $o->game->still_purchasable);

        if ($purchasable->isNotEmpty()) {
            return new PriorityResult(
                level: PriorityLevel::Purchasable,
                difficulty: $this->difficultyFrom($purchasable, $viaEvolution),
                reason: 'Spiel kaufen reicht – es ist noch regulär erhältlich.',
                routes: $this->routeLabels($purchasable, $prefix),
                consoles: $this->consolesFrom($purchasable),
                goRescuable: $this->goRescuable($go),
            );
        }

        // Ab hier: keine Quelle in einem besessenen oder noch käuflichen Spiel.
        $bankOnly = $sources->filter(fn (Obtainability $o) => (bool) $o->game->bank_only);
        $directToHome = $sources->filter(
            fn (Obtainability $o) => $o->game->home_compatible && ! $o->game->bank_only
        );
        $needsLegacy = $sources->contains(fn (Obtainability $o) => $o->game->isLegacyHardware());

        // 🔴 Dringend: Der einzige Weg nach HOME führt über Pokémon Bank.
        //    Ein GO-Weg entschärft das (spec.md 2.4) – dann bleibt es orange.
        if ($bankOnly->isNotEmpty() && $directToHome->isEmpty()) {
            if ($this->goRescuable($go)) {
                return new PriorityResult(
                    level: PriorityLevel::OldHardware,
                    difficulty: $difficulty,
                    reason: 'Nur auf alter Hardware – aber über Pokémon GO ohne Bank-Deadline erreichbar.',
                    routes: array_merge($this->routeLabels($sources, $prefix), [$this->goRouteLabel($go)]),
                    consoles: array_values(array_unique(array_merge($consoles, ['Smartphone (Pokémon GO)']))),
                    goRescuable: true,
                );
            }

            return new PriorityResult(
                level: PriorityLevel::BankUrgent,
                difficulty: $difficulty,
                reason: 'Der einzige Weg nach HOME führt über Pokémon Bank – vor der Abschaltung erledigen!',
                routes: $this->routeLabels($sources, $prefix),
                consoles: $consoles,
            );
        }

        // 🟠 Alte Hardware nötig, aber ohne harte Deadline.
        if ($needsLegacy) {
            return new PriorityResult(
                level: PriorityLevel::OldHardware,
                difficulty: $difficulty,
                reason: 'Braucht ein älteres Spiel bzw. eine alte Konsole – aber es gibt einen Weg ohne Pokémon Bank.',
                routes: $this->routeLabels($sources, $prefix),
                consoles: $consoles,
                goRescuable: $this->goRescuable($go),
            );
        }

        // ⚪ Nur moderne Spiele, die weder besessen noch im Handel sind: Gebrauchtmarkt/Tausch.
        return new PriorityResult(
            level: PriorityLevel::TradeOnly,
            difficulty: $difficulty,
            reason: 'Das passende Spiel ist nicht mehr regulär erhältlich – gebraucht kaufen oder tauschen.',
            routes: $this->routeLabels($sources, $prefix),
            consoles: $consoles,
            goRescuable: $this->goRescuable($go),
        );
    }

    /**
     * Quellen, die tatsächlich noch ein neues Exemplar liefern.
     *
     * @param  Collection<int,Obtainability>  $obtainabilities
     * @return Collection<int,Obtainability>
     */
    private function usableSources(Collection $obtainabilities): Collection
    {
        return $obtainabilities
            ->filter(fn (Obtainability $o) => $o->isUsableSource() && $o->game !== null)
            ->values();
    }

    /** @return Collection<int,Obtainability> */
    private function loadSources(PokemonForm $form): Collection
    {
        return Obtainability::query()
            ->with('game')
            ->where('pokemon_id', $form->pokemon_id)
            ->where(function ($q) use ($form) {
                $q->whereNull('pokemon_form_id')->orWhere('pokemon_form_id', $form->id);
            })
            ->get();
    }

    /**
     * Der einfachste verfügbare Weg bestimmt die Schwierigkeit (spec.md 2.8).
     *
     * @param  Collection<int,Obtainability>  $sources
     */
    private function difficultyFrom(Collection $sources, bool $viaEvolution = false): Difficulty
    {
        if ($sources->isEmpty()) {
            return Difficulty::SehrSchwer;
        }

        $easiest = $sources
            ->map(fn (Obtainability $o) => $o->effectiveDifficulty())
            ->sortBy(fn (Difficulty $d) => $d->weight())
            ->first();

        // Erst fangen, dann entwickeln ist nie "leicht" – aber auch nicht beliebig
        // viel schwerer, deshalb genau eine Stufe hoch.
        return $viaEvolution ? $this->bump($easiest) : $easiest;
    }

    private function bump(Difficulty $difficulty): Difficulty
    {
        return match ($difficulty) {
            Difficulty::Leicht => Difficulty::Mittel,
            Difficulty::Mittel => Difficulty::Schwer,
            default => Difficulty::SehrSchwer,
        };
    }

    /**
     * @param  Collection<int,Obtainability>  $sources
     * @return array<int,string>
     */
    private function routeLabels(Collection $sources, string $prefix = ''): array
    {
        return $sources
            ->sortBy(fn (Obtainability $o) => $o->effectiveDifficulty()->weight())
            ->map(function (Obtainability $o) use ($prefix) {
                $label = $o->game->name_de.' · '.$o->method->label();
                $label = $o->location_detail ? $label.' · '.$o->location_detail : $label;

                return $prefix.$label;
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,Obtainability>  $sources
     * @return array<int,string>
     */
    private function consolesFrom(Collection $sources): array
    {
        return $sources
            ->map(fn (Obtainability $o) => $o->game->platform->label())
            ->unique()
            ->values()
            ->all();
    }

    private function goEasy(?GoAvailability $go, PriorityContext $context): bool
    {
        return $go !== null
            && $go->transferable_to_home
            && $go->method->isReliablyFarmable()
            && $go->availableInRegion($context->goRegion);
    }

    /**
     * Entschärft GO die Bank-Deadline? Auch eine regional exklusive Art lässt sich
     * in GO tauschen, deshalb reicht hier "irgendwo farmbar" (spec.md 2.4).
     */
    private function goRescuable(?GoAvailability $go): bool
    {
        return $go !== null
            && $go->transferable_to_home
            && $go->method->isReliablyFarmable();
    }

    private function goAvailableAnywhere(?GoAvailability $go): bool
    {
        return $go !== null && $go->method !== GoMethod::NotAvailable;
    }

    private function goRouteLabel(?GoAvailability $go): string
    {
        if ($go === null) {
            return 'Pokémon GO';
        }

        $label = 'Pokémon GO · '.$go->method->label();

        return $go->isRegionExclusive()
            ? $label.' · nur in: '.$go->regionLabels()
            : $label;
    }
}
