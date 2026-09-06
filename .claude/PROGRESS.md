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

### Nachtrag: MySQL steht, Vollimport ist komplett durch

- Alle Migrations laufen sauber gegen MySQL (`migrate:fresh`), Stammdaten geseedet.
- **1025 Arten, 1082 Formen** (57 Regionalformen), **18 Typen, 39 Spiele, 28 Orden**.
- **Fundorte importiert**, danach `pokedex:fill-gaps` und `pokedex:recalculate`.
- Der PokéAPI-Plattencache ist warm, ein erneuter Import ist deshalb deutlich schneller
  als die ~40 Minuten des ersten Laufs.

**Verteilung über den echten Dex** (Basisformen, ohne Spielebesitz):

| Stufe | Anzahl |
|---|---|
| 🟡 Kaufbar | 644 |
| 🔴 Bank-Deadline | 352 |
| ⚪ Tausch/Community | 15 |
| 🟠 Alte Hardware | 8 |
| 🟢 Einfach | 6 |

Mit Karmesin + Schwert + Sonne im Besitz: 692 einfach, 235 Bank-Deadline, 77 kaufbar.
Die Engine tut also genau das, was sie soll – die Zahl der dringenden Fälle hängt
direkt am eingetragenen Spielebesitz.

### Fehler, die erst der echte Datenbestand gezeigt hat

Der Vollimport hat sechs Fehler sichtbar gemacht, die kein Unit-Test gefunden hätte:

1. **Pikachu stand als „Elektro/Elektro" da, Mauzi als „Normal/Unlicht/Stahl".**
   Die Typen der Regionalformen hängen an derselben Pivot-Tabelle, und `Pokemon::types()`
   filterte `pokemon_form_id` nicht heraus.
2. **„Pikachu (Alola-Form)" wurde angelegt** – die gibt es gar nicht. Der Slug
   `pikachu-alola-cap` ist eine Mützen-Variante und rutschte über den Regionsvergleich
   herein; dasselbe galt für `darmanitan-galar-zen` (Trance-Modus).
3. **Der Typensammler-Orden** rechnete einem besessenen Mauzi auch Stahl und Unlicht an.
4. **14 von 151 Gen-1-Arten galten fälschlich als 🔴 bank-kritisch**, weil der Umweg über
   die Vorstufe nicht bzw. nur einen Schritt weit mitgerechnet wurde. Nach der Korrektur: 1.
5. **0 GO-Einträge nach dem Vollimport.** `CuratedObtainabilitySeeder` und
   `GoAvailabilitySeeder` hängen ihre Einträge an konkrete Pokémon. Die Reihenfolge im
   `DatabaseSeeder` legt nahe, sie vor dem Import laufen zu lassen – dann finden sie
   nichts und legen stillschweigend nichts an. Beide melden das jetzt.
6. **Die PokéAPI hat für Gen 9 fast keine Fundortdaten** (6 Einträge für über hundert
   Arten). Dadurch landete fast die komplette neunte Generation auf ⚪ statt auf 🟡.
   `pokedex:fill-gaps` schließt die Lücke mit klar gekennzeichneten Annahmen; die Zahl
   der Arten ohne jeden Weg fiel damit von 143 auf 15.

Alle sechs sind behoben und durch Regressionstests abgedeckt. Der Import räumt jetzt
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

### Performance

`PokedexQuery::evaluate()` bewertet den kompletten Dex (1025 Basisformen) in **9 Queries**
und rund **830 ms** gegen MySQL unter XAMPP, gemessen ohne Nebenlast. Der Löwenanteil ist
das Hydrieren der Modelle; die Prioritäts-Engine selbst braucht nur ~140 ms. Die Typen
werden bereits nur noch für die 60 angezeigten Karten nachgeladen.

Das ist brauchbar, aber nicht schnell. **Wenn es stört**, ist der nächste Schritt, die
Bewertung auf eine schlanke Query-Builder-Abfrage umzustellen (nur die Spalten, die die
Engine braucht) und die vollen Eloquent-Modelle erst für die aktuelle Seite zu laden.
Das ist ein spürbarer Umbau der Engine-Schnittstelle, deshalb bewusst nicht vorgezogen,
solange niemand über Ladezeiten klagt.

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

1. **Node installieren**, `npm install && npm run build` – danach ist die App im Browser
   benutzbar. Das ist der einzige verbleibende Blocker.
2. Dusk-Suite ausführen und ggf. Selektoren nachziehen
3. Eigenen Spielebesitz in den Einstellungen eintragen – erst dann sind die
   Dringlichkeitsstufen aussagekräftig
4. Spieleliste und GO-Daten gegen Bulbapedia/Serebii verifizieren; GO-Vollbestand per
   `pokedex:import-go` aus einer CSV nachladen
5. Echte Fundorte für Gen 8/9 per `pokedex:import-sources` nachliefern und danach
   `pokedex:fill-gaps --remove` ausführen
6. Repo nach GitHub pushen (vorher noch einmal auf Persönliches gegenprüfen, spec.md 10)

---

## 2026-09-06 — Projektstart
- Vollständige Projektspezifikation erstellt und mit dem Nutzer abgestimmt (siehe `spec.md`)
- Tech-Stack auf Laravel + MySQL + XAMPP (lokal) festgelegt
- Noch kein Code geschrieben
- Nächste Schritte: Laravel-Projekt lokal unter XAMPP aufsetzen, Repo-Grundgerüst committen,
  Datenimport-Command für die PokéAPI bauen (siehe spec.md Abschnitt 9, Phase 1: MVP)
