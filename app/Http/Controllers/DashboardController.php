<?php

namespace App\Http\Controllers;

use App\Enums\PriorityLevel;
use App\Services\BankDeadline;
use App\Services\PokedexQuery;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard mit Gesamtfortschritt, Bank-Countdown und den dringendsten
 * offenen Fällen (spec.md 2.5, 2.7).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly PokedexQuery $query,
        private readonly BankDeadline $deadline,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $bewertet = $this->query->evaluate($user);

        $offen = $bewertet->reject(fn (object $row) => $row->owned);
        $dringend = $offen->filter(fn (object $row) => $row->priority->isUrgent());

        return view('dashboard', [
            'gesamt' => $this->progress->total($user),
            'basis' => $this->progress->base($user),
            'regional' => $this->progress->regional($user),
            'shiny' => $this->progress->shiny($user),
            'generationen' => $this->progress->byGeneration($user),

            'deadline' => $this->deadline,
            'dringendAnzahl' => $dringend->count(),
            'dringendTop' => $dringend->take(12)->values(),

            'verteilung' => $this->verteilung($offen),
            'naechsteZiele' => $offen
                ->filter(fn (object $row) => $row->favourite)
                ->take(6)
                ->values(),
        ]);
    }

    /**
     * Wie verteilen sich die fehlenden Pokémon auf die Dringlichkeitsstufen?
     *
     * @return array<int,array{level:PriorityLevel,anzahl:int}>
     */
    private function verteilung($offen): array
    {
        $verteilung = [];

        foreach (PriorityLevel::byUrgencyDesc() as $level) {
            if ($level === PriorityLevel::Owned) {
                continue;
            }

            $verteilung[] = [
                'level' => $level,
                'anzahl' => $offen->filter(fn (object $row) => $row->priority->level === $level)->count(),
            ];
        }

        return $verteilung;
    }
}
