# Projektspezifikation: Pokémon-Sammlungs-Tracker ("Dex-Rescue" – Arbeitstitel)

> Vollständige Referenz-Spezifikation. Wird **nicht** automatisch bei jeder Claude-Code-Session
> geladen – das übernimmt das schlanke `.claude/CLAUDE.md`. Diese Datei hier lesen, bevor an einem
> größeren Feature gearbeitet wird oder wenn unklar ist, wie etwas genau gemeint ist.

**Repository:** https://github.com/BenHohnstedter/pokemon-database (öffentlich, aktuell leer – das
gesamte Projekt soll dorthin committet werden, siehe Abschnitt 10).

---

## 1. Ziel & Hintergrund

Der Nutzer möchte in **Pokémon HOME** irgendwann jedes existierende Pokémon einmal besessen haben.
Viele ältere Pokémon sind nur über alte Konsolen (GBA/DS/3DS) und den Umweg über **Pokémon Bank**
nach HOME transferierbar. **Pokémon Bank wird am 26./27. Februar 2027 endgültig abgeschaltet**
(Quellen variieren leicht je nach Zeitzone – offiziell bestätigt von The Pokémon Company/Nintendo,
Sommer 2026 angekündigt). Danach ist dieser Transferweg für immer geschlossen. Zusätzlich werden
ab Oktober 2026 Feuerrot/Blattgrün (Switch-Version) an HOME angebunden.

Die App soll deshalb nicht nur ein Pokédex-Katalog sein, sondern aktiv **priorisieren**, welche
Pokémon der Nutzer *jetzt noch dringend* über alte Hardware/Pokémon Bank retten muss, bevor das
nicht mehr geht – im Gegensatz zu Pokémon, die er jederzeit später (z.B. über aktuelle Switch-Spiele
oder Pokémon GO) nachholen kann.

**Kernnutzen der App:** "Zeig mir auf einen Blick, was ich besitze, was mir fehlt, und – ganz wichtig –
was davon eine tickende Uhr hat."

---

## 2. Kernfunktionen

### 2.1 Pokédex-Datenbank (Basisdaten)
Für **jedes** Pokémon (alle Generationen bis zum aktuellen Stand):
- Nationale Dex-Nummer, Name (mehrsprachig, mind. Deutsch/Englisch)
- Offizielles Artwork + In-Game-Sprite + Shiny-Sprite
- Typ(en)
- Basiswerte optional (nice-to-have, kein Muss)
- Entwicklungskette: wovon/wodurch entwickelt es sich, wozu entwickelt es sich (Level, Stein,
  Freundschaft, Tausch, Ortsbindung etc.), inkl. Sonderfälle (Tageszeit, Geschlecht, Item beim Tausch)

### 2.2 Formen & Varianten – Basis, Regional, Shiny (dein wichtigster Punkt)
- **Hauptzählung / großer Fortschrittsbalken** = klassischer nationaler Dex (Basisformen), z.B.
  "587 / 1.302 Pokémon gesammelt (45 %)".
- **Regionalformen** (Alola, Galar, Hisui, Paldea, Paldea-Konvergenzen etc.) und **Shiny-Formen**
  laufen als **eigene, separate Fortschrittsanzeigen** ("Regionalformen: 34 %", "Shiny: 2 %") und
  zählen **standardmäßig nicht** in den Hauptbalken hinein.
- In den **Einstellungen** kann der Nutzer pro Kategorie umschalten: "Regionalformen in
  Gesamtfortschritt einrechnen: an/aus", genauso für Shiny. So bleibt es flexibel.
- **Mega-Entwicklung / Gigadynamax werden bewusst NICHT getrackt** – diese Formen sind ohnehin nur
  temporäre Kampfzustände und lassen sich nicht dauerhaft in Pokémon HOME speichern.

### 2.3 Bezugsquellen je Pokémon (das Herzstück der Datenbank)
Pro Pokémon (und ggf. pro Form) eine Liste von Fundmöglichkeiten, jeweils mit:
- **Spiel(e)**, in denen es vorkommt (z.B. "Pokémon Schwert", "Pokémon Feuerrot")
- **Methode**: wilder Fang (mit Route/Gebiet/Höhle etc.), Geschenk, Tausch, Ei/Zucht, Entwicklung,
  Event (zeitlich begrenzt), Raid, Honigbaum o.ä.
