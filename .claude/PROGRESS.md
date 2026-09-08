# Progress Log

Kurzer Stand je Session/Phase. Neuester Eintrag oben. Am Ende jeder Session aktualisieren.

## 2026-09-08 (Nachmittag) — Deutsch auf den Konto-Seiten, dreizehn Stücke

### Umgesetzt

1. **Konto-Seiten auf Deutsch** (FEATURE-UPDATES.md 17). `lang/de.json` für die
   Oberflächentexte, dazu `auth.php`, `passwords.php` und ein bewusst kurzes
   `validation.php` — was dort fehlt, fällt über `APP_FALLBACK_LOCALE` auf
   Englisch zurück. Die englischen HTML-Kommentare aus dem Breeze-Gerüst sind
   durch Blade-Kommentare ersetzt; sie wurden bis in den Browser mitgeliefert.
2. **Dreizehn Musikstücke statt drei** (FEATURE-UPDATES.md 18). Zehn
   CC0-Stücke von Juhani Junkala aus den JRPG-Paketen *Calm* und *Towns*.
   Rund 33 MB Audio im Repo — Ausdünnen geht über `config/pokedex.php`.

### Ein Hinweis für die nächste Session

Während der Dusk-Untersuchung stand die `.env` einige Minuten lang auf der
Test-Konfiguration (leere Datenbank, anderer APP_URL). Der Nutzer hat genau in
dem Fenster die Seite aufgerufen und eine kaputte App gesehen. Wer die `.env`
für einen Test umbiegt, sagt vorher Bescheid oder benutzt eine
Umgebungsvariable am Prozess statt der Datei — die App unter
`http://localhost/dex-rescue` läuft nebenher weiter.

### Tests: 309 grün

307 vorher, zwei neu im `DarkUiTest`: Die Konto-Seiten zeigen deutsche Texte und
keine englischen mehr, und Eingabefehler kommen auf Deutsch zurück. Die
Musiktests hängen an der Konfiguration und decken die zehn neuen Stücke
automatisch mit ab (110 Assertions statt 30).

## 2026-09-08 (später) — Musik, und der Dusk-Job wird stillgelegt

### Musik: drei CC0-Stücke statt Oszillatoren

Der synthetisierte Hintergrund-Loop ist raus (FEATURE-UPDATES.md 15). Stattdessen
spielt ein `<audio>`-Element drei ruhige Stücke von OpenGameArt, alle CC0, mit
Weiterschalten in der Kopfzeile. Die Liste steht in `config/pokedex.php`, die
Herkunft in `public/audio/HERKUNFT.md`.

Originalmusik aus den Spielen liegt bewusst nicht im Repo — die Soundtracks
gehören Nintendo/Game Freak/The Pokémon Company, und ein öffentliches
Repository ist etwas anderes als ein Sprite unter Fan-Projekt-Vorbehalt.

Die 8-Bit-Effekte bleiben synthetisiert.

### Der Dusk-Job läuft nur noch von Hand

Auf Wunsch des Nutzers (FEATURE-UPDATES.md 16). Drei echte Fehler wurden auf dem
Weg dorthin gefunden und behoben:

1. **Falsche Datenbank in der `.env`** — der MySQL-Dienst legt nur
   `pokemon_database_dusk` an, die `.env` zeigte auf `pokemon_database`. Weil
   Session und Cache in der Datenbank liegen, endete jede Anfrage mit 500.
2. **Der Serverprozess überlebte die Schrittgrenze nicht** — Healthcheck grün,
   danach `net::ERR_CONNECTION_REFUSED`. Server und Tests laufen jetzt im
   selben Schritt.
3. **Unreproduzierbarer CSS-Build** — Tailwind scannte den maschinenlokalen
   Blade-Cache mit; 51,8 kB lokal gegen 39,1 kB im frischen Checkout.

