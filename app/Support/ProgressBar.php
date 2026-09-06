<?php

namespace App\Support;

/**
 * Ein Fortschrittswert für das Dashboard (spec.md 2.2).
 */
final class ProgressBar
{
    public function __construct(
        public readonly string $label,
        public readonly int $owned,
        public readonly int $total,
        public readonly string $key = '',
    ) {}

    public function percent(): float
    {
        return $this->total > 0 ? round(($this->owned / $this->total) * 100, 1) : 0.0;
    }

    public function missing(): int
    {
        return max(0, $this->total - $this->owned);
    }

    public function isComplete(): bool
    {
        return $this->total > 0 && $this->owned >= $this->total;
    }

    /** "587 / 1302 Pokémon gesammelt (45,1 %)" */
    public function caption(string $noun = 'Pokémon'): string
    {
        $percent = number_format($this->percent(), 1, ',', '.');

        return "{$this->owned} / {$this->total} {$noun} gesammelt ({$percent} %)";
    }
}
