<?php

namespace Database\Factories;

use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Obtainability> */
class ObtainabilityFactory extends Factory
{
    protected $model = Obtainability::class;

    public function definition(): array
    {
        return [
            'pokemon_id' => Pokemon::factory(),
            'game_id' => Game::factory(),
            'method' => ObtainMethod::Wild,
            'location_detail' => 'Route 1',
            'event_expired' => false,
            'source' => 'test',
        ];
    }

    /** Abgelaufenes Event – fuehrt auf Stufe TradeOnly (spec.md 2.7). */
    public function expiredEvent(): static
    {
        return $this->state(fn () => [
            'method' => ObtainMethod::Event,
            'event_expired' => true,
            'location_detail' => 'Verteilung 2010',
        ]);
    }

    public function transferOnly(): static
    {
        return $this->state(fn () => [
            'method' => ObtainMethod::TransferOnly,
            'location_detail' => 'Nur per Transfer aus aelteren Spielen',
        ]);
    }
}