Danach: 8 von 13 grün auf dem Runner, 13 von 13 lokal — auch mit nachgestellter
CI-Konfiguration. Die restlichen fünf scheitern an Inhalten, die im Runner nicht
sichtbar sind (`0/4` auf dem Dashboard, „Pokédex" in der Navigation, die
großgeschriebene Filterzeile). Der nächste Schritt wäre der Seitenquelltext aus
dem Artefakt gewesen — der Job schreibt ihn inzwischen mit.

Statt `main` dauerhaft rot zu halten, läuft der Job jetzt nur noch auf Zuruf
(Actions → CI → „Run workflow"). Die Tests bleiben erhalten.

### Tests: 307 grün

303 vorher, vier neu in `MusikTest`: Stückliste vorhanden und Dateien auf
Platte, Liste kommt in der Oberfläche an, Weiterschalten vorhanden,
Herkunftsnachweis deckt jedes Stück ab.

## 2026-09-08 — ORAS-Starter, und warum Dusk lokal grün und in CI rot war

### Umgesetzt

- **ORAS verschenkt Johto-, Einall- und Sinnoh-Starter** (FEATURE-UPDATES.md
  14). 18 weitere Wildfang-Zeilen auf „Hoenn Route 101" sind damit als
  Geschenk ausgewiesen, jede mit ihrer eigenen Bedingung als Notiz.

### Dusk: zwei verschiedene Datenbanken im selben Lauf

Der lokale Lauf scheiterte zuerst mit acht Fehlschlägen — und der
Fehlschlag-Screenshot zeigte den Grund: Der Browser sah die
**Entwicklungsdatenbank** (echter Nutzer, 283 Pokémon in HeartGold), während
die Tests ihre Fixtures in `pokemon_database_dusk` anlegten.

Ursache ist `php artisan serve --no-reload`: Der Serverprozess liest die `.env`
genau einmal beim Start. `php artisan dusk` tauscht sie danach gegen
`.env.dusk.local` — davon bekommt der laufende Server nichts mehr mit. Weichen
die beiden Dateien in der Datenbank ab, laufen Testprozess und Server
auseinander, und jedes `waitForText` läuft in seinen Timeout.

Mit gleicher Datenbank für beide: **13 von 13 Dusk-Tests grün**, lokal
verifiziert. Die Oberflächenänderungen dieser Session sind also unschuldig.

Im Workflow bekommt die `.env` deshalb dieselben Werte wie `.env.dusk.local`
(Datenbank, Session- und Cache-Treiber), sodass der Tausch nichts mehr ändert.
Dazu schreibt der Job bei Fehlschlag `laravel.log`, das Server-Log und die
Browser-Konsole ins Log und hängt sie ans Artefakt — die GitHub-Logs sind ohne
Token nicht abrufbar (403), und ohne diese Ausgabe bleibt nur Raten.

## 2026-09-07 (später) — Starterwahl, Cosmog und ein CI-Job, der nie lief

### Vom Nutzer gemeldet, umgesetzt

1. **Starter standen als Wildfang in der Liste** (FEATURE-UPDATES.md 12). Die
   PokéAPI führt die Übergabe des Starters als Encounter im Startort; neben dem
   Geschenk-Eintrag stand deshalb eine zweite Zeile mit erfundenem Fundort. 97
   solche Zeilen sind weg, erkannt am einzelnen Fundort — Let's Go bleibt
   ausgenommen, dort laufen die Kanto-Starter wirklich herum.
2. **Cosmog fehlte aus Schwert/Schild** (FEATURE-UPDATES.md 13). Das Geschenk
   aus den Kronen-Schneelanden kennt die PokéAPI nicht. Mit dem einen Eintrag
   fällt die ganze Linie von der Bank-Frist: Cosmovum steht für ein Konto mit
   Schwert jetzt auf 🟢 statt „vor der Abschaltung übertragen".

### Der Dusk-Job war schon vorher rot

Der Lauf vor dieser Session ist an derselben Stelle gescheitert wie der danach
— „Warten, bis der Server antwortet". Ursache war nicht Dusk, sondern die
Umgebung: Der MySQL-Dienst im Workflow legt nur `pokemon_database_dusk` an, die
`.env` zeigte aber weiter auf `pokemon_database`. Weil Session und Cache in der
Datenbank liegen, endete **jede** Anfrage an den Testserver mit 500, und der
Healthcheck lief 30 Sekunden lang in seinen Timeout.

Lokal nachgestellt: `php artisan serve` mit einer nicht existierenden Datenbank
liefert 500, `curl -sf` bricht mit Exitcode 22 ab — genau das Symptom.

Behoben ist beides: Die `.env` wird im Workflow auf die Dusk-Datenbank
umgebogen, der Server startet mit `nohup` und umgeleiteter Ausgabe (sonst
wartet der Runner am Schrittende auf die offene Ausgabe), und schlägt der
Healthcheck doch fehl, stehen jetzt Antwort und Server-Log im Log statt nur
„Server ist nicht hochgekommen".

### Tests: 302 grün, Pint sauber

299 vorher, drei neu im `SeederTest`: Starterwahl wird entfernt, echte
Let's-Go-Wildfänge bleiben, Cosmog steht in Schwert und Schild.

### Offen, weil Spielwissen nötig

- **Omega Rubin / Alpha Saphir zeigen Chelast weiter als „Wildfang, Hoenn Route
  101"**. Das sieht nach demselben Muster aus (ein geschenkter Starter, den die
  API als Encounter führt), steht aber nicht in der Starter-Liste dieser Titel.
  Gehört bestätigt, bevor die Zeile fällt.

