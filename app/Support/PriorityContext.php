<?php

namespace App\Support;

use App\Enums\GoRegion;
use App\Models\User;

/**
 * Alles, was die Prioritäts-Engine über den Nutzer wissen muss (spec.md 2.6, 2.7).
 *
 * Bewusst ein schlankes Wertobjekt statt des User-Models: die Engine läuft über
 * 1.300+ Pokémon und darf dabei nicht pro Aufruf die Datenbank anfassen.
 */
final class PriorityContext
{
    /** @param  array<int,int>  $ownedGameIds */
    public function __construct(
        public readonly array $ownedGameIds = [],
        public readonly GoRegion $goRegion = GoRegion::Europa,
        public readonly bool $owns3ds = false,
        public readonly bool $ownsSwitch = false,
    ) {}

    public static function forUser(User $user): self
    {
        $settings = $user->settingsOrDefault();

        return new self(
            ownedGameIds: $user->games()->pluck('games.id')->all(),
            goRegion: $settings->go_region ?? GoRegion::Europa,
            owns3ds: (bool) $settings->owns_3ds,
            ownsSwitch: (bool) $settings->owns_switch,
        );
    }

    /** Kontext für nicht eingeloggte Besucher: kein Spielebesitz. */
    public static function guest(): self
    {
        return new self;
    }

    public function ownsGame(int $gameId): bool
    {
        return in_array($gameId, $this->ownedGameIds, true);
    }
}
