<?php

namespace App\Console\Commands;

use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Console\Command;

/**
 * Schließt die Fundort-Lücken der PokéAPI (spec.md 4).
 *
 * `location-area-encounters` ist für die neueren Titel praktisch leer: für
 * Karmesin/Purpur liefert die API sechs Einträge für über hundert Arten. Ohne
 * Gegenmaßnahme landet damit fast die komplette neunte Generation auf
 * ⚪ „nur noch per Tausch/Community" – also auf der schlechtesten Stufe,
 * obwohl die Pokémon im aktuell erhältlichen Spiel schlicht fangbar sind.
 *
 * Dieser Befehl trägt für jede Art ohne Bezugsquelle einen Wildfang im
 * Hauptspiel ihrer eigenen Generation nach, klar als Datenlücke gekennzeichnet.
 * Das ist eine begründete Annahme, keine belegte Fundortangabe – deshalb steht
 * sie in einer eigenen `source` und lässt sich jederzeit wieder entfernen,
 * sobald echte Daten per `pokedex:import-sources` nachgeliefert werden.
 */
class FillObtainabilityGapsCommand extends Command
{
    protected $signature = 'pokedex:fill-gaps
        {--dry-run : Nur anzeigen, was passieren würde}
        {--with-mythical : Auch mysteriöse Pokémon behandeln (Standard: nein)}
        {--remove : Die bisherigen Lückenfüller wieder entfernen}';

    protected $description = 'Trägt für Arten ohne Fundort einen Wildfang im Hauptspiel ihrer Generation nach';

    public const SOURCE = 'generation-fallback';

    /** Hauptspiele je Generation – die Paare, in denen die Arten der Gen vorkommen. */
    private const MAIN_GAMES = [
        1 => ['red', 'blue'],
        2 => ['gold', 'silver'],
        3 => ['ruby', 'sapphire'],
        4 => ['diamond', 'pearl'],
        5 => ['black', 'white'],
        6 => ['x', 'y'],
        7 => ['sun', 'moon'],
        8 => ['sword', 'shield'],
        9 => ['scarlet', 'violet'],
    ];

    public function handle(): int
    {
        if ($this->option('remove')) {
            $entfernt = Obtainability::where('source', self::SOURCE)->delete();
            $this->info("{$entfernt} Lückenfüller entfernt.");
            $this->line('Nicht vergessen: `php artisan pokedex:recalculate`.');

            return self::SUCCESS;
        }

        $spiele = Game::query()->pluck('id', 'slug');

        /*
        | Nur Arten, die über KEINEN Weg erreichbar sind – auch nicht über eine
        | Vorstufe. Eine Endstufe ohne eigenen Wildfang braucht keinen
        | Lückenfüller: sie entsteht durch Entwicklung, und `pokedex:recalculate`
        | hat das in source_pokemon_id bereits aufgelöst. Ihr trotzdem einen
        | Wildfang anzudichten wäre schlicht falsch.
        */
        $luecken = Pokemon::query()
            ->whereNull('source_pokemon_id')
            ->whereDoesntHave('obtainabilities')
            ->when(! $this->option('with-mythical'), fn ($q) => $q->where('is_mythical', false))
            ->orderBy('dex_nr')
            ->get();

        if ($luecken->isEmpty()) {
            $this->info('Keine Lücken – jede Art ist über einen Weg erreichbar.');

            return self::SUCCESS;
        }

        $this->line('Grundlage ist source_pokemon_id – `pokedex:recalculate` sollte vorher gelaufen sein.');
        $this->newLine();
        $this->info("{$luecken->count()} Arten ohne jeden Beschaffungsweg gefunden:");

        foreach ($luecken->groupBy('generation')->sortKeys() as $generation => $arten) {
            $ziel = self::MAIN_GAMES[$generation] ?? null;
            $label = $ziel === null ? 'kein Hauptspiel hinterlegt' : implode(' / ', $ziel);
            $this->line("  Gen {$generation}: {$arten->count()} Arten → {$label}");
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Trockenlauf – es wurde nichts geschrieben.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($luecken->count());
        $bar->start();

        $angelegt = 0;
        $ohneSpiel = 0;

        foreach ($luecken as $pokemon) {
            $slugs = self::MAIN_GAMES[$pokemon->generation] ?? [];

            if ($slugs === []) {
                $ohneSpiel++;
                $bar->advance();

                continue;
            }

            foreach ($slugs as $slug) {
                $gameId = $spiele[$slug] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemon->id,
                        'game_id' => $gameId,
                        'method' => ObtainMethod::Wild->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => 'Fundort noch nicht hinterlegt',
                        'event_expired' => false,
                        'note' => 'Angenommen, weil die Art zur Generation dieses Spiels gehört – '
                            .'die PokéAPI liefert für diesen Titel keine Fundortdaten. '
                            .'Per pokedex:import-sources durch echte Angaben ersetzbar.',
                        'source' => self::SOURCE,
                    ],
                );

                $angelegt++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$angelegt} Einträge angelegt.");

        if ($ohneSpiel > 0) {
            $this->warn("{$ohneSpiel} Arten übersprungen – für ihre Generation ist kein Hauptspiel hinterlegt.");
        }

        $this->line('Nicht vergessen: `php artisan pokedex:recalculate`.');

        return self::SUCCESS;
    }
}
