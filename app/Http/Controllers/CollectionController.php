<?php

namespace App\Http\Controllers;

use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Services\DexRangeParser;
use App\Services\OwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Sammlungsstand ändern: Einzel-Klick, Wunschliste und Freitext-Masseneingabe
 * (spec.md 2.5).
 */
class CollectionController extends Controller
{
    public function __construct(
        private readonly OwnershipService $ownership,
        private readonly DexRangeParser $parser,
    ) {}

    /** Ein Klick pro Pokémon/Form – aus dem Raster heraus per Alpine.js. */
    public function toggle(Request $request, PokemonForm $form): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'variante' => 'nullable|in:normal,shiny,favorit',
        ]);

        $variante = $validated['variante'] ?? 'normal';
        $user = $request->user();

        $status = match ($variante) {
            'shiny' => $this->ownership->toggle($user, $form, shiny: true),
            'favorit' => $this->ownership->toggleFavourite($user, $form),
            default => $this->ownership->toggle($user, $form),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'variante' => $variante,
                'xp' => $user->fresh()->xp,
                'level' => $user->fresh()->level(),
            ]);
        }

        return back();
    }

    /** Formular der Masseneingabe. */
    public function bulkForm(): View
    {
        return view('collection.bulk', [
            'maxDex' => Pokemon::max('dex_nr') ?? 0,
        ]);
    }

    /**
     * Vorschau vor dem Übernehmen: "Das markiert 54 Pokémon als besessen –
     * bestätigen?" (spec.md 2.5)
     */
    public function bulkPreview(Request $request): View
    {
        $validated = $request->validate([
            'eingabe' => 'required|string|max:5000',
            'aktion' => 'required|in:besitzen,entfernen',
        ]);

        $maxDex = Pokemon::max('dex_nr') ?? 0;
        $ergebnis = $this->parser->parse($validated['eingabe'], 1, $maxDex);

        // Nur Nummern anzeigen, die es wirklich gibt – Lücken im Dex-Bereich
        // (noch nicht importierte Generationen) sonst stillschweigend zählen.
        $vorhanden = Pokemon::whereIn('dex_nr', $ergebnis->dexNumbers)->pluck('dex_nr')->all();

        return view('collection.bulk', [
            'maxDex' => $maxDex,
            'eingabe' => $validated['eingabe'],
            'aktion' => $validated['aktion'],
            'ergebnis' => $ergebnis,
            'treffer' => $vorhanden,
            'namen' => Pokemon::whereIn('dex_nr', array_slice($vorhanden, 0, 12))
                ->orderBy('dex_nr')
                ->pluck('name_de', 'dex_nr'),
        ]);
    }

    /** Übernahme nach bestätigter Vorschau. */
    public function bulkApply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'eingabe' => 'required|string|max:5000',
            'aktion' => 'required|in:besitzen,entfernen',
        ]);

        $maxDex = Pokemon::max('dex_nr') ?? 0;
        $ergebnis = $this->parser->parse($validated['eingabe'], 1, $maxDex);
        $besitzen = $validated['aktion'] === 'besitzen';

        $geaendert = $this->ownership->bulkSet($request->user(), $ergebnis->dexNumbers, $besitzen);

        $verb = $besitzen ? 'als besessen markiert' : 'aus der Sammlung entfernt';

        return redirect()
            ->route('collection.bulk')
            ->with('status', $geaendert === 0
                ? 'Nichts zu tun – die Auswahl war bereits so gesetzt.'
                : "{$geaendert} Pokémon {$verb}.");
    }
}