- **Ort im Detail**: konkrete Route/Gebiet, nicht nur "im Spiel vorhanden"
- Falls ausschließlich per Transfer aus älteren Spielen: klar kennzeichnen ("nur via Pokémon Bank
  aus Gen-X-Spielen transferierbar")

### 2.4 Pokémon-GO-Integration
Pokémon GO ist eine eigene, parallele Bezugsquelle mit eigenen Regeln:
- Pro Pokémon: ist es in GO grundsätzlich fangbar (wild, Ei, Raid, Community Day, nur durch
  Entwicklung)?
- **Regionale Exklusivität**: einige Pokémon spawnen in GO nur in bestimmten Weltregionen (z.B.
  Mr. Mime in Europa, Farfetch'd in Ost-/Südasien, Kangaskhan in Australien, verschiedene
  Formen von Tauros/Basculin/Corsola/Shellos etc.).
- **Nutzereinstellung "GO-Region/Land"**: Der Nutzer trägt sein Land ein, die App zeigt dann an,
  ob ein Pokémon dort spawnt, oder ob Reisen/Tausch nötig wäre.
- **Wichtige Verknüpfung zur Prioritäts-Logik (siehe 2.7)**: Ein Pokémon, das über GO gefangen und
  jederzeit per GO-Transporter nach HOME gebracht werden kann, hat **keine** Bank-Deadline – auch
  wenn es ursprünglich nur auf einer alten Konsole vorkam. Das entschärft viele "alte" Pokémon.

### 2.5 Nutzer-Sammlungsstatus
- Ein Klick pro Pokémon/Form: "besitze ich" ↔ "besitze ich nicht" (Basisform, Regionalform, Shiny
  jeweils getrennt anklickbar, aber Regional/Shiny nur relevant wenn in den Einstellungen aktiviert)
- **Freitext-Masseneingabe**: Eingabefeld, in das kommagetrennte Dex-Nummern und/oder Bereiche
  eingetippt werden können, z.B. `1,15,700` oder `1-50,60-63`, und die App markiert alle genannten
  Nummern in einem Rutsch als besessen. Parsing-Logik: an Kommas splitten, Einträge mit `-` als
  Bereich (von–bis) interpretieren, einzelne Zahlen direkt übernehmen, ungültige Einträge (außerhalb
  des Dex-Bereichs, falsches Format) abfangen. Vor dem Übernehmen eine Vorschau zeigen ("Das markiert
  54 Pokémon als besessen – bestätigen?"). Sollte auch umgekehrt für Massenkorrekturen funktionieren
  ("als nicht besessen markieren").
- **Wunschliste/Favoriten**: pro Pokémon zusätzlich ein Favoriten-Stern setzbar, unabhängig vom
  Besitzstatus – eigene gefilterte Ansicht "Meine Wunschliste", z.B. um sich das nächste Fangziel
  selbst zu priorisieren (ergänzt, ersetzt aber nicht die automatische Prioritäts-Engine aus 2.7)
- Großes Dashboard mit Gesamtfortschritt (siehe 2.2) + Generations-/Regions-Unterfortschritt

### 2.6 Nutzer-Einstellungen
- **Spielebesitz**: Checkliste aller Mainline-Spiele (siehe Anhang A), die der Nutzer besitzt
  (z.B. Pokémon Mond ✅, Pokémon Schwert ✅, Pokémon Feuerrot ❌ …)
- **GO-Region/Land**
- **Zähl-Toggles** für Regionalformen/Shiny im Hauptfortschritt (siehe 2.2)
- Optional: Konsolenbesitz explizit abfragen (3DS vorhanden? Switch vorhanden?), falls das aus dem
  Spielebesitz nicht eindeutig ableitbar ist

### 2.7 Beschaffungs-Sortierung / Prioritäts-Engine (zentrales Alleinstellungsmerkmal)
Für jedes fehlende Pokémon berechnet die App eine Dringlichkeitsstufe. Vorschlag für die Logik:

| Stufe | Bedingung | Bedeutung |
|---|---|---|
| ✅ Besessen | bereits im Bestand | – |
| 🟢 Einfach | fangbar in einem Spiel, das der Nutzer besitzt, ODER wild in GO in der eigenen Region | Kein Handlungsdruck |
| 🟡 Kaufbar | nur in einem Spiel erhältlich, das der Nutzer nicht besitzt, aber das Spiel ist noch regulär im Handel/eShop erhältlich | Spiel kaufen reicht |
| 🟠 Alte Hardware nötig | nur in einem älteren Spiel (GBA/DS/3DS-Ära) erhältlich, nicht per GO ersetzbar, aber (noch) über Bank transferierbar | Handeln, aber (noch) kein Weltuntergang |
| 🔴 Dringend – Bank-Deadline | wie orange, UND der einzige Weg nach HOME führt über Pokémon Bank (Transfer aus Gen ≤5/älteren 3DS-Spielen) | **Vor dem 26./27.02.2027 erledigen!** |
| ⚪ Nur noch per Tausch/Community | ursprünglich Event-exklusiv, Event ist vorbei, kein regulärer Fangweg mehr | Muss über Tauschbörsen/Community gelöst werden |

Die App sollte eine gefilterte/sortierbare Ansicht bieten: "Zeig mir alle 🔴 Dringend"-Pokémon
zuerst, inkl. genauer Anleitung, welches Spiel/welche Konsole dafür nötig ist.

Ein **Countdown-Widget** auf dem Dashboard ("Noch X Tage bis Pokémon Bank abgeschaltet wird –
Y Pokémon sind noch betroffen") macht die Dringlichkeit sichtbar.

### 2.8 Schwierigkeitsgrad & Mehrfach-Fang-Empfehlung

**Schwierigkeitsgrad** (zusätzlich zur Dringlichkeits-Stufe aus 2.7): Neben der Frage "brauche ich
noch ein Spiel/eine alte Konsole" soll die App kennzeichnen, wie schwer ein Pokémon grundsätzlich zu
bekommen ist – unabhängig vom eigenen Spielebesitz:

| Schwierigkeit | Kriterium |
|---|---|
| Leicht | normaler Wildfang oder einfaches Geschenk in mind. einem Spiel |
| Mittel | Tausch, Zucht mit Bedingung, oder auf bestimmte Route/Zeitfenster beschränkt |
| Schwer | nur per Tausch mit speziellem Item, nur per Entwicklung einer schwer erreichbaren Vorstufe, oder an ein laufendes/wiederkehrendes Event gebunden |
| Sehr schwer / kaum noch möglich | Event ist bereits vorbei und kommt nicht wieder, oder an eine mittlerweile abgeschaltete Funktion (z.B. alte Verbindungsfunktionen) gebunden |

Eigener Filter: "Zeig mir alle Pokémon, die ich mit meinem aktuellen Spielebesitz überhaupt nicht
bekommen kann" – kombiniert Spielebesitz-Check (2.7) und Schwierigkeitsgrad in einer Ansicht, auch
sortierbar nach Schwierigkeit statt nur nach Dringlichkeit.

**Mehrfach-Fang-Empfehlung für Entwicklungsreihen**: Viele mittlere/letzte Entwicklungsstufen sind
NICHT wild fangbar, sondern nur durch Entwickeln der Vorstufe erreichbar. Da in Pokémon HOME jede
Entwicklungsstufe als eigener Pokédex-Eintrag zählt, soll die App automatisch berechnen, wie viele
Exemplare der frühesten wild fangbaren Vorstufe nötig sind, um die komplette Reihe zu
vervollständigen, und das konkret vorschlagen, z.B.:

> "Fange 3× Bisasam: 1× so lassen, 1× zu Bisaknosp entwickeln (und stoppen), 1× bis Bisaflor
> weiterentwickeln – so hast Du alle drei Entwicklungsstufen in Deiner Sammlung."

Datengrundlage: pro Pokémon-Art vermerken, ob es einen direkten Fund-Weg (Wildfang/Geschenk) gibt
oder ausschließlich per Entwicklung erreichbar ist (siehe `obtainable_directly` in Abschnitt 3).
Daraus lässt sich pro Entwicklungslinie die nötige Fanganzahl der Basisform berechnen.

### 2.9 Gamification
Der Retro-Look soll durch spielerische Elemente unterstützt werden, ohne kitschig zu wirken:
- **Trainer-Level/XP**: Punkte pro gefangenem Pokémon, Bonus-XP für seltene/schwer erreichbare
- **Achievements/Badges**: z.B. "Kanto-Dex komplett", "Alle Typen mindestens 1×", "Shiny-Hunter I–V",
  "Region X zu 100 % abgeschlossen"
- **Trainer-Karte** im GameBoy-Stil als Profilseite (Level, Badges, Fortschrittsringe)
- Kurze Retro-Soundeffekte (8-Bit-"Fang-Jingle") bei neuem Eintrag, dezente Pixel-Konfetti-Animation
  bei Meilensteinen
- **Optionale Chiptune-Hintergrundmusik**: dezenter 8-Bit-Loop im Hintergrund, klar sichtbarer
  An/Aus-Schalter (Standard: aus, damit es niemanden überrumpelt), Lautstärkeregler
- Optional: Tages-Login-Streak

### 2.10 Statistik & Vergleich mit anderen Nutzern
- **Statistik-Seite**: Diagramme zur eigenen Sammlung, z.B. Fortschritt nach Typ, nach Generation/
  Region, Verhältnis Basis/Regional/Shiny, zeitlicher Verlauf (wie viele Pokémon pro Monat ergänzt)
- **Freunde-Vergleich/Bestenliste**: Rangliste unter den registrierten Nutzern (z.B. nach
  Gesamtfortschritt oder Trainer-Level), optional einschränkbar auf eine eigene Freundesliste statt
  aller Nutzer

### 2.11 Multi-User / Auth
- Registrierung, Login, Logout, Passwort-Reset
- Jeder Nutzer hat eigenen Sammlungsstand, eigene Einstellungen
- (Optional, nice-to-have: öffentliches Profil zum Teilen des Fortschritts)

---

## 3. Datenmodell (grobe Entitäten)

```
Pokemon          (dex_nr, name_de, name_en, types[], base_form_of?, evolution_info,
                  difficulty: leicht|mittel|schwer|sehr_schwer, obtainable_directly: bool)
PokemonForm      (pokemon_id, form_type: base|regional|other, region, sprite_url, shiny_sprite_url)
Game             (name, generation, platform, release_year, home_compatible: bool, bank_only: bool,
                  still_purchasable: bool)
Obtainability    (pokemon_id, form_id?, game_id, method: wild|gift|trade|egg|evolution|event,
                  location_detail: text)
GoAvailability   (pokemon_id, method: wild|egg|raid|community_day|evolution_only,
                  regions[]: Europa|Nordamerika|...|weltweit)
User             (id, email, password_hash, created_at)
UserSettings     (user_id, go_region, count_regional_in_total: bool, count_shiny_in_total: bool)
UserGameOwned    (user_id, game_id, owned: bool)
UserOwnership    (user_id, pokemon_id, form_type: base|regional|shiny, owned: bool)
```

---

## 4. Datenquellen & Beschaffungsstrategie

- **Primärquelle (empfohlen): [PokéAPI](https://pokeapi.co/)** – freie, strukturierte, gut
  dokumentierte REST-API. Liefert Namen, Typen, Sprites (inkl. offizielle Artworks & Shiny),
  Entwicklungsketten und für viele Spiele bereits Fundort-Daten (`location-area-encounters`).
  Deutlich zuverlässiger und einfacher zu verarbeiten als das Scrapen von HTML-Seiten.
- **Ergänzend: [Bulbapedia](https://bulbapedia.bulbagarden.net/)** (die von Dir erwähnte
  "Pokémon-Wiki") für alles, was PokéAPI nicht abdeckt: Geschenk-Pokémon, Event-Exklusivität,
  neuere Spiele (Schwert/Schild, Karmesin/Purpur, Legenden: Arceus), sowie eine eigene Übersichts-
  seite "List of regional Pokémon" für die GO-Regionsdaten. Bulbapedia-Inhalte stehen unter
  CC BY-NC-SA – für ein privates, nicht-kommerzielles Projekt mit Attribution unproblematisch.
- **Wichtig für die Umsetzung:** Die Daten sollten per **einmaligem Import-Befehl** (z.B. einem
  Laravel-Artisan-Command mit Guzzle als HTTP-Client) in die eigene MySQL-Datenbank geladen werden
  (nicht live bei jedem Seitenaufruf scrapen). Der Befehl sollte wiederholt ausführbar sein, damit
  neue Spiele/Generationen später ergänzt werden können.
- Die genaue, aktuell gültige Liste aller Mainline-Spiele sowie ganz neue Titel (z.B. kürzlich
  erschienene Ableger) sollte beim Daten-Import gegen Bulbapedia/Serebii verifiziert werden, da sich
  das Line-up seit meinem Wissensstand weiterentwickelt haben kann.

---

## 5. Design & UX: "Retro, aber High-End"

- **Bildsprache**: 8-Bit/16-Bit-Pixel-Art-Elemente (Rahmen, Icons, Button-Stil), Pixel-Font für
  Überschriften (z.B. "Press Start 2P" oder ähnliche freie Google-Font), aber **klare, moderne
  Informationsarchitektur** darunter (Grid-Layout, Suchleiste, Filter-Chips, responsive für
  Mobile & Desktop) – kein "billig wirkendes" Retro, sondern bewusst gestaltetes Retro-Design.
- Farbpalette angelehnt an klassische Pokémon-Typen-Farben.
- **Umschaltbare Retro-Farbpaletten**: Nutzer kann zwischen mehreren Themes wechseln, mindestens
  ein Standard-Theme und ein "Game Boy-Grün-Modus" (die vier typischen Grüntöne des Original-Game
  Boy). Einstellung wird pro Nutzer gespeichert (z.B. in UserSettings, siehe Abschnitt 3).
- Eigene, selbst gestaltete Pixel-Icons/UI-Elemente statt 1:1 kopierter Nintendo-Grafiken – die
  Pokémon-Sprites/Artworks selbst kommen legitim über die PokéAPI (dort für Fanprojekte freigegeben).
  Da es sich um ein privates, nicht-kommerzielles Projekt handelt, ist das unkritisch; ein Verkauf
  oder eine kommerzielle Nutzung wäre es nicht.
- Sanfte Übergangsanimationen/Sound trotz Retro-Optik – "High-End" heißt hier vor allem: durchdachte
  Micro-Interactions, keine Ladeflackerer, konsistentes Spacing/Typografie.

---

## 6. Technologie-Stack (Empfehlung – angepasst an XAMPP-Vorgabe)

Lokal soll es erstmal auf **XAMPP** (Apache + MySQL + PHP) laufen, live ist noch offen. Das passt
außerdem gut zu Deinem PHP/TYPO3-Hintergrund, deswegen wechsle ich die Empfehlung von einem
JS-Fullstack auf einen PHP-Stack, der direkt auf XAMPP läuft:

- **Framework**: Laravel (PHP) – eingebautes Auth-Scaffolding (Laravel Breeze für
  Login/Registrierung/Logout), Eloquent ORM, Migrations, Artisan-CLI für den Datenimport.
  Alternative wäre Symfony, Laravel ist für dieses Projektformat aber schneller produktiv und
  bringt die bessere Testinfrastruktur direkt mit.
- **Datenbank**: MySQL/MariaDB (läuft direkt in XAMPP mit, inkl. phpMyAdmin zur Kontrolle)
- **Templating/Frontend**: Blade-Templates + Tailwind CSS (über Vite kompiliert) für den
  Pixel-/Retro-Look, plus Alpine.js für leichte Interaktivität (Klick-Toggles, Live-Filter,
  Vorschau beim Freitext-Massenimport), ohne ein komplettes JS-Framework wie React laden zu müssen
- **Auth**: Laravel Breeze (E-Mail/Passwort, sitzungsbasiert)
- **Testing**: Pest oder PHPUnit für Unit-/Feature-Tests (v.a. die Prioritäts-Engine!), Laravel Dusk
  für echte Browser-/End-to-End-Tests
- **CI**: GitHub Actions (Composer install, PHPUnit/Pest, Dusk-Tests bei jedem Push)
- **PWA**: Web-App-Manifest + Service Worker, damit sich die App auf dem Handy als installierbare
  App (Icon auf dem Homescreen, eigenes Fenster ohne Browser-Leiste) nutzen lässt; mit Laravel z.B.
  über ein Vite-PWA-Plugin oder ein manuell eingebundenes Manifest/Service-Worker-Skript umsetzbar
- **Lokales Setup**: Projekt im XAMPP-`htdocs`-Ordner (oder per virtuellem Host verknüpft), `.env`
  mit lokalen MySQL-Zugangsdaten, `npm run dev`/`build` nur für den Tailwind/Alpine-Asset-Build
- **Live-Hosting**: bewusst offengelassen – ein Laravel/MySQL-Projekt läuft praktisch überall, von
  klassischem Shared-PHP-Hosting über einen simplen VPS (z.B. Hetzner) bis zu spezialisierten
  Diensten wie Laravel Forge oder Laravel Cloud. Die Entscheidung kann beim eigentlichen Deployment
  fallen, die App selbst wird nicht an einen bestimmten Host gekoppelt.

Das ist mein begründeter Vorschlag, kein Dogma – Claude Code kann davon abweichen, wenn es beim
Umsetzen bessere Gründe dafür sieht, das sollte dann aber kurz begründet werden.

---

## 7. Nicht-funktionale Anforderungen

- **Tests, verpflichtend, bevor das Projekt als "fertig" gilt:**
  - Unit-Tests für Geschäftslogik (v.a. die Prioritäts-Engine aus 2.7!)
  - Komponententests für zentrale UI-Bausteine (Pokémon-Karte, Filter, Dashboard-Fortschritt)
  - Integrationstests für API-Routen (Auth, Ownership-Updates, Settings)
  - End-to-End-Tests für die Kernflows: Registrieren → Login → Pokémon als besessen markieren →
    Fortschritt aktualisiert sich → Logout
- **Performance**: über 1.300 Pokémon-Datensätze inkl. Formen – Pagination bzw. virtualisiertes
  Grid/Liste im Frontend, keine Ladezeiten-Explosion
- **Responsive**: Desktop und Mobile vollwertig nutzbar
- **Installierbarkeit**: als PWA auf dem Smartphone installierbar (siehe Abschnitt 6)
- **Barrierefreiheit**: trotz Pixel-Font ausreichend Kontrast, Alt-Texte für alle Sprites,
  Tastaturbedienbarkeit
- **Sicherheit**: Passwort-Hashing (bcrypt/argon2), CSRF-Schutz, Rate-Limiting auf Login/Registrierung

---

## 8. Rechtlicher Hinweis

Dies ist ein **privates, nicht-kommerzielles Fan-Projekt**. Pokémon-Namen, -Sprites und -Artworks
sind Eigentum von Nintendo/Game Freak/The Pokémon Company. Die Nutzung über die PokéAPI (die
ausdrücklich für solche Community-/Fan-Projekte gedacht ist) und mit Attribution der Datenquellen
ist für den privaten Gebrauch üblich, sollte aber nicht kommerzialisiert oder öffentlich als
"offizielles" Produkt dargestellt werden.

---

## 9. Entwicklungs-Roadmap (Phasenvorschlag)

1. **MVP**: Datenimport (PokéAPI), Grundraster aller Pokémon, Login/Registrierung, einfaches
   Besitz-Tracking, Gesamtfortschritt
2. **Bezugsquellen & Priorität**: Obtainability-Daten ergänzen, Prioritäts-Engine (2.7),
   Schwierigkeitsgrad & Mehrfach-Fang-Empfehlung (2.8), Bank-Countdown-Widget
3. **Formen-Erweiterung**: Regionalformen + Shiny als separates Tracking, Einstellungs-Toggles
4. **Pokémon-GO-Modul**: Regionsdaten, Nutzer-Länder-Einstellung, Verknüpfung mit Prioritäts-Engine
5. **Gamification & Politur**: XP/Level, Achievements, Trainer-Karte, Sounds/Animationen,
   Chiptune-Musik-Toggle, umschaltbare Farbpaletten
6. **Social & Komfort**: Wunschliste/Favoriten, Statistik-Seite, Freunde-Vergleich/Bestenliste,
   PWA-Installierbarkeit
7. **Testabdeckung & Deployment**: vollständige Unit/Integration/E2E-Tests, CI-Pipeline,
   XAMPP-taugliches lokales Setup + Live-Deployment nach Wahl

---

## 10. Repository, Versionierung & Dokumentation

- **Öffentliches GitHub-Repository**: https://github.com/BenHohnstedter/pokemon-database (Stand
  jetzt leer) – das gesamte Projekt wird dorthin committet, mit sprechenden, thematisch geschnittenen
  Commits statt eines Riesen-Commits (grob entlang der Roadmap-Phasen aus Abschnitt 9).
- **Doku-Struktur im Repo** – gesammelt in einem `.claude/`-Ordner:

  ```
  .claude/
  ├── CLAUDE.md            # Agenten-Anweisungen, wird von Claude Code jede Session automatisch geladen
  ├── spec.md              # diese vollständige Spezifikation
  ├── progress.md          # Fortschritts-Log: Stand je Session/Phase, offene Punkte, nächste Schritte
  └── feature-updates.md   # laufendes Änderungsprotokoll für neue/geänderte Feature-Entscheidungen
  ```

  Wichtig zur Einordnung: Claude Code lädt automatisch eine Datei mit **genau dem Namen `CLAUDE.md`**
  (im Projekt-Root oder unter `.claude/CLAUDE.md`) – eine `agent.md` würde nicht von selbst gelesen.
  `CLAUDE.md` übernimmt hier also die Rolle, die Du Dir als "Agent-Datei" vorgestellt hast: kurz
  halten (Kernbefehle, Projektaufbau, Konventionen) und auf die anderen drei Dateien verweisen, statt
  sie hineinzukopieren – sonst wird bei jeder Session unnötig viel Kontext geladen.
  `spec.md`, `progress.md` und `feature-updates.md` sind normale Dateien, die Claude Code bei Bedarf
  liest, wenn `CLAUDE.md` darauf verweist oder Du explizit danach fragst.
  Zusätzlich lohnt sich ein kurzes `README.md` im Projekt-Root (für Menschen, nicht für Claude):
  Kurzüberblick + Setup-Anleitung für XAMPP (Repo klonen, `.env` aus `.env.example` anlegen,
  `composer install`, `php artisan migrate`, `npm install && npm run build`, Datenimport-Befehl
  ausführen).
- **Zur Klarstellung, weil das leicht verwechselt wird:** Claude Code hat zusätzlich ein eingebautes
  "Auto Memory", das eigene Beobachtungen automatisch speichert – das liegt aber lokal auf dem
  jeweiligen Rechner (`~/.claude/projects/.../memory/`) und landet **nicht** im Git-Repo. Für das
  Ziel "alles Wichtige ist öffentlich im Repo nachvollziehbar" ist deshalb genau das manuell
  gepflegte `progress.md` der richtige Ort, nicht das eingebaute Auto-Memory-Feature. `CLAUDE.md`
  sollte explizit anweisen, `progress.md` am Ende jeder Session zu aktualisieren.
- **Ganz wichtig, weil das Repo öffentlich ist – niemals committen:**
  - `.env`-Datei mit echten Zugangsdaten (nur `.env.example` mit Platzhaltern committen)
  - echte Datenbank-Dumps oder Seed-Daten mit eigenen Nutzer-/Zugangsdaten
  - API-Keys/Secrets jeglicher Art (PokéAPI selbst braucht zwar keinen Key, aber falls später z.B.
    ein Mail- oder Auth-Dienst dazukommt)
  - der lokale Laravel-`APP_KEY`
  - persönliche Log-/Debug-Dateien (`storage/logs/*`)
  - Laravels Standard-`.gitignore` deckt `.env`, `vendor/`, `node_modules/`, Logs etc. bereits
    weitgehend ab – vor dem ersten Commit trotzdem einmal bewusst gegenprüfen, dass nichts
    Persönliches drin landet.

---

## Anhang A: Mainline-Spiele (Vorlage für die Einstellungs-Checkliste)

Gen 1: Rot/Blau/Grün/Gelb · Gen 2: Gold/Silber/Kristall · Gen 3: Rubin/Saphir/Smaragd,
Feuerrot/Blattgrün · Gen 4: Diamant/Perl/Platin, HeartGold/SoulSilver · Gen 5: Schwarz/Weiß,
Schwarz2/Weiß2 · Gen 6: X/Y, Omega Rubin/Alpha Saphir · Gen 7: Sonne/Mond, Ultrasonne/Ultramond,
Let's Go Pikachu/Evoli · Gen 8: Schwert/Schild (+ DLCs), Strahlender Diamant/Leuchtende Perle,
Legenden: Arceus · Gen 9: Karmesin/Purpur (+ DLCs) · plus alle danach erschienenen Titel
(beim Daten-Import aktuell halten, siehe Abschnitt 4).

---

## Offene Punkte, die Du selbst noch einmal prüfen solltest

- Hosting-Entscheidung final treffen (lokal vs. Cloud) – aktuell als "beides möglich" ausgelegt
- Prüfen, ob seit meinem Wissensstand (Jan. 2026) neue Mainline-Spiele erschienen sind, die in
  Anhang A noch fehlen
- Genaues Startdatum für den Bau festlegen, damit die Bank-Deadline realistisch in die Roadmap passt
- Vor dem ersten Push ins öffentliche Repo einmal manuell durchsehen, dass wirklich nichts
  Persönliches/Sensibles enthalten ist (siehe Abschnitt 10)
