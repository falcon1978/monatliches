# Monatliches

Monatliches ist ein schlankes Budget- und Monatsplanungs-Tool auf Laravel-Basis. Open Source, pragmatisch und für Selbsthosting gebaut.

## Kurzüberblick

- Monatsübersicht mit genau einem **aktuellen Monat**.
- Einnahmen, Ausgaben, Fixkosten und Transfers pro Monat.
- Konten mit **globalen Kontoständen** (nicht pro Monat).
- Übertragen offener Posten in den nächsten Monat (inkl. Rückgängig‑Funktion).
- Wiederkehrende Vorlagen für monatliche Posten.
- Ferien & Lebensunterhalt (Abzug, Beibehalten oder eigener Tagessatz).
- Kennzahlen pro Monat (z. B. offen, bezahlt, kumuliert ab heute).
- Nutzerprofil inkl. Akzentfarbe und Profilbild.
- Adminbereich für User‑Management und In‑App‑Updates.

## Mobile‑First UI (App‑like)

- **Bottom‑Navigation** (Profil, Monatsübersicht/Kalender, Wiederkehrende, Konten, Ferien, + Neu).
- **Bottom‑Sheets** für Aktionen/Navigation; öffnen **oberhalb** der Navi (Navi bleibt sichtbar).
- **Monatsauswahl** über Kalender‑Icon als kompaktes Sheet (Übersicht + Monate, keine Archive).
- **Monatsansicht** auf Mobile als Card‑Listen statt Tabellen, kompakte Einträge.
- **Sticky Abschnittstitel** und **Sticky Untertitel** (zeigen immer den aktuellen Bereich).
- **Swipe** zwischen Monaten (vor/zurück, nur nicht‑archivierte).
- **Kontostände** werden in der Monatsansicht als editierbare Boxen gezeigt (nicht in der Kontenübersicht).
- **Wiederkehrende**: mobile Übersicht im Karten‑Stil, keine umgebende Box, sticky Titel.
- **Labels**: „Forecast“ heißt jetzt **„Erwartet“** (z. B. Konten‑Typ, Abschnittstitel).
- **+ Neu** ist kontextsensitiv (z. B. Monat: Rechnung/Zahlung/Erwartete Einnahme; Ferien/Konten/Wiederkehrende: direkt erfassen).

## Wichtige Konzepte

- Es gibt genau einen **aktuellen Monat**. Nur dieser Monat rechnet den Kontostand ins Monatsergebnis ein.
- Das **Monatsergebnis** ist standardmäßig ohne Kontostand. **Kumuliert ab heute** enthält den Kontostand immer.
- **Kontostände sind global pro Konto** (kein Kontostand pro Monat).
- **Übertragen** verschiebt offene Posten in den nächsten Monat, inklusive Badge „Aus <Monat>“.
- Übertragen ist gesperrt, solange im Vormonat noch offene Posten existieren.
- Ein Übertrag kann wieder **rückgängig** gemacht werden.
 - Wiederkehrende Posten können bei Erstellung/Bearbeitung automatisch in **aktuelle und zukünftige** Monate übernommen werden.

## Demo

Demo‑Login:

- URL: [demo.monatlich.es](https://demo.monatlich.es)
- E‑Mail: `demo@monatlich.es`
- Passwort: `demo1234`
- Hinweis: Die Datenbank wird stündlich zurückgesetzt.

## Gehostete Variante

Wenn du keine eigene Installation möchtest, gibt es eine gehostete Variante für **CHF 6.– / Monat**.

- Wunsch‑Subdomain: `xxx.monatlich.es`
- Kontakt: `cv@vitalmedia.ch`

## Installation (Self‑Hosting)

1. Lade die Release‑Datei `monatliches-dist-vX.Y.Z.zip` aus den GitHub Releases herunter.
2. Entpacke die ZIP in dein Webverzeichnis (z. B. `example.com/monatlich`).
3. Lege im Hosting‑Panel eine MySQL‑Datenbank + Benutzer an.
4. Öffne im Browser: `https://example.com/monatlich/install`.
5. Folge dem Installer (Systemcheck → DB → App → Migration → Admin).

Hinweis: Wenn die App in einem Unterordner läuft, muss `APP_URL` diesen Pfad enthalten.

## Technische Anforderungen

- PHP **>= 8.2**
- MySQL/MariaDB
- Webserver mit Schreibrechten auf `storage/` und `bootstrap/cache/`

## Entwicklung (für Devs)

Schnellstart:

```bash
composer run setup
```

Manuell:

```bash
composer install
npm ci
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Lokaler Dev‑Stack:

```bash
composer run dev
```

## Hosting im Unterordner

Wenn die App unter `https://example.com/monatlich` läuft, muss `APP_URL` genau so gesetzt sein.

## Updates

1. Neues `monatliches-dist-vX.Y.Z.zip` herunterladen.
2. Im Admin‑Bereich unter **Update** das ZIP hochladen.
3. Das Update wird direkt nach dem Upload installiert.

Dabei `.env` und `storage/` behalten. Migrationen werden beim Update automatisch ausgeführt.

## Release-/Dist-Konzept

- Das GitHub‑Repo ist die Source (ohne `vendor/` und ohne `node_modules/`).
- GitHub Releases enthalten `monatliches-dist-vX.Y.Z.zip` inklusive `vendor/` und `public/build/`, aber ohne `.env`.
- Lokal kann ein Release mit `tools/build-dist.sh` gebaut werden.

## Troubleshooting

- Profilbilder fehlen: `php artisan storage:link` ausführen und prüfen, ob `public/storage` öffentlich erreichbar ist.
- Installer hängt: Schreibrechte auf `storage/` und `bootstrap/cache/` prüfen.

## Local test plan

- `storage/app/installed.lock` löschen
- `/install` durchlaufen
- Login prüfen
- Profilbild‑Upload prüfen
