<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Bezugsquellen für Remakes aus ihren Originalen übernehmen (spec.md 2.3, 4).
 *
 * Hintergrund: Die PokéAPI kennt für die Switch-Remakes praktisch keine
 * Fundorte – für Strahlender Diamant und Leuchtende Perle ganze fünf Arten,
 * gegenüber knapp 300 in Diamant und Perl. Ohne Gegenmaßnahme behauptet die
 * App, fast der komplette Sinnoh-Nationaldex sei nur über alte Hardware und
 * damit über Pokémon Bank erreichbar. Das ist schlicht falsch: Die Remakes
 * enthalten denselben Bestand, und sie hängen direkt an HOME.
 *
 * Ein Remake ist per Definition dasselbe Spiel mit derselben Artenliste,
 * deshalb ist die Übernahme keine Schätzung, sondern die Anwendung dieser
 * Eigenschaft auf echte Daten des Originals. Die Fundortangabe stammt aber aus
 * dem Original – im Remake kann derselbe Fang woanders liegen (bei BDSP etwa
 * im Grand Underground). Das steht als Hinweis an jeder Zeile.
 *
 * Alle Einträge tragen `source = remake:<original>` und lassen sich damit
 * jederzeit gezielt wieder entfernen.
 */
class RemakeObtainabilitySeeder extends Seeder
{
    /** original-slug => remake-slug */
    public const REMAKES = [
        'diamond' => 'brilliant-diamond',
        'pearl' => 'shining-pearl',
        'firered' => 'firered-switch',
        'leafgreen' => 'leafgreen-switch',
    ];

    /**
     * Was die Remakes zusätzlich zum Original bieten.
     *
     * pokeapi-slug => [remake-slug, Methode, Detail]
     */
    public const ZUSAETZE = [
        // In Strahlender Diamant / Leuchtender Perle über Speicherstände
        // anderer Switch-Titel erreichbar.
        'mew' => [['brilliant-diamond', 'shining-pearl'], ObtainMethod::Gift,
            'Geschenk bei vorhandenem Let\'s-Go-Speicherstand'],
        'jirachi' => [['brilliant-diamond', 'shining-pearl'], ObtainMethod::Gift,
            'Geschenk bei vorhandenem Schwert-/Schild-Speicherstand'],
    ];

    public function run(): void
    {
        $spiele = Game::query()->pluck('id', 'slug');
        $uebernommen = 0;
        $fehlend = [];

        foreach (self::REMAKES as $original => $remake) {
            $originalId = $spiele[$original] ?? null;
            $remakeId = $spiele[$remake] ?? null;

            if ($originalId === null || $remakeId === null) {
                $fehlend[] = $remakeId === null ? $remake : $original;

                continue;
            }

            $uebernommen += $this->spiegeln($originalId, $remakeId, $original);
        }

        $uebernommen += $this->zusaetze($spiele);

        $this->melden($uebernommen, $fehlend);
    }

    /** Überträgt alle nutzbaren Quellen eines Spiels auf sein Remake. */
    private function spiegeln(int $originalId, int $remakeId, string $originalSlug): int
    {
        $quellen = Obtainability::query()
            ->where('game_id', $originalId)
            ->where('event_expired', false)
            ->get();

        $angelegt = 0;

        foreach ($quellen as $quelle) {
            Obtainability::updateOrCreate(
                [
                    'pokemon_id' => $quelle->pokemon_id,
                    'game_id' => $remakeId,
                    'method' => $quelle->method->value,
                ],
                [
                    'pokemon_form_id' => null,
                    'location_detail' => $quelle->location_detail,
                    'difficulty' => $quelle->difficulty?->value,
                    'event_expired' => false,
                    'note' => 'Aus dem Originalspiel übernommen – das Remake enthält '
                        .'dieselben Arten. Der genaue Fundort kann abweichen.',
                    'source' => 'remake:'.$originalSlug,
                ],
            );

            $angelegt++;
        }

        return $angelegt;
    }

    /** @param  Collection<string,int>  $spiele */
    private function zusaetze($spiele): int
    {
        $angelegt = 0;

        foreach (self::ZUSAETZE as $slug => [$remakes, $methode, $detail]) {
            $pokemon = Pokemon::where('slug', $slug)->first();

            if ($pokemon === null) {
                continue;
            }

            foreach ($remakes as $remake) {
                $gameId = $spiele[$remake] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemon->id,
                        'game_id' => $gameId,
                        'method' => $methode->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => $detail,
                        'difficulty' => Difficulty::Mittel->value,
                        'event_expired' => false,
                        'note' => 'Im Remake ohne Event erreichbar.',
                        'source' => 'remake:zusatz',
                    ],
                );

                $angelegt++;
            }
        }

        return $angelegt;
    }

    /** @param  array<int,string>  $fehlend */
    private function melden(int $uebernommen, array $fehlend): void
    {
        if ($this->command === null) {
            return;
        }

        if ($uebernommen === 0) {
            $this->command->warn(
                'RemakeObtainabilitySeeder: nichts übernommen – '
                .'nach `pokedex:import-encounters` erneut ausführen.'
            );

            return;
        }

        $this->command->getOutput()->writeln(
            "  <fg=gray>{$uebernommen} Bezugsquellen aus den Originalspielen übernommen.</>"
        );

        foreach (array_unique($fehlend) as $slug) {
            $this->command->warn("RemakeObtainabilitySeeder: Spiel '{$slug}' fehlt in der Datenbank.");
        }
    }
}
