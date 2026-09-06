# Progress Log

Kurzer Stand je Session/Phase. Neuester Eintrag oben. Am Ende jeder Session aktualisieren.

## 2026-09-06 — Erste Umsetzungs-Session: Phasen 1–7 im Kern gebaut

### Was steht

**Grundgerüst**
- Laravel 12.69 unter XAMPP (PHP 8.2.12), Composer lokal nach `E:\xampp\php\composer.phar`
  installiert (Prüfsumme verifiziert), dazu ein `composer.bat`-Shim, weil Breeze `composer`
  im PATH erwartet
- Laravel Breeze (Blade-Stack), Pest als Test-Runner, Laravel Dusk für E2E
- `.env.example` auf MySQL/XAMPP umgestellt, `.gitignore` erweitert, Git-Repo initialisiert

**Datenmodell** (spec.md 3)
- Migrations für `types`, `pokemon`, `pokemon_forms`, `pokemon_type`, `games`,
  `obtainabilities`, `go_availabilities`, `user_settings`, `game_user`,
  `user_pokemon_forms`, `achievements` + `achievement_user`, `friendships`,
  XP-Spalten an `users`, `pokemon.source_pokemon_id`
- Domain-Enums statt String-Konstanten: `FormType`, `Region`, `Difficulty`,
  `ObtainMethod`, `GoMethod`, `GoRegion`, `PriorityLevel`, `Platform`
- Drei Abweichungen von der Schema-Skizze sind in `FEATURE-UPDATES.md` begründet

**Datenimport** (spec.md 4)
- `pokedex:import` — Arten, Formen, Typen, Entwicklungsketten aus der PokéAPI;
  erkennt Regionalformen am Varietätsnamen, überspringt Mega/Gigadynamax
- `pokedex:import-encounters` — Wildfang-Fundorte je Spiel
- `pokedex:recalculate` — leitet Schwierigkeit, Fangbarkeit und Beschaffungsweg ab
- `pokedex:import-sources` / `pokedex:import-go` — kuratierte Daten aus CSV
- `pokedex:icons` — PWA-Icons als Pixel-Art aus Code
- `pokedex:demo-user` — lokaler Testnutzer mit generiertem Passwort
- `PokeApiClient` mit Plattencache und Retries

**Prioritäts-Engine** (spec.md 2.7, 2.8) — das Kernstück, am dichtesten getestet
- Alle sechs Dringlichkeitsstufen, GO entschärft die Bank-Deadline
- Mehrfach-Fang-Empfehlung, Schwierigkeitsgrad, Freitext-Massen-Parser
- Fortschritts-Service mit den drei getrennten Balken und den Zähl-Toggles

**Anwendung** (spec.md 2.1–2.10, 5)
- Pokédex-Raster mit allen Filtern, Detailseite, Dashboard mit Bank-Countdown,
  Masseneingabe mit Vorschau, Einstellungen, Statistik, Trainerkarte, Bestenliste
- Retro-Theme mit vier Farbpaletten über CSS-Variablen, 8-Bit-Sounds per Web Audio API
  (keine fremden Audio-Assets im Repo), PWA-Manifest und Service Worker

**Qualität** (spec.md 7)
- 136 Tests grün (Unit + Feature), Pint sauber
- Dusk-Tests für den Kernflow geschrieben — **noch nicht ausgeführt**, siehe unten
- GitHub Actions: Pest, Pint, Vite-Build, Dusk gegen MySQL

### Nachtrag: MySQL steht, Vollimport ist durch

- Alle Migrations laufen sauber gegen MySQL (`migrate:fresh`), Stammdaten geseedet.
- **1025 Arten und 1082 Formen importiert**, davon 57 Regionalformen. Der Fundort-Import
  lief zum Zeitpunkt dieses Eintrags noch.
- Der PokéAPI-Plattencache ist warm (~2.800 Dateien), ein erneuter Import ist deshalb
  deutlich schneller als die ~40 Minuten des ersten Laufs.

### Fehler, die erst der echte Datenbestand gezeigt hat

Der Vollimport hat vier Fehler sichtbar gemacht, die kein Unit-Test gefunden hätte:

1. **Pikachu stand als „Elektro/Elektro" da, Mauzi als „Normal/Unlicht/Stahl".**
   Die Typen der Regionalformen hängen an derselben Pivot-Tabelle, und `Pokemon::types()`
   filterte `pokemon_form_id` nicht heraus.
2. **„Pikachu (Alola-Form)" wurde angelegt** – die gibt es gar nicht. Der Slug
   `pikachu-alola-cap` ist eine Mützen-Variante und rutschte über den Regionsvergleich
   herein; dasselbe galt für `darmanitan-galar-zen` (Trance-Modus).
3. **Der Typensammler-Orden** rechnete einem besessenen Mauzi auch Stahl und Unlicht an.
4. **14 von 151 Gen-1-Arten galten fälschlich als 🔴 bank-kritisch**, weil der Umweg über
   die Vorstufe nicht bzw. nur einen Schritt weit mitgerechnet wurde. Nach der Korrektur: 1.

Alle vier sind behoben und durch Regressionstests abgedeckt. Der Import räumt jetzt
außerdem auf: eine einmal falsch angelegte Form verschwindet beim nächsten Lauf.