## 2026-09-07 — Lokales Setup auf dem neuen Rechner, Boxraster, dunkle Konto-Seiten

### Das Projekt läuft jetzt unter `C:/xampp`

Erstaufsetzen auf einer Maschine, auf der XAMPP noch **PHP 7.4.29** mitbringt —
Laravel 12 braucht ≥ 8.2. Statt XAMPP anzufassen läuft es wie beim
Nachbarprojekt sah-inventory: Apache reicht `.php` per FastCGI an das
vorhandene **PHP 8.3.33** unter `C:/php83` weiter, hier auf Port **9124**.

Aufrufbar ist die App unter **http://localhost/dex-rescue** — kein eigener
vHost, sondern ein `Alias` unter dem normalen localhost. Damit bleiben htdocs,
phpMyAdmin und die anderen Projekte unangetastet. Der Front-Controller hängt
bewusst an einer `FallbackResource` in der Apache-Konfiguration statt an einer
angepassten `.htaccess`: So bleibt die Maschinen-Konfiguration aus dem
öffentlichen Repo heraus.

Zwei Stolpersteine, die wiederkommen können:

- **`SetHandler "proxy:fcgi://…"` braucht unter Windows den Schrägstrich am
  Ende.** Ohne ihn klebt Apache den Dateipfad direkt an den Port
  (`…:9124C:/xampp/…`) und antwortet mit *400 URI cannot be parsed*.
- **Kein CA-Bundle in der `php.ini` von PHP 8.3.** Der PokéAPI-Import brach mit
  *cURL error 60* ab. `curl.cainfo`/`openssl.cafile` zeigen jetzt auf ein
  Bundle neben der PHP-Installation.

Start- und Stoppskripte liegen als `start-dex-rescue.bat` bzw.
`stop-dex-rescue.bat` im XAMPP-Verzeichnis, also außerhalb des Repos — das
XAMPP-Control-Panel kennt den PHP-8.3-Prozess nicht.

### Vom Nutzer gewünscht, umgesetzt

1. **Höchstens sechs Pokémon nebeneinander** (FEATURE-UPDATES.md 10). Eine Box
   auf der Switch fasst sechs pro Reihe; das Raster ging bis `xl:grid-cols-8`
   und lief damit am Abgleich vorbei. Auf dem Handy bleiben zwei bzw. drei
   Spalten. Die Spielansicht listet untereinander und bleibt unverändert.
2. **Login, Registrierung und Profil sind nicht mehr weiß**
   (FEATURE-UPDATES.md 11). Umgestellt sind nicht nur die Seiten, sondern die
   gemeinsamen Breeze-Bausteine — sie brachten `bg-white`/`text-gray-700` mit
   und hätten das Weiß sonst jeder neuen Seite zurückgegeben.
