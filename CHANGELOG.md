# Changelog

Alle relevanten Änderungen an den Helpern werden in dieser Datei dokumentiert.

## 2.1.0 - 2026-07-26

- Add a machine-readable helper manifest.
- Add centralized vendor synchronization for subscribed consumer repositories.
- Create/update pull requests against consumer `dev` branches when helper sources change.
- Validate helper versions and SHA-256 hashes in CI.

## 2.0.0 - 2026-07-26

### Changed

- `VariablePresentationHelper` auf Version 2.0.0 erweitert und mit den allgemein nutzbaren Darstellungsfunktionen aus `IPS_Wolf_WSR1` zusammengeführt.
- Bestehende API für Boolean, Text, WebContent und DateTime vollständig beibehalten.
- Geräte-, Expose-, Feature-, Preset- und Property-spezifische Logik bewusst nicht in den zentralen Helper übernommen.

### Added

- Generische numerische `ValuePresentation()` sowie Komfortmethoden für Temperatur, Prozent, Drehzahl, Integer und Dezimalwerte.
- Native Slider-Darstellung einschließlich Gradient, Usage-Type, Einheiten, Stellenzahl und Intervallen.
- Komfortdarstellungen für Helligkeit und Kelvin-Farbtemperatur mit automatisch erzeugtem Warm-/Kalt-Gradienten.
- Native Darstellungen für Farbe, Dauer, Schalter, Rollladen und Aufzählungen.
- `OptionsPresentation()` für read-only Boolean-/String-Zustände mit frei definierbaren Optionslisten.
- Umfangreiche Regressionstests für die neuen Darstellungen, Standardwerte, JSON-Daten und Parameter-Validierung.

## 1.9.1 - 2026-07-26

### Changed

- Test-Runner bereinigt: `PersistentJsonCacheHelper` besitzt jetzt wie alle anderen Helper eine eigene Testdatei.
- Gemeinsame Test-Assertions nach `tests/bootstrap.php` ausgelagert und alle acht Helper-Testgruppen explizit im zentralen Runner registriert.
- GitHub Actions um die Symcon-PHP-Style-Prüfung mit `symcon/action-style@v3` ergänzt.
- Checkout-Action des bestehenden PHP-Testworkflows auf `actions/checkout@v6` aktualisiert.

## 1.9.0 - 2026-07-26

### Added

- `DateHelper` zum einheitlichen Formatieren von Datumswerten aus APIs und Konfigurationsquellen.
- Standardausgabe im Format `d.m.Y` mit optional frei wählbarem `DateTime`-Ausgabeformat.
- Unveränderte Rückgabe nicht interpretierbarer Datumsstrings sowie leerer Rückgabewert für leere oder Nicht-String-Werte.
- Tests für Standardformat, benutzerdefiniertes Format und Fallback-Verhalten.

## 1.8.0 - 2026-07-26

### Changed

- `VariableHelper` auf Version 1.1.0 erweitert.
- `GetVariableIDByIdent()` und `VariableExists()` unterstützen jetzt optional eine explizite Parent-ID für Variablen unterhalb anderer Symcon-Objekte.
- Gefundene Objekt-IDs werden zusätzlich mit `IPS_VariableExists()` validiert, sodass nur echte Variablen zurückgegeben werden.
- Tests für explizite Parent-IDs und gleichnamige Nicht-Variablen ergänzt.

## 1.7.0 - 2026-07-26

### Added

- `VariableHelper` zum einheitlichen Auflösen von Variablen über ihren Ident relativ zur aktuellen Symcon-Modulinstanz.
- Normalisierung fehlgeschlagener `IPS_GetObjectIDByIdent()`-Lookups auf die ID `0`.
- Existenzprüfung für Variablen ohne wiederholte direkte Objekt-Lookups im aufrufenden Modul.
- Tests für vorhandene, fehlende und ungültige Variablen-IDs sowie unterschiedliche Modulinstanzen.

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
