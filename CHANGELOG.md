# Changelog

Alle relevanten Änderungen an den Helpern werden in dieser Datei dokumentiert.

## 1.6.0 - 2026-07-26

### Added

- `HttpResponseHelper` 1.1.0 mit sicher maskierten HTML-Text-Antworten für OAuth-Callbacks und ähnliche HTTP-Endpunkte.
- Gemeinsame interne Response-Basis für Klartext- und HTML-Text-Antworten mit identischen Sicherheits- und Cache-Headern.
- Tests für HTML-Escaping, Unicode, Statuscodes und beide unterstützten Content-Types.

## 1.5.0 - 2026-07-26

### Added

- `HttpResponseHelper` für kleine HTTP-/WebHook-Antworten aus Symcon-Modulen.
- Klartext-Antworten mit explizitem HTTP-Status, UTF-8-Content-Type, Cache-Schutz und `nosniff`-Header.
- Tests für Antworttext, Statuscode und die vorgesehenen HTTP-Header.

## 1.4.0 - 2026-07-26

### Added

- `VisualizationAssetHelper` zum Laden modulspezifischer Dateien aus dem `visualization`-Verzeichnis.
- Reflection-basierte Auflösung des Verzeichnisses der konkreten Symcon-Modulklasse.
- Debug-Ausgabe und leerer Rückgabewert für nicht lesbare Assets.
- Tests für erfolgreiches Laden, fehlende Dateien und Debug-Ausgabe.

## 1.3.0 - 2026-07-26

### Added

- `ParentConnectionHelper` zum Auslesen der physisch verbundenen Parent-Instanz eines Symcon-Moduls.
- Prüfung, ob die konfigurierte Parent-Instanz tatsächlich noch existiert.
- Tests für gültige, fehlende und nicht mehr vorhandene Parent-Verbindungen.

## 1.2.0 - 2026-07-26

### Added

- `VariablePresentationHelper` für wiederverwendbare native Symcon-Darstellungen ohne Legacy-Profile.
- Hilfen für Boolean-Beschriftungen, Text, Webinhalt und Datum/Uhrzeit einschließlich nativer DateTime-Templates.
- Parametrisierbare WebContent- und DateTime-Darstellungen mit Validierung der Symcon-Wertebereiche.
- Tests für Standardwerte, optionale Parameter, Unicode und ungültige Darstellungswerte.

## 1.1.0 - 2026-07-25

### Added

- `ConfigurationFormHelper` zum sicheren Laden und Serialisieren dynamischer Symcon-Konfigurationsformulare.
- Reflection-basierte Auflösung der `form.json` relativ zur konkreten Modulklasse.
- Validierung, dass die Konfigurationsform an der JSON-Wurzel ein Objekt enthält.
- Tests für fehlende Dateien, ungültiges JSON, ungültige Wurzeltypen, leere Formulare und Round-Trips.

## 1.0.0 - 2026-07-25

### Added

- `PersistentJsonCacheHelper` zum persistenten Speichern strukturierter Array-Daten in Symcon-Attributen.
- Änderungserkennung, damit identische Inhalte nicht erneut geschrieben werden.
- Tests für Registrierung, Lesen, Schreiben, Löschen, Unicode, Float-Erhalt und ungültige JSON-Daten.
- GitHub-Actions-Testlauf mit PHP 8.5.
