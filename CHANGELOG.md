# Changelog

Alle relevanten Änderungen an den Helpern werden in dieser Datei dokumentiert.

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
