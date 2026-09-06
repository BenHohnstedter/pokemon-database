<?php

namespace App\Console\Commands;

use App\Enums\GoMethod;
use App\Enums\GoRegion;
use App\Models\GoAvailability;
use App\Models\Pokemon;
use Illuminate\Console\Command;

/**
 * Pokémon-GO-Verfügbarkeit aus einer CSV nachladen (spec.md 2.4, 4).
 *
 * Für GO gibt es keine offene API, und die Daten ändern sich laufend
 * (neue Generationen, gedrehte Regionalexklusive). Der GoAvailabilitySeeder
 * enthält deshalb nur die stabilen, gut dokumentierten Fälle; der vollständige
 * Datensatz gehört gepflegt und über diesen Befehl eingespielt.
 *
 * Spaltenformat (Kopfzeile erforderlich):
 *   dex_nr;method;regions;transferable;note
 *
 * `regions` ist eine komma-getrennte Liste von GoRegion-Werten,
 * z.B. `europa,afrika`. Leer oder `weltweit` heißt: keine Einschränkung.
 */
class ImportGoCommand extends Command
{
    protected $signature = 'pokedex:import-go
        {datei : Pfad zur CSV-Datei}
        {--trennzeichen=; : Spaltentrenner}';

    protected $description = 'Importiert Pokémon-GO-Verfügbarkeit und Regionalexklusivität aus einer CSV';

    public function handle(): int
    {
        $datei = $this->argument('datei');

        if (! is_file($datei)) {
            $this->error("Datei nicht gefunden: {$datei}");

            return self::FAILURE;
        }

        $pokemon = Pokemon::pluck('id', 'dex_nr');
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
            $method = GoMethod::tryFrom(trim((string) ($satz['method'] ?? '')));

            if ($pokemonId === null) {
                $fehler[] = "Zeile {$zeile}: Dex-Nummer unbekannt ({$satz['dex_nr']})";

                continue;
            }

            if ($method === null) {
                $fehler[] = "Zeile {$zeile}: GO-Methode unbekannt ({$satz['method']})";

                continue;
            }

            [$regionen, $unbekannt] = $this->regionen($satz['regions'] ?? '');

            foreach ($unbekannt as $wert) {
                $fehler[] = "Zeile {$zeile}: Region unbekannt ({$wert})";
            }

            GoAvailability::updateOrCreate(
                ['pokemon_id' => $pokemonId, 'pokemon_form_id' => null],
                [
                    'method' => $method->value,
                    'regions' => $regionen,
                    'transferable_to_home' => filter_var(
                        $satz['transferable'] ?? true,
                        FILTER_VALIDATE_BOOL,
                        FILTER_NULL_ON_FAILURE,
                    ) ?? true,
                    'note' => trim((string) ($satz['note'] ?? '')) ?: null,
                ],
            );

            $importiert++;
        }

        fclose($handle);

        $this->info("{$importiert} GO-Einträge importiert.");

        foreach (array_slice($fehler, 0, 20) as $meldung) {
            $this->warn($meldung);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:array<int,string>,1:array<int,string>} [gültige Regionen, unbekannte Werte]
     */
    private function regionen(?string $roh): array
    {
        $werte = array_filter(array_map('trim', explode(',', (string) $roh)));

        if ($werte === []) {
            return [[GoRegion::Weltweit->value], []];
        }

        $gueltig = [];
        $unbekannt = [];

        foreach ($werte as $wert) {
            $region = GoRegion::tryFrom(strtolower($wert));

            if ($region === null) {
                $unbekannt[] = $wert;

                continue;
            }

            $gueltig[] = $region->value;
        }

        return [$gueltig ?: [GoRegion::Weltweit->value], $unbekannt];
    }
}
