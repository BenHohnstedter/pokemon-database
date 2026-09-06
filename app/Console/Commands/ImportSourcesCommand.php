<?php

namespace App\Console\Commands;

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use Illuminate\Console\Command;

/**
 * Bezugsquellen aus einer CSV nachladen (spec.md 2.3, 4).
 *
 * Die PokéAPI kennt nur Wildfänge. Alles andere – Geschenke, Events, Raids,
 * Fundorte für Regionalformen, neu erschienene Titel – kommt entweder aus dem
 * CuratedObtainabilitySeeder oder über diesen Befehl aus einer gepflegten CSV.
 * So lässt sich die Datenbasis erweitern, ohne PHP anzufassen.
 *
 * Spaltenformat (Kopfzeile erforderlich):
 *   dex_nr;form_slug;game_slug;method;location_detail;difficulty;event_expired;note
 *
 * form_slug, difficulty, event_expired und note dürfen leer bleiben.
 */
class ImportSourcesCommand extends Command
{
    protected $signature = 'pokedex:import-sources
        {datei : Pfad zur CSV-Datei}
        {--trennzeichen=; : Spaltentrenner}
        {--ersetzen : Vorhandene Einträge aus derselben Quelle vorher löschen}';

    protected $description = 'Importiert kuratierte Bezugsquellen aus einer CSV-Datei';

    public function handle(): int
    {
        $datei = $this->argument('datei');

        if (! is_file($datei)) {
            $this->error("Datei nicht gefunden: {$datei}");

            return self::FAILURE;
        }

        if ($this->option('ersetzen')) {
            $geloescht = Obtainability::where('source', 'csv')->delete();
            $this->line("{$geloescht} frühere CSV-Einträge entfernt.");
        }

        $spiele = Game::pluck('id', 'slug');
        $pokemon = Pokemon::pluck('id', 'dex_nr');
        $formen = PokemonForm::pluck('id', 'slug');

        $handle = fopen($datei, 'rb');
        $kopf = fgetcsv($handle, 0, $this->option('trennzeichen'));

        if ($kopf === false) {
            fclose($handle);
            $this->error('Die Datei ist leer.');

            return self::FAILURE;
        }

        $kopf = array_map(fn (string $s) => trim(strtolower($s)), $kopf);
        $zeile = 1;
        $importiert = 0;
        $fehler = [];

        while (($daten = fgetcsv($handle, 0, $this->option('trennzeichen'))) !== false) {
            $zeile++;

            if ($daten === [null] || $daten === []) {
                continue;
            }

            $satz = array_combine($kopf, array_pad(array_slice($daten, 0, count($kopf)), count($kopf), null));

            $pokemonId = $pokemon[(int) ($satz['dex_nr'] ?? 0)] ?? null;
            $gameId = $spiele[trim((string) ($satz['game_slug'] ?? ''))] ?? null;
            $method = ObtainMethod::tryFrom(trim((string) ($satz['method'] ?? '')));

            if ($pokemonId === null) {
                $fehler[] = "Zeile {$zeile}: Dex-Nummer unbekannt ({$satz['dex_nr']})";

                continue;
            }

            if ($gameId === null) {
                $fehler[] = "Zeile {$zeile}: Spiel unbekannt ({$satz['game_slug']})";

                continue;
            }

            if ($method === null) {
                $fehler[] = "Zeile {$zeile}: Methode unbekannt ({$satz['method']})";

                continue;
            }

            $formSlug = trim((string) ($satz['form_slug'] ?? ''));

            Obtainability::updateOrCreate(
                [
                    'pokemon_id' => $pokemonId,
                    'pokemon_form_id' => $formSlug !== '' ? ($formen[$formSlug] ?? null) : null,
                    'game_id' => $gameId,
                    'method' => $method->value,
                ],
                [
                    'location_detail' => trim((string) ($satz['location_detail'] ?? '')) ?: null,
                    'difficulty' => Difficulty::tryFrom(trim((string) ($satz['difficulty'] ?? '')))?->value,
                    'event_expired' => filter_var($satz['event_expired'] ?? false, FILTER_VALIDATE_BOOL),
                    'note' => trim((string) ($satz['note'] ?? '')) ?: null,
                    'source' => 'csv',
                ],
            );

            $importiert++;
        }

        fclose($handle);

        $this->info("{$importiert} Bezugsquellen importiert.");

        foreach (array_slice($fehler, 0, 20) as $meldung) {
            $this->warn($meldung);
        }

        if (count($fehler) > 20) {
            $this->warn('… und '.(count($fehler) - 20).' weitere Zeilen mit Problemen.');
        }

        $this->line('Nicht vergessen: `php artisan pokedex:recalculate` ausführen.');

        return self::SUCCESS;
    }
}
