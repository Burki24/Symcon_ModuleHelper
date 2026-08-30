# Symcon ModuleHelper & IPSView – Project Status

> Zentrale Arbeits- und Übergabedatei für die schrittweise Zentralisierung von IPSView-Fonts, Stilquellen und Style-Profilen.
>
> **Maßgeblicher Arbeitsstand:** `Symcon_ModuleHelper/dev`
>
> Diese Datei wird nach jedem abgeschlossenen Teilabschnitt aktualisiert. In einem neuen Chat ist sie zuerst zu lesen, damit unmittelbar am letzten Stand weitergearbeitet werden kann.

## Aktueller Stand

**Datum:** 2026-08-30  
**Phase:** Paket A – Zentraler Fontkatalog  
**Status:** A1–A4 technisch umgesetzt; A3 ist zur Übernahme nach `Symcon_ModuleHelper/dev` vorbereitet  
**Nächster Schritt:** A3-Dateien nach `dev` übernehmen und anschließend A5/A6 mit dem tatsächlichen Repository-Stand, vollständigen Helper-Tests und StylePHP abschließen.

---

## 1. Verbindliche Arbeitsregeln

### Branch- und Release-Modell

- `dev` ist der Entwicklungs- und Integrationsbranch des `Symcon_ModuleHelper`.
- `main` enthält ausschließlich freigegebene/verteilbare Stände des `Symcon_ModuleHelper`.
- Für den `IPSViewAssistant` ist `dev-popup` der maßgebliche Entwicklungsbranch.
- `IPSViewAssistant/dev-popup` setzt Symcon 9.1 oder neuer voraus.
- Der `IPSViewAssistant` wird nicht vor Symcon 9.1 veröffentlicht; eine Rückportierung der aktuellen Pakete auf den 9.0-kompatiblen Assistant-Branch `dev` ist daher nicht vorgesehen.
- Änderungen werden während der Entwicklung **nicht direkt auf `main`** vorgenommen.
- Die automatische Helper-Versionierung bleibt ausschließlich auf `main`.
- Die automatische Verteilung der Helper an Consumer-Repositories bleibt ausschließlich auf `main`.
- Auf `dev` wird mit normalen, thematisch sauberen Commits gearbeitet.
- Erst ein vollständig getestetes Arbeitspaket wird von `dev` nach `main` übernommen.
- Dadurch erzeugen Zwischenstände auf `dev` keine künstlichen Helper-Versionen und werden nicht an Consumer verteilt.

### Maßgebliche Branches je Repository

| Repository | Arbeitsbranch | Zielplattform / Bedeutung |
| --- | --- | --- |
| `Symcon_ModuleHelper` | `dev` | Entwicklung und Integration; keine automatische Consumer-Verteilung |
| `Symcon_ModuleHelper` | `main` | freigegebener, automatisch versionierter und verteilter Stand |
| `IPSViewAssistant` | `dev-popup` | maßgeblicher Entwicklungsstand; Symcon 9.1+ |
| `IPSViewAssistant` | `dev` | älterer Symcon-9.0-kompatibler Stand; für Pakete A–F nicht maßgeblich |

### Qualitätsregeln vor Freigabe

Vor der Übernahme eines Pakets nach `main` sind mindestens zu prüfen:

- PHP-Syntax
- vorhandene Repository-Tests
- neue paketbezogene Tests
- offizielle Symcon-StylePHP-Regeln
- insbesondere `ordered_imports`, `braces_position`, `method_argument_space`, `binary_operator_spaces` und überflüssige Leerzeilen
- Abwärtskompatibilität bestehender Consumer

### Auslieferung / Änderungen

- Keine Patch- oder Diff-Dateien als Endergebnis.
- Bei Datei-Auslieferungen vollständige geänderte Dateien verwenden.
- Für abgeschlossene Arbeitspakete einen kurzen englischen Commit-/PR-Betreff angeben.
- `PROJECT_STATUS.md` nach jedem wesentlichen Teilabschnitt aktualisieren.

---

## 2. Projektziel

Fonts, Stilquellen und universelle IPSView-Styledefinitionen sollen aus einzelnen Consumer-/Editor-Projekten in eine gemeinsame zentrale Basis im `Symcon_ModuleHelper` überführt werden.

Zielbild:

