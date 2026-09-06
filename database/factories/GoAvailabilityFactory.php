<?php

namespace Database\Factories;

use App\Enums\GoMethod;
use App\Enums\GoRegion;
use App\Models\GoAvailability;
use App\Models\Pokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoAvailability> */
class GoAvailabilityFactory extends Factory
{
    protected $model = GoAvailability::class;

    public function definition(): array
    {
        return [
            'pokemon_id' => Pokemon::factory(),
            'pokemon_form_id' => null,
            'method' => GoMethod::Wild,
            'regions' => [GoRegion::Weltweit->value],
            'transferable_to_home' => true,
        ];
    }

    /** @param  array<int,GoRegion>  $regions */
    public function exclusiveTo(array $regions): static
    {
        return $this->state(fn () => [
            'regions' => array_map(fn (GoRegion $r) => $r->value, $regions),
        ]);
    }

    public function notAvailable(): static
    {
        return $this->state(fn () => [
            'method' => GoMethod::NotAvailable,
            'regions' => [],
            'transferable_to_home' => false,
        ]);
    }

    /** Nur waehrend Community Days – kein verlaesslicher Rettungsweg. */
    public function communityDayOnly(): static
    {
        return $this->state(fn () => ['method' => GoMethod::CommunityDay]);
    }
}
