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
