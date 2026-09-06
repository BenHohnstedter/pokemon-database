<?php

namespace App\Support;

/**
 * Ergebnis der Freitext-Masseneingabe (spec.md 2.5) – Grundlage für die
 * Vorschau "Das markiert 54 Pokémon als besessen – bestätigen?".
 */
final class DexRangeResult
{
    /**
     * @param  array<int,int>  $dexNumbers  aufsteigend sortiert, ohne Duplikate
     * @param  array<int,string>  $invalidTokens
     */
    public function __construct(
        public readonly array $dexNumbers = [],
        public readonly array $invalidTokens = [],
    ) {}

    public function count(): int
    {
        return count($this->dexNumbers);
    }

    public function isEmpty(): bool
    {
        return $this->dexNumbers === [];
    }

    public function hasInvalid(): bool
    {
        return $this->invalidTokens !== [];
    }

    /** "1–50, 60–63, 700" – kompakte Darstellung für die Vorschau. */
    public function summary(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $ranges = [];
        $start = $previous = $this->dexNumbers[0];

        foreach (array_slice($this->dexNumbers, 1) as $number) {
            if ($number === $previous + 1) {
                $previous = $number;

                continue;
            }

            $ranges[] = $start === $previous ? (string) $start : "{$start}–{$previous}";
            $start = $previous = $number;
        }

        $ranges[] = $start === $previous ? (string) $start : "{$start}–{$previous}";

        return implode(', ', $ranges);
    }

    public function invalidSummary(): string
    {
        return implode(', ', $this->invalidTokens);
    }
}
