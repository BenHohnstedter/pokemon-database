<?php

namespace App\Support;

use App\Enums\Difficulty;
use App\Enums\PriorityLevel;

/**
 * Ergebnis der Prioritäts-Engine für genau eine Form (spec.md 2.7, 2.8).
 */
final class PriorityResult
{
    /**
     * @param  array<int,string>  $routes  Konkrete Handlungsanweisungen fürs UI
     * @param  array<int,string>  $consoles  Benoetigte Konsolen, deduped
     */
    public function __construct(
        public readonly PriorityLevel $level,
        public readonly Difficulty $difficulty,
        public readonly string $reason,
        public readonly array $routes = [],
        public readonly array $consoles = [],
        public readonly bool $goRescuable = false,
        public readonly bool $obtainableAtAll = true,
    ) {}

    public function isUrgent(): bool
    {
        return $this->level->isBankCritical();
    }

    /**
     * Kann der Nutzer es mit seinem aktuellen Spielebesitz überhaupt bekommen?
     * Speist den Filter aus spec.md 2.8.
     */
    public function reachableWithCurrentGames(): bool
    {
        return $this->level === PriorityLevel::Owned || $this->level === PriorityLevel::Easy;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'icon' => $this->level->icon(),
            'urgency' => $this->level->urgency(),
            'difficulty' => $this->difficulty->value,
            'difficulty_label' => $this->difficulty->label(),
            'reason' => $this->reason,
            'routes' => $this->routes,
            'consoles' => $this->consoles,
            'go_rescuable' => $this->goRescuable,
            'obtainable_at_all' => $this->obtainableAtAll,
        ];
    }
}
