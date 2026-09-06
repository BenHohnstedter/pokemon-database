<?php

namespace App\Http\Controllers;

use App\Enums\PriorityLevel;
use App\Services\PokedexQuery;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Statistik-Seite: Fortschritt nach Typ und Generation, Verhältnis
 * Basis/Regional/Shiny, zeitlicher Verlauf (spec.md 2.10).
 */
class StatisticsController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly PokedexQuery $query,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $bewertet = $this->query->evaluate($user);

        return view('statistics.index', [
            'nachTyp' => $this->progress->byType($user),
            'nachGeneration' => $this->progress->byGeneration($user),
            'basis' => $this->progress->base($user),
            'regional' => $this->progress->regional($user),
            'shiny' => $this->progress->shiny($user),
            'verlauf' => $this->progress->monthlyTimeline($user, 12),
            'nachDringlichkeit' => $this->nachDringlichkeit($bewertet),
            'nachSchwierigkeit' => $bewertet
                ->reject(fn (object $row) => $row->owned)
                ->groupBy(fn (object $row) => $row->priority->difficulty->value)
                ->map->count(),
        ]);
    }

    /** @return array<int,array{level:PriorityLevel,anzahl:int}> */
    private function nachDringlichkeit($bewertet): array
    {
        $ergebnis = [];

        foreach (PriorityLevel::byUrgencyDesc() as $level) {
            $anzahl = $bewertet->filter(fn (object $row) => $row->priority->level === $level)->count();

            if ($anzahl > 0) {
                $ergebnis[] = ['level' => $level, 'anzahl' => $anzahl];
            }
        }

        return $ergebnis;
    }
}
