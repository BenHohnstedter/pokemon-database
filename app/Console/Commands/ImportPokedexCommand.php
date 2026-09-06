<?php

namespace App\Console\Commands;

use App\Enums\FormType;
use App\Enums\Region;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Services\PokeApiClient;
use Database\Seeders\TypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Einmaliger, wiederholbarer Datenimport aus der PokéAPI (spec.md 4).
 *
 * Der Command ist idempotent: jeder Lauf aktualisiert vorhandene Datensätze,
 * statt Duplikate anzulegen. Neue Generationen holt man später mit demselben
 * Befehl nach.
 */
class ImportPokedexCommand extends Command
{
    protected $signature = 'pokedex:import
        {--from=1 : Erste National-Dex-Nummer}
        {--to= : Letzte National-Dex-Nummer (Standard: alles, was die API kennt)}
        {--limit= : Nur so viele Arten importieren – praktisch für einen Testlauf}
        {--include-other-forms : Auch Sonderformen jenseits der Regionalformen anlegen}
        {--fresh-cache : Plattencache vorher leeren und alles neu abrufen}';

    protected $description = 'Importiert Pokémon, Formen, Typen und Entwicklungsketten aus der PokéAPI';

    /**
     * Regionalform-Suffixe aus den PokéAPI-Varietätsnamen.
     * "tauros-paldea-combat-breed" trifft über den Präfix-Vergleich ebenfalls.
     */
    private const REGIONAL_SUFFIXES = [
        'alola' => Region::Alola,
        'galar' => Region::Galar,
        'hisui' => Region::Hisui,
        'paldea' => Region::Paldea,
    ];

    /**
     * Formen, die HOME nicht dauerhaft speichert bzw. die reine Kampfzustände
     * oder Kostüme sind – werden nie importiert (spec.md 2.2).
     *
     * `-cap` und `-zen` stehen hier, weil sie sonst über den Regionsvergleich
     * hereinrutschen: "pikachu-alola-cap" ist eine Mützen-Variante und keine
     * Alola-Form (die es für Pikachu gar nicht gibt), "darmanitan-galar-zen"
     * der Trance-Modus der Galar-Form.
     */
    private const SKIPPED_FORM_MARKERS = [
        '-mega', '-gmax', '-totem', '-eternamax', '-starter', '-busted', '-cap', '-zen',
    ];

