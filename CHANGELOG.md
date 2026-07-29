# Changelog

Alle relevanten Änderungen an den Helpern werden in dieser Datei dokumentiert.

## 3.0.0 - 2026-07-29

### Added

- `HelperTranslationHelper` Version 1.0.0 für helper-eigene Übersetzungen ohne Nachträge in den `locale.json`-Dateien der Consumer-Module.
- Zentraler JSON-Übersetzungskatalog für `IPSViewStyleHelper` mit vollständigen englischen und deutschen Formulartexten.
- Automatischer Übersetzungsworkflow über GitHub Models: Neue oder geänderte englische Helper-Texte werden als deutscher Übersetzungs-PR vorgeschlagen.
- CI-Prüfung auf fehlende, veraltete oder nicht synchronisierte Übersetzungsschlüssel sowie abweichende Platzhalter.
- Manifest-Unterstützung für Helper-Abhängigkeiten und zusätzliche Assets wie Übersetzungskataloge.

### Fixed

- Abhängige Helper werden im Consumer-Manifest nun innerhalb des abonnierten Helper-Bundles dokumentiert, statt als zusätzliche Subscription aufzutauchen. Dadurch bleiben bestehende Integritätsprüfungen mit exakter Subscription-/Manifest-Zuordnung gültig.
- Änderungen an der Sync-Logik lösen automatisch einen erneuten Abgleich der Consumer aus, sodass fehlerhafte Bot-PRs ohne manuelle Änderungen in den Ziel-Repositories aktualisiert werden.

### Changed

- `IPSViewStyleHelper` auf Version 1.2.0 erweitert und vollständig auf helper-eigene Übersetzungen umgestellt.
- Der automatische Vendor-Sync verteilt nun benötigte Helper-Abhängigkeiten und Übersetzungsdateien gemeinsam mit dem abonnierten Helper.
- Änderungen am zentralen Übersetzungskatalog lösen automatisch eine Aktualisierung der betroffenen Consumer aus.

## 2.9.0 - 2026-07-29

### Changed

- `IPSViewStyleHelper` auf Version 1.1.0 erweitert.
- Benutzerdefinierte Stile unterstützen nun getrennte Deckkraftwerte für View-, Seiten-, Label-, normale/aktive/inaktive Steuerelement-, Popup-, Rahmen-, Linien- und Popup-Rahmenfarben.
- Schatten und Popup-Schatten besitzen eigene zentral verwaltete Deckkraftwerte; die bisherigen Standardwerte von 24 % beziehungsweise 32 % bleiben kompatibel erhalten.
- Die resultierenden RGBA-Farben und alle aufgelösten Deckkraftwerte werden als gemeinsame `--ipsview-*`-CSS-Variablen ausgegeben.
- Importierte IPSView-Standardstile behalten weiterhin ihre in der `.ipsView`-Datei gespeicherten Alpha-Werte und werden nicht durch benutzerdefinierte Deckkraftwerte überschrieben.
- Regressionstests für Standardwerte, Formularfelder, RGBA-Ausgabe, vollständige Transparenz, CSS-Tokens und IPSView-Alpha-Import ergänzt.

## 2.8.0 - 2026-07-29

### Added

- `IPSViewHTMLPageHelper` Version 1.0.0 für eine gemeinsame technische Erzeugung nativer HTML-SDK- und eigenständiger IPSView-Seiten.
- Einheitlicher Asset-Vertrag aus `visualization/index.html`, `style.css` und `app.js` sowie validierte zentrale Template-Platzhalter.
- Gemeinsamer Bootstrap-Vertrag mit Version, Modus, Zustand, Laufzeitkonfiguration, Übersetzungen und Optionen.
- Sichere JSON-Einbettung mit HTML-Schutzflags, Unicode-/Slash-Erhalt und bewahrten Float-Nachkommastellen.
- Gemeinsame Sprach-, Viewport-, Klassen- und Schriftgrößenbehandlung für native und IPSView-Darstellungen.
- Wiederverwendbare Übersetzungserzeugung aus der `locale.json` sowie strikte Prüfung unbekannter Konfigurationswerte und nicht aufgelöster Platzhalter.
- Regressionstests für beide Darstellungsmodi, Asset-Einbettung, Bootstrap, JSON-Sicherheit, Übersetzungen und Fehlerfälle.

## 2.7.0 - 2026-07-29

### Added

