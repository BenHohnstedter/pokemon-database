<?php

namespace Database\Factories;

use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Type> */
class TypeFactory extends Factory
{
    protected $model = Type::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->word();

        return [
            'slug' => $slug,
            'name_de' => ucfirst($slug),
            'name_en' => ucfirst($slug),
            'color' => '#777777',
        ];
    }
}
