<?php

namespace App\Support;

use App\Models\Pokemon;

/**
 * Ergebnis der Mehrfach-Fang-Empfehlung für eine Entwicklungslinie (spec.md 2.8).
 */
final class MultiCatchPlan
{
    /**
     * @param  array<int,MultiCatchStep>  $steps
     * @param  array<int,Pokemon>  $unreachable  Stufen ohne jeden direkten Fundweg in der Linie
     */
    public function __construct(
        public readonly array $steps = [],
        public readonly array $unreachable = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->steps === [] && $this->unreachable === [];
    }

    /** Wie viele Exemplare insgesamt gefangen werden müssen. */
    public function totalCatches(): int
    {
        return array_sum(array_map(fn (MultiCatchStep $s) => $s->count(), $this->steps));
    }

    /** @return array<int,string> Ein Satz pro zu fangender Vorstufe. */
    public function sentences(): array
    {
        return array_map(fn (MultiCatchStep $s) => $s->sentence(), $this->steps);
    }
}
