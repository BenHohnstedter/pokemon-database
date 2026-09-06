<?php

namespace App\Support;

use App\Models\Pokemon;

/**
 * "Fange 3× Bisasam: 1× so lassen, 1× zu Bisaknosp entwickeln (und stoppen),
 * 1× bis Bisaflor weiterentwickeln." (spec.md 2.8)
 */
final class MultiCatchStep
{
    /**
     * @param  Pokemon  $origin  die wild fangbare Vorstufe
     * @param  array<int,Pokemon>  $targets  Stufen, die aus diesen Exemplaren entstehen sollen,
     *                                       aufsteigend nach Entwicklungstiefe
     */
    public function __construct(
        public readonly Pokemon $origin,
        public readonly array $targets,
    ) {}

    public function count(): int
    {
        return count($this->targets);
    }

    public function sentence(): string
    {
        $count = $this->count();
        $last = $count - 1;

        $parts = [];

        foreach (array_values($this->targets) as $index => $target) {
            if ($target->is($this->origin)) {
                $parts[] = '1× so lassen';

                continue;
            }

            $parts[] = $index === $last
                ? "1× bis {$target->name_de} weiterentwickeln"
                : "1× zu {$target->name_de} entwickeln (und stoppen)";
        }

        return "Fange {$count}× {$this->origin->name_de}: ".implode(', ', $parts).'.';
    }
}
