<?php

namespace Database\Factories;

use App\Enums\FormType;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PokemonForm> */
class PokemonFormFactory extends Factory
{
    protected $model = PokemonForm::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'pokemon_id' => Pokemon::factory(),
            'slug' => $name.'-'.$this->faker->unique()->numberBetween(1, 999999),
            'name_de' => ucfirst($name),
            'name_en' => ucfirst($name),
            'form_type' => FormType::Base,
            'is_default' => true,
            'sort_order' => 0,
        ];
    }

    public function regional(?string $region = null): static
    {
        return $this->state(fn () => [
            'form_type' => FormType::Regional,
            'region' => $region ?? 'alola',
            'is_default' => false,
            'sort_order' => 10,
        ]);
    }
}
