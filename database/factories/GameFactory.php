<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 *
 * Die benannten States entsprechen genau den Fällen, die die Prioritäts-Engine
 * unterscheidet (spec.md 2.7) – Tests lesen sich dadurch wie die Tabelle dort.
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'slug' => $slug,
            'name_de' => ucfirst($slug),
            'name_en' => ucfirst($slug),
            'generation' => 8,
            'platform' => Platform::Switch,
            'release_year' => 2019,
            'home_compatible' => true,
            'bank_only' => false,
            'still_purchasable' => true,
            'sort_order' => 0,
        ];
    }

    /** Aktueller Switch-Titel: hängt direkt an HOME und ist noch im Handel. */
    public function modern(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Switch,
            'generation' => 9,
            'home_compatible' => true,
            'bank_only' => false,
            'still_purchasable' => true,
        ]);
    }

    /** Alter Titel, der nur über Pokémon Bank nach HOME kommt. */
    public function bankOnly(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::Nintendo3ds,
            'generation' => 6,
            'home_compatible' => false,
            'bank_only' => true,
            'still_purchasable' => false,
        ]);
    }

    /** Alte Hardware, aber ohne Bank-Anbindung (z.B. Gen-1-Modul ohne VC). */
    public function legacyWithoutBank(): static
    {
        return $this->state(fn () => [
            'platform' => Platform::GameBoyAdvance,
            'generation' => 3,
            'home_compatible' => false,
            'bank_only' => false,
            'still_purchasable' => false,
        ]);
    }

    public function notPurchasable(): static
    {
        return $this->state(fn () => ['still_purchasable' => false]);
    }
}