- `IPSViewStyleHelper` Version 1.0.0 als universelles Stilsystem für eigenständige IPSView-HTML-Seiten.
- Zentrale Stilquellen für benutzerdefinierte Werte, helles und dunkles Preset sowie den globalen Standardstil aus einem ausgewählten `.ipsView`-Medienobjekt.
- Whitelist-basierter Import von IPSView-Seiten-, Label-, Steuerelement-, Text-, Icon-, Rahmen-, Linien-, Popup-, Schrift- und Schattenwerten ohne Übernahme sonstiger View-Daten.
- Universelle semantische Rollen für Akzent, Information, positiv, Warnung und kritisch einschließlich Kontrastfarben, Softfarben und zentral erzeugter Verläufe.
- Gemeinsame Typografie-, Rahmen-, Linien-, Schatten-, Opacity- und Verlaufseinstellungen, sodass Consumer keine eigenen Stilwerte mehr vorgeben müssen.
- Aktualisierungsüberwachung für ausgewählte IPSView-Medienobjekte und kompatible Alias-Tokens für bestehende Consumer.
- Regressionstests für Registrierung, Formularstruktur, Presets, Medienimport, Favoritenfarben, Typografie, Schatten, Base64-Dokumente, CSS-Ausgabe und Medienupdates.

### Changed

- `IPSViewColorPaletteHelper` bleibt als kompatibler Vorgänger erhalten; neue Consumer sollen auf `IPSViewStyleHelper` migriert werden.

## 2.6.0 - 2026-07-29

### Added

- `IPSViewColorPaletteHelper` Version 1.0.0 für wiederverwendbare IPSView-Farbkonfigurationen in eigenständigen HTML-Seiten.
- Neun `SelectColor`-kompatible Integer-Properties und dynamisch einbindbare Formularelemente für Seiten-, Flächen-, Text-, Akzent- und Statusfarben.
- Gemeinsame Kontrastberechnung für abgestufte Flächen sowie primäre, sekundäre und dezente Schriftfarben.
- Wiederverwendbare `--ipsview-*`-CSS-Variablen mit transparentem Hintergrundmodus, Rahmen- und Softfarben.
- Regressionstests für Registrierung, Formularstruktur, Farbnormalisierung, Kontrast, CSS-Ausgabe und ungültige Eingaben.

## 2.5.0 - 2026-07-28

### Added

- `VisualizationThemeHelper` Version 1.0.0 für ein gemeinsames Symcon-nahes Design von HTML-SDK-Visualisierungen.
- Native Symcon-Farbvariablen mit Light-/Dark-Fallbacks sowie wiederverwendbare Tokens für Oberflächen, Rahmen, Statusfarben, Radien und Fokusdarstellung.
- Regressionstests für die zentralen Theme-Tokens und die Anbindung an Symcon-Farbvariablen.

## 2.4.0 - 2026-07-28

### Added

- `SymconOAuthHelper` Version 1.0.0 für zentral registrierte OAuth-Anbieter über den Symcon-OAuth-Dienst.
- Generierung der lizenzbezogenen Autorisierungs-URL ohne Offenlegung zentraler Client-Zugangsdaten.
- Austausch von Autorisierungscodes und Refresh-Tokens mit Validierung von HTTP-, JSON-, Bearer- und Ablaufzeitdaten.
- Transportinjektion für die Wiederverwendung vorhandener, modulspezifisch abgesicherter HTTP-Clients.
- Regressionstests für Autorisierung, Tokenaustausch, Tokenrotation, Fehlerantworten und ungültige Transportdaten.

### Fixed

- Ungültiges JSON in der Consumer-Liste des automatischen Helper-Syncs korrigiert.
- `OpenHomeAlarm` in die Repository-Berechtigungen des Helper-Sync-Workflows aufgenommen.

## 2.3.0 - 2026-07-26

### Added

- `DataFlowHelper` Version 1.0.0 für einheitliche JSON-Transportpakete im Symcon-Datenfluss.
- `EncodeDataFlowMessage()` erzeugt Nachrichten mit einer zentral verwalteten `DataID`, Unicode-/Slash-Erhalt und `JSON_PRESERVE_ZERO_FRACTION`.
- `DecodeDataFlowMessage()` validiert JSON-Objekt, `DataID` und optional die erwartete `DataID` und liefert verschachtelte Objekte als assoziative Arrays.
- Regressionstests für Encoding, Decoding, DataID-Validierung, ungültige JSON-Wurzeln und doppelte DataID-Felder.

## 2.2.0 - 2026-07-26

### Changed

- `VariableHelper` auf Version 1.2.0 erweitert.
- Sichere Werteleser lösen Variablen weiterhin per Ident und optionaler Parent-ID auf und liefern bei fehlenden oder typinkompatiblen Werten definierte Standardwerte.

### Added

- `GetVariableValueByIdent()` für typunveränderte Rohwerte.
- `GetBooleanVariableValueByIdent()` für native Boolean- sowie numerische 0/1-Zustände.
- `GetFloatVariableValueByIdent()` für Integer-/Float-Werte mit Normalisierung auf Float.
- `GetIntegerVariableValueByIdent()` und `GetStringVariableValueByIdent()` für typsichere Zugriffe ohne implizite Konvertierung.
- Regressionstests für Defaultwerte, Typprüfung, numerische Boolean-Zustände und explizite Parent-IDs.

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
