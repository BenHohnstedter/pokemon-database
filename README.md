# Dex-Rescue — Pokémon-Sammlungs-Tracker

Web-App, die trackt, welche Pokémon in **Pokémon HOME** schon im Bestand sind und wie die
fehlenden noch zu bekommen sind — mit einer Priorisierung, die die Abschaltung von
**Pokémon Bank am 26./27. Februar 2027** berücksichtigt.

Der Kernnutzen: *Zeig mir auf einen Blick, was ich besitze, was mir fehlt, und — ganz wichtig —
was davon eine tickende Uhr hat.*

> Privates, nicht-kommerzielles Fan-Projekt. Pokémon-Namen, -Sprites und -Artworks gehören
> Nintendo / Game Freak / The Pokémon Company. Daten über die [PokéAPI](https://pokeapi.co/),
> ergänzt um Angaben aus [Bulbapedia](https://bulbapedia.bulbagarden.net/) (CC BY-NC-SA).

---

## Was die App kann

| Bereich | Kurz |
|---|---|
| **Pokédex** | Alle Arten mit deutschen Namen, Typen, Artwork, Shiny-Sprite und Entwicklungskette |
| **Prioritäts-Engine** | Sechs Dringlichkeitsstufen von 🟢 *einfach* bis 🔴 *Bank-Deadline*, berechnet aus Spielebesitz, GO-Region und den echten Transferwegen nach HOME |
| **Bezugsquellen** | Pro Pokémon: welches Spiel, welche Methode, welche Route, welcher Weg nach HOME |
| **Mehrfach-Fang** | „Fange 3× Bisasam: 1× so lassen, 1× zu Bisaknosp entwickeln …" — für Stufen, die nur durch Entwicklung erreichbar sind |
| **Formen** | Regionalformen und Shiny als eigene Fortschrittsbalken, per Einstellung in den Hauptbalken einrechenbar |
| **Pokémon GO** | Regionalexklusivität gegen die eigene Weltregion abgeglichen; ein GO-Weg entschärft die Bank-Deadline |
| **Masseneingabe** | `1-50,60-63,700` als Freitext, mit Vorschau vor dem Übernehmen |
| **Gamification** | Trainer-Level, Orden, Trainerkarte im GameBoy-Stil, 8-Bit-Sounds, optionaler Chiptune-Loop |
| **Statistik & Social** | Fortschritt nach Typ/Generation, Zuwachs pro Monat, Bestenliste mit Freundesliste |
| **PWA** | Auf dem Handy als App installierbar |

Die vollständige Spezifikation steht in [`.claude/SPEC.md`](.claude/SPEC.md), der Arbeitsstand in
[`.claude/PROGRESS.md`](.claude/PROGRESS.md).

---

## Setup unter XAMPP

Vorausgesetzt sind **PHP ≥ 8.2**, **MySQL/MariaDB**, **Composer** und **Node.js ≥ 18**.
Unter XAMPP liegt PHP in `E:\xampp\php` — dieser Ordner sollte im `PATH` stehen.

```bash
git clone https://github.com/BenHohnstedter/pokemon-database.git
cd pokemon-database
composer install
cp .env.example .env
php artisan key:generate
```

Datenbank anlegen (z.B. in phpMyAdmin oder auf der Konsole):

```sql
CREATE DATABASE pokemon_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Zugangsdaten in der `.env` eintragen (unter XAMPP meist `root` ohne Passwort), dann:

```bash
php artisan migrate
php artisan db:seed
npm install
npm run build
```

### Daten importieren

Der Import läuft in drei Schritten und ist **wiederholbar** — ein zweiter Lauf aktualisiert
vorhandene Datensätze, statt Duplikate anzulegen. Antworten der PokéAPI werden auf Platte
gecacht, ein erneuter Lauf ist deshalb deutlich schneller.

```bash
php artisan pokedex:import
```

```bash
php artisan pokedex:import-encounters
```

```bash
php artisan pokedex:recalculate
```

Danach **noch einmal seeden**:

```bash
php artisan db:seed
```

Und die Fundort-Lücken der PokéAPI schließen:

```bash
php artisan pokedex:fill-gaps && php artisan pokedex:recalculate
```

`location-area-encounters` ist für die neueren Titel praktisch leer — für
Karmesin/Purpur liefert die API sechs Einträge für über hundert Arten. Ohne
diesen Schritt landet fast die komplette neunte Generation auf ⚪ *nur noch per
Tausch*, obwohl sie im aktuell erhältlichen Spiel schlicht fangbar ist. Der
Befehl trägt für Arten **ohne jeden** Beschaffungsweg einen Wildfang im
Hauptspiel ihrer Generation nach, klar als Annahme gekennzeichnet
(`source = generation-fallback`, Fundort „noch nicht hinterlegt"). Mit
`--dry-run` erst ansehen, mit `--remove` wieder entfernen, sobald echte Daten
per `pokedex:import-sources` vorliegen.

Das ist kein Versehen: `CuratedObtainabilitySeeder` und `GoAvailabilitySeeder`
hängen ihre Einträge an konkrete Pokémon. Laufen sie vor dem Import, finden sie
nichts und legen nichts an – Starter, Fossilien und die GO-Regionalexklusiven
fehlten dann. Beide Seeder sagen in dem Fall Bescheid und sind wiederholbar.

Für einen schnellen Testlauf reicht ein Ausschnitt:

```bash
php artisan pokedex:import --to=151 && php artisan pokedex:import-encounters --to=151 && php artisan pokedex:recalculate
```

Nützliche Optionen:

| Befehl | Wozu |
|---|---|
| `pokedex:import --from= --to= --limit=` | Nur einen Dex-Ausschnitt laden |
| `pokedex:import --include-other-forms` | Auch Sonderformen jenseits der Regionalformen anlegen |
| `pokedex:import --fresh-cache` | Plattencache leeren und alles neu abrufen |
| `pokedex:import-encounters --translate-locations` | Deutsche Ortsnamen mitladen (deutlich mehr Requests) |
| `pokedex:fill-gaps --dry-run` | Zeigen, für welche Arten die PokéAPI keinen Fundort kennt |
| `pokedex:fill-gaps --remove` | Die angenommenen Einträge wieder entfernen |
| `pokedex:import-sources datei.csv` | Kuratierte Bezugsquellen aus CSV nachladen |
| `pokedex:import-go datei.csv` | GO-Verfügbarkeit und Regionalexklusive aus CSV nachladen |
| `pokedex:icons` | PWA-Icons neu erzeugen |
| `pokedex:demo-user --besitz=200` | Lokalen Testnutzer mit generiertem Passwort anlegen |

### Starten

```bash
php artisan serve
```

Alternativ per XAMPP-VHost auf `public/` zeigen. Läuft die App in einem Unterordner
(`http://localhost/pokemon-database/public`), gehört genau diese Adresse in `APP_URL`.

---

## Tests

```bash
php artisan test
```

Die Unit- und Feature-Tests laufen gegen SQLite in-memory und brauchen weder MySQL noch einen
Asset-Build.

Die Browser-Tests (Dusk) brauchen beides plus einen laufenden Server:

```bash
cp .env.dusk.local.example .env.dusk.local
```

```bash
npm run build && php artisan serve
```

```bash
php artisan dusk
```

> Dusk **leert** die in `.env.dusk.local` konfigurierte Datenbank bei jedem Lauf — dort niemals
> die Entwicklungsdatenbank eintragen.

---

## Aufbau

```
app/
├── Console/Commands/   Import- und Wartungsbefehle
├── Enums/              Dringlichkeit, Schwierigkeit, Methoden, Regionen, Plattformen
├── Http/Controllers/   Pokédex, Sammlung, Dashboard, Einstellungen, Statistik, Trainerkarte
├── Listeners/          Login-Streak
├── Models/             Eloquent-Modelle
├── Services/           Prioritäts-Engine, Fortschritt, Mehrfach-Fang, Import, Achievements
└── Support/            Wertobjekte (PriorityResult, ProgressBar, Filter …)

database/
├── migrations/         Schema
├── factories/          Test-Factories mit sprechenden States
└── seeders/            Kuratierte Stammdaten (Spiele, Typen, Orden, GO-Regionalexklusive)

resources/
├── css/app.css         Retro-Theme mit vier Farbpaletten über CSS-Variablen
├── js/                 Alpine-Komponenten und 8-Bit-Audio per Web Audio API
└── views/              Blade-Templates
```

### Wie die Dringlichkeitsstufe entsteht

Die Logik steckt in [`app/Services/PriorityEngine.php`](app/Services/PriorityEngine.php) und ist
der am dichtesten getestete Teil der App. Entscheidend sind drei Flags am Spiel:

- `home_compatible` — hängt direkt an Pokémon HOME
- `bank_only` — der einzige Weg nach HOME führt über Pokémon Bank
- `still_purchasable` — noch regulär im Handel erhältlich

Daraus ergibt sich:

| Stufe | Bedingung |
|---|---|
| ✅ Besessen | im Bestand |
| 🟢 Einfach | in einem besessenen Spiel, oder in GO in der eigenen Region farmbar |
| 🟡 Kaufbar | nur in einem nicht besessenen Spiel, das noch im Handel ist |
| 🟠 Alte Hardware | altes Spiel nötig, aber es gibt einen Weg ohne Bank (z.B. über GO) |
| 🔴 Bank-Deadline | der einzige Weg nach HOME führt über Pokémon Bank |
| ⚪ Tausch/Community | Event vorbei oder kein regulärer Fangweg mehr |

Fehlen für ein Pokémon die GO-Daten, rechnet die Engine **bewusst konservativ** ohne
GO-Rettungsweg — bei einer Deadline ist eine Warnung zu viel besser als eine zu wenig.

---

## Datenstand und offene Punkte

- Die Spieleliste in `GameSeeder` und die GO-Regionalexklusiven in `GoAvailabilitySeeder` sind
  kuratiert und sollten gegen Bulbapedia/Serebii gegengeprüft werden — besonders das Flag
  `still_purchasable` und Titel, die nach dem Projektstart erschienen sind.
- Der GO-Datensatz ist bewusst ein belastbarer Kern, kein Vollbestand; Ergänzungen gehören per
  `pokedex:import-go` aus einer gepflegten CSV nachgeladen.

---

## Lizenz und Nutzung

Privates Fan-Projekt ohne kommerzielle Absicht. Nicht als offizielles Produkt ausgeben,
nicht verkaufen.
