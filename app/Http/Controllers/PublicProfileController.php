<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProgressService;
use Illuminate\View\View;

/**
 * Öffentliches Profil zum Teilen des Fortschritts (spec.md 2.11).
 *
 * Bewusst ohne Login erreichbar, aber nur wenn der Nutzer es in den
 * Einstellungen freigegeben hat. Gezeigt werden ausschließlich Anzeigename,
 * Fortschrittszahlen und Orden – keine E-Mail, keine Freundesliste, keine
 * Einstellungen.
 */
class PublicProfileController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function __invoke(User $user): View
    {
        // 404 statt 403: ein nicht freigegebenes Profil soll sich nicht von
        // einem nicht existierenden unterscheiden lassen.
        abort_unless($user->profile_public, 404);

        return view('trainer.public', [
            'trainer' => $user,
            'basis' => $this->progress->base($user),
            'regional' => $this->progress->regional($user),
            'shiny' => $this->progress->shiny($user),
            'generationen' => $this->progress->byGeneration($user),
            'orden' => $user->achievements()->orderBy('sort_order')->get(),
        ]);
    }
}
