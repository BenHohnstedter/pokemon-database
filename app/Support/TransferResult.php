<?php

namespace App\Support;

/**
 * Ergebnis eines Sammlungs-Imports.
 */
final class TransferResult
{
    /** @param  array<int,string>  $unknownSlugs */
    private function __construct(
        public readonly bool $successful,
        public readonly int $imported = 0,
        public readonly array $unknownSlugs = [],
        public readonly ?string $error = null,
    ) {}

    /** @param  array<int,string>  $unknownSlugs */
    public static function erfolg(int $imported, array $unknownSlugs = []): self
    {
        return new self(true, $imported, $unknownSlugs);
    }

    public static function fehler(string $meldung): self
    {
        return new self(false, error: $meldung);
    }

    public function hasUnknown(): bool
    {
        return $this->unknownSlugs !== [];
    }

    /** Kurzfassung für die Statusmeldung im UI. */
    public function summary(): string
    {
        if (! $this->successful) {
            return (string) $this->error;
        }

        $text = "{$this->imported} Einträge übernommen.";

        if ($this->hasUnknown()) {
            $anzahl = count($this->unknownSlugs);
            $beispiele = implode(', ', array_slice($this->unknownSlugs, 0, 5));
            $text .= " {$anzahl} unbekannte Formen übersprungen ({$beispiele}"
                .($anzahl > 5 ? ' …' : '').').';
        }

        return $text;
    }
}
