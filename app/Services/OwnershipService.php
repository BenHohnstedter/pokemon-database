<?php

namespace App\Services;

use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt den Sammlungsstand und vergibt dabei XP (spec.md 2.5, 2.9).
 *
 * Zentral gebündelt, weil es drei Einstiegspunkte gibt – Einzel-Klick,
 * Freitext-Masseneingabe und Wunschliste – die alle dieselben Nebenwirkungen
 * (owned_at setzen, XP gutschreiben, Achievements prüfen) auslösen müssen.
 */
class OwnershipService
{
    public function __construct(private readonly AchievementService $achievements) {}

    /** @return bool  der neue Besitzstatus */
    public function toggle(User $user, PokemonForm $form, bool $shiny = false): bool
    {
        $record = $this->record($user, $form);
        $column = $shiny ? 'owned_shiny' : 'owned';
        $next = ! $record->{$column};

        $this->write($user, $record, $column, $next);

        return $next;
    }

    public function set(User $user, PokemonForm $form, bool $owned, bool $shiny = false): void
    {
        $record = $this->record($user, $form);
        $this->write($user, $record, $shiny ? 'owned_shiny' : 'owned', $owned);
    }

    /** @return bool  der neue Favoritenstatus */
    public function toggleFavourite(User $user, PokemonForm $form): bool
    {
        $record = $this->record($user, $form);
        $record->is_favourite = ! $record->is_favourite;
        $record->save();

        return $record->is_favourite;
    }

    /**
     * Massenmarkierung anhand von Dex-Nummern (spec.md 2.5).
     * Wirkt immer auf die Basisform – Regional- und Shiny-Bestände werden
     * bewusst nicht per Nummernliste überschrieben.
     *
     * @param  array<int,int>  $dexNumbers
     * @return int Anzahl tatsächlich geänderter Einträge
     */
    public function bulkSet(User $user, array $dexNumbers, bool $owned): int
    {
        if ($dexNumbers === []) {
            return 0;
        }

        $formIds = PokemonForm::query()
            ->base()
            ->whereIn('pokemon_id', Pokemon::whereIn('dex_nr', $dexNumbers)->select('id'))
            ->pluck('id');

        $changedFormIds = [];
        $now = now();

        DB::transaction(function () use ($user, $formIds, $owned, $now, &$changedFormIds) {
            foreach ($formIds as $formId) {
                $record = UserPokemonForm::firstOrNew([
                    'user_id' => $user->id,
                    'pokemon_form_id' => $formId,
                ]);

                if ($record->exists && $record->owned === $owned) {
                    continue;
                }

                $record->owned = $owned;
                $record->owned_at = $owned ? ($record->owned_at ?? $now) : null;
                $record->save();
                $changedFormIds[] = $formId;
            }
        });

        if ($changedFormIds !== []) {
            // XP nur für die Einträge, die sich wirklich geändert haben –
            // ein zweiter Durchlauf derselben Liste darf nichts gutschreiben.
            $this->awardXpForBulk($user, $changedFormIds, $owned);
            $this->achievements->sync($user->refresh());
        }

        return count($changedFormIds);
    }

    private function record(User $user, PokemonForm $form): UserPokemonForm
    {
        return UserPokemonForm::firstOrNew([
            'user_id' => $user->id,
            'pokemon_form_id' => $form->id,
        ]);
    }

    private function write(User $user, UserPokemonForm $record, string $column, bool $value): void
    {
        $record->{$column} = $value;
        $record->{$column === 'owned' ? 'owned_at' : 'owned_shiny_at'} = $value ? now() : null;
        $record->save();

        $xp = $this->xpFor($record->form, $column === 'owned_shiny');

        if ($value) {
            $user->increment('xp', $xp);
        } else {
            // Beim Zurücknehmen die Punkte wieder abziehen, sonst ließe sich
            // das Level durch Hin- und Herklicken hochtreiben.
            $user->decrement('xp', min($xp, $user->xp));
        }

        $this->achievements->sync($user->refresh());
    }

    /** XP-Vergabe nach Seltenheit und Schwierigkeit (spec.md 2.9). */
    public function xpFor(?PokemonForm $form, bool $shiny = false): int
    {
        if ($form === null) {
            return (int) config('pokedex.xp.base', 10);
        }

        $pokemon = $form->pokemon;
        $config = config('pokedex.xp');

        $xp = (int) $config['base'];
        $xp += (int) ($config['difficulty_bonus'][$pokemon?->difficulty?->value] ?? 0);

        if ($pokemon?->is_legendary) {
            $xp += (int) $config['legendary_bonus'];
        }

        if ($pokemon?->is_mythical) {
            $xp += (int) $config['mythical_bonus'];
        }

        if (! $form->form_type->isBase()) {
            $xp += (int) $config['regional_bonus'];
        }

        return $shiny ? $xp * (int) $config['shiny_multiplier'] : $xp;
    }

    /** @param  array<int,int>  $formIds */
    private function awardXpForBulk(User $user, array $formIds, bool $owned): void
    {
        $forms = PokemonForm::with('pokemon')->whereIn('id', $formIds)->get();
        $xp = $forms->sum(fn (PokemonForm $form) => $this->xpFor($form));

        if ($owned) {
            $user->increment('xp', $xp);
        } else {
            $user->decrement('xp', min($xp, $user->xp));
        }
    }
}
