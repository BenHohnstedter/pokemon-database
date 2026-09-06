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
     * @param  array<int,EvolutionFallback>  $fallbacks  Wege über die Vorstufen der Linie
     */
    public function evaluate(
        PokemonForm $form,
        PriorityContext $context,
        bool $owned,
        ?Collection $obtainabilities = null,
        ?GoAvailability $go = null,
        array $fallbacks = [],
    ): PriorityResult {
        if ($owned) {
            return new PriorityResult(
                level: PriorityLevel::Owned,
                difficulty: Difficulty::Leicht,
                reason: 'Schon in Deiner Sammlung.',
            );
        }

        $alle = $this->usableSources($obtainabilities ?? $this->loadSources($form));
        $sources = $this->erreichbareSources($alle, $context);
        $ergebnis = $this->evaluateSources(
            $sources, $context, $go,
            transporterFehlt: $sources->count() < $alle->count(),
        );

        /*
        | Die Umwege über die Vorstufen werden IMMER mitgerechnet, nicht nur wenn
        | die Stufe selbst gar keine Quelle hat: Bisaknosp ist wild nur in X zu
        | finden (3DS, also Bank-Weg), lässt sich aber aus einem Bisasam des noch
        | käuflichen Let's Go entwickeln. Und zwar über die ganze Linie hinweg –
        | für Bisaflor zählt auch Bisasam, nicht nur die direkte Vorstufe
        | (spec.md 2.8).
        */
        foreach ($fallbacks as $fallback) {
            // 🟢 ist die beste erreichbare Stufe für ein fehlendes Pokémon –
            // besser wäre nur "besessen", und das ist oben schon abgehandelt.
            // Weitersuchen kann das Ergebnis also nicht mehr verbessern, spart
            // über den ganzen Dex aber ein paar tausend Auswertungen.
            if ($ergebnis->level === PriorityLevel::Easy) {
                break;
            }

            if (! $fallback->isUsable()) {
                continue;
            }

            $alleDerVorstufe = $this->usableSources($fallback->sources);
            $vorstufe = $this->erreichbareSources($alleDerVorstufe, $context);

            $ueberVorstufe = $this->evaluateSources(
                $vorstufe,
                $context,
                $go,
                evolutionSteps: $fallback->steps,
                prefix: $fallback->label().' · ',
                transporterFehlt: $vorstufe->count() < $alleDerVorstufe->count(),
            );

            if ($ueberVorstufe->level->hardship() < $ergebnis->level->hardship()) {
                $ergebnis = $ueberVorstufe;
            }
        }

        return $this->applyDifficultyFloor($ergebnis, $form);
    }

    /**
     * Die eigentliche Stufenlogik für einen Satz Bezugsquellen.
     *
     * @param  Collection<int,Obtainability>  $sources
     */
    private function evaluateSources(
        Collection $sources,
        PriorityContext $context,
        ?GoAvailability $go,
        int $evolutionSteps = 0,
        string $prefix = '',
        bool $transporterFehlt = false,
    ): PriorityResult {
        /*
        | Schwierigkeit und Konsolenliste werden bewusst erst in dem Zweig
        | berechnet, der sie zurückgibt: Die beiden häufigsten Fälle (🟢 und 🟡)
        | rechnen ohnehin mit einer engeren Quellenmenge, und diese Methode läuft
        | pro Form bis zu viermal – einmal für den eigenen Weg und einmal je
        | Vorstufe. Vorab berechnet kostete das über den ganzen Dex spürbar Zeit.
        */

        // 🟢 Einfach: Es gibt eine Quelle in einem Spiel, das der Nutzer besitzt.
        $ownedGameSources = $sources->filter(
            fn (Obtainability $o) => $context->ownsGame($o->game_id)
        );

        if ($ownedGameSources->isNotEmpty()) {
            /*
            | Wichtig: "einfach" heißt nur, dass Du drankommst – nicht, dass es
            | entspannt ist. Wer Pokémon Schwarz besitzt, fängt das Pokémon
            | jederzeit, muss es aber trotzdem vor dem Stichtag über Pokémon Bank
            | nach HOME schieben. Diese Fälle verschwanden vorher komplett aus dem
            | Countdown, obwohl sie genauso an der Frist hängen.
            */
            $deadline = $this->requiresBank($ownedGameSources, $go);

            return new PriorityResult(
                level: PriorityLevel::Easy,
                difficulty: $this->difficultyFrom($ownedGameSources, $evolutionSteps),
                reason: $deadline
                    ? 'Du besitzt ein passendes Spiel – der Weg nach HOME führt aber über '
                        .'Pokémon Bank. Vor der Abschaltung übertragen!'
                    : 'Du besitzt bereits ein Spiel, in dem es vorkommt.',
                routes: $this->routeLabels($ownedGameSources, $prefix),
                consoles: $this->consolesFrom($ownedGameSources),
                goRescuable: $this->goRescuable($go),
                bankDeadline: $deadline,
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

            /*
            | Sonderfall mit eigener Begründung: Fangen ginge, nur käme das
            | Gefangene nie bei HOME an, weil ohne Poké Transporter der Weg zu
            | Pokémon Bank fehlt. Für dieses Projekt zählt aber nur, was in HOME
            | landet – deshalb dieselbe Stufe, aber ein ehrlicher Grund statt
            | "Event vorbei".
            */
            $grund = match (true) {
                $transporterFehlt && $inGo => 'Erreichbar nur in Spielen, die ohne Poké Transporter '
                    .'nicht nach HOME kommen – in GO außerdem nur außerhalb Deiner Region.',
                $transporterFehlt => 'Es gäbe Fangwege, aber alle führen über Pokémon Bank und damit '
                    .'über Poké Transporter – die App hast Du laut Einstellungen nicht.',
                $inGo => 'Kein regulärer Fangweg mehr – in GO nur außerhalb Deiner Region oder über Events.',
                default => 'Event ist vorbei, kein regulärer Fangweg mehr – nur über Tauschbörsen/Community.',
            };

            return new PriorityResult(
                level: PriorityLevel::TradeOnly,
                difficulty: Difficulty::SehrSchwer,
                reason: $grund,
                routes: $inGo ? [$this->goRouteLabel($go)] : [],
                goRescuable: $this->goRescuable($go),
                obtainableAtAll: $inGo,
                transporterMissing: $transporterFehlt,
            );
        }

        // 🟡 Kaufbar: Ein passendes Spiel ist noch regulär im Handel.
        $purchasable = $sources->filter(fn (Obtainability $o) => (bool) $o->game->still_purchasable);

        if ($purchasable->isNotEmpty()) {
            return new PriorityResult(
                level: PriorityLevel::Purchasable,
                difficulty: $this->difficultyFrom($purchasable, $evolutionSteps),
                reason: 'Spiel kaufen reicht – es ist noch regulär erhältlich.',
                routes: $this->routeLabels($purchasable, $prefix),
                consoles: $this->consolesFrom($purchasable),
                goRescuable: $this->goRescuable($go),
                bankDeadline: $this->requiresBank($purchasable, $go),
            );
        }

        // Ab hier: keine Quelle in einem besessenen oder noch käuflichen Spiel.
        // Dieser Teil trifft nur eine Minderheit der Arten, deshalb erst hier
        // die etwas teureren Auswertungen.
        $difficulty = $this->difficultyFrom($sources, $evolutionSteps);
        $consoles = $this->consolesFrom($sources);

        $hatBankWeg = $sources->contains(fn (Obtainability $o) => (bool) $o->game->bank_only);
        $hatDirektenWeg = $sources->contains(
            fn (Obtainability $o) => $o->game->home_compatible && ! $o->game->bank_only
        );
        $needsLegacy = $sources->contains(fn (Obtainability $o) => $o->game->isLegacyHardware());

        // 🔴 Dringend: Der einzige Weg nach HOME führt über Pokémon Bank.
        //    Ein GO-Weg entschärft das (spec.md 2.4) – dann bleibt es orange.
        if ($hatBankWeg && ! $hatDirektenWeg) {
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
                bankDeadline: true,
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
                bankDeadline: $this->requiresBank($sources, $go),
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
            bankDeadline: $this->requiresBank($sources, $go),
        );
    }

    /**
     * Führt dieser Satz Bezugsquellen zwangsläufig über Pokémon Bank?
     *
     * Gefragt wird immer nach dem Weg, den der Nutzer tatsächlich gehen würde:
     * Für jemanden, der nur Pokémon Schwarz besitzt, ist die Frist real, auch
     * wenn es das Pokémon theoretisch noch in einem Switch-Titel gäbe, den er
     * nicht hat. Ein GO-Weg hebt die Frist immer auf (spec.md 2.4).
     *
     * @param  Collection<int,Obtainability>  $sources
     */
    private function requiresBank(Collection $sources, ?GoAvailability $go): bool
    {
        if ($sources->isEmpty() || $this->goRescuable($go)) {
            return false;
        }

        $ohneBank = $sources->contains(
            fn (Obtainability $o) => $o->game->home_compatible && ! $o->game->bank_only
        );

        return ! $ohneBank
            && $sources->contains(fn (Obtainability $o) => (bool) $o->game->bank_only);
    }

    /**
     * Die Schwierigkeit darf nie unter dem liegen, was am Pokémon selbst steht.
     *
     * `pokedex:recalculate` schreibt dort bereits die Zuschläge für legendäre und
     * mysteriöse Arten hinein. Ohne diese Untergrenze käme Mew als „leicht" durch,
     * nur weil die PokéAPI für Smaragd einen Wildfang auf Eiland 9 kennt – der
     * war in Wahrheit an ein längst abgelaufenes Ticket-Event gebunden.
     */
    private function applyDifficultyFloor(PriorityResult $ergebnis, PokemonForm $form): PriorityResult
    {
        $floor = $form->pokemon?->difficulty;

        if ($floor === null || $floor->weight() <= $ergebnis->difficulty->weight()) {
            return $ergebnis;
        }

        return new PriorityResult(
            level: $ergebnis->level,
            difficulty: $floor,
            reason: $ergebnis->reason,
            routes: $ergebnis->routes,
            consoles: $ergebnis->consoles,
            goRescuable: $ergebnis->goRescuable,
            obtainableAtAll: $ergebnis->obtainableAtAll,
            bankDeadline: $ergebnis->bankDeadline,
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

    /**
     * Quellen, aus denen das Pokémon auch tatsächlich nach HOME kommt.
     *
     * Ohne die 3DS-App „Poké Transporter" endet der Weg aus Gen 1 bis 5 vor
     * Pokémon Bank. Fangen ginge weiterhin – für dieses Projekt zählt aber nur,
     * was in HOME ankommt, und deshalb fallen solche Quellen komplett heraus,
     * statt als dringender Bank-Fall zu erscheinen. Das ist genau anders herum
     * als beim Countdown: Wo der Weg ohnehin verschlossen ist, hilft auch keine
     * Frist mehr.
     *
     * @param  Collection<int,Obtainability>  $sources
     * @return Collection<int,Obtainability>
     */
    private function erreichbareSources(Collection $sources, PriorityContext $context): Collection
    {
        if ($context->hasTransporter) {
            return $sources;
        }

        return $sources
            ->reject(fn (Obtainability $o) => $o->game->needs_transporter)
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
    private function difficultyFrom(Collection $sources, int $evolutionSteps = 0): Difficulty
    {
        if ($sources->isEmpty()) {
            return Difficulty::SehrSchwer;
        }

        $easiest = $sources
            ->map(fn (Obtainability $o) => $o->effectiveDifficulty())
            ->sortBy(fn (Difficulty $d) => $d->weight())
            ->first();

        // Erst fangen, dann entwickeln ist nie "leicht": jede nötige Entwicklung
        // macht die Beschaffung genau eine Stufe aufwendiger.
        for ($i = 0; $i < $evolutionSteps; $i++) {
            $easiest = $this->bump($easiest);
        }

        return $easiest;
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
