<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;

/**
 * Bezugsquellen, die die PokéAPI nicht liefert (spec.md 2.3, 4).
 *
 * `location-area-encounters` deckt nur Wildfänge ab. Starter, Fossilien und
 * Event-Verteilungen müssen deshalb kuratiert dazu – ohne sie hielte die
 * Mehrfach-Fang-Empfehlung (2.8) z.B. Bisaflor für gar nicht erreichbar,
 * weil Bisasam nirgends „wild" vorkommt.
 *
 * Ergänzungen gehören hier hinein oder per `pokedex:import-sources` aus einer
 * CSV – der Seeder ist idempotent.
 */
class CuratedObtainabilitySeeder extends Seeder
{
    /** pokeapi-slug => [game-slugs] – Starter als Geschenk zu Spielbeginn. */
    public const STARTERS = [
        'bulbasaur' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'charmander' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'squirtle' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'pikachu' => ['yellow', 'lets-go-pikachu'],
        'eevee' => ['lets-go-eevee'],

        'chikorita' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver'],
        'cyndaquil' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver', 'legends-arceus'],
        'totodile' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver'],

        'treecko' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'torchic' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'mudkip' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],

        'turtwig' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'chimchar' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'piplup' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],

        'snivy' => ['black', 'white', 'black-2', 'white-2'],
        'tepig' => ['black', 'white', 'black-2', 'white-2'],
        'oshawott' => ['black', 'white', 'black-2', 'white-2', 'legends-arceus'],

        'chespin' => ['x', 'y'],
        'fennekin' => ['x', 'y'],
        'froakie' => ['x', 'y'],

        'rowlet' => ['sun', 'moon', 'ultra-sun', 'ultra-moon', 'legends-arceus'],
        'litten' => ['sun', 'moon', 'ultra-sun', 'ultra-moon'],
        'popplio' => ['sun', 'moon', 'ultra-sun', 'ultra-moon'],

        'grookey' => ['sword', 'shield'],
        'scorbunny' => ['sword', 'shield'],
        'sobble' => ['sword', 'shield'],

        'sprigatito' => ['scarlet', 'violet'],
        'fuecoco' => ['scarlet', 'violet'],
        'quaxly' => ['scarlet', 'violet'],
    ];

    /** pokeapi-slug => [game-slugs] – Fossil-Wiederbelebung. */
    public const FOSSILS = [
        'omanyte' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'kabuto' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'aerodactyl' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'lileep' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'anorith' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'cranidos' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'shieldon' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'tirtouga' => ['black', 'white', 'black-2', 'white-2'],
        'archen' => ['black', 'white', 'black-2', 'white-2'],
        'tyrunt' => ['x', 'y'],
        'amaura' => ['x', 'y'],
    ];

    /**
     * Mysteriöse Pokémon: wurden ausschließlich über abgelaufene Verteilungen
     * ausgegeben. Sie landen dadurch auf ⚪ „nur noch per Tausch/Community"
     * (spec.md 2.7). Ausnahmen mit regulärem Fangweg stehen nicht in der Liste.
     */
    public const EXPIRED_EVENT_MYTHICALS = [
        'mew', 'celebi', 'jirachi', 'deoxys', 'phione', 'manaphy', 'darkrai',
        'shaymin', 'arceus', 'victini', 'keldeo', 'meloetta', 'genesect',
        'diancie', 'hoopa', 'volcanion', 'magearna', 'marshadow', 'zeraora',
        'zarude',
    ];

    public function run(): void
    {
        $games = Game::query()->pluck('id', 'slug');
        $pokemon = Pokemon::query()->pluck('id', 'slug');

        // Ohne diesen Hinweis bliebe es unbemerkt, wenn der Seeder vor dem
        // Import läuft: er findet dann keine Art und legt stillschweigend
        // nichts an – Starter und Fossilien fehlten danach als Bezugsquelle.
        if ($pokemon->isEmpty() && $this->command !== null) {
            $this->command->warn(
                'CuratedObtainabilitySeeder: keine Pokémon in der Datenbank – '
                .'nach `pokedex:import` erneut ausführen.'
            );

            return;
        }

        $this->seedGroup(self::STARTERS, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht, 'Starter-Pokémon zu Spielbeginn');
        $this->seedGroup(self::FOSSILS, $games, $pokemon, ObtainMethod::Fossil, Difficulty::Mittel, 'Fossil wiederbeleben');

        foreach (self::EXPIRED_EVENT_MYTHICALS as $slug) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            // Ohne Spielbezug – die Verteilung lief über mehrere Titel hinweg.
            // Der Eintrag hängt am ältesten Spiel der jeweiligen Generation.
            $gameId = $games['diamond'] ?? $games->first();

            Obtainability::updateOrCreate(
                [
                    'pokemon_id' => $pokemonId,
                    'game_id' => $gameId,
                    'method' => ObtainMethod::Event->value,
                ],
                [
                    'pokemon_form_id' => null,
                    'location_detail' => 'Zeitlich begrenzte Verteilung',
                    'difficulty' => Difficulty::SehrSchwer->value,
                    'event_expired' => true,
                    'note' => 'Event ist vorbei – nur noch über Tausch/Community erreichbar.',
                    'source' => 'curated',
                ],
            );
        }
    }

    /** @param  array<string,array<int,string>>  $group */
    private function seedGroup(
        array $group,
        $games,
        $pokemon,
        ObtainMethod $method,
        Difficulty $difficulty,
        string $detail,
    ): void {
        foreach ($group as $slug => $gameSlugs) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            foreach ($gameSlugs as $gameSlug) {
                $gameId = $games[$gameSlug] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemonId,
                        'game_id' => $gameId,
                        'method' => $method->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => $detail,
                        'difficulty' => $difficulty->value,
                        'event_expired' => false,
                        'source' => 'curated',
                    ],
                );
            }
        }
    }
}
