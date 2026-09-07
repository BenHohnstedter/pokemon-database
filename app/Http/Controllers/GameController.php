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
            // Ohne Poké Transporter ist ein Gen-1-bis-5-Titel keine Frist mehr,
            // sondern eine Sackgasse – das gehört dorthin, wo man danach handelt.
            'hatTransporter' => (bool) $user->settingsOrDefault()->has_poke_transporter,
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

        /*
         * Standardmäßig nur normale Formen.
         *
         * Anders als der Pokédex, der die Sammlung vollständig abbilden will,
         * beantwortet diese Seite die Frage "was kann ich hier fangen?" – und
         * die stellt sich auf Artebene. Deshalb hängt die Anzeige hier auch
         * nicht an der Einstellung `count_regional_in_total`, sondern nur am
         * bewussten Umschalten.
         */
        $zeigeFormen = $request->boolean('formen');

        $bewertet = $this->query
            ->evaluate($user, $this->formTypes($zeigeFormen))
            ->groupBy(fn (object $row) => $row->form->pokemon_id);

        // Bezugsquellen dieses Spiels, gruppiert nach Art.
        $quellen = Obtainability::query()
            ->where('game_id', $game->id)
            ->get()
            ->groupBy('pokemon_id');

        $nurOffene = $request->boolean('offen', true);

        $zeilen = collect($quellen)
            ->flatMap(fn (Collection $quellenDerArt, int $pokemonId) => $this->zeilenDerArt(
                $bewertet->get($pokemonId) ?? collect(),
                $quellenDerArt,
            ))
            ->when($nurOffene, fn (Collection $c) => $c->reject(fn (object $row) => $row->owned))
            // Nach Dex-Nummer, und innerhalb einer Art die normale Form zuerst.
            ->sortBy(fn (object $row) => sprintf(
                '%05d-%d-%s',
                $row->form->pokemon->dex_nr,
                $row->form->form_type === FormType::Base ? 0 : 1,
                $row->form->name_de,
            ))
            ->values();

        $this->query->loadTypesFor($zeilen);

        return view('games.show', [
            'game' => $game,
            'zeilen' => $zeilen,
            'nurOffene' => $nurOffene,
            'zeigtFormen' => $zeigeFormen,
            // Ehrlich sagen, warum das Umschalten nichts ändert, statt still
            // dieselbe Liste noch einmal zu zeigen.
            'formenOhneFundort' => $zeigeFormen
                && $quellen->flatten()->whereNotNull('pokemon_form_id')->isEmpty(),
            'gesamtImSpiel' => $quellen->count(),
            'besitztSpiel' => $user->games()->where('games.id', $game->id)->exists(),
            'hatTransporter' => (bool) $user->settingsOrDefault()->has_poke_transporter,
        ]);
    }

    /** @return array<int,FormType> */
    private function formTypes(bool $mitFormen): array
    {
        return $mitFormen
            ? [FormType::Base, FormType::Regional, FormType::Other]
            : [FormType::Base];
    }

    /**
     * Eine Zeile je Form, für die dieses Spiel tatsächlich einen Fundort hat.
     *
     * Die Zuordnung ist der springende Punkt: Fundorte hängen bei uns fast
     * immer an der Art, nicht an einer bestimmten Form. Eine solche Quelle
     * gehört zur normalen Form – "Vulpix in Rot" heißt nicht, dass es dort
     * auch das Alola-Vulpix gäbe. Regionalformen erscheinen deshalb nur, wenn
     * ein Fundort ausdrücklich auf genau diese Form zeigt.
     *
     * @param  Collection<int,object>  $formenDerArt
     * @param  Collection<int,Obtainability>  $quellenDerArt
     * @return Collection<int,object>
     */
    private function zeilenDerArt(Collection $formenDerArt, Collection $quellenDerArt): Collection
    {
        return $formenDerArt
            ->map(function (object $row) use ($quellenDerArt) {
                $passend = $row->form->form_type === FormType::Base
                    ? $quellenDerArt->whereNull('pokemon_form_id')
                    : $quellenDerArt->where('pokemon_form_id', $row->form->id);

                // Für die Anzeige zählt der Weg IN DIESEM Spiel, nicht der
                // insgesamt beste – sonst stünde bei jedem Eintrag dieselbe
                // Route aus einem ganz anderen Titel.
                $beste = $passend
                    ->sortBy(fn (Obtainability $o) => $o->effectiveDifficulty()->weight())
                    ->first();

                if ($beste === null) {
                    return null;
                }

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
            ->values();
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