3. **Favicon** in Tab und Lesezeichenleiste. Das Icon lag längst unter
   `public/icons/favicon-32.png`, nur verlinkt hat es keiner.

### Tests: 299 grün

293 vorher, sechs neu: fünf in `DarkUiTest` (keine hellen Breeze-Klassen mehr
in Login, Registrierung und Profil; Pixel-Bausteine vorhanden; Favicon
verlinkt) und einer im `PokedexTest` fürs Sechserraster.

### Weiterhin offen

- **Die Konto-Seiten sind noch auf Englisch** („Email", „Log in", „Remember
  me"). Breeze liefert die Texte über `__()`, es gibt aber keine `lang/de.json`.
  Dunkel sind sie jetzt, deutsch noch nicht.
- Die Punkte aus der vorigen Session (Legenden-Fundorte, formspezifische
  Fundorte) stehen unverändert.

## 2026-09-07 — Formen-Schalter, Poké Transporter, Spielansicht geschärft

### Vom Nutzer gemeldet, umgesetzt

1. **Die Spielansicht zeigte Regionalformen** — unter Ultrasonne etwa das
   Alola-Rattfratz statt Rattfratz. Ursache war ein `keyBy(pokemon_id)` über
   Basis-, Regional- und Sonderformen: pro Art überlebte die zuletzt
   einsortierte Form. Standard ist jetzt „nur normale Formen", dazu ein
   Schalter. Regionalformen erscheinen nur mit eigenem Fundort — und wo es
   keinen gibt, sagt die Seite das, statt still dieselbe Liste zu zeigen.
2. **Feuerrot/Blattgrün laufen über HOME, nicht über Bank** — der Nutzer besitzt
   die Switch-Version. Deren Einträge waren in seinem Profil noch nicht
   angehakt; nachgetragen. Die GBA-Häkchen blieben stehen (unklar, ob die Module
   noch da sind — ändert am Ergebnis nichts).
3. **Poké Transporter** als eigene Hürde vor Pokémon Bank (Details in
   `FEATURE-UPDATES.md` 9). Der Nutzer hat die App nicht; entsprechend gesetzt.

### Wirkung auf den echten Bestand (Konto „Ben")

| Kennzahl | vorher | nachher |
|---|---|---|
| 🔴 dringend, Spiel fehlt | 58 | 2 |
| an der Bank-Frist gesamt | 372 | 67 |
| davon selbst holbar | 314 | 65 |

Die verbleibenden 67 sind genau die richtigen: X, Alpha Saphir, Mond und
Ultrasonne sind Gen 6/7 und laden direkt zu Bank hoch — dort ist die Frist real
und die Liste abarbeitbar. Ho-Oh und Lugia stehen über Feuerrot (Switch) auf 🟢
ohne Frist.

### Der Transporter-Zustand steht dort, wo gehandelt wird

Nicht nur in der Engine: Spielübersicht, Spielseite, Bezugsquellen-Tabelle und
die Spieleliste in den Einstellungen unterscheiden jetzt drei Fälle statt zwei —
direkt an HOME, „Poké Transporter → Bank", und „Sackgasse, Transporter fehlt
Dir". Ein Gen-5-Titel ohne die App ist für den Nutzer keine Frist mehr, sondern
ein verschlossener Weg, und genau so liest sich die Seite jetzt auch.

### Tests: 293 + 13 grün

- **293 Unit-/Feature-Tests** (833 Assertions), neu: Poké-Transporter-Logik in
  der Engine (7), Transporter in der Spielansicht (4), Regionalformen in der
  Spielansicht (4), „keine Fundorte" vs. „alles gefangen" (2).
- **13 Dusk-Browsertests** (96 Assertions), neu: Gen-5-Spiel ohne Transporter
  als Sackgasse, gleichzeitig mit einem Gen-6-Titel, für den die Frist weiter
  gilt — beide Zustände auf derselben Seite.

### Weiterhin offen

- **Legenden: Arceus hat nur 3 Fundorte.** Die PokéAPI liefert keine
  Encounter-Daten, und der Titel ist kein Remake — die Spiegelung greift nicht.
  Bewusst nicht geraten: ein erfundener Fundort in einem HOME-Spiel würde
  Erreichbarkeit vortäuschen, und das ist vor einer Frist der teuerste Fehler.
  Gehört von Hand aus Bulbapedia nachgetragen.
- **Keine formspezifischen Fundorte.** Von 8.256 Zeilen zeigt keine auf eine
  Form, deshalb bleibt der Regionalformen-Schalter in der Spielansicht vorerst
  wirkungslos. Alola-, Galar-, Hisui- und Paldea-Formen hängen real an
  bestimmten Titeln — das wäre der nächste sinnvolle Datensatz.
- **GO-Datensatz ist ein Kern (21 Einträge)**, kein Vollbestand.
- **Titel und Erscheinungsjahr der Switch-Neuauflage** von Feuerrot/Blattgrün
  sind geschätzt.
- Spieleliste im Übrigen gegen Bulbapedia/Serebii verifizieren, besonders
  `still_purchasable`.
- Fundorte sind englisch, solange `pokedex:import-encounters` ohne
  `--translate-locations` läuft.
- Kein Push nach GitHub — bislang nur lokale Commits (so abgestimmt).

### Nächste Schritte

1. Formspezifische Fundorte für Regionalformen nachtragen (`pokemon_form_id`),
   dann wird der Formen-Schalter in der Spielansicht nutzbar
2. Hisui-Bestand für Legenden: Arceus nachtragen, danach `pokedex:recalculate`
3. GO-Vollbestand per `pokedex:import-go` aus einer gepflegten CSV
4. Repo nach GitHub pushen (vorher auf Persönliches gegenprüfen, spec.md 10)

## 2026-09-06 — Spielansicht, Quellen-Gruppierung, Remake-Daten

### Vom Nutzer gemeldet, umgesetzt

1. **Remakes hatten fast keine Fundorte.** Die PokéAPI kennt für Strahlender Diamant und
   Leuchtende Perle fünf Arten — gegenüber knapp 300 in Diamant und Perl. Die App
   behauptete daraufhin, fast der ganze Sinnoh-Dex sei nur über alte Hardware und damit
   über Pokémon Bank erreichbar. Neuer `RemakeObtainabilitySeeder` überträgt den Bestand
   der Originale auf ihre Neuauflagen (Details in `FEATURE-UPDATES.md` 7).
2. **Feuerrot/Blattgrün für Switch** sind jetzt im `GameSeeder` — mit dem Bestand der
   GBA-Originale inklusive Ho-Oh und Lugia (Eiland 9). Beide sind damit ohne Bank
   erreichbar, wie vom Nutzer beschrieben.
3. **Mew und Jirachi in BDSP** über Speicherstände anderer Switch-Titel, ohne Event.
4. **Bezugsquellen nach eigenen Spielen getrennt**: „✔ In Deinen Spielen" zuerst, dann
   „Außerdem in diesen Spielen". Am Beispiel des Nutzers (Kapilz, sechs Titel, davon X im
   Besitz) live geprüft.
