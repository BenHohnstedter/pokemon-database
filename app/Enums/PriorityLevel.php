<?php

namespace App\Enums;

/**
 * Dringlichkeitsstufe der Prioritäts-Engine (spec.md 2.7).
 *
 * Die Reihenfolge der Cases ist die Sortierreihenfolge im UI: was oben steht,
 * hat den größten Handlungsdruck.
 */
enum PriorityLevel: string
{
    /** Bereits im Bestand. */
    case Owned = 'owned';

    /** Fangbar in einem besessenen Spiel oder wild in GO in der eigenen Region. */
    case Easy = 'easy';

    /** Nur in einem Spiel, das der Nutzer nicht hat – aber noch regulär kaufbar. */
    case Purchasable = 'purchasable';

    /** Nur auf alter Hardware, nicht per GO ersetzbar, aber noch nicht Bank-abhängig. */
    case OldHardware = 'old_hardware';

    /** Der einzige Weg nach HOME führt über Pokémon Bank – vor der Abschaltung erledigen. */
    case BankUrgent = 'bank_urgent';

    /** Event vorbei, kein regulärer Fangweg mehr – nur noch über Tausch/Community. */
    case TradeOnly = 'trade_only';

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'Besessen',
            self::Easy => 'Einfach',
            self::Purchasable => 'Kaufbar',
            self::OldHardware => 'Alte Hardware nötig',
            self::BankUrgent => 'Dringend – Bank-Deadline',
            self::TradeOnly => 'Nur noch per Tausch/Community',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Owned => '✅',
            self::Easy => '🟢',
            self::Purchasable => '🟡',
            self::OldHardware => '🟠',
            self::BankUrgent => '🔴',
            self::TradeOnly => '⚪',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owned => 'Schon in Deiner Sammlung.',
            self::Easy => 'Kein Handlungsdruck – Du kommst jederzeit ran.',
            self::Purchasable => 'Das passende Spiel ist noch im Handel erhältlich.',
            self::OldHardware => 'Braucht ein altes Spiel/eine alte Konsole, hat aber keine harte Deadline.',
            self::BankUrgent => 'Führt nur über Pokémon Bank – vor der Abschaltung erledigen!',
            self::TradeOnly => 'Kein regulärer Fangweg mehr – über Tauschbörsen/Community lösen.',
        };
    }

    /** Sortiergewicht: höher = dringlicher. Wird als Spalte persistiert. */
    public function urgency(): int
    {
        return match ($this) {
            self::Owned => 0,
            self::Easy => 1,
            self::Purchasable => 2,
            self::TradeOnly => 3,
            self::OldHardware => 4,
            self::BankUrgent => 5,
        };
    }

    /** Betrifft diese Stufe die Bank-Deadline? Speist das Countdown-Widget. */
    public function isBankCritical(): bool
    {
        return $this === self::BankUrgent;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Owned => 'bg-slate-500/15 text-slate-300 border-slate-500/40',
            self::Easy => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40',
            self::Purchasable => 'bg-yellow-500/15 text-yellow-200 border-yellow-500/40',
            self::OldHardware => 'bg-orange-500/15 text-orange-300 border-orange-500/40',
            self::BankUrgent => 'bg-red-600/20 text-red-300 border-red-500/60',
            self::TradeOnly => 'bg-zinc-400/15 text-zinc-200 border-zinc-400/40',
        };
    }

    /** Alle Stufen absteigend nach Dringlichkeit – für Filter-Chips im UI. */
    public static function byUrgencyDesc(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $b->urgency() <=> $a->urgency());

        return $cases;
    }
}
