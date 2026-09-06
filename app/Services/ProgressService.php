<?php

namespace App\Services;

use App\Enums\FormType;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Support\ProgressBar;
use Illuminate\Support\Facades\DB;

/**
 * Fortschrittszahlen für Dashboard und Statistik-Seite (spec.md 2.2, 2.10).
 *
 * Alles läuft über Aggregat-Queries statt über geladene Modelle – bei 1.300+
 * Pokémon inkl. Formen wäre alles andere zu langsam (spec.md 7, Performance).
 */
class ProgressService
{
    /** Hauptbalken: klassischer nationaler Dex, also nur Basisformen. */
    public function base(User $user): ProgressBar
    {
        return new ProgressBar(
            label: 'Nationaler Dex',
            owned: $this->ownedCount($user, [FormType::Base]),
            total: $this->totalCount([FormType::Base]),
            key: 'base',
        );
    }

    /** Eigener Balken für Regional- und sonstige Sonderformen. */
    public function regional(User $user): ProgressBar
    {
        return new ProgressBar(
            label: 'Regionalformen',
            owned: $this->ownedCount($user, [FormType::Regional, FormType::Other]),
            total: $this->totalCount([FormType::Regional, FormType::Other]),
            key: 'regional',
        );
    }

    /** Shiny läuft quer über alle Formen. */
    public function shiny(User $user): ProgressBar
    {
        return new ProgressBar(
            label: 'Shiny',
            owned: UserPokemonForm::query()
                ->where('user_id', $user->id)
                ->where('owned_shiny', true)
                ->count(),
            total: PokemonForm::query()->count(),
            key: 'shiny',
        );
    }

    /**
     * Der große Balken oben, der die Zähl-Toggles aus den Einstellungen
     * berücksichtigt (spec.md 2.2, 2.6).
     */
    public function total(User $user): ProgressBar
    {
        $settings = $user->settingsOrDefault();

        $formTypes = [FormType::Base];

        if ($settings->count_regional_in_total) {
            $formTypes[] = FormType::Regional;
            $formTypes[] = FormType::Other;
        }

        $owned = $this->ownedCount($user, $formTypes);
        $total = $this->totalCount($formTypes);

        if ($settings->count_shiny_in_total) {
            // Jede mitgezählte Form kann zweimal im Bestand sein: normal und shiny.
            $owned += $this->ownedShinyCount($user, $formTypes);
            $total *= 2;
        }

        return new ProgressBar('Gesamtfortschritt', $owned, $total, 'total');
    }

    /**
     * Fortschritt je Generation (spec.md 2.5, 2.10).
     *
     * @return array<int,ProgressBar>
     */
    public function byGeneration(User $user): array
    {
        $totals = DB::table('pokemon_forms')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_forms.pokemon_id')
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->groupBy('pokemon.generation')
            ->pluck(DB::raw('count(*) as aggregate'), 'pokemon.generation');

        $owned = DB::table('user_pokemon_forms')
            ->join('pokemon_forms', 'pokemon_forms.id', '=', 'user_pokemon_forms.pokemon_form_id')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_forms.pokemon_id')
            ->where('user_pokemon_forms.user_id', $user->id)
            ->where('user_pokemon_forms.owned', true)
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->groupBy('pokemon.generation')
            ->pluck(DB::raw('count(*) as aggregate'), 'pokemon.generation');

        $bars = [];

        foreach ($totals as $generation => $total) {
            $bars[] = new ProgressBar(
                label: "Generation {$generation}",
                owned: (int) ($owned[$generation] ?? 0),
                total: (int) $total,
                key: "gen-{$generation}",
            );
        }

        usort($bars, fn (ProgressBar $a, ProgressBar $b) => strcmp($a->key, $b->key));

        return $bars;
    }

    /**
     * Fortschritt je Typ – speist das Diagramm der Statistik-Seite und das
     * Achievement "Alle Typen mindestens 1×" (spec.md 2.9, 2.10).
     *
     * @return array<int,ProgressBar>
     */
    public function byType(User $user): array
    {
        $totals = DB::table('pokemon_type')
            ->join('pokemon_forms', 'pokemon_forms.pokemon_id', '=', 'pokemon_type.pokemon_id')
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->whereNull('pokemon_type.pokemon_form_id')
            ->groupBy('pokemon_type.type_id')
            ->pluck(DB::raw('count(distinct pokemon_type.pokemon_id) as aggregate'), 'pokemon_type.type_id');

        $owned = DB::table('pokemon_type')
            ->join('pokemon_forms', 'pokemon_forms.pokemon_id', '=', 'pokemon_type.pokemon_id')
            ->join('user_pokemon_forms', function ($join) use ($user) {
                $join->on('user_pokemon_forms.pokemon_form_id', '=', 'pokemon_forms.id')
                    ->where('user_pokemon_forms.user_id', '=', $user->id)
                    ->where('user_pokemon_forms.owned', '=', true);
            })
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->whereNull('pokemon_type.pokemon_form_id')
            ->groupBy('pokemon_type.type_id')
            ->pluck(DB::raw('count(distinct pokemon_type.pokemon_id) as aggregate'), 'pokemon_type.type_id');

        return Type::query()
            ->orderBy('name_de')
            ->get()
            ->map(fn (Type $type) => new ProgressBar(
                label: $type->name_de,
                owned: (int) ($owned[$type->id] ?? 0),
                total: (int) ($totals[$type->id] ?? 0),
                key: $type->slug,
            ))
            ->filter(fn (ProgressBar $bar) => $bar->total > 0)
            ->values()
            ->all();
    }

    /**
     * Zeitlicher Verlauf: wie viele Pokémon pro Monat dazugekommen sind
     * (spec.md 2.10).
     *
     * @return array<string,int> "2026-09" => 42
     */
    public function monthlyTimeline(User $user, int $months = 12): array
    {
        $rows = UserPokemonForm::query()
            ->where('user_id', $user->id)
            ->where('owned', true)
            ->whereNotNull('owned_at')
            ->where('owned_at', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['owned_at'])
            ->groupBy(fn (UserPokemonForm $row) => $row->owned_at->format('Y-m'))
            ->map(fn ($group) => $group->count());

        $timeline = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $timeline[$key] = (int) ($rows[$key] ?? 0);
        }

        return $timeline;
    }

    /** @param  array<int,FormType>  $formTypes */
    private function ownedCount(User $user, array $formTypes): int
    {
        return UserPokemonForm::query()
            ->where('user_id', $user->id)
            ->where('owned', true)
            ->whereIn(
                'pokemon_form_id',
                PokemonForm::query()
                    ->whereIn('form_type', array_map(fn (FormType $t) => $t->value, $formTypes))
                    ->select('id')
            )
            ->count();
    }

    /** @param  array<int,FormType>  $formTypes */
    private function ownedShinyCount(User $user, array $formTypes): int
    {
        return UserPokemonForm::query()
            ->where('user_id', $user->id)
            ->where('owned_shiny', true)
            ->whereIn(
                'pokemon_form_id',
                PokemonForm::query()
                    ->whereIn('form_type', array_map(fn (FormType $t) => $t->value, $formTypes))
                    ->select('id')
            )
            ->count();
    }

    /** @param  array<int,FormType>  $formTypes */
    private function totalCount(array $formTypes): int
    {
        return PokemonForm::query()
            ->whereIn('form_type', array_map(fn (FormType $t) => $t->value, $formTypes))
            ->count();
    }
}
