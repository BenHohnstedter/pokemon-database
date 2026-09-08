# Feature-Updates-Protokoll

Neue oder geänderte Feature-Entscheidungen werden hier zuerst mit Datum notiert, bevor sie
inhaltlich in `spec.md` eingearbeitet werden. So bleibt nachvollziehbar, wann und warum sich etwas
gegenüber der ursprünglichen Spezifikation geändert hat.

Der komplette Stand aus der Erstspezifikation (inkl. aller bisher besprochenen Erweiterungen wie
Schwierigkeitsgrad, Mehrfach-Fang-Empfehlung, Wunschliste, Statistik-Seite, PWA, Farbpaletten,
Chiptune-Musik) ist bereits vollständig in `spec.md` eingearbeitet. Diese Datei ist bereit für
alles, was danach noch dazukommt.

---

## 2026-09-06 — Datenmodell-Präzisierungen beim Umsetzungsstart

Beim Bau des Schemas sind drei Punkte aufgefallen, an denen die Skizze aus `spec.md`
Abschnitt 3 in der Praxis nicht ausgereicht hätte. Die Spec bleibt inhaltlich gültig,
das Modell ist nur feiner aufgelöst:

1. **Besitz hängt an der Form, nicht am `form_type`.**
   `spec.md` skizziert `UserOwnership(user_id, pokemon_id, form_type: base|regional|shiny)`.
   Damit ließen sich Pokémon mit *mehreren* Regionalformen nicht trennen — Mauzi hat eine
   Alola- **und** eine Galar-Form, Wooper eine Paldea-Form, Rattfratz nur Alola.
   Umgesetzt ist deshalb `user_pokemon_forms(user_id, pokemon_form_id, owned, owned_shiny,
   is_favourite)`: eine Zeile pro konkreter Form, Shiny als zweite Besitz-Spalte statt als
   dritter „Form-Typ". Die drei Fortschrittsbalken aus 2.2 entstehen daraus per Aggregation
   (Haupt = `form_type=base AND owned`, Regional = `form_type=regional AND owned`,
   Shiny = `owned_shiny` über alle Formen). Fachlich ändert sich nichts, nur die Auflösung
   wird feiner.

2. **`is_favourite` liegt in derselben Tabelle.**
   Die Wunschliste aus 2.5 ist damit ein Filter auf dem Sammlungsstand statt einer eigenen
   Tabelle — spart einen Join und hält „besessen" und „gewünscht" pro Form beieinander.

