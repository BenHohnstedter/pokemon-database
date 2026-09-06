<?php

namespace App\Http\Controllers;

use App\Enums\Difficulty;
use App\Enums\FormType;
use App\Enums\PriorityLevel;
use App\Models\Pokemon;
use App\Models\PokemonForm;
use App\Models\Type;
use App\Services\MultiCatchCalculator;
use App\Services\PokedexQuery;
use App\Support\PokedexFilter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pokédex-Raster und Detailseite (spec.md 2.1–2.3, 2.7, 2.8).
 */
class PokedexController extends Controller
{
    public function __construct(
        private readonly PokedexQuery $query,
        private readonly MultiCatchCalculator $multiCatch,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = PokedexFilter::fromRequest($request);

        $formTypes = $this->formTypesFor($request, $user);
        $rows = $this->query->evaluate($user, $formTypes);
        $filtered = $filter->apply($rows);

        return view('pokedex.index', [
            'paginator' => $this->paginate($filtered, $request),
            'filter' => $filter,
            'proSeite' => $this->perPageFor($request, $filter),
            'gesamt' => $rows->count(),
            'gefunden' => $filtered->count(),
            'typen' => Type::orderBy('name_de')->get(),
            'generationen' => Pokemon::query()->distinct()->orderBy('generation')->pluck('generation'),
            'prioritaeten' => PriorityLevel::byUrgencyDesc(),
            'schwierigkeiten' => Difficulty::cases(),
            'zeigtFormen' => count($formTypes) > 1,
        ]);
    }

    public function show(Request $request, Pokemon $pokemon): View
    {
        $user = $request->user();

        $pokemon->load([
            'types',
            'forms',
            'evolvesFrom:id,dex_nr,name_de',
            'evolvesTo:id,dex_nr,name_de,evolves_from_id',
            'sourcePokemon:id,dex_nr,name_de',
            'goAvailability',
        ]);

        $formen = $pokemon->forms->map(fn ($form) => (object) [
            'form' => $form,
            'priority' => $this->query->evaluateForm($form, $user),
            'ownership' => $user
                ? $user->ownerships()->where('pokemon_form_id', $form->id)->first()
                : null,
        ]);

        $linie = $pokemon->evolutionLine()->get();

        return view('pokedex.show', [
            'pokemon' => $pokemon,
            'formen' => $formen,
            'quellen' => $this->query->sourcesFor($pokemon->id)
                ->sortBy(fn ($o) => $o->game?->sort_order ?? 0),
            'linie' => $linie,
            'fangplan' => $this->multiCatch->plan($linie, $this->ownedDexNumbers($request, $linie)),
        ]);
    }

    /**
     * Regionalformen erscheinen im Raster, sobald der Nutzer sie zählt oder
     * explizit danach filtert (spec.md 2.2).
     *
     * @return array<int,FormType>
     */
    private function formTypesFor(Request $request, $user): array
    {
        $zeigeFormen = $request->boolean('formen')
            || ($user && $user->settingsOrDefault()->count_regional_in_total);

        return $zeigeFormen
            ? [FormType::Base, FormType::Regional, FormType::Other]
            : [FormType::Base];
    }

    /**
     * Welche Stufen der Linie hat der Nutzer schon? Grundlage der
     * Mehrfach-Fang-Empfehlung (spec.md 2.8).
     *
     * @param  Collection<int,Pokemon>  $linie
     * @return array<int,int>
     */
    private function ownedDexNumbers(Request $request, Collection $linie): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        // Basisformen der Linie, damit Dex-Nummer und Form-ID zusammenfinden.
        $dexByFormId = PokemonForm::query()
            ->base()
            ->whereIn('pokemon_id', $linie->pluck('id'))
            ->pluck('pokemon_id', 'id')
            ->map(fn (int $pokemonId) => $linie->firstWhere('id', $pokemonId)?->dex_nr);

        return $user->ownerships()
            ->where('owned', true)
            ->whereIn('pokemon_form_id', $dexByFormId->keys())
            ->pluck('pokemon_form_id')
            ->map(fn (int $formId) => $dexByFormId[$formId] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Einträge pro Seite: Query-Parameter schlägt Nutzereinstellung schlägt
     * Standardwert. So lässt sich die Größe einmal dauerhaft setzen und
     * trotzdem pro Aufruf abweichen.
     */
    private function perPageFor(Request $request, PokedexFilter $filter): int
    {
        if ($filter->perPage !== null) {
            return $filter->perPage;
        }

        $gespeichert = $request->user()?->settingsOrDefault()->per_page;

        return $gespeichert ?: (int) config('pokedex.per_page', 60);
    }

    /**
     * Manuelle Pagination, weil die Sammlung bereits im Speicher liegt –
     * anders lässt sich nicht nach Dringlichkeit sortieren (siehe PokedexQuery).
     */
    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = $this->perPageFor($request, PokedexFilter::fromRequest($request));
        $page = max(1, (int) $request->query('page', 1));

        $seite = $rows->forPage($page, $perPage)->values();

        // Typen erst jetzt nachladen – nur für die tatsächlich gezeigten Karten.
        $this->query->loadTypesFor($seite);

        return new LengthAwarePaginator(
            $seite,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }
}
