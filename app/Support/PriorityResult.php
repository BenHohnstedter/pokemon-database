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
    /**
     * @param  bool  $bankDeadline  Führt der Weg, den dieser Nutzer gehen würde,
     *                              über Pokémon Bank? Bewusst unabhängig von der
     *                              Stufe: wer das passende Spiel besitzt, kommt
     *                              zwar leicht an das Pokémon – muss es aber
     *                              trotzdem vor dem Stichtag übertragen.
     */
    public function __construct(
        public readonly PriorityLevel $level,
        public readonly Difficulty $difficulty,
        public readonly string $reason,
        public readonly array $routes = [],
        public readonly array $consoles = [],
        public readonly bool $goRescuable = false,
        public readonly bool $obtainableAtAll = true,
        public readonly bool $bankDeadline = false,
    ) {}

    /** Stufe 🔴: alte Hardware nötig UND nur über Bank erreichbar. */
    public function isUrgent(): bool
    {
        return $this->level->isBankCritical();
    }

    /**
     * Hängt an der Bank-Abschaltung – egal auf welcher Stufe.
     * Speist Countdown-Widget und Deadline-Filter (spec.md 2.7).
     */
    public function affectedByBankDeadline(): bool
    {
        return $this->bankDeadline;
    }

    /**
     * Betroffen, aber der Nutzer kann es sofort selbst holen: das Spiel ist da,
     * es fehlt nur die Übertragung vor dem Stichtag.
     */
    public function bankDeadlineButReachable(): bool
    {
        return $this->bankDeadline && $this->level === PriorityLevel::Easy;
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
            'bank_deadline' => $this->bankDeadline,
        ];
    }
}
