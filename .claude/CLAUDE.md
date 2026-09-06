# CLAUDE.md — Pokémon-Sammlungs-Tracker

Kurz-Briefing für Claude Code. Die vollständige Spezifikation steht in `.claude/spec.md` — dort
nachlesen, bevor an einem größeren Feature gearbeitet wird.

## Projekt in einem Satz
Web-App, die trackt, welche Pokémon der Nutzer in Pokémon HOME schon besitzt und wie/wo er die
fehlenden noch bekommt — mit Priorisierung wegen der Pokémon-Bank-Abschaltung am 26./27.02.2027.

## Tech-Stack
- Laravel (PHP) + MySQL/MariaDB, lokale Entwicklung über XAMPP
- Blade-Templates + Tailwind CSS (via Vite) + Alpine.js für Interaktivität
- Auth: Laravel Breeze
- Tests: Pest oder PHPUnit (Unit/Feature) + Laravel Dusk (Browser/E2E)
- CI: GitHub Actions

## Arbeitsweise in diesem Repo
- Vor größeren Aufgaben `.claude/spec.md` lesen (Datenmodell, Prioritäts-Engine, alle Features im
  Detail)
- Neue oder geänderte Feature-Wünsche zuerst in `.claude/feature-updates.md` protokollieren, bevor
  sie inhaltlich in `spec.md` eingearbeitet werden
- Am Ende jeder Session `.claude/progress.md` aktualisieren: was wurde gemacht, was ist offen,
  nächste Schritte
- Kein Feature gilt als fertig ohne passende Tests (Unit/Feature + ggf. Dusk), siehe spec.md
  Abschnitt 7

## Sicherheit — das Repo ist öffentlich
Niemals committen: `.env` mit echten Zugangsdaten, echte DB-Dumps/Seed-Daten mit Nutzerdaten,
API-Keys/Secrets, den lokalen Laravel-`APP_KEY`, persönliche Log-/Debug-Dateien. Details siehe
spec.md Abschnitt 10.

## Repository
https://github.com/BenHohnstedter/pokemon-database
