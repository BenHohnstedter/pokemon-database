<?php

namespace App\Support;

use App\Models\Obtainability;
use Illuminate\Support\Collection;

/**
 * Viele mittlere und letzte Entwicklungsstufen sind nirgends wild fangbar
 * (spec.md 2.8). Für die Prioritäts-Engine zählt dann der Weg der nächsten
 * fangbaren Vorstufe – wer Bisasam bekommt, bekommt auch Bisaflor.
 */
final class EvolutionFallback
{
    /** @param  Collection<int,Obtainability>  $sources  Bezugsquellen der Vorstufe */
    public function __construct(
        public readonly string $ancestorName,
        public readonly Collection $sources,
        public readonly ?string $evolutionSummary = null,
    ) {}

    public function isUsable(): bool
    {
        return $this->sources->isNotEmpty();
    }

    /** "über Entwicklung aus Bisasam (aus Bisaknosp ab Level 32)" */
    public function label(): string
    {
        $label = "über Entwicklung aus {$this->ancestorName}";

        return $this->evolutionSummary ? $label.' – '.$this->evolutionSummary : $label;
    }
}
