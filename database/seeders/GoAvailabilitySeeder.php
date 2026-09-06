<?php

namespace Database\Seeders;

use App\Enums\GoMethod;
use App\Enums\GoRegion;
use App\Models\GoAvailability;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;

/**
 * Regionale Exklusivität in Pokémon GO (spec.md 2.4).
 *
 * ACHTUNG – bewusst konservativ:
 * Für die Prioritäts-Engine ist ein fehlender GO-Eintrag die *sichere* Annahme.
 * Ohne Eintrag geht die App davon aus, dass GO das Pokémon nicht rettet, und
 * stuft es eher als 🔴 dringend ein. Lieber eine Warnung zu viel als eine zu
 * wenig, wenn eine Deadline im Spiel ist.
 *
 * Diese Liste enthält deshalb nur gut dokumentierte Regionalexklusive. Der
 * vollständige GO-Datensatz gehört per `php artisan pokedex:import-go` aus
 * einer CSV nachgeladen und sollte gegen Bulbapedias „List of regional Pokémon"
 * geprüft werden (spec.md 4).
 */
class GoAvailabilitySeeder extends Seeder
{
    /** pokeapi-slug => [GO-Methode, Regionen] */
    public const REGIONAL_EXCLUSIVES = [
        'mr-mime' => [GoMethod::Wild, [GoRegion::Europa]],
        'farfetchd' => [GoMethod::Wild, [GoRegion::Ostasien]],
        'kangaskhan' => [GoMethod::Wild, [GoRegion::Ozeanien]],
        'tauros' => [GoMethod::Wild, [GoRegion::Nordamerika]],
        'heracross' => [GoMethod::Wild, [GoRegion::Suedamerika, GoRegion::Mittelamerika]],
        'corsola' => [GoMethod::Wild, [GoRegion::Mittelamerika, GoRegion::Naherosten, GoRegion::Suedostasien]],
        'torkoal' => [GoMethod::Wild, [GoRegion::Suedasien, GoRegion::Suedostasien]],
        'tropius' => [GoMethod::Wild, [GoRegion::Afrika, GoRegion::Naherosten]],
        'relicanth' => [GoMethod::Wild, [GoRegion::Ozeanien]],
        'pachirisu' => [GoMethod::Wild, [GoRegion::Nordamerika]],
        'carnivine' => [GoMethod::Wild, [GoRegion::Nordamerika]],
        'maractus' => [GoMethod::Wild, [GoRegion::Mittelamerika, GoRegion::Suedamerika]],
        'sigilyph' => [GoMethod::Wild, [GoRegion::Afrika, GoRegion::Europa]],
        'bouffalant' => [GoMethod::Wild, [GoRegion::Nordamerika]],
        'klefki' => [GoMethod::Wild, [GoRegion::Europa]],

        // Gegensatzpaare: westliche vs. östliche Hemisphäre
        'zangoose' => [GoMethod::Wild, [GoRegion::Nordamerika, GoRegion::Mittelamerika, GoRegion::Suedamerika]],
        'seviper' => [GoMethod::Wild, [GoRegion::Europa, GoRegion::Afrika, GoRegion::Ostasien, GoRegion::Suedasien, GoRegion::Suedostasien, GoRegion::Ozeanien]],
        'throh' => [GoMethod::Wild, [GoRegion::Nordamerika, GoRegion::Suedamerika, GoRegion::Afrika]],
        'sawk' => [GoMethod::Wild, [GoRegion::Europa, GoRegion::Ostasien, GoRegion::Suedasien, GoRegion::Suedostasien]],
        'heatmor' => [GoMethod::Wild, [GoRegion::Nordamerika, GoRegion::Suedamerika, GoRegion::Afrika]],
        'durant' => [GoMethod::Wild, [GoRegion::Europa, GoRegion::Ostasien, GoRegion::Suedasien, GoRegion::Ozeanien]],
    ];

    public function run(): void
    {
        foreach (self::REGIONAL_EXCLUSIVES as $slug => [$method, $regions]) {
            $pokemon = Pokemon::where('slug', $slug)->first();

            if ($pokemon === null) {
                continue; // Art noch nicht importiert – beim nächsten Lauf erneut versuchen.
            }

            GoAvailability::updateOrCreate(
                ['pokemon_id' => $pokemon->id, 'pokemon_form_id' => null],
                [
                    'method' => $method,
                    'regions' => array_map(fn (GoRegion $r) => $r->value, $regions),
                    'transferable_to_home' => true,
                    'note' => 'Regional exklusiv – außerhalb der Region nur per GO-Tausch.',
                ],
            );
        }
    }
}