### Zwei Fehleinstufungen, die ein Testimport aufgedeckt hat

Der Import der ersten 151 Arten hat gezeigt, dass die Engine anfangs 14 Arten fälschlich
als 🔴 Bank-kritisch meldete:

1. **Bisaknosp, Turtok & Co.** — ihr einziger *eigener* Wildfang liegt in einem 3DS-Titel,
   aber man bekommt sie, indem man die Vorstufe aus einem noch käuflichen Spiel entwickelt.
   Die Engine zog die Vorstufe bisher nur heran, wenn die Stufe gar keine eigene Quelle
   hatte. Jetzt werden beide Wege bewertet und der günstigere gewinnt. Danach: 1 statt 14.
2. **Mew** galt als „leicht", weil die PokéAPI für Smaragd einen Wildfang kennt, der in
   Wahrheit an ein abgelaufenes Ticket-Event gebunden war. Die Schwierigkeit darf jetzt
   nie unter den Wert am Pokémon selbst fallen.

### Blockiert — braucht eine Installation auf dem Rechner

- **Node.js fehlt.** Damit kein `npm install && npm run build`, also kein Vite-Manifest.
  Die Views sind vollständig geschrieben und rendern in den Tests (dort mit `withoutVite()`),
  aber im Browser fehlt bislang das CSS/JS-Bundle. **Nächster Schritt:** Node LTS
  installieren, dann `npm install && npm run build`.
- **Dusk** braucht das Asset-Bundle plus einen laufenden Server und ist deshalb noch
  ungetestet. Die Testdatei steht, die Selektoren (`dusk="..."`) sind gesetzt.

### Performance — noch nicht belastbar gemessen

`PokedexQuery::evaluate()` bewertet den kompletten Dex in 10–11 Queries. Erste Messungen
gegen MySQL schwanken zwischen 500 ms und 3,8 s, allerdings lief dabei der Fundort-Import
parallel und blockierte die Datenbank. Die Aufteilung war: Formen laden ~200 ms (warm),
Bezugsquellen laden ~260 ms, Engine selbst nur ~140 ms für 1025 Arten.

**Offen:** eine saubere Messung ohne Nebenlast. Falls es dann zu langsam bleibt, ist der
naheliegende Schritt, die Bewertung auf eine schlanke Query-Builder-Abfrage umzustellen
und die vollen Eloquent-Modelle nur für die 60 Einträge der aktuellen Seite zu laden.

### Offene inhaltliche Punkte

- **Vollimport steht aus.** Bisher sind nur 151 Arten importiert (Testlauf, ~5,5 Minuten).
  Ein Vollimport dauert hochgerechnet rund 40 Minuten und sollte einmal am Stück laufen.
- **Spieleliste gegenprüfen** (spec.md 4): `GameSeeder` ist kuratiert und sollte gegen
  Bulbapedia/Serebii verifiziert werden — besonders das Flag `still_purchasable`.
- **Feuerrot/Blattgrün für Switch** ist in spec.md 1 erwähnt (HOME-Anbindung ab Oktober 2026),
  fehlt aber im `GameSeeder`, weil der genaue Titel hier nicht belastbar bekannt ist.
  Sobald der Name feststeht, als Eintrag mit `home_compatible = true` ergänzen — das
  entschärft vermutlich einige Gen-1/Gen-3-Fälle.
- **GO-Datensatz ist ein Kern, kein Vollbestand.** `GoAvailabilitySeeder` enthält die gut
  dokumentierten Regionalexklusiven. Fehlt für ein Pokémon der GO-Eintrag, rechnet die
  Engine bewusst konservativ ohne GO-Rettungsweg — bei einer Deadline lieber eine Warnung
  zu viel. Ergänzungen per `pokedex:import-go` aus einer gepflegten CSV.
- **Fundorte sind englisch**, solange `pokedex:import-encounters` ohne
  `--translate-locations` läuft. Die Option kostet deutlich mehr Requests.
- **Kein Push nach GitHub** — bislang nur lokale Commits (so abgestimmt).

### Nächste Schritte

1. Node installieren, `npm install && npm run build`, App im Browser durchklicken
2. `pokedex:recalculate` nach Abschluss des Fundort-Imports laufen lassen
3. Performance ohne Nebenlast messen (siehe oben)
4. Dusk-Suite ausführen und ggf. Selektoren nachziehen
5. Spieleliste und GO-Daten gegen Bulbapedia/Serebii verifizieren
6. Repo nach GitHub pushen (vorher noch einmal auf Persönliches gegenprüfen, spec.md 10)

---

## 2026-09-06 — Projektstart
- Vollständige Projektspezifikation erstellt und mit dem Nutzer abgestimmt (siehe `spec.md`)
- Tech-Stack auf Laravel + MySQL + XAMPP (lokal) festgelegt
- Noch kein Code geschrieben
- Nächste Schritte: Laravel-Projekt lokal unter XAMPP aufsetzen, Repo-Grundgerüst committen,
  Datenimport-Command für die PokéAPI bauen (siehe spec.md Abschnitt 9, Phase 1: MVP)