3. **Typen als eigene Tabelle statt als Array-Spalte.**
   `spec.md` notiert `types[]` am Pokémon. Für die Statistik-Seite (2.10, „Fortschritt nach
   Typ") und das Achievement „Alle Typen mindestens 1×" (2.9) braucht es aber eine
   abfragbare Relation, deshalb `types` + Pivot `pokemon_type` (mit optionaler
   `pokemon_form_id`, weil Regionalformen abweichende Typen haben — Alola-Vulpix ist Eis
   statt Feuer).

Zusätzlich neu, ohne Widerspruch zur Spec: `achievements`/`achievement_user` und
XP-Spalten am User (2.9), `friendships` (2.10), `user_settings.theme` (5).

---

## 2026-09-06 — Änderungen aus dem ersten Ausprobieren

Vier Punkte, die beim Durchklicken der laufenden App aufgefallen sind.

### 1. Die Bank-Frist hängt nicht mehr an der Dringlichkeitsstufe

`spec.md` 2.7 beschreibt 🔴 als „nur auf alter Hardware **und** nur über Bank". Wer das
passende Spiel besitzt, landet dadurch auf 🟢 *einfach* — und verschwand komplett aus dem
Countdown-Widget. Fachlich ist das falsch: Ein Pokémon aus Pokémon Schwarz ist leicht zu
fangen, muss aber trotzdem **vor dem 26./27.02.2027** über Pokémon Bank nach HOME.

Neu trägt jedes Bewertungsergebnis ein eigenes Deadline-Kennzeichen, unabhängig von der
Stufe. Es ist gesetzt, wenn der Weg, den *dieser* Nutzer gehen würde, über Bank führt —
also kein besessenes Spiel direkt an HOME hängt und GO nicht aushilft.

Das Dashboard trennt danach zwei Gruppen, weil sie unterschiedliche Handlungen verlangen:

- **⏳ Kannst Du selbst holen – aber vor der Deadline**: Spiel ist da, es fehlt nur die
  Übertragung.
- **🔴 Dafür fehlt Dir noch Spiel oder Konsole**: erst beschaffen, dann übertragen.

Der Countdown zählt beide Gruppen. Am echten Bestand (Schwarz, Weiß 2, X, Schwert,
Karmesin im Besitz): 404 betroffene Pokémon, davon 316 selbst holbar. Vorher zeigte das
Widget nur die 88 aus der zweiten Gruppe.

Die sechs Stufen aus `spec.md` 2.7 bleiben unverändert — das Kennzeichen kommt daneben,
nicht statt ihrer.

### 2. „Kaufbar" heißt jetzt „Spiel fehlt Dir noch"

Die alte Beschriftung las sich, als wäre das Pokémon selbst käuflich. Gemeint ist, dass
nur das Spiel dazu fehlt und noch regulär im Handel ist. Der Enum-Wert (`purchasable`)
bleibt, damit gespeicherte Filter-Links weiter funktionieren.

### 3. Einträge pro Seite einstellbar

Das Raster stand fest auf 60. Neu wählbar zwischen 30, 60, 120 und 240 — dauerhaft in den
Einstellungen (`user_settings.per_page`) oder per Query-Parameter für einen einzelnen
Aufruf. Andere Werte werden abgewiesen, damit niemand versehentlich alle 1.082 Einträge
auf einmal anfordert.

### 4. Export und Import des Sammlungsstands

Neu gegenüber `spec.md`: Der eigene Stand lässt sich als JSON herunterladen und wieder
einspielen — zum Umziehen auf einen anderen Rechner und als Sicherung vor einer großen
Massenaktion.

Referenziert wird über den **Form-Slug** (`bulbasaur`, `vulpix-alola`), nicht über interne
IDs: die vergibt jede Datenbank neu, ein Export wäre sonst nur auf genau der Installation
brauchbar, aus der er stammt.

Zwei Modi:

- **Ergänzen** (Standard) nimmt nie etwas weg — ein älterer Export kann den Stand nicht
  verschlechtern.
- **Ersetzen** setzt den Stand exakt auf die Datei.

Unbekannte Formen werden übersprungen und gemeldet, statt den Import abzubrechen. Die
Datei enthält keine Kontodaten und kann gefahrlos weitergegeben werden.

### 5. Bezugsquellen nach eigenen Spielen getrennt

Auf der Detailseite standen alle Fundorte in einer Liste. Bei Kapilz sind das sechs Titel
(HeartGold, SoulSilver, Schwarz 2, Weiß 2, X, Y) — die eigentliche Frage „komme ich mit
dem ran, was ich habe?" beantwortete das nicht.

Die Tabelle ist jetzt zweigeteilt: zuerst **„✔ In Deinen Spielen"**, darunter
**„Außerdem in diesen Spielen"**. Wer noch keine Spiele eingetragen hat, sieht stattdessen
den Hinweis, das nachzuholen — ohne diese Angabe kann die App die Trennung nicht leisten.

### 6. Spiel-für-Spiel-Ansicht (`/spiele`)

Neu gegenüber `spec.md`: Der Pokédex denkt von der Art aus („wo bekomme ich das?"). Wer
eine Konsole in der Hand hat, denkt umgekehrt: erst das Spiel, dann die Liste.

- `/spiele` zeigt alle Titel nach Generation, die eigenen markiert, mit der Zahl der dort
  noch fehlenden Arten.
- `/spiele/{spiel}` listet alles, was in genau diesem Titel noch zu holen ist — mit dem
  Fundort **aus diesem Spiel**, nicht mit der insgesamt besten Route. Sonst stünde bei
  jedem Eintrag ein Weg aus einem ganz anderen Titel.
- Abhaken geht direkt in der Liste, ohne Umweg über die Detailseite.
- Oben steht, wie das Gefangene nach HOME kommt — bei Bank-Titeln mit der Frist.

### 7. Datenkorrekturen: Remakes erben den Bestand ihrer Originale

Gemeldet vom Nutzer, bestätigt in den Daten: Die PokéAPI kennt für die Switch-Remakes
kaum Fundorte — Strahlender Diamant und Leuchtende Perle hatten fünf Arten gegenüber
knapp 300 in Diamant und Perl. Die App behauptete daraufhin, fast der ganze
Sinnoh-Nationaldex sei nur über alte Hardware und damit über Pokémon Bank erreichbar.

Ein Remake ist dasselbe Spiel mit derselben Artenliste. `RemakeObtainabilitySeeder`
überträgt deshalb die Quellen der Originale (Diamant/Perl, Feuerrot/Blattgrün) auf ihre
Neuauflagen, inklusive der Event-Legendären wie Ho-Oh und Lugia auf Eiland 9. Abgelaufene
Events werden **nicht** mitübernommen. Dazu kommen Mew und Jirachi, die es in BDSP über
Speicherstände anderer Switch-Titel ohne Event gibt.

Alle so entstandenen Zeilen tragen `source = remake:<original>` und lassen sich gezielt
wieder entfernen. Der Fundort stammt aus dem Original und kann im Remake abweichen (bei
BDSP oft der Grand Underground) — das steht als Hinweis an jeder Zeile.

Wirkung auf echte Daten: Arten, die nur über Bank-Titel erreichbar sind, fielen von 355
auf 219; beim Demo-Nutzer sanken die 🔴-Fälle von 88 auf 58.

**Offen:** Für *Legenden: Arceus* liefert die PokéAPI keine Fundorte (3 Arten), und der
Titel ist kein Remake — die Spiegelung greift dort nicht. Die Hisui-Liste müsste von Hand
aus Bulbapedia nachgetragen werden. Praktische Auswirkung ist gering, weil der
Sinnoh-Bestand inzwischen über BDSP abgedeckt ist.

### 8. Spielansicht listet nur normale Formen

In der Spiel-für-Spiel-Ansicht standen Regionalformen in der Liste — unter
Ultrasonne etwa das Alola-Rattfratz statt Rattfratz. Ursache war ein
`keyBy(pokemon_id)` über Basis-, Regional- und Sonderformen: pro Art überlebte
die zuletzt einsortierte Form.

Die Zuordnung ist jetzt explizit. Fundorte hängen bei uns an der **Art**, nicht
an einer Form — von 8.256 Zeilen zeigt keine einzige auf eine Form. Eine solche
Quelle gehört zur normalen Form: „Vulpix in Rot" heißt nicht, dass es dort auch
das Alola-Vulpix gäbe. Regionalformen erscheinen deshalb nur, wenn ein Fundort
ausdrücklich auf genau diese Form zeigt.

Dazu ein Schalter „Regionalformen mit anzeigen". Anders als im Pokédex hängt er
nicht an `count_regional_in_total`: diese Seite beantwortet „was kann ich hier
fangen?", und das ist eine Frage auf Artebene.

### 9. Poké Transporter als eigene Hürde vor Pokémon Bank

Vom Nutzer gemeldet: Aus den älteren Generationen kommt man nicht einfach so zu
Pokémon Bank, dafür braucht es zusätzlich die 3DS-App **Poké Transporter**.

Das stimmt und war bisher nicht abgebildet:

| Generation | Weg zu Pokémon Bank |
|---|---|
| 6 und 7 (X/Y, ORAS, S/M, USUM) | lädt selbst hoch — kein Transporter |
| 5 (Schwarz/Weiß, S2/W2) | Poké Transporter |
| 1 und 2 (Virtual Console) | Poké Transporter |
| 3 und 4 | Pal Park / Poké-Transfer → Gen 5 → Poké Transporter |

Wer die App nicht hat, kommt aus diesen Titeln **überhaupt nicht** nach HOME —
für den ist die Bank-Frist dort gegenstandslos, weil schon die Stufe davor
fehlt. Solche Quellen fallen deshalb ganz heraus, statt als 🔴 zu erscheinen,
und zwar mit eigener Begründung, damit der Unterschied zu einem echten
„Event vorbei" sichtbar bleibt. Auch ein besessenes Spiel hilft dann nicht mehr:
🟢 „einfach" wäre gelogen, wenn das Gefangene nie in HOME ankommt.

Neu sind `games.needs_transporter` und die Einstellung `has_poke_transporter`,
**Standard true** — für Bestandsnutzer ändert sich nichts, bis sie den Schalter
umlegen.

---

## 2026-09-07 — Boxraster und dunkle Konto-Seiten

### 10. Der Pokédex zeigt höchstens sechs Pokémon nebeneinander

Vom Nutzer gewünscht: Eine Box auf der Switch fasst **sechs Pokémon pro Reihe**.
Wer nebeneinander abgleicht, was in HOME steht und was die App anzeigt, zählt
sonst dauernd um. Das Raster im Pokédex ging bisher bis `xl:grid-cols-8` und
lief damit an der Box vorbei.

Neu ist bei sechs Schluss — auf großen Schirmen wird die Karte breiter, nicht
die Reihe länger. Auf dem Handy bleiben es zwei bzw. drei Spalten: eine
Sechserreihe auf 375px wäre nicht mehr lesbar, und dort vergleicht ohnehin
niemand mit der Konsole nebendran.

Die Spielansicht bleibt, wie sie ist — sie listet untereinander mit Fundort und
Methode je Zeile, dort gibt es kein Raster zum Ausrichten.

### 11. Login, Registrierung und Profil sind nicht mehr weiß

Vom Nutzer gemeldet: Diese Seiten waren „komplett weiß" — sie stammten noch
unverändert aus dem Breeze-Gerüst, während der Rest der App im dunklen
Pixel-Theme läuft. Wer sich einloggt, bekam also erst eine grelle weiße Seite
und danach die dunkle App.

Umgestellt sind nicht nur die Seiten, sondern die **gemeinsamen Bausteine**
(`x-text-input`, `x-input-label`, `x-primary-button`, `x-secondary-button`,
`x-danger-button`, `x-input-error`, `x-modal`, …). Sie sind der eigentliche
Grund: Solange die Komponenten `bg-white` und `text-gray-700` mitbringen, holt
sich jede neue Seite das Weiß automatisch zurück. Die Bausteine benutzen jetzt
dieselben CSS-Variablen wie der Rest (`--dex-*`), womit auch die vier Themes
(Standard, Game Boy, Game Boy Pocket, CRT-Amber) auf diesen Seiten greifen.

### 12. Die Starterwahl ist ein Geschenk, kein Wildfang

Vom Nutzer gemeldet: Die Sinnoh-Starter standen mit „Wildfang, Lake Verity
Before Galactic Intervention" in der Liste — dort fängt sie aber niemand, man
bekommt sie zu Spielbeginn geschenkt.

Ursache ist die PokéAPI: Sie führt die Übergabe des Starters als regulären
Encounter im jeweiligen Startort. Neben dem kuratierten Geschenk-Eintrag stand
damit eine zweite Zeile, die dasselbe Ereignis falsch benennt — quer durch alle
Generationen (Alabastia, Neuborkia, Vita City, Aquarellia, Iki, Wedeldorf).

`CuratedObtainabilitySeeder` räumt sie jetzt weg. Erkennungsmerkmal ist der
**eine** Fundort: Die Starterwahl passiert an genau einem Ort, während ein
wirklich wild vorkommender Starter mehrere Gebiete nennt. Zusätzlich sind die
Let's-Go-Titel ausgenommen (`STARTER_ALSO_WILD`) — dort laufen Bisasam,
Glumanda und Schiggy tatsächlich herum.

Betroffen waren 97 Zeilen. Der nächste `pokedex:import-encounters` legt sie
wieder an; der Seeder läuft laut README danach und räumt erneut auf.

### 13. Cosmog kommt auch aus den Kronen-Schneelanden

Vom Nutzer gemeldet: Cosmovum hing an der Bank-Frist, obwohl die Linie über
Schwert/Schild erreichbar ist. Stimmt — im DLC *Kronen-Schneelande* steht
Cosmog im Haus in Freezington und wird übergeben, nachdem der Angriff auf das
Dorf beendet ist. Die PokéAPI kennt solche Geschenke nicht.

Neu ist deshalb die Gruppe `GIFTS` im `CuratedObtainabilitySeeder`, vorerst mit
diesem einen Eintrag, samt Notiz „Setzt den Erweiterungspass voraus."

Ein Eintrag genügt für die ganze Linie: Cosmovum, Solgaleo und Lunala erben
ihren Weg über `source_pokemon_id` von Cosmog. Für ein Konto mit Schwert steht
Cosmovum damit auf 🟢 statt an der Frist.

### 14. Omega Rubin und Alpha Saphir verschenken drei weitere Startergruppen

Nachtrag zu 12: Chelast stand dort weiter als „Wildfang, Hoenn Route 101" —
auf Route 101 laufen aber nur Zigzachs, Waumpel und Fiffyen herum.

Der Nutzer erinnerte sich an nachträgliche Starter-Geschenke, war bei den
Generationen unsicher. Die Daten und Bulbapedia sagen übereinstimmend
dasselbe: Prof. Birk verschenkt in drei Schüben, jeweils an einen Fortschritt
gebunden —

| Schub | Starter | Auslöser |
|---|---|---|
| Johto | Endivie, Feurigel, Karnimani | nach dem Ligasieg und dem Treffen mit Amara |
| Einall | Serpifeu, Floink, Ottaro | nach Abschluss der Delta-Episode |
| Sinnoh | Chelast, Panflam, Plinfa | nach dem zweiten Einzug in die Ruhmeshalle |

Genau diese neun Arten — und keine anderen — tragen in den ORAS-Daten der
PokéAPI einen Encounter auf Route 101. Kanto- und Kalos-Starter fehlen dort,
was zur Quelle passt: Sie werden in ORAS nicht verschenkt.

Die drei Gruppen stehen als eigene Konstanten im Seeder, damit jede ihre
eigene Bedingung als Notiz tragen kann. Die falschen Wildfang-Zeilen räumt
derselbe Mechanismus wie in 12 weg.

---

## 2026-09-08 — Musik und ein stillgelegter CI-Job

### 15. Echte Musikstücke statt des synthetisierten Loops

Vom Nutzer gewünscht: ruhige Musik zum Danebenlaufen, mehrere Stücke zum
Durchschalten. Der bisherige Hintergrund-Loop war aus Oszillatoren
zusammengesetzt — vier Takte Bass, acht Töne Melodie. Als Beleg, dass es ohne
fremde Assets geht, war er in Ordnung; zum Danebenlaufen taugt er nicht.

Ersetzt durch drei Stücke aus OpenGameArt, alle **CC0**:

| Titel | Urheber |
|---|---|
| Town Theme | cynicmusic |
| A New Town | cynicmusic |
| Exploring Town | Julie Damsgaard |

**Keine Originalmusik aus den Spielen.** Die Soundtracks gehören
Nintendo/Game Freak/The Pokémon Company. Sprites zeigt die App unter dem
Fan-Projekt-Vorbehalt; vollständige Musikstücke in ein öffentliches Repository
zu legen, ist etwas anderes. Die drei oben sind frei lizenziert und nur in der
Stimmung verwandt.

Die Liste steht in `config/pokedex.php` unter `music`, die Herkunft in
`public/audio/HERKUNFT.md`. Ein weiteres Stück braucht drei Handgriffe: Datei
ablegen, eintragen, dokumentieren — die Kopfzeile liest die Liste aus der
Konfiguration, und der Test prüft mit, ob die Datei auch wirklich da liegt.

Die 8-Bit-Effekte (Fangen, Shiny, Meilenstein) bleiben synthetisiert. Sie sind
kurz, brauchen keinen Request und klingen genau richtig.

### 16. Der Dusk-Job läuft nur noch auf Zuruf

Auf dem GitHub-Runner scheitern fünf der dreizehn Browsertests, während
dieselben dreizehn lokal durchlaufen — auch mit nachgestellter
CI-Konfiguration. Ausgeschlossen sind inzwischen: falsche Datenbank, ein
Serverprozess, der Schrittgrenzen nicht überlebt, und ein unreproduzierbarer
CSS-Build (alle drei waren echte Fehler und sind behoben).

Auf Wunsch des Nutzers läuft der Job jetzt nur noch von Hand
(Actions → CI → „Run workflow"), statt `main` dauerhaft rot zu halten. Die
Tests bleiben erhalten und sind vor größeren Umbauten weiter das Mittel der
Wahl — lokal laufen sie durch.

### 17. Die Konto-Seiten sprechen Deutsch

Nachtrag zu 11: Dunkel waren Anmeldung, Registrierung und Profil danach, aber
die Texte kamen weiter aus dem Breeze-Gerüst — „Email", „Log in", „Remember me".
Der Grund war schlicht: `APP_LOCALE=de` war gesetzt, es gab nur keine
Übersetzungen.

Neu sind `lang/de.json` für die Oberflächentexte und `lang/de/auth.php`,
`passwords.php`, `validation.php` für die Meldungen. Die Validierungsdatei deckt
bewusst nur die Regeln ab, die diese App wirklich benutzt: Was fehlt, fällt über
`APP_FALLBACK_LOCALE=en` auf die englische Fassung zurück — eine halb gepflegte
Volldatei wäre schlechter als eine kurze, die stimmt.

Nebenbei sind die englischen HTML-Kommentare aus dem Gerüst
(`<!-- Confirm Password -->`) durch Blade-Kommentare ersetzt. Die wurden bis in
den Browser mitgeliefert, ohne dort etwas zu tun.

Ein Browsertest musste mit: Dusk drückt Schaltflächen über den *gerenderten*
Text, und der heißt jetzt „REGISTRIEREN" statt „REGISTER".

### 18. Dreizehn Stücke statt drei

Erweiterung von 15. Der Nutzer wollte deutlich mehr zum Durchschalten, in der
Stimmung der ruhigen Stücke aus den neueren Spielen.

Dazugekommen sind zehn Stücke von **Juhani Junkala** aus den CC0-Paketen
*JRPG Music Pack #4 [Calm]* und *#2 [Towns]* — sechs ruhige und vier
Stadt-Themen. Die dem Paket beiliegende INFO.txt nennt CC0 ausdrücklich.

Damit liegen rund **33 MB Audio** im Repo. Das ist der Preis dafür, dass die
Musik ohne fremden Dienst funktioniert; wer klont, lädt sie mit. Ausdünnen geht
jederzeit über die Liste in `config/pokedex.php`, und der Test merkt es, wenn
Datei und Nachweis auseinanderlaufen.

Weiterhin keine Originalmusik aus den Spielen — die Begründung steht in 15.

---

## 2026-09-08 (Abend) — Musik raus, Datenlücken zu

### 19. Die Musik ist wieder raus

Vom Nutzer entschieden: „alles was Musik ist kann raus". Entfernt sind die
dreizehn Dateien (rund 33 MB), die Liste in `config/pokedex.php`, der Player,
die Schalter in der Kopfzeile und die beiden Einstellungen — samt Migration, die
`music_enabled` und `music_volume` aus `user_settings` nimmt.

Die 8-Bit-Effekte beim Fangen bleiben. Sie hängen an `sound_effects_enabled`
und sind davon nicht betroffen.

### 20. Legenden: Z-A, Hisui-Arten und die Dyna-Raids

Drei Meldungen aus der Praxis, alle bestätigt:

**Legenden: Z-A fehlte ganz.** Erschienen am 16.10.2025, die HOME-Anbindung kam
am 02.04.2026 mit HOME 4.0.0 nach. Steht jetzt als Gen-9-Titel in der
Spieleliste. Eine Einschränkung passt nicht ins Modell und ist deshalb nur
kommentiert: In das Spiel *hinein* lassen sich nur Arten aus dem Illumina- und
dem Hyperraum-Dex übertragen. Für diese App zählt die Gegenrichtung, und die ist
offen. Fundorte liefert die PokéAPI für den Titel noch nicht — die Seite sagt
das ehrlich, statt Vollzug zu melden.

**Salmagnis und Cupidos standen unter Schwert.** Beide gibt es nur in Hisui.
Ursache war `pokedex:fill-gaps`: Der Befehl hängt Arten ohne jeden Fundweg ans
Hauptspiel ihrer Generation, und das ist für Generation 8 Schwert/Schild. Alle
sieben Hisui-Exklusiven haben jetzt einen echten Eintrag in Legenden: Arceus,
sind damit keine Lücke mehr, und die falschen Fallback-Zeilen sind weg.

**Die Kronen-Schneelande verschenken Legendäre.** 47 Arten aus den Dyna-Raids
im Max-Lager sind nachgetragen, an *beiden* Editionen. Das ist Absicht: Die
Versionsbindung gilt nur beim eigenen Hosten — wer bei jemand anderem mitgeht,
trifft auch die Arten der anderen Edition. Für die Frage „komme ich da noch
dran?" sind sie in beiden erreichbar; die Notiz an jedem Eintrag sagt es dazu.

### 21. Die Spielansicht zeigt die ganze Entwicklungslinie

Vom Nutzer gemeldet: Legenden: Arceus listete Feurigel, nicht aber Igelavar und
Tornupto — dabei ist die Linie mit dem Starter in der Hand komplett abarbeitbar.

Die Seite zeigt jetzt zusätzlich, was sich hier aus einer Vorstufe entwickeln
lässt, mit „Entwicklung aus Feurigel" in der Fundort-Spalte. Grundlage ist
`source_pokemon_id`, das bis zur Basis der Linie durchgezogen ist — für Tornupto
steht dort Feurigel, nicht Igelavar.

Die Zeilen sind bewusst **nicht** in der Datenbank: Sie beschreiben keinen
Fundort, sondern eine Folgerung aus der Linie. Gespeichert wären sie Redundanz,
die beim nächsten Import auseinanderläuft.