- Der `Symcon_ModuleHelper` definiert den universellen Style-Contract.
- Der `Symcon_ModuleHelper` definiert den zentralen IPSView-Fontkatalog.
- Der `IPSViewStyleHelper` konsumiert diese zentralen Definitionen.
- Der `IPSViewAssistant` verwendet dieselben Definitionen und wird zum Editor/Erzeuger von Style-Profilen.
- Style-Profile können exportiert, zentral gespeichert, importiert und von mehreren Modulen verwendet werden.
- Consumer wie OpenCalendar oder OpenHomeAlarm können später dasselbe zentrale Style-Profil verwenden.

---

## 3. Bestätigter Ausgangsstand

### Zentrale IPSView-Helfer im `Symcon_ModuleHelper`

Relevante Dateien:

- `src/IPSViewStyleHelper.php`
- `src/IPSViewColorPaletteHelper.php`
- `src/IPSViewHTMLPageHelper.php`

Der `IPSViewStyleHelper` besitzt aktuell vier Stilquellen:

```php
IPSVIEW_STYLE_SOURCE_CUSTOM = 0;
IPSVIEW_STYLE_SOURCE_MEDIA  = 1;
IPSVIEW_STYLE_SOURCE_LIGHT  = 2;
IPSVIEW_STYLE_SOURCE_DARK   = 3;
```

Diese numerischen Werte müssen aus Gründen der Abwärtskompatibilität erhalten bleiben.

Der aktuelle zentrale Helper besitzt zwar `IPSViewStyleFontFamily`, verwendet dafür aber nur ein freies Textfeld. Ein zentraler Fontkatalog mit gültigen Schnitten existiert dort noch nicht.

### IPSViewAssistant – untersuchter Stand

Untersuchtes Paket:

`202608300616_IVA.zip`

Im Assistant existieren aktuell getrennte lokale Bereiche:

- `libs/IPSViewTheme.php` – Themes/Farben
- `libs/IPSViewTypography.php` – Fonts und Fontfähigkeiten
- `libs/IPSViewEffects.php` – Schatten, Transparenz, Verlauf
- `libs/IPSViewShape.php` – Rundungen/Rahmen
- `libs/IPSViewBackground.php` – Hintergrundbilder

Die Fontlogik wurde im Assistant bereits vollständig umgesetzt. Historisch relevante Commits im Paket:

- `3101fcb` – `Align font selection with IPSView catalogue` (2026-08-02)
- `ab42351` – `Add IPSView font styles and original Segment7 preview` (2026-08-03)

### Verifizierter IPSView-Fontkatalog

Folgende Fontfamilien sind bestätigt:

| Font | Regular | Bold | Italic | Bold Italic |
|---|---:|---:|---:|---:|
| Roboto | ✓ | ✓ | ✓ | ✓ |
| Roboto Mono | ✓ | ✓ | ✓ | ✓ |
| Open Sans | ✓ | ✓ | ✓ | ✓ |
| PT Sans | ✓ | ✓ | ✓ | ✓ |
| Dancing Script | ✓ | ✓ | – | – |
| Bebas Neue | ✓ | – | – | – |
| Indie Flower | ✓ | – | – | – |
| Segment7 | ✓ | – | – | – |

Die Original-TTF-Dateien bleiben zunächst Assistant-spezifisch, da sie dort für die Browser-/SVG-Vorschau benötigt werden. Der zentrale Helper soll primär Fontnamen, Fontfähigkeiten, Validierung und Auswahl bereitstellen.

### Frühere Stilquellen-Diskussion

In früheren Arbeiten wurde konzeptionell zwischen `IPSView`, `IPSStudio` und `Import` als Quellen unterschieden. In der Git-Historie des zentralen `IPSViewStyleHelper` und des untersuchten Assistant-Stands ließ sich jedoch kein tatsächlich committed zentraler `IPSStudio`-Source-Zweig nachweisen.

Daher gilt vorerst:

- Keine künstliche Wiederherstellung einer vermeintlich entfernten `IPSStudio`-Quelle.
- Bestehende zentrale Quellen bleiben erhalten.
- Neue Quellen werden nur auf Basis eines klar definierten Datenmodells ergänzt.

---

## 4. Gesamtagenda

### Paket A – Zentraler Fontkatalog

**Ziel:** Fonts und Fontfähigkeiten existieren nur noch als gemeinsame zentrale Definition.

#### A1 – Zentralen Fontkatalog definieren

