<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\FormType;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pokemon> */
class PokemonFactory extends Factory
{
    protected $model = Pokemon::class;

    /** Fortlaufende Dex-Nummern, damit Tests keine Kollisionen bauen. */
    private static int $nextDexNr = 1;

    public function definition(): array
    {
        $dexNr = self::$nextDexNr++;
        $name = $this->faker->unique()->word();

        return [
            'dex_nr' => $dexNr,
            'slug' => $name.'-'.$dexNr,
            'name_de' => ucfirst($name),
            'name_en' => ucfirst($name),
            'generation' => 1,
            'is_legendary' => false,
            'is_mythical' => false,
            'is_baby' => false,
            'evolution_chain_id' => $dexNr,
            'difficulty' => Difficulty::Leicht,
            'obtainable_directly' => true,
        ];
    }

    public static function resetSequence(): void
    {
        self::$nextDexNr = 1;
    }

    /** Legt gleich die Basisform mit an – die braucht praktisch jeder Test. */
    public function withBaseForm(): static
    {
        return $this->afterCreating(function (Pokemon $pokemon) {
            PokemonForm::factory()->for($pokemon)->create([
                'slug' => $pokemon->slug,
                'name_de' => $pokemon->name_de,
                'name_en' => $pokemon->name_en,
                'form_type' => FormType::Base,
                'is_default' => true,
            ]);
        });
    }

    public function legendary(): static
    {
        return $this->state(fn () => ['is_legendary' => true]);
    }

    public function mythical(): static
    {
        return $this->state(fn () => ['is_mythical' => true]);
    }

    /** Nur per Entwicklung erreichbar (spec.md 2.8). */
    public function evolutionOnly(Pokemon $from): static
    {
        return $this->state(fn () => [
            'evolves_from_id' => $from->id,
            'evolution_chain_id' => $from->evolution_chain_id,
            'obtainable_directly' => false,
        ]);
    }
}
