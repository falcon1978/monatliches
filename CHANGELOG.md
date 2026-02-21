# Changelog

## v2.1.4 - 2026-02-21
- Wiederkehrende Einnahmen: Monats-Override kann jetzt auch auf Forecast-/Verrechnungskonten umgestellt werden (Dropdown im Monats-Edit).

## v2.1.3 - 2026-02-16
- Einnahmen: Forecast-Einträge können in der Monatsansicht beim Bearbeiten auf ein anderes Forecast-/Verrechnungskonto verschoben werden.
- Monatsansicht: Plus-Icon für neue Einnahmen nach oben zum Bereichs-Header verschoben (konsistent zu Rechnungen).
- Kontologik: Verrechnungskonten werden nicht mehr als Ist-Kontostand geführt und erscheinen stattdessen bei erwarteten Einnahmen.

## v2.1.2 - 2026-02-13
- Kontostände: Inline-Editor mit zwei Modi (Rechnen via +/- und Direktüberschreiben).
- Kontostände: Operator-Toggle deutlicher hervorgehoben, aktiver Zustand nutzt Benutzer-Akzentfarbe.
- Kontostände: Ergebnisvorschau im Editor verbessert.

## v2.1.1 - 2026-02-08
- Update: Installierte Version wird nicht mehr auf eine niedrigere Version überschrieben.
- Release-Metadaten im Update-Paket konsistent.

## v2.0.3 - 2026-02-08
- Mobile Monatsnavigation: Kalendericon auch in der Einzelansicht aktiv.
- Mobile Monatsnavigation: "Zur Monatsübersicht" ganz oben, keine Navigation/Monate-Header.

## v2.0.2 - 2026-02-08
- Update-Dialog: Paket automatisch herunterladen und installieren.
- Mobile Profil-Navigation: Update-Menüpunkt ergänzt.

## v2.0.1 - 2026-02-08
- Mobile Monatsansicht: Header/Navi stabilisiert beim Scrollen.

## v1.6.6 - 2026-02-06
- Update-Check: installierte Version robust aus mehreren Quellen ermitteln.
- Update-Installer: Paketversion auch ohne aktuelle updates/latest.json erkennen.

## v1.6.5 - 2026-02-06
- Update-Check: Installed-Version aus localem updates/latest.json ableiten.

## v1.6.4 - 2026-02-06
- Update-Check: installed.lock wird aktualisiert, wenn APP_VERSION höher ist.

## v1.6.3 - 2026-02-06
- Kein „Lebensunterhalt nächster Monat“ für vergangene, nicht aktuelle Monate.

## v1.6.2 - 2026-02-06
- Lebensunterhalt ab Heute nur im aktuell markierten Monat.

## v1.6.1 - 2026-02-06
- Wenn der aktuelle Monat bereits vergangen ist: nächster Monat ab heute berechnen.

## v1.6.0 - 2026-02-06
- Lebensunterhalt: nächste Monatsposten für alle Benutzer, getrennt nach Ferienanteil.
- Ferienhinweise erscheinen zusätzlich im Vormonat.

## v1.5.0 - 2026-02-06
- Ferien: Erfassung mit Lebensunterhalt-Modi (abziehen, belassen, pro Tag benutzerdefiniert).
- Ferien: Anzeige in Monatsübersicht und Monatsansicht; klickbare Ferienkarten.
- Lebensunterhalt: Ferien-Logik für Selbstständige inkl. abgezogener Arbeitstage.

## v1.4.1 - 2026-02-05
- Monatsübersicht: kumulierte Kennzahlen, Dark-Mode-Hover angepasst.
- Monat: Archivieren (Resultat 0.00, vergangen, nicht aktuell) mit Folge-Januar.
- Navigation/Month-Band: neues Layout, mobile optimiert.
- Kontostände: prominenter und volle Breite.

## v1.2.0 - 2026-02-05
- Profil: Beschäftigungstyp (Angestellt/Selbstständig).
- Kumuliert: Arbeitszeit-Kennzahlen bei Selbstständigen ausblenden.

## v1.1.0 - 2026-02-04

- Kontostände sind global pro Konto und werden in der Monatsansicht als Kopfzeile geführt.
- Monatsergebnis standardmäßig ohne Kontostand; aktueller Monat schaltet Kontostand ein.
- Übertrag offener Posten mit Herkunfts-Badge und Rückgängig-Funktion.
- Übertrag gesperrt, solange im Vormonat offene Posten existieren.
- Kontenansicht: „nicht relevant“-Badge entfernt, 0.00 wird normal angezeigt.

## v1.0.0 - 2026-01-27

- Erste veröffentlichte Version.
