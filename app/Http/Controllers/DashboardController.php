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

        /*
        | An der Bank-Frist hängen zwei sehr verschiedene Gruppen, und beide
        | gehören ins Countdown-Widget:
        |
        |  - "Du kommst noch nicht dran" (🔴): Dir fehlt das Spiel bzw. die
        |    Konsole. Hier musst Du erst etwas beschaffen.
        |  - "Du kannst es selbst holen": Du besitzt das Spiel, musst das Pokémon
        |    aber vor dem Stichtag über Pokémon Bank nach HOME schieben.
        |
        | Vorher tauchte die zweite Gruppe nirgends auf, weil sie als 🟢 einfach
        | eingestuft war – genau die Fälle, die man kurz vor Schluss vergisst.
        */
        $betroffen = $offen->filter(fn (object $row) => $row->priority->affectedByBankDeadline());
        $dringend = $betroffen->filter(fn (object $row) => $row->priority->isUrgent());
        $selbstHolbar = $betroffen->filter(fn (object $row) => $row->priority->bankDeadlineButReachable());

        // Nur die tatsächlich angezeigten Karten brauchen ihre Typen.
        $dringendTop = $dringend->take(12)->values();
        $selbstHolbarTop = $selbstHolbar->take(12)->values();
        $naechsteZiele = $offen->filter(fn (object $row) => $row->favourite)->take(6)->values();

        $this->query->loadTypesFor($dringendTop->merge($selbstHolbarTop)->merge($naechsteZiele));

        return view('dashboard', [
            'gesamt' => $this->progress->total($user),
            'basis' => $this->progress->base($user),
            'regional' => $this->progress->regional($user),
            'shiny' => $this->progress->shiny($user),
            'generationen' => $this->progress->byGeneration($user),

            'deadline' => $this->deadline,
            'betroffenAnzahl' => $betroffen->count(),
            'dringendAnzahl' => $dringend->count(),
            'dringendTop' => $dringendTop,
            'selbstHolbarAnzahl' => $selbstHolbar->count(),
            'selbstHolbarTop' => $selbstHolbarTop,

            'verteilung' => $this->verteilung($offen),
            'naechsteZiele' => $naechsteZiele,
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
