<?php

namespace App\Console\Commands;

use App\Enums\Difficulty;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Leitet die abgeleiteten Felder am Pokémon aus den Bezugsquellen ab
 * (spec.md 2.8):
 *
 * - `obtainable_directly` … gibt es einen Fundweg, der kein Weiterentwickeln ist?
 *   Grundlage der Mehrfach-Fang-Empfehlung.
 * - `source_pokemon_id` … über welche Stufe der Linie läuft die Beschaffung?
 *   Bisaflor zeigt hier auf Bisasam.
 * - `difficulty` … wie schwer ist die Art grundsätzlich zu bekommen, unabhängig
 *   vom Spielebesitz des Nutzers.
 *
 * Nach jedem Import ausführen. Der Command ist idempotent.
 */
class RecalculateDerivedDataCommand extends Command
{
    protected $signature = 'pokedex:recalculate';

    protected $description = 'Berechnet Schwierigkeitsgrad, Fangbarkeit und Beschaffungsweg aus den Bezugsquellen neu';

    public function handle(): int
    {
        $total = Pokemon::count();

        if ($total === 0) {
            $this->error('Keine Pokémon in der Datenbank – bitte zuerst `php artisan pokedex:import`.');

            return self::FAILURE;
        }

        $this->info('Bezugsquellen werden ausgewertet …');

        /** @var Collection<int,Pokemon> $all */
        $all = Pokemon::query()
            ->with(['obtainabilities' => fn ($q) => $q->with('game')])
            ->orderBy('dex_nr')
            ->get()
            ->keyBy('id');

        // Schritt 1: eigene Fundwege je Art.
        $ownDifficulty = [];
        $hasDirect = [];

        foreach ($all as $pokemon) {
            $sources = $pokemon->obtainabilities->filter(
                fn (Obtainability $o) => $o->isUsableSource()
            );

            $hasDirect[$pokemon->id] = $sources->contains(
                fn (Obtainability $o) => $o->method->isDirect()
            );

            $ownDifficulty[$pokemon->id] = $sources->isEmpty()
                ? null
                : $sources
                    ->map(fn (Obtainability $o) => $o->effectiveDifficulty())
                    ->sortBy(fn (Difficulty $d) => $d->weight())
                    ->first();
        }

        // Schritt 2: Für Arten ohne eigenen Fundweg die nächste fangbare Vorstufe suchen.
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $changed = 0;

        foreach ($all as $pokemon) {
            [$sourceId, $steps] = $this->resolveSource($pokemon, $all, $hasDirect);

            $difficulty = $this->resolveDifficulty($pokemon, $sourceId, $steps, $ownDifficulty);

            $attributes = [
                'obtainable_directly' => $hasDirect[$pokemon->id],
                'source_pokemon_id' => $sourceId,
                'difficulty' => $difficulty,
            ];

            if ($this->differs($pokemon, $attributes)) {
                $pokemon->update($attributes);
                $changed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $viaEvolution = Pokemon::whereColumn('source_pokemon_id', '!=', 'id')->count();
        $unreachable = Pokemon::whereNull('source_pokemon_id')->count();

        $this->info("Fertig: {$changed} von {$total} Arten aktualisiert.");
        $this->line("  · {$viaEvolution} Arten werden über eine Vorstufe beschafft.");
        $this->line("  · {$unreachable} Arten haben aktuell gar keinen hinterlegten Fundweg.");

        if ($unreachable > 0) {
            $this->warn('Arten ohne Fundweg landen auf ⚪ "nur noch per Tausch/Community".');
            $this->warn('Fehlt dort etwas, gehört es in den CuratedObtainabilitySeeder bzw. per pokedex:import-sources ergänzt.');
        }

        return self::SUCCESS;
    }

    /**
     * Nächste Stufe aufwärts (inkl. der Art selbst) mit direktem Fundweg.
     *
     * @param  Collection<int,Pokemon>  $all
     * @param  array<int,bool>  $hasDirect
     * @return array{0:int|null,1:int} [source_pokemon_id, Anzahl Entwicklungsschritte]
     */
    private function resolveSource(Pokemon $pokemon, Collection $all, array $hasDirect): array
    {
        $current = $pokemon;
        $steps = 0;

        while ($current !== null && $steps < 10) {
            if ($hasDirect[$current->id] ?? false) {
                return [$current->id, $steps];
            }

            $current = $current->evolves_from_id ? $all->get($current->evolves_from_id) : null;
            $steps++;
        }

        return [null, 0];
    }

    /** @param  array<int,Difficulty|null>  $ownDifficulty */
    private function resolveDifficulty(
        Pokemon $pokemon,
        ?int $sourceId,
        int $steps,
        array $ownDifficulty,
    ): Difficulty {
        $difficulty = $sourceId === null
            ? Difficulty::SehrSchwer
            : ($ownDifficulty[$sourceId] ?? Difficulty::SehrSchwer);

        // Jede nötige Entwicklung macht die Beschaffung eine Stufe aufwendiger.
        for ($i = 0; $i < $steps; $i++) {
            $difficulty = $this->bump($difficulty);
        }

        return $this->applyRarityBump($pokemon, $difficulty);
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
     * Legendäre und mysteriöse Pokémon sind nie „leicht", auch wenn ihr einziger
     * Fundort formal ein fester Encounter ist.
     */
    private function applyRarityBump(Pokemon $pokemon, Difficulty $difficulty): Difficulty
    {
        if ($pokemon->is_mythical) {
            return Difficulty::SehrSchwer;
        }

        if ($pokemon->is_legendary && $difficulty->weight() < Difficulty::Schwer->weight()) {
            return Difficulty::Schwer;
        }

        return $difficulty;
    }

    /** @param  array<string,mixed>  $attributes */
    private function differs(Pokemon $pokemon, array $attributes): bool
    {
        return $pokemon->obtainable_directly !== $attributes['obtainable_directly']
            || $pokemon->source_pokemon_id !== $attributes['source_pokemon_id']
            || $pokemon->difficulty !== $attributes['difficulty'];
    }
}
