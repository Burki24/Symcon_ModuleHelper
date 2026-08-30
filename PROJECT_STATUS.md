# Symcon ModuleHelper & IPSView – Project Status

> Zentrale Arbeits- und Übergabedatei für die schrittweise Zentralisierung von IPSView-Fonts, Stilquellen und Style-Profilen.
>
> **Maßgeblicher Arbeitsstand:** `Symcon_ModuleHelper/dev`
>
> Diese Datei wird nach jedem abgeschlossenen Teilabschnitt aktualisiert. In einem neuen Chat ist sie zuerst zu lesen, damit unmittelbar am letzten Stand weitergearbeitet werden kann.

## Aktueller Stand

**Datum:** 2026-08-30  
**Phase:** Paket A – Zentraler Fontkatalog  
**Status:** Vorbereitung abgeschlossen, Implementierung noch nicht begonnen  
**Nächster Schritt:** Zentralen IPSView-Fontkatalog und dessen API im `Symcon_ModuleHelper/dev` entwerfen und implementieren.

---

## 1. Verbindliche Arbeitsregeln

### Branch- und Release-Modell

- `dev` ist der Entwicklungs- und Integrationsbranch des `Symcon_ModuleHelper`.
- `main` enthält ausschließlich freigegebene/verteilbare Stände.
- Änderungen werden während der Entwicklung **nicht direkt auf `main`** vorgenommen.
- Die automatische Helper-Versionierung bleibt ausschließlich auf `main`.
- Die automatische Verteilung der Helper an Consumer-Repositories bleibt ausschließlich auf `main`.
- Auf `dev` wird mit normalen, thematisch sauberen Commits gearbeitet.
- Erst ein vollständig getestetes Arbeitspaket wird von `dev` nach `main` übernommen.
- Dadurch erzeugen Zwischenstände auf `dev` keine künstlichen Helper-Versionen und werden nicht an Consumer verteilt.

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

- [ ] Geeigneten Ort/API im `Symcon_ModuleHelper` festlegen.
- [ ] Acht bestätigte Fontfamilien zentral abbilden.
- [ ] Verfügbare Schnitte/Fähigkeiten zentral abbilden.
- [ ] Stabile interne Bezeichner und angezeigte Namen trennen, falls erforderlich.

#### A2 – Gemeinsame Font-API implementieren

Vorgesehene Fähigkeiten:

- [ ] verfügbare Fontfamilien liefern
- [ ] verfügbare Schnitte für eine Fontfamilie liefern
- [ ] Fontfamilie validieren
- [ ] Font-Schnitt validieren
- [ ] Fontwerte normalisieren/Fallback bereitstellen

Die endgültigen Methodennamen werden passend zur bestehenden Helper-Architektur gewählt.

#### A3 – `IPSViewStyleHelper` auf zentralen Katalog umstellen

- [ ] freies `FontFamily`-Textfeld durch eine definierte Auswahl ersetzen
- [ ] Font-Schnitt in den zentralen Style aufnehmen, soweit der aktuelle Contract dies sauber zulässt
- [ ] bestehende gespeicherte Werte weiterhin lesen
- [ ] ungültige/veraltete Werte sicher behandeln
- [ ] keine bestehenden Source-IDs verändern

#### A4 – IPSViewAssistant umstellen

- [ ] lokale Font-Matrix aus `IPSViewTypography.php` entfernen bzw. auf zentrale Definition delegieren
- [ ] Assistant-Auswahl vollständig aus zentralem Fontkatalog erzeugen
- [ ] Bold/Italic nur anbieten, wenn vom zentralen Katalog erlaubt
- [ ] lokale Font-Dateipfade/TTFs für Vorschau beibehalten
- [ ] SVG-/Browser-Vorschau unverändert funktionsfähig halten

#### A5 – Abwärtskompatibilität

- [ ] vorhandene Assistant-Instanzen behalten ihre Fontauswahl
- [ ] vorhandene Module mit `IPSViewStyleHelper` bleiben lauffähig
- [ ] keine unnötige Änderung bestehender Property-Namen oder IDs
- [ ] sinnvolle Fallbacks für unbekannte alte Fontwerte

#### A6 – Tests und Freigabe

- [ ] zentralen Fontkatalog testen
- [ ] alle Fontfamilien testen
- [ ] alle erlaubten Schnitte testen
- [ ] nicht erlaubte Schnitte testen
- [ ] Fallbacks testen
- [ ] Assistant-Tests ausführen
- [ ] Helper-Tests ausführen
- [ ] StylePHP prüfen
- [ ] `PROJECT_STATUS.md` aktualisieren

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

**Paket A / A1:**

Zuerst wird die bestehende Fontlogik aus `IPSViewAssistant/libs/IPSViewTypography.php` vollständig gegen den aktuellen `Symcon_ModuleHelper/dev` analysiert. Danach wird entschieden, ob der Fontkatalog:

- als eigener Helper/Trait,
- als zentraler Contract innerhalb des `IPSViewStyleHelper`, oder
- als eigenständige statische Definition mit gemeinsamer API

implementiert wird.

Bevorzugt wird eine Lösung, die auch außerhalb des `IPSViewStyleHelper` direkt vom `IPSViewAssistant` verwendet werden kann und keine unnötige Abhängigkeit von HTML-/Style-Rendering erzeugt.
