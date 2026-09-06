<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Seeder;

/**
 * Die 18 Typen inkl. deutscher Namen und Typenfarben (spec.md 5).
 *
 * Bewusst statisch gepflegt statt aus der PokéAPI gezogen: die Liste ändert
 * sich seit Generation 6 nicht mehr, und so laufen Tests ohne Netzzugriff.
 * Der Import-Command aktualisiert die Namen bei Bedarf trotzdem.
 */
class TypeSeeder extends Seeder
{
    /** slug => [name_de, name_en, farbe] */
    public const TYPES = [
        'normal' => ['Normal', 'Normal', '#A8A77A'],
        'fire' => ['Feuer', 'Fire', '#EE8130'],
        'water' => ['Wasser', 'Water', '#6390F0'],
        'electric' => ['Elektro', 'Electric', '#F7D02C'],
        'grass' => ['Pflanze', 'Grass', '#7AC74C'],
        'ice' => ['Eis', 'Ice', '#96D9D6'],
        'fighting' => ['Kampf', 'Fighting', '#C22E28'],
        'poison' => ['Gift', 'Poison', '#A33EA1'],
        'ground' => ['Boden', 'Ground', '#E2BF65'],
        'flying' => ['Flug', 'Flying', '#A98FF3'],
        'psychic' => ['Psycho', 'Psychic', '#F95587'],
        'bug' => ['Käfer', 'Bug', '#A6B91A'],
        'rock' => ['Gestein', 'Rock', '#B6A136'],
        'ghost' => ['Geist', 'Ghost', '#735797'],
        'dragon' => ['Drache', 'Dragon', '#6F35FC'],
        'dark' => ['Unlicht', 'Dark', '#705746'],
        'steel' => ['Stahl', 'Steel', '#B7B7CE'],
        'fairy' => ['Fee', 'Fairy', '#D685AD'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $slug => [$nameDe, $nameEn, $color]) {
            Type::updateOrCreate(
                ['slug' => $slug],
                ['name_de' => $nameDe, 'name_en' => $nameEn, 'color' => $color],
            );
        }
    }
}