5. **Spiel-für-Spiel-Ansicht** unter `/spiele` — Übersicht mit Restzahlen je Titel,
   Detailseite mit allem, was dort noch fehlt, und Abhaken direkt in der Liste.

### Wirkung auf den echten Bestand

| Kennzahl | vorher | nachher |
|---|---|---|
| Arten nur über Bank-Titel erreichbar | 355 | 219 |
| 🔴 beim Demo-Nutzer (Spiel fehlt) | 88 | 58 |
| Quellen in Strahlender Diamant | 5 | ~280 |

### Ein echter Layoutfehler, gefunden durch den erweiterten Responsive-Test

Die Spielansicht ließ sich auf dem Handy um ~56 px seitlich schieben. Ursache ist
subtiler als beim letzten Mal: Chrome rechnet die Mindestbreite des Tabelleninhalts
(`min-w-[40rem]`) bis zur Wurzel hoch, obwohl `overflow-x: auto` sie längst abschneidet.
Weder `max-width: 100%` noch `overflow-x: clip` auf Panel, `main`, `body` oder `html`
halten das auf — erst `contain: layout` tut es. Dafür gibt es jetzt die Utility
`.pixel-scroll-x`, die alle drei breiten Tabellen der App benutzen.

Der Responsive-Test misst deshalb nicht mehr `documentElement.scrollWidth`: der meldet bei
einem inneren Scroll-Container die ungekürzte Inhaltsbreite und hätte künftig genau die
richtige Lösung angemeckert. Gemessen wird jetzt, was der Nutzer merkt — lässt sich die
Seite tatsächlich schieben? — plus die überstehenden Elemente, die kein Vorfahre clippt.

