<?php

namespace App\Support;

use App\Models\Obtainability;
use Illuminate\Support\Collection;

/**
 * Ein Beschaffungsweg über eine Vorstufe (spec.md 2.8).
 *
 * Viele mittlere und letzte Entwicklungsstufen sind nirgends wild fangbar –
 * und selbst wenn doch, ist der Weg über eine Vorstufe oft der bequemere:
 * Bisaknosp findet man wild nur in X (3DS, Bank-Weg), bekommt es aber aus
 * einem Bisasam des noch käuflichen Let's Go.
 *
 * Es gibt deshalb einen Eintrag pro Vorstufe der Linie, nicht nur für die
 * direkte – die Prioritäts-Engine vergleicht sie alle und nimmt den besten.
 */
final class EvolutionFallback
{
    /**
     * @param  Collection<int,Obtainability>  $sources  Bezugsquellen der Vorstufe
     * @param  int  $steps  Wie viele Entwicklungen liegen dazwischen?
     */
    public function __construct(
        public readonly string $ancestorName,
        public readonly Collection $sources,
        public readonly ?string $evolutionSummary = null,
        public readonly int $steps = 1,
    ) {}

    public function isUsable(): bool
    {
        return $this->sources->isNotEmpty();
    }

    /** "über Entwicklung aus Bisasam – aus Bisaknosp ab Level 32" */
    public function label(): string
    {
        $label = "über Entwicklung aus {$this->ancestorName}";

        return $this->evolutionSummary ? $label.' – '.$this->evolutionSummary : $label;
    }
}
