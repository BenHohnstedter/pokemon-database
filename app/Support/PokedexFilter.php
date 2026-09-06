<?php

namespace App\Support;

use App\Enums\Difficulty;
use App\Enums\PriorityLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filter und Sortierung der Pokédex-Ansicht (spec.md 2.5, 2.7, 2.8).
 *
 * Arbeitet auf der bereits bewerteten Sammlung aus PokedexQuery, weil sich
 * Dringlichkeit und Schwierigkeit nicht in SQL ausdrücken lassen – sie hängen
 * am Spielebesitz und an der GO-Region des Nutzers.
 */
final class PokedexFilter
{
    public const STATUS_ALL = 'alle';

    public const STATUS_OWNED = 'besessen';

    public const STATUS_MISSING = 'fehlend';

    public const STATUS_FAVOURITES = 'wunschliste';

    public const STATUS_SHINY_MISSING = 'shiny-fehlend';

    public const SORT_DEX = 'dex';

    public const SORT_URGENCY = 'dringlichkeit';

    public const SORT_DIFFICULTY = 'schwierigkeit';

    public const SORT_NAME = 'name';

    /** Auswählbare Seitengrößen (spec.md 7, Performance). */
    public const PER_PAGE_OPTIONS = [30, 60, 120, 240];

    public function __construct(
        public readonly string $search = '',
        public readonly ?int $generation = null,
        public readonly ?string $type = null,
        public readonly ?PriorityLevel $priority = null,
        public readonly ?Difficulty $difficulty = null,
        public readonly string $status = self::STATUS_ALL,
        public readonly bool $onlyUnreachable = false,
        public readonly bool $onlyBankDeadline = false,
        public readonly string $sort = self::SORT_DEX,
        public readonly ?int $perPage = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: trim((string) $request->query('q', '')),
            generation: $request->filled('gen') ? (int) $request->query('gen') : null,
            type: $request->query('typ') ?: null,
            priority: PriorityLevel::tryFrom((string) $request->query('prio')),
            difficulty: Difficulty::tryFrom((string) $request->query('schwierigkeit')),
            status: (string) $request->query('status', self::STATUS_ALL),
            onlyUnreachable: $request->boolean('unerreichbar'),
            onlyBankDeadline: $request->boolean('deadline'),
            sort: (string) $request->query('sortierung', self::SORT_DEX),
            perPage: self::gueltigeSeitengroesse($request->query('pro_seite')),
        );
    }

    /** Nur die angebotenen Größen zulassen – sonst könnte man 100.000 anfordern. */
    private static function gueltigeSeitengroesse(mixed $wert): ?int
    {
        $zahl = (int) $wert;

        return in_array($zahl, self::PER_PAGE_OPTIONS, true) ? $zahl : null;
    }

    /**
     * @param  Collection<int,object>  $rows  Ergebnis von PokedexQuery::evaluate()
     * @return Collection<int,object>
     */
    public function apply(Collection $rows): Collection
    {
        return $this->sort(
            $rows->filter(fn (object $row) => $this->matches($row))->values()
        );
    }

    private function matches(object $row): bool
    {
        $pokemon = $row->form->pokemon;

        if ($this->search !== '' && ! $this->matchesSearch($row)) {
            return false;
        }

        if ($this->generation !== null && $pokemon->generation !== $this->generation) {
            return false;
        }

        if ($this->type !== null && ! $pokemon->types->contains('slug', $this->type)) {
            return false;
        }

        if ($this->priority !== null && $row->priority->level !== $this->priority) {
            return false;
        }

        if ($this->difficulty !== null && $row->priority->difficulty !== $this->difficulty) {
            return false;
        }

        // "Zeig mir alles, was ich mit meinem Spielebesitz gar nicht bekommen kann" (spec.md 2.8)
        if ($this->onlyUnreachable && $row->priority->reachableWithCurrentGames()) {
            return false;
        }

        // Alles, was an der Bank-Frist hängt – auch das, was Du selbst holen kannst.
        if ($this->onlyBankDeadline && ! $row->priority->affectedByBankDeadline()) {
            return false;
        }

        return match ($this->status) {
            self::STATUS_OWNED => $row->owned,
            self::STATUS_MISSING => ! $row->owned,
            self::STATUS_FAVOURITES => $row->favourite,
            self::STATUS_SHINY_MISSING => ! $row->ownedShiny,
            default => true,
        };
    }

    private function matchesSearch(object $row): bool
    {
        $needle = mb_strtolower($this->search);
        $pokemon = $row->form->pokemon;

        if (ctype_digit($this->search)) {
            return $pokemon->dex_nr === (int) $this->search;
        }

        foreach ([$row->form->name_de, $row->form->name_en, $pokemon->name_de, $pokemon->name_en] as $candidate) {
            if (str_contains(mb_strtolower((string) $candidate), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int,object>  $rows
     * @return Collection<int,object>
     */
    private function sort(Collection $rows): Collection
    {
        return match ($this->sort) {
            // Dringendstes zuerst, innerhalb einer Stufe nach Dex-Nummer.
            self::SORT_URGENCY => $rows->sortBy([
                fn (object $a, object $b) => $b->priority->level->urgency() <=> $a->priority->level->urgency(),
                fn (object $a, object $b) => $a->form->pokemon->dex_nr <=> $b->form->pokemon->dex_nr,
            ])->values(),

            self::SORT_DIFFICULTY => $rows->sortBy([
                fn (object $a, object $b) => $b->priority->difficulty->weight() <=> $a->priority->difficulty->weight(),
                fn (object $a, object $b) => $a->form->pokemon->dex_nr <=> $b->form->pokemon->dex_nr,
            ])->values(),

            self::SORT_NAME => $rows->sortBy(fn (object $row) => $row->form->name_de)->values(),

            default => $rows,
        };
    }

    /** Ist überhaupt ein Filter gesetzt? Steuert den "Filter zurücksetzen"-Button. */
    public function isActive(): bool
    {
        return $this->search !== ''
            || $this->generation !== null
            || $this->type !== null
            || $this->priority !== null
            || $this->difficulty !== null
            || $this->onlyUnreachable
            || $this->onlyBankDeadline
            || $this->status !== self::STATUS_ALL
            || $this->sort !== self::SORT_DEX;
    }

    /** Aktuelle Filter als Query-Parameter, z.B. für Pagination-Links. */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->search ?: null,
            'gen' => $this->generation,
            'typ' => $this->type,
            'prio' => $this->priority?->value,
            'schwierigkeit' => $this->difficulty?->value,
            'status' => $this->status !== self::STATUS_ALL ? $this->status : null,
            'unerreichbar' => $this->onlyUnreachable ? 1 : null,
            'deadline' => $this->onlyBankDeadline ? 1 : null,
            'sortierung' => $this->sort !== self::SORT_DEX ? $this->sort : null,
            'pro_seite' => $this->perPage,
        ], fn ($v) => $v !== null);
    }

    /** @return array<string,string> */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ALL => 'Alle',
            self::STATUS_MISSING => 'Nur fehlende',
            self::STATUS_OWNED => 'Nur besessene',
            self::STATUS_FAVOURITES => 'Meine Wunschliste',
            self::STATUS_SHINY_MISSING => 'Shiny fehlt noch',
        ];
    }

    /** @return array<string,string> */
    public static function sortOptions(): array
    {
        return [
            self::SORT_DEX => 'Dex-Nummer',
            self::SORT_URGENCY => 'Dringlichkeit',
            self::SORT_DIFFICULTY => 'Schwierigkeit',
            self::SORT_NAME => 'Name',
        ];
    }
}