### Tests: 288 grün

- **276 Unit-/Feature-Tests** (773 Assertions), darunter neu: Remake-Seeder (8),
  Spielübersicht und -detailseite (11), Quellen-Gruppierung (4).
- **12 Dusk-Browsertests** (92 Assertions), neu: der komplette Weg von der Spielübersicht
  über die Liste bis zum Abhaken, und die Bank-Warnung auf einem Altspiel. Der
  Responsive-Test deckt jetzt elf Seiten über fünf Breiten ab (neu: `/spiele`,
  Spiel-Detail, Pokémon-Detail).

### Weiterhin offen

- **Legenden: Arceus hat nur 3 Fundorte.** Die PokéAPI liefert für den Titel keine
  Encounter-Daten, und er ist kein Remake — die Spiegelung greift dort nicht. Die
  Hisui-Liste müsste von Hand aus Bulbapedia nachgetragen werden. Praktisch fällt es kaum
  ins Gewicht, weil der Sinnoh-Bestand über BDSP abgedeckt ist.
- **GO-Datensatz ist ein Kern (21 Einträge), kein Vollbestand** — die Engine rechnet ohne
  Eintrag bewusst konservativ ohne GO-Rettungsweg.
- **Titel und Erscheinungsjahr der Switch-Neuauflage** von Feuerrot/Blattgrün sind
  geschätzt und gehören gegengeprüft, sobald sie offiziell feststehen (Kommentar steht im
  `GameSeeder`).
- Spieleliste im Übrigen gegen Bulbapedia/Serebii verifizieren, besonders
  `still_purchasable`.
- Fundorte sind englisch, solange `pokedex:import-encounters` ohne `--translate-locations`
  läuft.
- Kein Push nach GitHub — bislang nur lokale Commits (so abgestimmt).

### Nächste Schritte

1. Hisui-Bestand für Legenden: Arceus nachtragen (Bulbapedia), danach `pokedex:recalculate`
2. GO-Vollbestand per `pokedex:import-go` aus einer gepflegten CSV nachladen
3. Spieleliste gegen Bulbapedia/Serebii verifizieren
4. Repo nach GitHub pushen (vorher noch einmal auf Persönliches gegenprüfen, spec.md 10)


## 2026-09-06 — Die App läuft, Feedback eingearbeitet, Tests vollständig

### Lauffähig

- **Node 24.20.0 LTS** portabel nach `E:\xampp\nodejs` installiert (offizielles ZIP,
  Prüfsumme verifiziert). `npm install && npm run build` läuft, die App ist im Browser
  benutzbar.
- **Chrome for Testing 152.0.7977.82** portabel nach `E:\xampp\chrome-win64` — auf dem
  Rechner war nur Edge, den der mitgelieferte ChromeDriver nicht steuern kann.
  `DUSK_CHROME_BINARY` in `.env.dusk.local` zeigt darauf.
- MySQL läuft, Vollbestand importiert (1025 Arten, 1082 Formen).

### Feedback aus dem Ausprobieren, alles umgesetzt

