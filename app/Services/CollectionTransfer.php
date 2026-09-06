<?php

namespace App\Services;

use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Support\TransferResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Sammlungsstand exportieren und wieder einspielen.
 *
 * Zweck: den eigenen Stand zwischen Installationen mitnehmen und vor größeren
 * Massenaktionen sichern.
 *
 * Referenziert wird über den **Form-Slug** (`bulbasaur`, `vulpix-alola`), nicht
 * über interne IDs: die vergibt jede Datenbank neu, ein Export wäre sonst nur
 * auf genau der Installation brauchbar, aus der er stammt.
 */
class CollectionTransfer
{
    /** Wird beim Einlesen geprüft, damit ein späteres Format erkennbar bleibt. */
    public const FORMAT_VERSION = 1;

    public function __construct(private readonly AchievementService $achievements) {}

    /**
     * Vollständiger Stand als JSON-taugliches Array.
     *
     * Enthält bewusst weder E-Mail noch sonstige Kontodaten – die Datei soll
     * gefahrlos weitergegeben werden können.
     */
    public function export(User $user): array
    {
        $eintraege = UserPokemonForm::query()
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->where('owned', true)
                ->orWhere('owned_shiny', true)
                ->orWhere('is_favourite', true))
            ->with('form:id,slug,name_de')
            ->get()
            ->sortBy(fn (UserPokemonForm $e) => $e->form?->slug ?? '')
            ->map(fn (UserPokemonForm $e) => array_filter([
                'form' => $e->form?->slug,
                'name' => $e->form?->name_de,
                'owned' => $e->owned ?: null,
                'shiny' => $e->owned_shiny ?: null,
                'favourite' => $e->is_favourite ?: null,
                'owned_at' => $e->owned_at?->toIso8601String(),
                'note' => $e->note,
            ], fn ($wert) => $wert !== null))
            ->values()
            ->all();

        return [
            'format' => self::FORMAT_VERSION,
            'app' => config('app.name'),
            'exported_at' => Carbon::now()->toIso8601String(),
            'trainer' => $user->name,
            'count' => count($eintraege),
            'entries' => $eintraege,
        ];
    }

    public function exportJson(User $user): string
    {
        return json_encode(
            $this->export($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /** Dateiname mit Datum, damit mehrere Sicherungen nebeneinander liegen können. */
    public function filename(User $user): string
    {
        $trainer = preg_replace('/[^A-Za-z0-9_-]+/', '-', $user->name) ?: 'trainer';

        return 'dex-rescue-'.strtolower(trim($trainer, '-')).'-'.Carbon::now()->format('Y-m-d').'.json';
    }

    /**
     * Spielt einen Export wieder ein.
     *
     * @param  bool  $ersetzen  true: alles, was nicht in der Datei steht, wird
     *                          zurückgesetzt. false (Standard): die Datei wird
     *                          zum vorhandenen Stand hinzugefügt.
     */
    public function import(User $user, string $json, bool $ersetzen = false): TransferResult
    {
        try {
            $daten = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return TransferResult::fehler('Die Datei ist kein gültiges JSON: '.$e->getMessage());
        }

        if (! is_array($daten) || ! isset($daten['entries']) || ! is_array($daten['entries'])) {
            return TransferResult::fehler(
                'Der Datei fehlt das Feld "entries" – stammt sie wirklich aus dem Export?'
            );
        }

        $format = (int) ($daten['format'] ?? 0);

        if ($format > self::FORMAT_VERSION) {
            return TransferResult::fehler(
                "Die Datei nutzt Format {$format}, diese Version kennt nur ".self::FORMAT_VERSION.'.'
            );
        }

        $formen = PokemonForm::query()->pluck('id', 'slug');

        $gesetzt = 0;
        $unbekannt = [];
        $formIds = [];

        DB::transaction(function () use ($user, $daten, $ersetzen, $formen, &$gesetzt, &$unbekannt, &$formIds) {
            foreach ($daten['entries'] as $eintrag) {
                $slug = is_array($eintrag) ? ($eintrag['form'] ?? null) : null;
                $formId = $slug !== null ? ($formen[$slug] ?? null) : null;

                if ($formId === null) {
                    if ($slug !== null) {
                        $unbekannt[] = $slug;
                    }

                    continue;
                }

                $owned = (bool) ($eintrag['owned'] ?? false);
                $shiny = (bool) ($eintrag['shiny'] ?? false);

                $datensatz = UserPokemonForm::firstOrNew([
                    'user_id' => $user->id,
                    'pokemon_form_id' => $formId,
                ]);

                // Beim Zusammenführen nie etwas wegnehmen: was schon da ist,
                // bleibt. Sonst würde ein älterer Export den Stand verschlechtern.
                $datensatz->owned = $ersetzen ? $owned : ($datensatz->owned || $owned);
                $datensatz->owned_shiny = $ersetzen ? $shiny : ($datensatz->owned_shiny || $shiny);
                $datensatz->is_favourite = $ersetzen
                    ? (bool) ($eintrag['favourite'] ?? false)
                    : ($datensatz->is_favourite || (bool) ($eintrag['favourite'] ?? false));

                $datensatz->owned_at = $datensatz->owned
                    ? ($datensatz->owned_at ?? $this->zeitpunkt($eintrag['owned_at'] ?? null))
                    : null;

                if (! empty($eintrag['note'])) {
                    $datensatz->note = (string) $eintrag['note'];
                }

                $datensatz->save();
                $formIds[] = $formId;
                $gesetzt++;
            }

            if ($ersetzen) {
                // Alles, was die Datei nicht nennt, verliert den Besitzstatus.
                UserPokemonForm::query()
                    ->where('user_id', $user->id)
                    ->when($formIds !== [], fn ($q) => $q->whereNotIn('pokemon_form_id', $formIds))
                    ->update([
                        'owned' => false,
                        'owned_shiny' => false,
                        'owned_at' => null,
                        'owned_shiny_at' => null,
                    ]);
            }
        });

        $this->achievements->sync($user->refresh());

        return TransferResult::erfolg($gesetzt, array_values(array_unique($unbekannt)));
    }

    private function zeitpunkt(?string $wert): Carbon
    {
        if ($wert === null) {
            return Carbon::now();
        }

        try {
            return Carbon::parse($wert);
        } catch (\Throwable) {
            return Carbon::now();
        }
    }
}