- [x] Geeigneten Ort/API im `Symcon_ModuleHelper` festlegen: eigenständiger `IPSViewFontCatalogHelper` ohne Style-/HTML-Abhängigkeit.
- [x] Acht bestätigte Fontfamilien zentral abbilden.
- [x] Verfügbare Schnitte/Fähigkeiten zentral abbilden.
- [x] Kanonische IPSView-Dokumentwerte und lesbare Anzeigenamen getrennt abbilden.

#### A2 – Gemeinsame Font-API implementieren

Vorgesehene Fähigkeiten:

- [x] verfügbare Fontfamilien liefern
- [x] verfügbare Schnitte für eine Fontfamilie liefern
- [x] Fontfamilie validieren
- [x] Font-Schnitt validieren
- [x] Fontwerte normalisieren/Fallback bereitstellen

Die endgültigen Methodennamen werden passend zur bestehenden Helper-Architektur gewählt.

#### A3 – `IPSViewStyleHelper` auf zentralen Katalog umstellen

- [x] freies `FontFamily`-Textfeld durch eine definierte Auswahl aus `IPSViewFontCatalogHelper` ersetzen
- [x] Font-Schnitt für Paket A bewusst **nicht** als neue `IPSViewStyleHelper`-Property einführen: Die Fähigkeiten sind zentral katalogisiert und werden im Assistant bereits genutzt; die universelle `FontStyle`-Semantik wird erst mit dem versionsfähigen Style-Contract in Paket B verbindlich definiert.
- [x] bestehende gespeicherte Werte weiterhin lesen
- [x] bekannte frühere Schreibweisen wie `Open Sans`, `PT Sans` oder `Roboto Mono` auf die kanonischen IPSView-Werte normalisieren
- [x] sichere bisherige benutzerdefinierte Fontwerte als Kompatibilitätsoption erhalten
- [x] ungültige/unsichere Werte auf den bisherigen System-Fontstack zurückfallen lassen
- [x] keine bestehenden Property-Namen oder Source-IDs verändern

#### A4 – IPSViewAssistant umstellen

- [x] lokale Font-Matrix aus `IPSViewTypography.php` entfernen bzw. auf zentrale Definition delegieren
- [x] Assistant-Auswahl vollständig aus zentralem Fontkatalog erzeugen
- [x] Bold/Italic nur anbieten, wenn vom zentralen Katalog erlaubt
- [x] lokale Font-Dateipfade/TTFs für Vorschau beibehalten
- [x] SVG-/Browser-Vorschau unverändert funktionsfähig halten

#### A5 – Abwärtskompatibilität

- [x] vorhandene Assistant-Instanzen behalten ihre Fontauswahl (numerische Assistant-Font-IDs 0–8 unverändert)
- [ ] vorhandene Module mit `IPSViewStyleHelper` im vollständigen Repository-/Consumer-Test bestätigen
- [x] bestehende `IPSViewStyleFontFamily`-Property unverändert beibehalten
- [x] bestehende Style-Source-IDs 0–3 unverändert beibehalten
- [x] bekannte frühere Font-Aliase ohne Konfigurationsmigration lesen
- [x] sichere bisherige freie Fontwerte weiterhin lesen und im Formular anzeigen
- [x] unsichere/veraltete Werte sicher auf den bisherigen System-Fontstack zurückführen

#### A6 – Tests und Freigabe

- [x] zentralen Fontkatalog testen
- [x] alle Fontfamilien testen
- [x] alle erlaubten Schnitte testen
- [x] nicht erlaubte Schnitte testen
- [x] Fallbacks testen
- [x] Assistant-Tests für A4 ausführen
- [x] gezielte A3-Integrationstests lokal ausführen
- [ ] vollständige Helper-Tests auf dem übernommenen `dev`-Stand ausführen
- [ ] offiziellen Symcon-StylePHP-Endstand prüfen
- [ ] Assistant-Tests nach finalem Helper-Stand erneut ausführen
- [x] `PROJECT_STATUS.md` nach A3 aktualisieren

**Freigabekriterium Paket A:** Helper und Assistant verwenden dieselbe Fontdefinition; bestehende Instanzen funktionieren weiter.

---

### Paket B – Style Profile Contract V1

**Ziel:** Ein versionsfähiges, universelles Austauschformat für IPSView-Styles definieren.

