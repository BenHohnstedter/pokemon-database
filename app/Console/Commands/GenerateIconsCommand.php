<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Erzeugt die PWA-Icons als Pixel-Art aus Code (spec.md 5, 6).
 *
 * Selbst gezeichnet statt kopiert: die Spec verlangt eigene Pixel-Icons statt
 * 1:1 übernommener Nintendo-Grafiken. Als Command statt als eingecheckte
 * Binärdateien, damit im öffentlichen Repo nachvollziehbar bleibt, wie das
 * Icon entsteht – und damit Farben ohne Grafikprogramm änderbar sind.
 */
class GenerateIconsCommand extends Command
{
    protected $signature = 'pokedex:icons {--size=* : Zusätzliche Kantenlängen}';

    protected $description = 'Erzeugt die PWA-Icons (Pixel-Art, aus Code gezeichnet)';

    /**
     * 16×16-Raster des Icons: eine stilisierte Pokéball-Silhouette mit
     * Sanduhr-Andeutung. 0 = Hintergrund, 1 = Rot, 2 = Weiß, 3 = Rahmen.
     */
    private const GRID = [
        '0000333333330000',
        '0033111111330000',
        '0311111111113000',
        '3111111111111300',
        '3111111111111300',
        '3111111111111300',
        '3333333333333300',
        '3222233223222300',
        '3222233223222300',
        '3333333333333300',
        '3222222222222300',
        '3222222222222300',
        '3222222222222300',
        '0322222222223000',
        '0033222222330000',
        '0000333333330000',
    ];

    private const COLORS = [
        '0' => [15, 23, 42],      // Hintergrund (dex-bg)
        '1' => [220, 38, 38],     // Rot
        '2' => [241, 245, 249],   // Weiß
        '3' => [15, 23, 42],      // Rahmen
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('Die PHP-Erweiterung GD ist nicht aktiv – ohne sie lassen sich keine Icons erzeugen.');

            return self::FAILURE;
        }

        $verzeichnis = public_path('icons');

        if (! is_dir($verzeichnis) && ! mkdir($verzeichnis, 0775, true) && ! is_dir($verzeichnis)) {
            $this->error("Konnte {$verzeichnis} nicht anlegen.");

            return self::FAILURE;
        }

        $groessen = array_map('intval', $this->option('size')) ?: [];
        $groessen = array_unique(array_merge([192, 512], $groessen));

        foreach ($groessen as $groesse) {
            $this->zeichne($verzeichnis."/icon-{$groesse}.png", $groesse, padding: 1);
            $this->line("  · icons/icon-{$groesse}.png");
        }

        // Maskable braucht mehr Rand, sonst schneidet Android die Ecken ab.
        $this->zeichne($verzeichnis.'/icon-512-maskable.png', 512, padding: 3);
        $this->line('  · icons/icon-512-maskable.png');

        // Favicon als kleines PNG – reicht für alle aktuellen Browser.
        $this->zeichne($verzeichnis.'/favicon-32.png', 32, padding: 0);
        $this->line('  · icons/favicon-32.png');

        $this->info('Icons erzeugt.');

        return self::SUCCESS;
    }

    /** @param  int  $padding  Randbreite in Rasterzellen */
    private function zeichne(string $pfad, int $groesse, int $padding): void
    {
        $raster = count(self::GRID);
        $zellenGesamt = $raster + 2 * $padding;
        $zelle = max(1, (int) floor($groesse / $zellenGesamt));
        $offset = (int) (($groesse - $zelle * $raster) / 2);

        $bild = imagecreatetruecolor($groesse, $groesse);
        imagealphablending($bild, true);

        $farben = [];

        foreach (self::COLORS as $schluessel => [$r, $g, $b]) {
            $farben[$schluessel] = imagecolorallocate($bild, $r, $g, $b);
        }

        imagefilledrectangle($bild, 0, 0, $groesse, $groesse, $farben['0']);

        foreach (self::GRID as $y => $zeile) {
            foreach (str_split($zeile) as $x => $schluessel) {
                if ($schluessel === '0') {
                    continue;
                }

                imagefilledrectangle(
                    $bild,
                    $offset + $x * $zelle,
                    $offset + $y * $zelle,
                    $offset + ($x + 1) * $zelle - 1,
                    $offset + ($y + 1) * $zelle - 1,
                    $farben[$schluessel],
                );
            }
        }

        imagepng($bild, $pfad, 9);
        imagedestroy($bild);
    }
}
