<?php

namespace App\Console\Commands;

use App\Models\PokemonForm;
use App\Models\User;
use App\Models\UserPokemonForm;
use App\Services\AchievementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Legt einen lokalen Testnutzer an (spec.md 10).
 *
 * Bewusst ein Command statt eines Seeders: Zugangsdaten haben in einem
 * öffentlichen Repo nichts zu suchen, auch keine erfundenen. Das Passwort wird
 * hier erzeugt und einmalig ausgegeben.
 */
class CreateDemoUserCommand extends Command
{
    protected $signature = 'pokedex:demo-user
        {--email=demo@localhost : E-Mail-Adresse}
        {--name=Demo-Trainer : Anzeigename}
        {--besitz=0 : So viele zufällige Pokémon gleich als besessen markieren}';

    protected $description = 'Legt lokal einen Testnutzer mit generiertem Passwort an';

    public function handle(AchievementService $achievements): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Dieser Befehl läuft nur in der lokalen Umgebung.');

            return self::FAILURE;
        }

        $email = $this->option('email');
        $passwort = Str::password(16);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('name'),
                'password' => Hash::make($passwort),
                'email_verified_at' => now(),
            ],
        );

        $user->settingsOrDefault();

        $besitz = (int) $this->option('besitz');

        if ($besitz > 0) {
            $formen = PokemonForm::base()->inRandomOrder()->limit($besitz)->pluck('id');

            foreach ($formen as $formId) {
                UserPokemonForm::updateOrCreate(
                    ['user_id' => $user->id, 'pokemon_form_id' => $formId],
                    ['owned' => true, 'owned_at' => now()->subDays(random_int(0, 300))],
                );
            }

            $achievements->sync($user);
            $this->line("{$formen->count()} Pokémon als besessen markiert.");
        }

        $this->newLine();
        $this->info('Testnutzer angelegt:');
        $this->line("  E-Mail:   {$email}");
        $this->line("  Passwort: {$passwort}");
        $this->newLine();
        $this->warn('Das Passwort wird nur jetzt angezeigt und nirgends gespeichert.');

        return self::SUCCESS;
    }
}
