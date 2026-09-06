<?php

namespace App\Http\Controllers;

use App\Models\UserPokemonForm;
use App\Services\CollectionTransfer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sammlungsstand sichern und wieder einspielen.
 */
class TransferController extends Controller
{
    public function __construct(private readonly CollectionTransfer $transfer) {}

    public function index(Request $request)
    {
        $user = $request->user();

        return view('collection.transfer', [
            'besessen' => UserPokemonForm::where('user_id', $user->id)->where('owned', true)->count(),
            'shinys' => UserPokemonForm::where('user_id', $user->id)->where('owned_shiny', true)->count(),
            'favoriten' => UserPokemonForm::where('user_id', $user->id)->where('is_favourite', true)->count(),
            'dateiname' => $this->transfer->filename($user),
        ]);
    }

    /** Lädt den Stand als JSON-Datei herunter. */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $json = $this->transfer->exportJson($user);

        return response()->streamDownload(
            function () use ($json) {
                echo $json;
            },
            $this->transfer->filename($user),
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'datei' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'],
            'modus' => ['required', 'in:ergaenzen,ersetzen'],
        ], [
            'datei.mimetypes' => 'Bitte eine JSON-Datei aus dem Export hochladen.',
            'datei.max' => 'Die Datei ist größer als 5 MB – das kann kein Sammlungsstand sein.',
        ]);

        $inhalt = file_get_contents($validated['datei']->getRealPath());

        if ($inhalt === false) {
            return back()->withErrors(['datei' => 'Die Datei konnte nicht gelesen werden.']);
        }

        $ergebnis = $this->transfer->import(
            $request->user(),
            $inhalt,
            ersetzen: $validated['modus'] === 'ersetzen',
        );

        if (! $ergebnis->successful) {
            return back()->withErrors(['datei' => $ergebnis->summary()]);
        }

        return redirect()
            ->route('collection.transfer')
            ->with('status', $ergebnis->summary());
    }
}