- [ ] Schema `burki24.ipsview-style` festlegen
- [ ] `version = 1` definieren
- [ ] Metadaten definieren (`name`, `description`, `createdBy`, `createdAt` nach Bedarf)
- [ ] universelle Style-Felder festlegen
- [ ] Farben definieren
- [ ] Transparenzen definieren
- [ ] Typografie definieren
- [ ] Formen/Rahmen definieren
- [ ] Schatten definieren
- [ ] Effekte/Verlauf definieren
- [ ] Assistant-spezifische Werte ausdrücklich ausschließen
- [ ] zentralen Decoder/Validator implementieren
- [ ] zentralen Encoder implementieren
- [ ] Normalisierung und Wertebereiche implementieren
- [ ] Zukunftskompatibilität für unbekannte Felder festlegen
- [ ] Tests ergänzen

**Nicht Teil des Style-Profils:** Hintergrundbilder, Zielseite, Seitenformat, Orientation, Scope, Control-Mapping, Media-IDs.

---

### Paket C – Style-Profil als neue `IPSViewStyleHelper`-Quelle

**Ziel:** Zentrale Helper-Consumer können exportierte Style-Profile verwenden.

Bestehende IDs bleiben unverändert. Vorgesehene Ergänzung:

```php
IPSVIEW_STYLE_SOURCE_PROFILE = 4;
```

- [ ] neue Source-ID ergänzen
- [ ] Form-Auswahl ergänzen
- [ ] Style-Profil als Symcon-Medium auswählbar machen
- [ ] Profil lesen und validieren
- [ ] Profil in `IPSViewResolvedStyle()` integrieren
- [ ] sicheren Fallback definieren
- [ ] Media-Update-Überwachung auf Profile erweitern
- [ ] Tests ergänzen

---

### Paket D – Presets zentralisieren

**Ziel:** Universelle Presetwerte nicht mehr parallel im Assistant und Helper pflegen.

Zu prüfen/zentralisieren:

- [ ] Light
- [ ] Dark
- [ ] Warm
- [ ] Cool
- [ ] Earthy
- [ ] Water
- [ ] Sunny

Zusätzlich:

- [ ] bestehende Assistant-Theme-IDs prüfen
- [ ] Mapping/Migration sicherstellen
- [ ] `IPSViewTheme.php` auf zentrale Presets umstellen
- [ ] Tests ergänzen

---

### Paket E – Export und Import im IPSViewAssistant

**Ziel:** Der Assistant wird Editor/Erzeuger des zentralen Style-Profile-Formats.

#### Export

- [ ] aktuellen Assistant-Style vollständig auflösen
- [ ] keine internen numerischen Assistant-Theme-IDs exportieren
- [ ] zentrales Style Profile V1 erzeugen
- [ ] Profilname/Beschreibung unterstützen
- [ ] JSON-Export anbieten
- [ ] optional direkt als Symcon-Medium speichern

#### Import

- [ ] JSON-Profil laden
- [ ] Symcon-Medium laden
- [ ] zentralen Validator verwenden
- [ ] Werte wieder auf Assistant-Editor verteilen
- [ ] importiertes Profil bearbeiten können
- [ ] erneuten Export ermöglichen
- [ ] Roundtrip-Test durchführen

---

### Paket F – Integration, Consumer und Dokumentation

**Ziel:** Konzept außerhalb des Assistant praktisch verifizieren.

- [ ] ersten realen Consumer auswählen
- [ ] Style-Profil dort als Quelle testen
- [ ] Farben vergleichen
- [ ] Fonts vergleichen
- [ ] Transparenzen vergleichen
- [ ] Popup/Rahmen/Schatten vergleichen
- [ ] responsives Verhalten prüfen
- [ ] End-to-End-Test Assistant → Export → Consumer → Import durchführen
- [ ] README/Dokumentation `Symcon_ModuleHelper` ergänzen
- [ ] README/Dokumentation `IPSViewAssistant` ergänzen

---

## 5. Architekturentscheidungen

### Zentral gehört in den `Symcon_ModuleHelper`

- Fontfamilien
- verfügbare Font-Schnitte/Fähigkeiten
- Fontvalidierung
- universelle Farben
- Transparenzen
- Typografie-Contract
- Rahmen/Rundungen
- Schatten
- Verlauf/Effekte
- universelle Presets
- Style-Profile-Schema
- Style-Profile-Validierung
- Style-Profile-Encoding/Decoding

### Im IPSViewAssistant verbleibt

- Editor-Workflow
- Vorschau
- Original-TTF-Dateien für Browser-/SVG-Vorschau
- Hintergrundbild-Verarbeitung
- Zuordnung eines Styles auf Controls/Seiten
- Assistant-spezifische UI und Anwendungslogik