    public function handle(PokeApiClient $api): int
    {
        if ($this->option('fresh-cache')) {
            $cleared = $api->clearCache();
            $this->line("Cache geleert: {$cleared} Dateien.");
        }

        $this->info('Typen werden aktualisiert …');
        (new TypeSeeder)->run();
        $typeIds = Type::query()->pluck('id', 'slug');

        $from = max(1, (int) $this->option('from'));
        $to = $this->resolveUpperBound($api);

        if ($limit = $this->option('limit')) {
            $to = min($to, $from + (int) $limit - 1);
        }

        $count = $to - $from + 1;
        $this->info("Importiere Arten #{$from} bis #{$to} ({$count} Stück) …");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $chainUrls = [];
        $failures = [];

        for ($id = $from; $id <= $to; $id++) {
            try {
                $species = $api->get("pokemon-species/{$id}");
                $pokemon = $this->upsertSpecies($species, $id);
                $this->upsertForms($api, $pokemon, $species, $typeIds);

                if ($url = $species['evolution_chain']['url'] ?? null) {
                    $chainUrls[$url] = true;
                }
            } catch (Throwable $e) {
                $failures[] = "#{$id}: ".$e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Entwicklungsketten werden verknüpft …');
        $this->importEvolutionChains($api, array_keys($chainUrls));

        foreach ($failures as $failure) {
            $this->warn('Übersprungen – '.$failure);
        }

        $this->newLine();
        $this->info(sprintf(
            'Fertig: %d Arten, %d Formen in der Datenbank.%s',
            Pokemon::count(),
            PokemonForm::count(),
            $failures === [] ? '' : ' '.count($failures).' Fehler (siehe oben).',
        ));

        return self::SUCCESS;
    }

    /** Wie weit reicht der nationale Dex aktuell? */
    private function resolveUpperBound(PokeApiClient $api): int
    {
        // Bewusst gegen null geprüft und nicht auf Wahrheitswert: "--to=0" ist
        // eine gültige Angabe, wäre als String aber falsy und würde die API
        // unnötig nach der Gesamtzahl fragen.
        if (($to = $this->option('to')) !== null) {
            return (int) $to;
        }

        try {
            $index = $api->get('pokemon-species?limit=1');

            return (int) ($index['count'] ?? 1025);
        } catch (RuntimeException) {
            $this->warn('Konnte die Gesamtzahl nicht abfragen – nutze 1025 als Obergrenze.');

            return 1025;
        }
    }

    private function upsertSpecies(array $species, int $dexNr): Pokemon
    {
        $names = $species['names'] ?? [];

        return Pokemon::updateOrCreate(
            ['dex_nr' => $dexNr],
            [
                'slug' => $species['name'],
                'name_de' => PokeApiClient::localizedName($names, 'de', $species['name']),
                'name_en' => PokeApiClient::localizedName($names, 'en', $species['name']),
                'generation' => PokeApiClient::idFromUrl($species['generation']['url'] ?? null) ?? 1,
                'is_legendary' => (bool) ($species['is_legendary'] ?? false),
                'is_mythical' => (bool) ($species['is_mythical'] ?? false),
                'is_baby' => (bool) ($species['is_baby'] ?? false),
                'evolution_chain_id' => PokeApiClient::idFromUrl($species['evolution_chain']['url'] ?? null),
            ],
        );
    }

    /** Legt Basisform und – je nach Option – Regional-/Sonderformen an. */
    private function upsertForms(PokeApiClient $api, Pokemon $pokemon, array $species, $typeIds): void
    {
        $this->pruneSkippedForms($pokemon, $species);

        foreach ($species['varieties'] ?? [] as $variety) {
            $slug = $variety['pokemon']['name'] ?? null;

            if ($slug === null || $this->isSkippedForm($slug)) {
                continue;
            }

            $isDefault = (bool) ($variety['is_default'] ?? false);
            $region = $this->regionFor($slug);

            $formType = match (true) {
                $isDefault => FormType::Base,
                $region !== null => FormType::Regional,
                default => FormType::Other,
            };

            if ($formType === FormType::Other && ! $this->option('include-other-forms')) {
                continue;
            }

            $detail = $api->get("pokemon/{$slug}");

            $form = PokemonForm::updateOrCreate(
                ['slug' => $slug],
                [
                    'pokemon_id' => $pokemon->id,
                    'name_de' => $this->formName($pokemon->name_de, $formType, $region, $slug),
                    'name_en' => $this->formName($pokemon->name_en, $formType, $region, $slug),
                    'form_type' => $formType,
                    'region' => $region,
                    'is_default' => $isDefault,
                    'sprite_url' => data_get($detail, 'sprites.front_default'),
                    'shiny_sprite_url' => data_get($detail, 'sprites.front_shiny'),
                    'artwork_url' => data_get($detail, 'sprites.other.official-artwork.front_default')
                        ?: data_get($detail, 'sprites.front_default'),
                    'sort_order' => $isDefault ? 0 : 10,
                ],
            );

            $this->syncTypes($pokemon, $form, $detail, $typeIds, $isDefault);

            if ($isDefault) {
                $pokemon->update([
                    'base_stats' => collect($detail['stats'] ?? [])
                        ->mapWithKeys(fn (array $s) => [$s['stat']['name'] => $s['base_stat']])
                        ->all(),
                    'height' => $detail['height'] ?? null,
                    'weight' => $detail['weight'] ?? null,
                ]);
            }
        }
    }

    /**
     * Formen entfernen, die inzwischen als "nicht speicherbar" gelten.
     *
     * Ohne das bliebe eine einmal falsch importierte Form für immer stehen –
     * der Command soll aber wiederholbar sein und dabei auch aufräumen
     * (spec.md 4).
     */
    private function pruneSkippedForms(Pokemon $pokemon, array $species): void
    {
        $erlaubt = collect($species['varieties'] ?? [])
            ->pluck('pokemon.name')
            ->filter(fn (?string $slug) => $slug !== null && ! $this->isSkippedForm($slug))
            ->all();

        PokemonForm::query()
            ->where('pokemon_id', $pokemon->id)
            ->when($erlaubt !== [], fn ($q) => $q->whereNotIn('slug', $erlaubt))
            ->delete();
    }

    /**
     * Typen hängen an der Form: Alola-Vulpix ist Eis statt Feuer. Für die
     * Basisform bleibt pokemon_form_id null, damit Typ-Statistiken einfach
     * über die Art aggregieren können.
     */
    private function syncTypes(Pokemon $pokemon, PokemonForm $form, array $detail, $typeIds, bool $isDefault): void
    {
        DB::table('pokemon_type')
            ->where('pokemon_id', $pokemon->id)
            ->when($isDefault, fn ($q) => $q->whereNull('pokemon_form_id'))
            ->when(! $isDefault, fn ($q) => $q->where('pokemon_form_id', $form->id))
            ->delete();

        foreach ($detail['types'] ?? [] as $entry) {
            $typeId = $typeIds[$entry['type']['name'] ?? ''] ?? null;

            if ($typeId === null) {
                continue;
            }

            DB::table('pokemon_type')->insert([
                'pokemon_id' => $pokemon->id,
                'pokemon_form_id' => $isDefault ? null : $form->id,
                'type_id' => $typeId,
                'slot' => $entry['slot'] ?? 1,
            ]);
        }
    }

    /** @param  array<int,string>  $urls */
    private function importEvolutionChains(PokeApiClient $api, array $urls): void
    {
        $bar = $this->output->createProgressBar(count($urls));
        $bar->start();

        foreach ($urls as $url) {
            try {
                $chain = $api->get($url);
                $this->walkChain($chain['chain'] ?? [], null);
            } catch (Throwable $e) {
                $this->warn('Entwicklungskette übersprungen: '.$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function walkChain(array $node, ?Pokemon $parent): void
    {
        $slug = $node['species']['name'] ?? null;

        if ($slug === null) {
            return;
        }

        $pokemon = Pokemon::where('slug', $slug)->first();

        if ($pokemon === null) {
            // Art liegt außerhalb des importierten Bereichs – Kinder trotzdem laufen.
            foreach ($node['evolves_to'] ?? [] as $child) {
                $this->walkChain($child, null);
            }

            return;
        }

        if ($parent !== null) {
            $details = $node['evolution_details'][0] ?? [];
            $conditions = $this->evolutionConditions($details);

            $pokemon->update([
                'evolves_from_id' => $parent->id,
                'evolution_trigger' => $details['trigger']['name'] ?? null,
                'evolution_conditions' => $conditions,
                'evolution_summary_de' => $this->evolutionSummary($parent, $details, $conditions),
            ]);
        }

        foreach ($node['evolves_to'] ?? [] as $child) {
            $this->walkChain($child, $pokemon);
        }
    }

    /** Nur die tatsächlich gesetzten Bedingungen behalten – die API liefert viele Nullen. */
    private function evolutionConditions(array $details): array
    {
        $keep = [
            'min_level', 'min_happiness', 'min_affection', 'min_beauty', 'time_of_day',
            'needs_overworld_rain', 'turn_upside_down', 'gender', 'relative_physical_stats',
        ];

        $conditions = [];

        foreach ($keep as $key) {
            $value = $details[$key] ?? null;

            if ($value !== null && $value !== '' && $value !== false) {
                $conditions[$key] = $value;
            }
        }

        foreach (['item', 'held_item', 'known_move', 'known_move_type', 'location', 'party_species', 'trade_species'] as $key) {
            if ($name = $details[$key]['name'] ?? null) {
                $conditions[$key] = $name;
            }
        }

        return $conditions;
    }

    /** Klartext für die Detailseite, z.B. "aus Bisasam ab Level 16". */
    private function evolutionSummary(Pokemon $parent, array $details, array $conditions): string
    {
        $trigger = $details['trigger']['name'] ?? 'level-up';

        $base = match ($trigger) {
            'trade' => isset($conditions['held_item'])
                ? "aus {$parent->name_de} durch Tausch mit Item"
                : "aus {$parent->name_de} durch Tausch",
            'use-item' => "aus {$parent->name_de} mit einem Item",
            'shed' => "aus {$parent->name_de} (Sonderfall: freier Team-Platz + Pokéball)",
            default => isset($conditions['min_level'])
                ? "aus {$parent->name_de} ab Level {$conditions['min_level']}"
                : "aus {$parent->name_de} durch Level-Aufstieg",
        };

        $extras = [];

        if (isset($conditions['item'])) {
            $extras[] = 'Item: '.str_replace('-', ' ', $conditions['item']);
        }

        if (isset($conditions['held_item'])) {
            $extras[] = 'getragenes Item: '.str_replace('-', ' ', $conditions['held_item']);
        }

        if (isset($conditions['time_of_day'])) {
            $extras[] = match ($conditions['time_of_day']) {
                'day' => 'nur tagsüber',
                'night' => 'nur nachts',
                'dusk' => 'nur in der Dämmerung',
                default => 'Tageszeit: '.$conditions['time_of_day'],
            };
        }

        if (isset($conditions['min_happiness'])) {
            $extras[] = 'hohe Freundschaft';
        }

        if (isset($conditions['location'])) {
            $extras[] = 'Ort: '.str_replace('-', ' ', $conditions['location']);
        }

        if (isset($conditions['gender'])) {
            $extras[] = $conditions['gender'] === 1 ? 'nur weiblich' : 'nur männlich';
        }

        return $extras === [] ? $base : $base.' ('.implode(', ', $extras).')';
    }

    private function isSkippedForm(string $slug): bool
    {
        foreach (self::SKIPPED_FORM_MARKERS as $marker) {
            if (str_contains($slug, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function regionFor(string $slug): ?Region
    {
        foreach (self::REGIONAL_SUFFIXES as $needle => $region) {
            if (str_contains($slug, '-'.$needle)) {
                return $region;
            }
        }

        return null;
    }

    private function formName(string $baseName, FormType $type, ?Region $region, string $slug): string
    {
        if ($type === FormType::Base) {
            return $baseName;
        }

        if ($region !== null) {
            return $baseName.' ('.$region->label().'-Form)';
        }

        $suffix = str_replace('-', ' ', trim(substr($slug, strpos($slug, '-') ?: 0), '-'));

        return $baseName.' ('.ucfirst($suffix).')';
    }
}
