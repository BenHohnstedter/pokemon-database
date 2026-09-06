<?php

namespace App\Services;

use App\Support\DexRangeResult;

/**
 * Parser für die Freitext-Masseneingabe (spec.md 2.5).
 *
 * Akzeptiert kommagetrennte Dex-Nummern und Bereiche, z.B.
 * `1,15,700` oder `1-50,60-63`. Semikolon, Leerzeichen und Zeilenumbrüche
 * gelten ebenfalls als Trenner, weil Nutzer Listen gern so einfügen.
 * Ungültige Einträge werden gesammelt statt geworfen — das UI zeigt sie
 * in der Vorschau an, bevor irgendetwas gespeichert wird.
 */
class DexRangeParser
{
    /** Obergrenze für einen einzelnen Bereich, damit "1-99999" nicht durchrutscht. */
    private const MAX_RANGE_SPAN = 2000;

    public function parse(?string $input, int $minDex = 1, ?int $maxDex = null): DexRangeResult
    {
        $maxDex ??= PHP_INT_MAX;
        $numbers = [];
        $invalid = [];

        foreach ($this->tokenize($input) as $token) {
            if (str_contains($token, '-')) {
                $this->parseRange($token, $minDex, $maxDex, $numbers, $invalid);

                continue;
            }

            if (! ctype_digit($token)) {
                $invalid[] = $token;

                continue;
            }

            $value = (int) $token;

            if ($value < $minDex || $value > $maxDex) {
                $invalid[] = $token;

                continue;
            }

            $numbers[$value] = true;
        }

        $sorted = array_keys($numbers);
        sort($sorted);

        return new DexRangeResult($sorted, array_values(array_unique($invalid)));
    }

    /** @return array<int,string> */
    private function tokenize(?string $input): array
    {
        $parts = preg_split('/[,;\r\n\t ]+/', trim((string) $input)) ?: [];

        return array_values(array_filter($parts, fn (string $p) => $p !== ''));
    }

    /**
     * @param  array<int,bool>  $numbers
     * @param  array<int,string>  $invalid
     */
    private function parseRange(
        string $token,
        int $minDex,
        int $maxDex,
        array &$numbers,
        array &$invalid,
    ): void {
        $bounds = explode('-', $token);

        if (count($bounds) !== 2 || ! ctype_digit($bounds[0]) || ! ctype_digit($bounds[1])) {
            $invalid[] = $token;

            return;
        }

        $from = (int) $bounds[0];
        $to = (int) $bounds[1];

        // "50-1" ist eine plausible Vertipper-Variante von "1-50", nicht ungültig.
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($from < $minDex || $to > $maxDex || ($to - $from) >= self::MAX_RANGE_SPAN) {
            $invalid[] = $token;

            return;
        }

        for ($i = $from; $i <= $to; $i++) {
            $numbers[$i] = true;
        }
    }
}
