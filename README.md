# Monatliches

Monatliches ist ein schlankes Budget- und Monatsplanungs-Tool auf Laravel-Basis.

## Wichtige Konzepte

- Es gibt genau einen **aktuellen Monat**. Nur dieser Monat rechnet den Kontostand ins Monatsergebnis ein.
- Das **Monatsergebnis** ist standardmäßig ohne Kontostand. **Kumuliert ab heute** enthält den Kontostand immer.
- **Kontostände sind global pro Konto** (kein Kontostand pro Monat).
- **Übertragen** verschiebt offene Posten in den nächsten Monat, inklusive Badge „Aus <Monat>“.
- Übertragen ist gesperrt, solange im Vormonat noch offene Posten existieren.
- Ein Übertrag kann wieder **rückgängig** gemacht werden.

## Installation (für Laien)

1. Lade die Release-Datei `monatliches-dist-vX.Y.Z.zip` aus den GitHub Releases herunter.
2. Entpacke die ZIP in dein Webverzeichnis (z. B. `example.com/budget`).
3. Lege im Hosting-Panel eine MySQL-Datenbank + Benutzer an.
4. Öffne im Browser: `https://example.com/budget/install`
5. Folge dem Installer (Systemcheck → DB → App → Migration → Admin).

Hinweis: Wenn die App in einem Unterordner läuft, muss `APP_URL` diesen Pfad enthalten.

## Installation (für Devs)

```bash
composer install
npm ci
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Hosting im Unterordner

Wenn die App unter `https://example.com/budget` läuft, muss `APP_URL` genau so gesetzt sein.

## Updates

Im Admin-Bereich unter **Update** kann ein neues `monatliches-dist-vX.Y.Z.zip` hochgeladen werden.
Dabei `.env` und `storage/` behalten. Danach ggf. Migrationen ausführen (`php artisan migrate --force`).

## Release-/Dist-Konzept

- Das GitHub-Repo ist die Source (ohne `vendor/` und ohne `node_modules/`).
- GitHub Releases enthalten `monatliches-dist-vX.Y.Z.zip` inklusive `vendor/` und `public/build/`, aber ohne `.env`.
- Lokal kann ein Release mit `tools/build-dist.sh` gebaut werden.

## Local test plan

- `storage/app/installed.lock` löschen
- `/install` durchlaufen
- Login prüfen
