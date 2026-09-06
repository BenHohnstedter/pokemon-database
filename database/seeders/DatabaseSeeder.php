<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Stammdaten, die die App zum Laufen braucht.
 *
 * Bewusst OHNE Testnutzer: das Repo ist öffentlich, deshalb landen hier keine
 * Zugangsdaten (spec.md 10). Zum lokalen Ausprobieren gibt es
 * `php artisan pokedex:demo-user`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            TypeSeeder::class,
            GameSeeder::class,
            AchievementSeeder::class,
            CuratedObtainabilitySeeder::class,
            // Muss nach dem Fundort-Import laufen: übernimmt die Quellen der
            // Originalspiele auf ihre Remakes.
            RemakeObtainabilitySeeder::class,
            GoAvailabilitySeeder::class,
        ]);
    }
}
