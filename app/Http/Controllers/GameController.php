<?php

namespace App\Http\Controllers;

use App\Enums\FormType;
use App\Models\Game;
use App\Models\Obtainability;
use App\Services\PokedexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Spiel-für-Spiel abarbeiten: „Ich spiele gerade Pokémon X – was kann ich hier
 * noch holen, das mir fehlt?"
 *
 * Ergänzt die Pokédex-Ansicht, die von der Art ausgeht. Wer tatsächlich eine
 * Konsole in der Hand hat, denkt umgekehrt: erst das Spiel, dann die Liste.
 */
class GameController extends Controller
{
    public function __construct(private readonly PokedexQuery $query) {}

    /** Übersicht aller Spiele mit der Zahl der dort noch offenen Pokémon. */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('games.index', [
            'spiele' => Game::ordered()->get()->groupBy('generation'),
            'besesseneSpiele' => $user->games()->pluck('games.id')->all(),
            'offeneJeSpiel' => $this->offeneJeSpiel($user),
            // Ohne diese Zahl ließen sich "alles gefangen" und "für diesen
            // Titel sind gar keine Fundorte hinterlegt" nicht unterscheiden –
            // beides käme als 0 an, und die Übersicht würde für Pokémon GO
            // oder Grün fälschlich Vollzug melden.
            'gesamtJeSpiel' => $this->gesamtJeSpiel(),
        ]);
    }

    /** Alles, was in genau diesem Spiel noch zu holen ist. */
    public function show(Request $request, Game $game): View
    {
        $user = $request->user();

        $bewertet = $this->query
            ->evaluate($user, [FormType::Base, FormType::Regional, FormType::Other])
            ->keyBy(fn (object $row) => $row->form->pokemon_id);

        // Bezugsquellen dieses Spiels, gruppiert nach Art.
        $quellen = Obtainability::query()
            ->where('game_id', $game->id)
            ->get()
            ->groupBy('pokemon_id');

        $nurOffene = $request->boolean('offen', true);

        $zeilen = collect($quellen)
            ->map(function (Collection $quellenDerArt, int $pokemonId) use ($bewertet) {
                $row = $bewertet->get($pokemonId);

                if ($row === null) {
                    return null;
                }

                // Für die Anzeige zählt der Weg IN DIESEM Spiel, nicht der
                // insgesamt beste – sonst stünde bei jedem Eintrag dieselbe
                // Route aus einem ganz anderen Titel.
                $beste = $quellenDerArt
                    ->sortBy(fn (Obtainability $o) => $o->effectiveDifficulty()->weight())
                    ->first();

                return (object) [
                    'form' => $row->form,
                    'owned' => $row->owned,
                    'ownedShiny' => $row->ownedShiny,
                    'favourite' => $row->favourite,
                    'priority' => $row->priority,
                    'quelle' => $beste,
                ];
            })
            ->filter()
            ->when($nurOffene, fn (Collection $c) => $c->reject(fn (object $row) => $row->owned))
            ->sortBy(fn (object $row) => $row->form->pokemon->dex_nr)
            ->values();

        $this->query->loadTypesFor($zeilen);

        return view('games.show', [
            'game' => $game,
            'zeilen' => $zeilen,
            'nurOffene' => $nurOffene,
            'gesamtImSpiel' => $quellen->count(),
            'besitztSpiel' => $user->games()->where('games.id', $game->id)->exists(),
        ]);
    }

    /**
     * Wie viele fehlende Pokémon lassen sich je Spiel noch holen?
     *
     * Eine Query über die Pivot-Tabelle statt einer Bewertung pro Spiel – bei
     * 39 Titeln wäre alles andere zu langsam.
     *
     * @return array<int,int>
     */
    private function offeneJeSpiel($user): array
    {
        return Obtainability::query()
            ->selectRaw('game_id, count(distinct obtainabilities.pokemon_id) as offen')
            ->whereNotExists(function ($q) use ($user) {
                $q->from('user_pokemon_forms')
                    ->join('pokemon_forms', 'pokemon_forms.id', '=', 'user_pokemon_forms.pokemon_form_id')
                    ->whereColumn('pokemon_forms.pokemon_id', 'obtainabilities.pokemon_id')
                    ->where('user_pokemon_forms.user_id', $user->id)
                    ->where('user_pokemon_forms.owned', true)
                    ->where('pokemon_forms.form_type', FormType::Base->value);
            })
            ->groupBy('game_id')
            ->pluck('offen', 'game_id')
            ->all();
    }

    /**
     * Wie viele Arten sind je Spiel überhaupt hinterlegt?
     *
     * @return array<int,int>
     */
    private function gesamtJeSpiel(): array
    {
        return Obtainability::query()
            ->selectRaw('game_id, count(distinct pokemon_id) as gesamt')
            ->groupBy('game_id')
            ->pluck('gesamt', 'game_id')
            ->all();
    }
}