### Style-Profil-Prinzip

Der Assistant darf nicht sein internes Datenmodell exportieren. Exportiert werden aufgelöste universelle Werte gemäß zentralem Contract.

Beispielprinzip:

```json
{
  "schema": "burki24.ipsview-style",
  "version": 1,
  "name": "Mein Hausdesign",
  "style": {
    "ViewBackground": "#F4F5F7",
    "Text": "#202124",
    "Accent": "#55CBB5",
    "FontFamily": "Open Sans",
    "FontStyle": "Bold",
    "FontSize": 16,
    "BorderRadius": 8,
    "ShadowBlur": 18,
    "GradientStrength": 0
  }
}
```

Das endgültige Schema wird erst in Paket B verbindlich festgelegt.

---

## 5.1 Paket-A-Architekturentscheidungen

- Der zentrale Fontkatalog ist ein eigenständiger `final class IPSViewFontCatalogHelper` und kein Trait im `IPSViewStyleHelper`.
- Der Katalog enthält ausschließlich Metadaten und Validierungs-/Normalisierungslogik; TTF-Dateien verbleiben beim jeweiligen Consumer, derzeit im `IPSViewAssistant`.
- Kanonische Family-Werte entsprechen den realen IPSView-Dokumentwerten (`RobotoMono`, `OpenSans`, `PTSans` usw.); Anzeigenamen bleiben lesbar (`Roboto Mono`, `Open Sans`, `PT Sans`).
- Der `IPSViewAssistant` behält seine bestehenden numerischen Font-Modi 0–8 unverändert und mappt diese nur auf den zentralen Katalog.
- Unbekannte/systemweite Fonts werden vom zentralen Katalog nicht fälschlich als native IPSView-Fonts validiert. Consumer können für solche Alt-/Custom-Werte ihre bestehende Fallback-Logik erhalten.

---

## 6. Chat-/Projektübergabe

Bei Beginn eines neuen Chats in diesem Projekt:

1. `Symcon_ModuleHelper/dev/PROJECT_STATUS.md` lesen.
2. Den dort angegebenen **aktuellen Paketstand** als maßgeblich verwenden.
3. Danach den tatsächlichen Stand der betroffenen Dateien auf `dev` prüfen.
4. Bereits erledigte Schritte nicht erneut beginnen.
5. Bei Abweichungen zwischen Chat-Erinnerung und Repository gilt der aktuelle Repository-Stand auf `dev`.
6. Nach Abschluss eines Teilschritts diese Datei aktualisieren.

---

## 7. Unmittelbar nächster Arbeitsschritt

**Paket A / Übernahme A3 und Abschluss A5/A6**

A3 ist als vollständiges Dateipaket vorbereitet. Nach der Übernahme in `Symcon_ModuleHelper/dev` wird der tatsächliche Repository-Stand erneut gelesen und Paket A abgeschlossen.

A3 umfasst:

- `IPSViewStyleHelper` verwendet `IPSViewFontCatalogHelper` als zentrale Fontquelle.
- `IPSViewStyleFontFamily` bleibt als String-Property unverändert bestehen.
- Das Formular verwendet statt eines freien Textfelds eine definierte Fontauswahl.
- Der bisherige leere Wert bleibt `Systemstandard`.
- Bekannte Aliase werden auf kanonische IPSView-Dokumentwerte normalisiert.
- Sichere bisherige freie Fontwerte bleiben als Legacy-/Custom-Option erhalten.
- Unsichere Werte fallen auf den bisherigen System-Fontstack zurück.
- Style-Source-IDs `0–3` bleiben unverändert.
- `FontStyle` wird in A3 nicht als neue Style-Property eingeführt; die universelle Semantik wird mit dem Style Profile Contract V1 in Paket B festgelegt.
- `IPSViewStyleHelper` erhält im Manifest die Abhängigkeit `IPSViewFontCatalogHelper`.
- Ein eigener Integrationstest deckt Auswahl, Aliase, Legacy-Werte, Fallback, Media-Import und unveränderte Source-IDs ab.

Danach:

1. vollständige `php tests/run.php`-Suite auf `Symcon_ModuleHelper/dev`,
2. Manifest-/Translation-Prüfungen,
3. offizieller Symcon StylePHP-Check,
4. erneuter kompletter Test von `IPSViewAssistant/dev-popup`,
5. Paket A als abgeschlossen markieren,
6. erst danach Paket B beginnen.
