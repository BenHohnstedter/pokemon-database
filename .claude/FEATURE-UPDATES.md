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
