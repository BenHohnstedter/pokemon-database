<?php

namespace App\Console\Commands;

use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Services\PokeApiClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fundorte je Spiel aus `location-area-encounters` (spec.md 2.3, 4).
 *
 * Getrennt vom Stammdaten-Import, weil dieser Schritt noch einmal so viele
 * Requests macht und man ihn deshalb gezielt (z.B. nur eine Generation)
 * wiederholen möchte.
 */
class ImportEncountersCommand extends Command
{
    protected $signature = 'pokedex:import-encounters
        {--from=1 : Erste National-Dex-Nummer}
        {--to= : Letzte National-Dex-Nummer}
        {--translate-locations : Deutsche Ortsnamen mitladen (deutlich mehr Requests)}';

    protected $description = 'Importiert Wildfang-Fundorte je Spiel aus der PokéAPI';

    /**
     * PokéAPI-Versionsnamen, die auf keinen eigenen Datensatz zeigen.
     * Die DLC-Gebiete gehören zum jeweiligen Hauptspiel.
     */
    private const VERSION_ALIASES = [
        'the-isle-of-armor' => 'sword',
        'the-crown-tundra' => 'shield',
    ];

    /** Nebenreihen ohne HOME-Bezug – für dieses Projekt irrelevant. */
    private const IGNORED_VERSIONS = ['colosseum', 'xd'];

    /** @var array<string,string> */
    private array $locationNames = [];

    public function handle(PokeApiClient $api): int
    {
        $games = Game::query()->pluck('id', 'slug');

        if ($games->isEmpty()) {
            $this->error('Keine Spiele in der Datenbank – bitte zuerst `php artisan db:seed` ausführen.');

            return self::FAILURE;
        }

        $query = Pokemon::query()
            ->where('dex_nr', '>=', (int) $this->option('from'))
            ->when($this->option('to'), fn ($q, $to) => $q->where('dex_nr', '<=', (int) $to))
            ->orderBy('dex_nr');

        $total = $query->count();

        if ($total === 0) {
            $this->error('Keine Pokémon im gewählten Bereich – bitte zuerst `php artisan pokedex:import`.');

            return self::FAILURE;
        }

        $this->info("Fundorte für {$total} Arten werden geladen …");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $created = 0;
        $skipped = 0;

        foreach ($query->cursor() as $pokemon) {
            try {
                $created += $this->importFor($api, $pokemon, $games);
            } catch (Throwable $e) {
                $skipped++;
                $this->newLine();
                $this->warn("#{$pokemon->dex_nr} {$pokemon->name_de}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Fertig: {$created} Fundorte gespeichert".($skipped ? ", {$skipped} Arten übersprungen." : '.'));
        $this->line('Tipp: `php artisan pokedex:recalculate` aktualisiert danach Schwierigkeit und Fangbarkeit.');

        return self::SUCCESS;
    }

    private function importFor(PokeApiClient $api, Pokemon $pokemon, $games): int
    {
        $encounters = $api->get("pokemon/{$pokemon->slug}/encounters");
        $created = 0;

        // Pro Spiel nur den prägnantesten Ort behalten – eine Liste mit 40
        // Routen hilft niemandem. Sortiert wird nach Begegnungschance.
        $bestPerGame = [];

        foreach ($encounters as $encounter) {
            $areaSlug = $encounter['location_area']['name'] ?? null;

            if ($areaSlug === null) {
                continue;
            }

            foreach ($encounter['version_details'] ?? [] as $versionDetail) {
                $version = $versionDetail['version']['name'] ?? null;
                $version = self::VERSION_ALIASES[$version] ?? $version;

                if ($version === null || in_array($version, self::IGNORED_VERSIONS, true)) {
                    continue;
                }

                $gameId = $games[$version] ?? null;

                if ($gameId === null) {
                    continue;
                }

                $chance = (int) ($versionDetail['max_chance'] ?? 0);

                if (! isset($bestPerGame[$gameId]) || $chance > $bestPerGame[$gameId]['chance']) {
                    $bestPerGame[$gameId] = ['chance' => $chance, 'areas' => []];
                }

                $bestPerGame[$gameId]['areas'][$areaSlug] = true;
            }
        }

        foreach ($bestPerGame as $gameId => $data) {
            $areas = array_slice(array_keys($data['areas']), 0, 4);
            $labels = array_map(fn (string $slug) => $this->locationName($api, $slug), $areas);
            $detail = implode(', ', $labels);

            if (count($data['areas']) > count($areas)) {
                $detail .= ' u.a.';
            }

            Obtainability::updateOrCreate(
                [
                    'pokemon_id' => $pokemon->id,
                    'game_id' => $gameId,
                    'method' => ObtainMethod::Wild->value,
                ],
                [
                    // Wildfänge der PokéAPI beziehen sich immer auf die Standardform;
                    // Regionalformen hängen an eigenen kuratierten Einträgen.
                    'pokemon_form_id' => null,
                    'location_detail' => $detail,
                    'event_expired' => false,
                    'source' => 'pokeapi',
                ],
            );

            $created++;
        }

        return $created;
    }

    private function locationName(PokeApiClient $api, string $slug): string
    {
        if (isset($this->locationNames[$slug])) {
            return $this->locationNames[$slug];
        }

        $label = $this->humanize($slug);

        if ($this->option('translate-locations')) {
            try {
                $area = $api->get("location-area/{$slug}");
                $german = PokeApiClient::localizedName($area['names'] ?? [], 'de');

                if ($german === '' && ($locationUrl = $area['location']['url'] ?? null)) {
                    $location = $api->get($locationUrl);
                    $german = PokeApiClient::localizedName($location['names'] ?? [], 'de');
                }

                if ($german !== '') {
                    $label = $german;
                }
            } catch (Throwable) {
                // Ortsname ist Beiwerk – der Fundort bleibt auch englisch nützlich.
            }
        }

        return $this->locationNames[$slug] = $label;
    }

    /** "kanto-route-2-south-towards-viridian-city" → "Kanto Route 2 South Towards Viridian City" */
    private function humanize(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }
}
