<?php

namespace App\Services;

use App\Enums\FormType;
use App\Models\Achievement;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Models\User;
use App\Models\UserPokemonForm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prüft die Achievement-Bedingungen und schaltet frei (spec.md 2.9).
 *
 * Die Bedingungen stehen hier, die Texte im AchievementSeeder – so bleibt der
 * Seeder reine Datenpflege und diese Klasse reine Logik.
 */
class AchievementService
{
    /**
     * Schaltet alle erfüllten Achievements frei und gibt die neu
     * hinzugekommenen zurück (für die Konfetti-Animation im UI).
     *
     * @return Collection<int,Achievement>
     */
    public function sync(User $user): Collection
    {
        $bereits = $user->achievements()->pluck('achievements.key')->all();
        $erfuellt = $this->fulfilledKeys($user);
        $neu = array_values(array_diff($erfuellt, $bereits));

        if ($neu === []) {
            return collect();
        }

        $achievements = Achievement::whereIn('key', $neu)->get();

        DB::transaction(function () use ($user, $achievements) {
            foreach ($achievements as $achievement) {
                $user->achievements()->attach($achievement->id, ['unlocked_at' => now()]);
            }

            $user->increment('xp', $achievements->sum('xp_reward'));
        });

        return $achievements;
    }

    /**
     * Welche Achievement-Keys erfüllt der Nutzer aktuell?
     *
     * @return array<int,string>
     */
    public function fulfilledKeys(User $user): array
    {
        $keys = [];

        $besessen = $this->ownedBaseCount($user);
        $gesamt = PokemonForm::base()->count();

        foreach ([1 => 'first_catch', 10 => 'dex_10', 100 => 'dex_100', 500 => 'dex_500', 1000 => 'dex_1000'] as $schwelle => $key) {
            if ($besessen >= $schwelle) {
                $keys[] = $key;
            }
        }

        if ($gesamt > 0 && $besessen >= $gesamt) {
            $keys[] = 'dex_complete';
        }

        foreach ($this->completedGenerations($user) as $generation) {
            $keys[] = "gen_{$generation}_complete";
        }

        if ($this->hasEveryType($user)) {
            $keys[] = 'all_types';
        }

        $shinys = UserPokemonForm::where('user_id', $user->id)->where('owned_shiny', true)->count();

        foreach ([1 => 'shiny_1', 10 => 'shiny_10', 50 => 'shiny_50', 100 => 'shiny_100', 250 => 'shiny_250'] as $schwelle => $key) {
            if ($shinys >= $schwelle) {
                $keys[] = $key;
            }
        }

        $regional = $this->ownedCount($user, [FormType::Regional, FormType::Other]);
        $regionalGesamt = PokemonForm::whereIn('form_type', [FormType::Regional->value, FormType::Other->value])->count();

        if ($regional >= 25) {
            $keys[] = 'regional_25';
        }

        if ($regionalGesamt > 0 && $regional >= $regionalGesamt) {
            $keys[] = 'regional_all';
        }

        if (($user->login_streak ?? 0) >= 7) {
            $keys[] = 'streak_7';
        }

        if (($user->login_streak ?? 0) >= 30) {
            $keys[] = 'streak_30';
        }

        return $keys;
    }

    /**
     * Achievements rund um die Bank-Deadline brauchen die Prioritäts-Engine und
     * werden deshalb separat geprüft – der Aufrufer übergibt die Zahlen, damit
     * hier nicht der ganze Dex neu bewertet wird.
     *
     * @return Collection<int,Achievement>
     */
    public function syncBankProgress(User $user, int $offeneBankFaelle, int $gerettet): Collection
    {
        $keys = [];

        if ($gerettet >= 10) {
            $keys[] = 'bank_rescue_10';
        }

        if ($gerettet >= 50) {
            $keys[] = 'bank_rescue_50';
        }

        if ($offeneBankFaelle === 0 && $gerettet > 0) {
            $keys[] = 'bank_clear';
        }

        $bereits = $user->achievements()->pluck('achievements.key')->all();
        $neu = array_values(array_diff($keys, $bereits));

        if ($neu === []) {
            return collect();
        }

        $achievements = Achievement::whereIn('key', $neu)->get();

        foreach ($achievements as $achievement) {
            $user->achievements()->attach($achievement->id, ['unlocked_at' => now()]);
        }

        $user->increment('xp', $achievements->sum('xp_reward'));

        return $achievements;
    }

    private function ownedBaseCount(User $user): int
    {
        return $this->ownedCount($user, [FormType::Base]);
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

    /** @return array<int,int> */
    private function completedGenerations(User $user): array
    {
        $gesamt = DB::table('pokemon_forms')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_forms.pokemon_id')
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->groupBy('pokemon.generation')
            ->pluck(DB::raw('count(*) as aggregate'), 'pokemon.generation');

        $besessen = DB::table('user_pokemon_forms')
            ->join('pokemon_forms', 'pokemon_forms.id', '=', 'user_pokemon_forms.pokemon_form_id')
            ->join('pokemon', 'pokemon.id', '=', 'pokemon_forms.pokemon_id')
            ->where('user_pokemon_forms.user_id', $user->id)
            ->where('user_pokemon_forms.owned', true)
            ->where('pokemon_forms.form_type', FormType::Base->value)
            ->groupBy('pokemon.generation')
            ->pluck(DB::raw('count(*) as aggregate'), 'pokemon.generation');

        $fertig = [];

        foreach ($gesamt as $generation => $anzahl) {
            if ($anzahl > 0 && (int) ($besessen[$generation] ?? 0) >= (int) $anzahl) {
                $fertig[] = (int) $generation;
            }
        }

        return $fertig;
    }

    /** Von jedem der 18 Typen mindestens ein Pokémon (spec.md 2.9). */
    private function hasEveryType(User $user): bool
    {
        $typenGesamt = Type::query()
            ->whereExists(fn ($q) => $q->from('pokemon_type')->whereColumn('pokemon_type.type_id', 'types.id'))
            ->count();

        if ($typenGesamt === 0) {
            return false;
        }

        $abgedeckt = DB::table('pokemon_type')
            ->join('pokemon_forms', 'pokemon_forms.pokemon_id', '=', 'pokemon_type.pokemon_id')
            ->join('user_pokemon_forms', function ($join) use ($user) {
                $join->on('user_pokemon_forms.pokemon_form_id', '=', 'pokemon_forms.id')
                    ->where('user_pokemon_forms.user_id', '=', $user->id)
                    ->where('user_pokemon_forms.owned', '=', true);
            })
            ->distinct()
            ->count('pokemon_type.type_id');

        return $abgedeckt >= $typenGesamt;
    }
}