1. **Die Bank-Frist verschwand, sobald man das Spiel besaß.** Größter fachlicher Fehler
   der bisherigen Umsetzung — Details in `FEATURE-UPDATES.md`. Am echten Bestand:
   404 betroffene Pokémon statt vorher sichtbarer 88.
2. **„Kaufbar" → „Spiel fehlt Dir noch"** (die alte Beschriftung las sich, als wäre das
   Pokémon käuflich).
3. **Einträge pro Seite** einstellbar: 30/60/120/240.
4. **Export/Import des Sammlungsstands** als JSON über Form-Slugs.
5. **Öffentliches Profil** (spec.md 2.11, war als optional geführt) — damit hängt jetzt
   auch etwas an der bis dahin ungenutzten Spalte `profile_public`.

### Tests: 262 grün

- **252 Unit-/Feature-Tests** (705 Assertions), laufen gegen SQLite in-memory ohne
  Asset-Build.
- **10 Dusk-Browsertests** (113 Assertions): der Kernflow aus spec.md 7 komplett
  (Registrieren → Login → markieren → Fortschritt → Logout), dazu Masseneingabe,
  Deadline-Filter, Theme-Wechsel, Seitengröße, Export-Seite — und ein Responsive-Test,
  der neun Seiten über fünf Breiten abfährt.

Beim erstmaligen Ausführen von Dusk fielen vier Dinge auf, die alle behoben sind:
fehlender Chrome, Textsuche scheitert am `uppercase` des Retro-Designs (Selenium liefert
den gerenderten Text), der statische Dex-Zähler der Factory überlebt das Leeren der
Tabellen, und `assertAttribute('html', …)` sucht innerhalb von `body`.

### Zwei echte Layoutfehler gefunden und behoben

- Die Kartenkomponente rendert in der Gast-Ansicht **zwei `class`-Attribute** nebeneinander
  — der Browser verwirft das zweite stillschweigend, wodurch die komplette Basis-Optik
  fehlte.
- Die **Detailseite lief auf dem Handy über** (255 px bei 375 px Breite): Die
  Bezugsquellen-Tabelle hat `min-w-[36rem]` in einem `overflow-x-auto`-Container, aber
  Grid-Elemente haben `min-width:auto` und schrumpfen nicht unter ihren Inhalt. `min-w-0`
  auf den Spalten löst es.

### Weiterhin offen

- Spieleliste und GO-Daten gegen Bulbapedia/Serebii verifizieren (siehe unten).
- Feuerrot/Blattgrün für Switch fehlt im `GameSeeder`, weil der Titel nicht belastbar
  bekannt ist.
- Kein Push nach GitHub — bislang nur lokale Commits.

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

`PokedexQuery::evaluate()` bewertet den kompletten Dex (1.082 Formen) in **9 Queries** und
rund **400 ms** gegen MySQL unter XAMPP.

Die Messung der einzelnen Schritte war aufschlussreich: das Laden kostet nur 145 ms
(7.407 Bezugsquellen, 1.082 Formen), die Engine selbst lag bei 352 ms. Zwei Änderungen
haben das halbiert — Schwierigkeit und Konsolenliste erst im zurückgebenden Zweig
berechnen, und die Suche nach besseren Wegen abbrechen, sobald 🟢 erreicht ist. Die Typen
werden ohnehin nur für die angezeigte Seite nachgeladen.

Seitenzeiten gegen den echten Bestand: Dashboard 729 ms, Pokédex 744 ms, Statistik 588 ms,
Detailseite 293 ms. Die Seitengröße wirkt sich kaum aus (30 → 240 Einträge kosten rund
150 ms mehr) — die Grundlast ist die Bewertung, nicht das Rendern.

**Falls es später doch zu langsam wird**, wäre der nächste Schritt, die bewertete Liste je
Kontext (Spielebesitz + GO-Region + Datenstand) zwischenzuspeichern. Bewusst nicht
vorgezogen: Cache-Invalidierung ist eine eigene Fehlerquelle, und unter einer Sekunde
lohnt sie sich nicht.

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
