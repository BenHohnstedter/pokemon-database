# Herkunft der Icons

Das Pokéball-Symbol stammt aus Wikimedia Commons und ersetzt seit dem
07.09.2026 die aus Code gezeichnete Pixel-Variante.

| | |
|---|---|
| Datei | [Poké Ball icon.svg](https://commons.wikimedia.org/wiki/File:Pok%C3%A9_Ball_icon.svg) |
| Autor | [Andreuvv](https://commons.wikimedia.org/wiki/User:Andreuvv) |
| Lizenz | Die Dateiseite führt „Public domain" (einfache geometrische Form, keine Schöpfungshöhe) und zusätzlich CC BY-SA 4.0. Genannt wird der Autor hier unabhängig davon — das erfüllt beide Lesarten. |
| Marke | Der Pokéball ist eine eingetragene Marke von Nintendo / Game Freak / The Pokémon Company. Die Verwendung hier erfolgt in einem privaten, nicht-kommerziellen Fan-Projekt. |

`pokeball.svg` ist die unveränderte Originaldatei. Die PNGs sind daraus im
Browser gerendert:

- `favicon-32.png`, `icon-192.png`, `icon-512.png` — maßstabsgetreu eingepasst
- `icon-512-maskable.png` — Motiv auf 78 % verkleinert auf dunklem Grund,
  damit Android beim Zuschneiden nichts abschneidet

**Achtung:** `php artisan pokedex:icons` überschreibt `favicon-32.png` und die
drei `icon-*.png` wieder mit der selbst gezeichneten Pixel-Variante. Der Befehl
ist nicht mehr die Quelle des App-Icons; wer ihn laufen lässt, sieht es im
`git status` und sollte die Änderung verwerfen.
