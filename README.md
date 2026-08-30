# Symcon_ModuleHelper

Wiederverwendbare Helper für die Entwicklung eigener Symcon-PHP-Module.

Die Helper sind bewusst fachlich klar abgegrenzt, unabhängig von konkreten Geräten oder Diensten und können direkt in eine Modul-Library übernommen werden. Das Repository selbst ist **keine Symcon-Library** und erzeugt keine zusätzliche Laufzeitabhängigkeit.

## Zielplattform

Die Helper werden für aktuelle `IPSModuleStrict`-Module entwickelt und mit **PHP 8.5 / Symcon 9.0** getestet.

## Automatische Versionierung

Änderungen auf `main` werden automatisch versioniert. Der Workflow
`.github/workflows/update-helper-metadata.yml` wertet die Commit-Titel aus und
aktualisiert anschließend:

- `repository_version`, `repository_build` und `repository_date` in `manifest.json`,
- die `@version` des jeweils geänderten Helpers,
- die zugehörige Helper-Version und sämtliche SHA-256-Prüfsummen im Manifest.

`FEAT:` erhöht die Minor-Version, `BREAKING:` beziehungsweise ein `!` im
Commit-Typ die Major-Version; alle übrigen fachlichen Änderungen erhöhen die
Patch-Version. Reine Metadaten-Commits werden ignoriert. Der Metadaten-Commit wird mit dem
repository-eigenen `GITHUB_TOKEN` geschrieben; anschließend startet der
Workflow den bestehenden Helper-Sync ausdrücklich per `workflow_dispatch`.

## ConfigurationFormHelper

`src/ConfigurationFormHelper.php` unterstützt dynamische Symcon-Konfigurationsformulare. Der Helper ermittelt per Reflection das Verzeichnis der konkreten Modulklasse und lädt von dort die zugehörige `form.json`.

Dabei wird geprüft, dass die Datei lesbar ist, gültiges JSON enthält und an der Wurzel tatsächlich ein JSON-Objekt vorliegt. Die geladene Struktur kann anschließend dynamisch ergänzt und mit einheitlichen JSON-Optionen wieder serialisiert werden.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';

use Burki24\SymconModuleHelper\ConfigurationFormHelper;

class ExampleModule extends IPSModuleStrict
{
    use ConfigurationFormHelper;

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();

        $form['actions'][] = [
            'type'  => 'Label',
            'label' => 'Dynamic content'
        ];

        return $this->EncodeConfigurationForm($form);
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `LoadConfigurationForm()` | Lädt und validiert die `form.json` des konkreten Moduls als assoziatives Array. |
| `EncodeConfigurationForm()` | Serialisiert die dynamisch bearbeitete Formularstruktur als JSON-Objekt. |

## DataFlowHelper

`src/DataFlowHelper.php` vereinheitlicht das JSON-Transportformat für Symcon-Datenflüsse zwischen Child-, Splitter- und Parent-Modulen. Der Helper kümmert sich bewusst nur um die Transporthülle aus `DataID` und Payload; `SendDataToParent()`, `SendDataToChildren()`, Fehlerübersetzung und fachliche Request-/Response-Strukturen bleiben Aufgabe des jeweiligen Moduls.

Beim Decodieren wird geprüft, dass die JSON-Wurzel ein Objekt ist, eine nichtleere String-`DataID` enthält und optional zur erwarteten `DataID` passt. Beim Encodieren darf die Payload keine eigene `DataID` definieren.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/DataFlowHelper.php';

use Burki24\SymconModuleHelper\DataFlowHelper;

class ExampleModule extends IPSModuleStrict
{
    use DataFlowHelper;

    private const DATA_ID_TO_PARENT = '{00000000-0000-0000-0000-000000000001}';
    private const DATA_ID_FROM_PARENT = '{00000000-0000-0000-0000-000000000002}';

    public function ReceiveData(string $JSONString): string
    {
        $message = $this->DecodeDataFlowMessage($JSONString, self::DATA_ID_FROM_PARENT);
        // $message enthält DataID und Payload-Felder.
        return '';
    }

    private function SendRequest(): string
    {
        return $this->SendDataToParent(
            $this->EncodeDataFlowMessage(self::DATA_ID_TO_PARENT, ['Operation' => 'Read'])
        );
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `EncodeDataFlowMessage()` | Erzeugt ein JSON-Datenflussobjekt aus `DataID` und Payload und verhindert eine zweite `DataID` in der Payload. |
| `DecodeDataFlowMessage()` | Decodiert und validiert ein JSON-Datenflussobjekt und prüft optional die erwartete `DataID`. |

## ChunkedJsonTransferHelper

`src/ChunkedJsonTransferHelper.php` zerlegt große JSON-Listen in kurzlebige, größenbegrenzte Seiten für mehrstufige Symcon-Modulaufrufe. Damit können Child-, Splitter- und Parent-Module Datenmengen austauschen, die Symcons festes 1-MiB-Limit für PHP- und Datenflussausgaben überschreiten würden.

Jede Seite wird in einem eigenen Modulbuffer abgelegt. Die Standardgröße von 192 KiB bleibt bewusst unter dem Buffer-Softlimit von 256 KiB; auch die maximal zulässige Seitengröße von 240 KiB hält einen Sicherheitsabstand ein. Der Helper gibt kleine Transfermetadaten und einzelne Seiten zurück, definiert aber bewusst keine fachlichen Operationen oder Response-Hüllen. Diese bleiben Aufgabe des jeweiligen Moduls und können mit dem `DataFlowHelper` kombiniert werden.

Transfers sind nicht persistent, laufen standardmäßig nach fünf Minuten ab und müssen nach erfolgreichem Empfang explizit gelöscht werden. Ein einzelnes Listenelement muss vollständig in eine Seite passen. Der Helper ist deshalb für bereits geparste Datensätze gedacht, nicht zum beliebigen Zerschneiden binärer Dateien oder einzelner übergroßer Objekte.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ChunkedJsonTransferHelper.php';

use Burki24\SymconModuleHelper\ChunkedJsonTransferHelper;

class ExampleModule extends IPSModuleStrict
{
    use ChunkedJsonTransferHelper;

    private const TRANSFER_SCOPE = 'ApiItems';

    /** @param list<array<string,mixed>> $items */
    private function BeginItemTransfer(array $items): array
    {
        return $this->CreateChunkedJsonTransfer(self::TRANSFER_SCOPE, $items);
    }

    private function ReadItemTransferPage(string $token, int $page): array
    {
        return $this->ReadChunkedJsonTransferPage(self::TRANSFER_SCOPE, $token, $page);
    }

    private function FinishItemTransfer(string $token): void
    {
        $this->ClearChunkedJsonTransfer(self::TRANSFER_SCOPE, $token);
    }
}
```

Der Empfänger startet zunächst einen Transfer, ruft danach die Seiten `0` bis `PageCount - 1` einzeln ab, führt deren `Items` in derselben Reihenfolge zusammen und beendet anschließend den Transfer. Bei Fehlern sollte er den Transfer ebenfalls bestmöglich löschen; verwaiste Transfers werden beim nächsten Start oder bei einem expliziten Cleanup desselben Scopes nach Ablauf entfernt.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `CreateChunkedJsonTransfer()` | Zerlegt eine JSON-Liste nach tatsächlicher UTF-8-Bytegröße, legt die Seiten in separaten Buffern ab und liefert kleine Transfermetadaten. |
| `ReadChunkedJsonTransferPage()` | Liest eine nullbasiert adressierte Seite einschließlich Seitenzahl, Gesamtzahl, Abschlussstatus und Items. |
| `ClearChunkedJsonTransfer()` | Entfernt Metadaten und alle bekannten Seiten eines Transfers. |
| `CleanupExpiredChunkedJsonTransfers()` | Entfernt abgelaufene Transfers eines Scopes und liefert deren Anzahl. |

## VariablePresentationHelper

`src/VariablePresentationHelper.php` erzeugt wiederverwendbare native Symcon-Darstellungen für Variablen. Version 2.0.0 führt den bisherigen Helper mit den allgemein nutzbaren Teilen des ursprünglich universell angelegten Presentation-Helpers aus `WolfWSR` zusammen.

Der Helper bleibt vollständig unabhängig von konkreten Geräten, Protokollen, Expose-/Feature-Strukturen oder Modul-Properties. Übernommen wurden ausschließlich generische Symcon-Darstellungen; geräte- oder dienstspezifische Ableitungslogik bleibt in den jeweiligen Libraries.

Unterstützt werden Wertanzeigen für Boolean, Integer, Float, Temperatur, Prozent und Drehzahl sowie Slider, Helligkeit, Farbtemperatur, Farbe, Dauer, Schalter, Rollladen, Aufzählungen, Text, Webinhalt und Datum/Uhrzeit. Alle bisherigen Methoden aus Version 1.x bleiben unverändert verfügbar.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VariablePresentationHelper.php';

use Burki24\SymconModuleHelper\VariablePresentationHelper;

class ExampleModule extends IPSModuleStrict
{
    use VariablePresentationHelper;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterVariableBoolean(
            'Available',
            'Available',
            $this->BooleanPresentation('Yes', 'No')
        );

        $this->RegisterVariableFloat(
            'Temperature',
            'Temperature',
            $this->TemperaturePresentation(-40, 100)
        );

        $this->RegisterVariableInteger(
            'Brightness',
            'Brightness',
            $this->BrightnessPresentation()
        );
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `BooleanPresentation()` | Wertanzeige für Boolean-Werte mit frei definierbaren Beschriftungen. |
| `TextPresentation()` | Ein- oder mehrzeilige native Textdarstellung. |
| `ValuePresentation()` | Generische numerische Wertanzeige mit Einheit, Stellenzahl, Wertebereich, Icon und optionalen Intervallen. |
| `TemperaturePresentation()` | Numerische Temperaturdarstellung mit Temperatur-Usage-Type. |
| `PercentPresentation()` | Prozentdarstellung mit konfigurierbarem Wertebereich und Stellenzahl. |
| `RpmPresentation()` | Wertdarstellung für Drehzahlen, standardmäßig in `U/min`. |
| `IntegerPresentation()` | Schlanke Wertdarstellung für ganzzahlige Messwerte mit optionaler Einheit. |
| `DecimalPresentation()` | Schlanke Wertdarstellung für Dezimalwerte mit frei wählbarer Stellenzahl und Einheit. |
| `SliderPresentation()` | Vollständig parametrisierbarer nativer Symcon-Slider einschließlich Gradient, Usage-Type und Intervallen. |
| `BrightnessPresentation()` | Prozent-Slider für Helligkeit/Intensität mit nativem Intensity-Usage-Type. |
| `ColorTemperaturePresentation()` | Kelvin-Slider mit automatisch erzeugtem Warm-/Kalt-Farbverlauf und Tuneable-White-Usage-Type. |
| `ColorPresentation()` | Native Farbdarstellung mit Encoding, Farbraum und optionalen Presets/Farbkurven. |
| `DurationPresentation()` | Native Dauerdarstellung für Sekundenwerte bzw. Zeitdifferenzen. |
| `SwitchPresentation()` | Native Schalterdarstellung mit Icons, Leuchtfarbe und Usage-Type. |
| `ShutterPresentation()` | Native Rollladen-/Rotationsdarstellung mit frei definierbaren Grenzwerten. |
| `EnumerationPresentation()` | Interaktive native Aufzählung aus frei übergebenen Optionsdefinitionen. |
| `OptionsPresentation()` | Read-only-Wertanzeige mit frei übergebener Optionsliste, z. B. für Boolean- oder String-Zustände. |
| `WebContentPresentation()` | Webinhalt als HTML oder Webseite mit steuerbarem Padding. |
| `DateTimePresentation()` | Parametrisierbare native Datum-/Uhrzeitdarstellung. |
| `DateTimeTemplatePresentation()` | Datum/Uhrzeit mit einer nativen Symcon-Vorlage, um bestehendes Darstellungsverhalten exakt beizubehalten. |

Die Methoden geben ausschließlich native Symcon-Presentation-Arrays zurück. Sie führen keine Geräteerkennung durch, lesen keine Modul-Properties und übersetzen keine Beschriftungen. Dadurch kann dieselbe Helper-Datei unverändert in unterschiedlichen Symcon-Libraries vendort werden.

## ParentConnectionHelper

`src/ParentConnectionHelper.php` kapselt den Zugriff auf die physisch verbundene Parent-Instanz eines Symcon-Moduls. Der Helper liest die `ConnectionID` der aktuellen Instanz und kann prüfen, ob die referenzierte Parent-Instanz tatsächlich noch existiert.

Er enthält bewusst keine Modul-GUIDs und baut selbst keine Verbindung auf. Dadurch kann er unabhängig von konkreten Splittern, I/O-Modulen oder Diensten verwendet werden.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ParentConnectionHelper.php';

use Burki24\SymconModuleHelper\ParentConnectionHelper;

class ExampleModule extends IPSModuleStrict
{
    use ParentConnectionHelper;

    public function HasValidParent(): bool
    {
        return $this->HasParent();
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `GetParentID()` | Liefert die physisch verbundene Parent-Instanz-ID oder `0`, wenn keine Verbindung besteht. |
| `HasParent()` | Prüft, ob eine Parent-ID gesetzt ist und die referenzierte Instanz noch existiert. |

## VisualizationAssetHelper

`src/VisualizationAssetHelper.php` lädt Visualisierungsdateien relativ zum Verzeichnis der konkreten Symcon-Modulklasse. Dadurch kann ein vendorter Helper zuverlässig auf Dateien im jeweiligen `visualization`-Verzeichnis zugreifen, ohne einen festen Modulpfad zu kennen.

Kann eine Datei nicht gelesen werden, liefert der Helper einen leeren String und schreibt den betroffenen Pfad über `SendDebug()`. Er ist damit für `IPSModule`- und `IPSModuleStrict`-Module gedacht.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VisualizationAssetHelper.php';

use Burki24\SymconModuleHelper\VisualizationAssetHelper;

class ExampleModule extends IPSModuleStrict
{
    use VisualizationAssetHelper;

    public function GetVisualizationHtml(): string
    {
        return '<style>' . $this->VisualizationAsset('style.css') . '</style>';
    }
}
```

Bei folgender Modulstruktur:

```text
ExampleModule/
├── module.php
└── visualization/
    ├── template.html
    ├── script.js
    └── style.css
```

wird beispielsweise `VisualizationAsset('style.css')` relativ zum konkreten Modul aus `visualization/style.css` geladen.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `VisualizationAsset()` | Lädt eine Datei aus dem `visualization`-Verzeichnis des konkreten Moduls und liefert deren Inhalt. |

## IPSViewHTMLPageHelper

`src/IPSViewHTMLPageHelper.php` vereinheitlicht die technische Erzeugung nativer HTML-SDK-Seiten und eigenständiger IPSView-WebContent-Seiten. Der Helper lädt immer dieselbe Asset-Struktur aus `visualization/index.html`, `visualization/style.css` und `visualization/app.js`, erzeugt einen gemeinsamen Bootstrap-Vertrag und ersetzt einen festen Satz validierter Template-Platzhalter.

Zusätzlich verwaltet der Helper die optionale IPSView-Ausgabe als getrennten Kanal: Die gemeinsame Eigenschaft `EnableIPSView` ist standardmäßig deaktiviert. Erst nach Aktivierung werden zusätzliche String-Variablen mit nativer WebContent-Darstellung angelegt und mit dem im IPSView-Modus gerenderten HTML befüllt. Native Symcon-Kacheln und vorhandene WebContent-Variablen bleiben davon unabhängig und können weiterhin das Symcon-Farbschema verwenden. Beim späteren Deaktivieren bleiben vorhandene IPSView-Variablen mit Objekt-ID, Inhalt und bestehenden Verknüpfungen erhalten; sie werden lediglich nicht mehr aktualisiert. Das Konfigurationsformular bietet anschließend eine getrennte, ausdrücklich zu bestätigende Löschaktion an.

Die sichtbare Oberfläche bleibt modulspezifisch. OpenHomeAlarm kann daher weiterhin ein Alarm-Dashboard, OpenCalendar eine Kalenderansicht und LMNB mehrere fachlich getrennte Seiten rendern, während Aktivierung, Variablenpflege, Sprache, Viewport, CSS-/JavaScript-Einbettung, JSON-Sicherheit, IPSView-Modus und Bootstrap-Struktur identisch verarbeitet werden.

Der gemeinsame Bootstrap steht im Template als `window.SYMC_VISUALIZATION` bereit und enthält immer:

```javascript
{
    contractVersion: 1,
    mode: 'symcon' | 'ipsview',
    state: {},
    runtime: {},
    translations: {},
    options: {}
}
```

### Vorgesehene Asset-Struktur

```text
ExampleModule/
├── module.php
├── locale.json
└── visualization/
    ├── index.html
    ├── style.css
    └── app.js
```

`index.html` verwendet den gemeinsamen Template-Vertrag:

```html
<!DOCTYPE html>
<html lang="{{HTML_LANGUAGE}}" class="{{HTML_CLASSES}}" style="font-size: {{ROOT_FONT_SIZE}};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="{{VIEWPORT_CONTENT}}">
    <style>{{VISUALIZATION_THEME}}
{{MODULE_STYLE}}
{{IPSVIEW_STYLE}}</style>
</head>
<body>
    <!-- modulspezifische HTML-Struktur -->
    <script>window.SYMC_VISUALIZATION = {{BOOTSTRAP_JSON}};</script>
    <script>{{MODULE_SCRIPT}}</script>
</body>
</html>
```

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VisualizationAssetHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewHTMLPageHelper.php';

use Burki24\SymconModuleHelper\IPSViewHTMLPageHelper;
use Burki24\SymconModuleHelper\VisualizationAssetHelper;

class ExampleModule extends IPSModuleStrict
{
    use VisualizationAssetHelper;
    use IPSViewHTMLPageHelper;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterIPSViewHTMLPageProperties();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->MaintainIPSViewHTMLVariable(
            'IPSViewExample',
            $this->Translate('IPSView example'),
            500,
            '<p>Die IPSView-Seite wird vorbereitet.</p>'
        );

        if ($this->IsIPSViewHTMLPageEnabled()) {
            $this->UpdateIPSViewHTMLVariable('IPSViewExample', $this->RenderPage(true));
        }
    }

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $this->InsertIPSViewHTMLPageFormItems($form['elements']);

        return json_encode($form, JSON_THROW_ON_ERROR);
    }

    private function RenderPage(bool $ipsView): string
    {
        return $this->RenderVisualizationHTMLPage($ipsView, [
            'language'           => 'de',
            'classes'            => $ipsView ? ['example-ipsview'] : [],
            'rootFontSize'       => $ipsView ? '18px' : '100%',
            'visualizationTheme' => $this->VisualizationThemeCSS(),
            'ipsViewStyle'       => $this->IPSViewStyleCSSVariables(':root'),
            'state'              => $this->BuildState(),
            'runtime'            => $ipsView ? ['endpoint' => '/hook/example'] : null,
            'translations'       => $this->IPSViewTranslationsFromLocale(),
            'options'            => ['compact' => $ipsView]
        ]);
    }
}
```

Der statische Formular-Marker für `InsertIPSViewHTMLPageFormItems()` lautet:

```text
Configure optional IPSView HTML output.
```

Ein Modul mit mehreren IPSView-Seiten ruft `MaintainIPSViewHTMLVariable()` und
`UpdateIPSViewHTMLVariable()` je Ident auf. Die fachlichen Daten können dabei
einmal aufgebaut und anschließend getrennt für Symcon und IPSView gerendert
werden. Beim Deaktivieren werden vorhandene IPSView-Variablen automatisch im
Konfigurationsformular als beibehalten erkannt. Erst die dortige bestätigte
Löschaktion entfernt sie über `UnregisterVariable()`; ein einfaches Deaktivieren
oder ein Modulupdate löscht keine Variablen.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `RegisterIPSViewHTMLPageProperties()` | Registriert `EnableIPSView` sowie die internen Eigenschaften und Attribute für sichere, bestätigte Löschaufträge. |
| `IPSViewHTMLPageFormItems()` | Liefert die zentral übersetzte Checkbox, Hinweise und bei beibehaltenen Variablen den Bestätigungsdialog zur Löschung. |
| `InsertIPSViewHTMLPageFormItems()` | Ersetzt einen verschachtelten Formular-Marker durch die gemeinsamen IPSView-Ausgabeeinstellungen. |
| `IsIPSViewHTMLPageEnabled()` | Liefert den aktuellen Zustand der gemeinsamen IPSView-Aktivierung. |
| `MaintainIPSViewHTMLVariable()` | Legt eine optionale Stringvariable mit WebContent-Darstellung an, behält sie beim Deaktivieren und löscht sie nur nach ausdrücklicher Bestätigung. |
| `UpdateIPSViewHTMLVariable()` | Aktualisiert eine vorhandene optionale IPSView-Variable nur bei aktivierter Ausgabe. |
| `RenderVisualizationHTMLPage()` | Lädt Template, CSS und JavaScript, erzeugt den gemeinsamen Bootstrap und rendert das vollständige HTML-Dokument. |
| `EncodeVisualizationHTMLJSON()` | Kodiert JSON mit zentralen Schutzflags für die sichere Einbettung in ein `script`-Element. |
| `IPSViewTranslationsFromLocale()` | Liest alle Quelltexte aus der `locale.json` des konkreten Moduls und übersetzt sie mit der aktiven Symcon-Sprache. |
| `IPSViewTranslationsFor()` | Übersetzt einen explizit übergebenen Satz von Quelltexten. |

Der Helper bezieht `HelperTranslationHelper` und `VisualizationAssetHelper` als zentrale Abhängigkeiten. Die helper-eigenen Formulartexte werden aus `translations/IPSViewHTMLPageHelper.json` geladen; Consumer benötigen dafür keine zusätzlichen `locale.json`-Einträge. `VisualizationThemeHelper` und `IPSViewStyleHelper` bleiben eigenständige Bausteine; deren CSS wird dem Seiten-Helper lediglich übergeben.

## VisualizationThemeHelper

`src/VisualizationThemeHelper.php` stellt ein gemeinsames Design-Fundament für HTML-SDK-Visualisierungen bereit. Der Helper bevorzugt die von Symcon angebotenen Farben für Inhalt, Kachel und Akzent und ergänzt robuste Light-/Dark-Fallbacks. Dadurch folgen verschiedene Module demselben Symcon-nahen Erscheinungsbild, ohne ihre fachlichen Komponenten miteinander zu koppeln.

Die CSS-Tokens umfassen Text, Hintergrund, abgestufte Oberflächen, Rahmen, Symcon-Akzent, Statusfarben, Radien, Steuerelementhöhe und Fokusdarstellung. Modulspezifische Regeln – beispielsweise Kalendertermine oder Alarmzustände – bleiben bewusst im jeweiligen Modul.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VisualizationThemeHelper.php';

use Burki24\SymconModuleHelper\VisualizationThemeHelper;

class ExampleModule extends IPSModuleStrict
{
    use VisualizationThemeHelper;

    public function GetVisualizationHtml(): string
    {
        return '<style>' . $this->VisualizationThemeCSS() . '</style>';
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `VisualizationThemeCSS()` | Liefert gemeinsame CSS-Tokens und eine kleine typografische/Fokus-Grundlage für Symcon-Visualisierungen. |

## HelperTranslationHelper

`src/HelperTranslationHelper.php` stellt helper-eigene Übersetzungen bereit. Sichtbare Beschriftungen und Hinweise eines zentralen Helpers werden damit bereits im Helper übersetzt; Consumer-Module benötigen dafür keine Einträge in ihrer eigenen `locale.json`.

Die Übersetzungen liegen als versionierte JSON-Kataloge unter `src/translations/`.
Der deutsche Katalog des `IPSViewStyleHelper` wird beim Vendor-Sync zusammen mit
dem Helper und seinen Abhängigkeiten verteilt:

```text
libs/helper/
├── HelperTranslationHelper.php
├── IPSViewFontCatalogHelper.php
├── IPSViewStylePresetHelper.php
├── IPSViewStyleProfileHelper.php
├── IPSViewStyleHelper.php
└── translations/
    └── IPSViewStyleHelper.json
```

Die Systemsprache wird über `IPS_GetSystemLanguage()` ermittelt und auf den
Sprachcode normalisiert. Nicht unterstützte Sprachen oder fehlende Einträge
fallen zuverlässig auf Englisch beziehungsweise den im Helper definierten
Ausgangstext zurück.

### Automatische Übersetzung

Helper mit sichtbaren Texten definieren ihre englischen Quellen in einer Konstanten, deren Name auf `_TRANSLATION_SOURCES` endet. Der Workflow `.github/workflows/helper-translations.yml` vergleicht diese Quellen mit den Katalogen und nutzt GitHub Models mit dem repository-eigenen `GITHUB_TOKEN`, um neue oder geänderte Texte nach Deutsch zu übersetzen. Bestehende geprüfte Übersetzungen werden nicht überschrieben. Der Bot erstellt
einen Pull Request zur Kontrolle; erst nach dessen Merge verteilt der normale
Helper-Sync den aktualisierten Helper-Bundle an die Consumer.

Die CI prüft zusätzlich:

- fehlende deutsche oder englische Einträge,
- veraltete Katalogschlüssel,
- geänderte englische Ausgangstexte,
- abweichende Platzhalter zwischen Quelle und Übersetzung.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `TranslateHelperText()` | Übersetzt einen stabilen Helper-Schlüssel aus dem passenden Katalog mit sicherem Fallback. |
| `ResolveHelperTranslationLanguage()` | Ermittelt die aktive Systemsprache. |
| `NormalizeHelperTranslationLanguage()` | Normalisiert Locale-Werte wie `de_DE.UTF-8` zu `de`. |

## IPSViewFontCatalogHelper

`src/IPSViewFontCatalogHelper.php` ist der zentrale, modulunabhängige
Schriftkatalog für IPSView. Er verwendet kanonische Familiennamen und kennt die
verfügbaren Schriftschnitte jeder Familie. Dadurch müssen Consumer und
IPSViewAssistant keine eigenen, voneinander abweichenden Fontlisten pflegen.

Unterstützt werden:

- **Roboto** – Regular, Bold, Italic, Bold Italic
- **Roboto Mono** – Regular, Bold, Italic, Bold Italic
- **Open Sans** – Regular, Bold, Italic, Bold Italic
- **PT Sans** – Regular, Bold, Italic, Bold Italic
- **Dancing Script** – Regular, Bold
- **Bebas Neue** – Regular
- **Indie Flower** – Regular
- **Segment7** – Regular

Historische Schreibweisen wie `Open Sans`, `Roboto Mono` oder `PT Sans` werden auf
die kanonischen Werte normalisiert. TTF-Dateien gehören bewusst nicht in den
zentralen Helper; der IPSViewAssistant hält seine lokalen Fontdateien weiterhin
selbst für die Vorschau vor.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `catalog()` | Liefert den vollständigen Schriftkatalog. |
| `families()` | Liefert alle kanonischen Fontfamilien. |
| `options()` | Liefert Select-Optionen mit Beschriftung und kanonischem Wert. |
| `capabilities()` / `styles()` | Liefert die verfügbaren Schriftschnitte einer Familie. |
| `normalizeFamily()` / `normalizeStyle()` | Normalisiert bekannte Familien- und Schnitt-Aliase. |
| `isValidFamily()` / `isValidStyle()` | Prüft kanonische Familie beziehungsweise Schriftschnitt. |

## IPSViewStyleProfileHelper

`src/IPSViewStyleProfileHelper.php` definiert das portable Austauschformat
**Style Profile V1**. Ein Profil trägt das Schema `burki24.ipsview-style`, die
Version `1`, einen Namen und einen vollständigen Snapshot der universellen
Stilwerte. Teilprofile sind bewusst nicht vorgesehen: Ein gültiges Profil ist
ohne Consumer-Fallback reproduzierbar.

Der V1-Contract umfasst 46 Basiswerte für Farben, Deckkräfte, Typografie,
Rahmen/Formen, Schatten und Effekte. Abgeleitete Werte wie Kontrastfarben,
Soft-Farben, CSS-Gradienten oder fertige `box-shadow`-Strings werden nicht
gespeichert, weil sie aus den Basiswerten reproduzierbar erzeugt werden. Nicht
portabel und deshalb ausgeschlossen sind insbesondere Hintergrundbilder,
Zielseiten-/Ausrichtungsinformationen, Scope, Control-Mappings und Symcon-Media-IDs.

`FontFamily` verwendet `system` oder eine kanonische Familie aus
`IPSViewFontCatalogHelper`; `FontStyle` wird gegen die Fähigkeiten der gewählten
Schrift validiert. Zusätzliche unbekannte V1-Felder werden beim Lesen toleriert
und bei der Normalisierung verworfen. Unbekannte zukünftige Profilversionen
werden abgelehnt, statt stillschweigend falsch interpretiert zu werden.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `contract()` | Liefert die Contract-Beschreibung von Style Profile V1. |
| `styleFields()` | Liefert die 46 universellen Stilfelder. |
| `create()` | Erzeugt ein normalisiertes vollständiges Profil. |
| `decode()` / `encode()` | Liest beziehungsweise schreibt das kanonische JSON-Format. |
| `normalize()` / `normalizeStyle()` | Normalisiert Profil und Stilwerte einschließlich Wertebereichen und Fonts. |
| `isValid()` / `isValidJson()` | Prüft Array- beziehungsweise JSON-Profile ohne Exception nach außen. |

## IPSViewStylePresetHelper

`src/IPSViewStylePresetHelper.php` hält die gemeinsam verwendeten
IPSView-Farbpaletten. Der Helper ist die zentrale Quelle für alle
vordefinierten Themes; Consumer und IPSViewAssistant verwenden dieselben
Rollen und Werte.

Enthalten sind **IPSView Standard**, **Hell**, **Dunkel**, **Warm**, **Kühl**,
**Erdig**, **Wasser** und **Sonnig**. `Custom` bleibt ein Editor-/Consumer-Modus
und ist kein Preset. Die numerischen Theme-IDs des IPSViewAssistant werden nicht
in den zentralen Contract übernommen.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `ids()` | Liefert alle zentralen Preset-IDs. |
| `presets()` | Liefert alle Preset-Definitionen. |
| `palette()` | Liefert die Rollen/Farben eines einzelnen Presets. |
| `label()` | Liefert die lesbare Bezeichnung eines Presets. |
| `isValid()` | Prüft eine Preset-ID. |

## IPSViewStyleHelper

`src/IPSViewStyleHelper.php` bildet das universelle IPSView-Stilsystem für
eigenständige HTML-Seiten ab. Alle vom Helper erzeugten Formulartexte werden
zentral aus seinem Übersetzungskatalog geladen und benötigen keine
Consumer-Lokalisierung. Der Helper besitzt die gemeinsamen Farben, Schriften,
Rahmen, Linien, Schatten, Deckkräfte und Verlaufswerte. Consumer ordnen ihren
Komponenten nur noch semantische Rollen wie Akzent, Information, positiv,
Warnung oder kritisch zu.

Für benutzerdefinierte Stile werden Farbe und Deckkraft getrennt gepflegt.
Schriftfamilie, **Schriftschnitt**, Basisschriftgröße und Skalierung gehören
ebenso zum gemeinsamen Stil wie Radien, Rahmen-/Linienstärken und
Schattenparameter.

### Stilquellen

Die Stilquelle wird direkt in einem einzigen Auswahlfeld gewählt:

- **Benutzerdefinierter Stil** – alle Werte sind frei editierbar.
- **IPSView-Standardstil** – liest eine Whitelist globaler Werte aus einem
  ausgewählten `.ipsView`-Medienobjekt.
- **Helle Vorgabe** und **Dunkle Vorgabe** – kompatible bestehende
  Standardquellen.
- **Stilprofil** – lädt ein vollständig validiertes Style Profile V1 aus einem
  Medienobjekt.
- **Hell**, **Dunkel**, **Warm**, **Kühl**, **Erdig**, **Wasser** und
  **Sonnig** – direkte zentrale Vorgaben aus `IPSViewStylePresetHelper`.

Die zwischenzeitlich verwendete generische Preset-Source-ID `5` bleibt intern
ausschließlich zur Kompatibilität bereits gespeicherter Konfigurationen erhalten
und wird neuen Konfigurationen nicht mehr als zweistufige Auswahl angeboten.

Bei allen nicht benutzerdefinierten Quellen zeigt das Formular die
**tatsächlich wirksamen Stilwerte** an. Farben, Deckkräfte, Typografie, Rahmen,
Schatten und Effekte werden schreibgeschützt dargestellt; die zuvor
gespeicherten Custom-Werte bleiben dadurch beim Ausprobieren einer Vorgabe
unangetastet.

Über **In benutzerdefinierten Stil übernehmen** lassen sich die aktuell
wirksamen Werte vollständig in die Custom-Properties kopieren. Die Stilquelle
wechselt anschließend auf **Benutzerdefinierter Stil**, sodass das Preset oder
Profil als Ausgangspunkt frei individualisiert werden kann. Kopiert werden
Farben, Deckkräfte, Schriftfamilie, Schriftschnitt, Basisschriftgröße,
Schriftskalierung, Radien, Rahmen-/Linienstärken, Schattenparameter,
Inaktivitäts-Opacity und Verlaufsstärke. **Transparenter Hintergrund** bleibt
bewusst eine globale Einstellung und wird dabei nicht überschrieben.

Nicht zur aktiven Quelle gehörende Medienfelder werden im Formular
ausgeblendet. Das Profil-Medienobjekt erscheint nur bei **Stilprofil**, das
`.ipsView`-Medienobjekt nur beim **IPSView-Standardstil**.

Beim IPSView-Standardstil wird ausschließlich eine feste Whitelist der globalen
Style-Einstellungen gelesen. Lizenz-, Verbindungs- oder sonstige View-Daten
werden nicht übernommen. Für statische `form.json`-Dateien kann weiterhin das
Label `Configure the shared IPSView style used by the standalone HTML page.` als
Einfügemarker verwendet werden.

### Universelle CSS-Rollen

Neben den technischen `--ipsview-*`-Tokens erzeugt der Helper verbindliche
`--ipsview-role-*`-Rollen für View-/Seiten-/Label-/Control-/Popup-Hintergründe,
Text, Icons, Rahmen, Linien, Akzent, Information, positiven, warnenden und
kritischen Status. Typografie wird unter anderem über

```css
--ipsview-font-family
--ipsview-font-size
--ipsview-font-style
--ipsview-font-weight
--ipsview-font-scale
```

bereitgestellt. Die bisherigen Alias-Tokens wie `--ipsview-surface`,
`--ipsview-success` und `--ipsview-danger` bleiben aus Kompatibilitätsgründen
erhalten.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `RegisterIPSViewStyleProperties()` | Registriert Stilquellen, Medienobjekte, globale Optionen und sämtliche Custom-Stilwerte. |
| `IPSViewStyleFormItems()` | Liefert die dynamische modulunabhängige Konfiguration einschließlich aktiver schreibgeschützter Stilvorschau und Übernahme-Button. |
| `InsertIPSViewStyleFormItems()` | Ersetzt einen auch verschachtelt abgelegten Formular-Marker durch die vollständigen Stilbedienelemente. |
| `IPSViewStyleRootFontSize()` | Liefert die aus Basisschriftgröße und Skalierung berechnete Root-Schriftgröße. |
| `IPSViewStyleSource()` | Liefert die normalisierte aktive Stilquelle. |
| `IPSViewStyleMediaID()` | Liefert die ausgewählte `.ipsView`-Medienobjekt-ID oder `0`. |
| `IPSViewStyleProfileMediaID()` | Liefert die ausgewählte Style-Profile-Medienobjekt-ID oder `0`. |
| `IPSViewStylePreset()` | Liefert ausschließlich für die kompatible historische Preset-Quelle die gespeicherte Preset-ID. |
| `RegisterIPSViewStyleMediaMessages()` | Registriert Aktualisierungen der aktuell aktiven medienbasierten Stilquelle. |
| `IsIPSViewStyleMediaUpdate()` | Erkennt eine Aktualisierung der aktiven IPSView-Stilquelle. |
| `ReadIPSViewStyleMediaContent()` | Liest den Inhalt des ausgewählten `.ipsView`-Medienobjekts. |
| `ReadIPSViewStyleProfileMediaContent()` | Liest den Inhalt des ausgewählten Style-Profile-Medienobjekts. |
| `IPSViewResolvedStyle()` | Liefert die vollständig aufgelösten universellen Stilwerte. |
| `IPSViewStyleCSSVariables()` | Rendert die aufgelösten Werte als gemeinsame `--ipsview-*`-CSS-Variablen. |

## IPSViewColorPaletteHelper

`src/IPSViewColorPaletteHelper.php` bleibt vorerst als kompatibler Vorgänger für bereits migrierte Consumer erhalten. Neue Integrationen sollen `IPSViewStyleHelper` verwenden. Der ältere Helper verwaltet nur neun Farben und besitzt weder Style Profile, zentrale Presets noch die gemeinsamen Typografie-, Rahmen-, Schatten-, Opacity- und Verlaufswerte.

## HttpResponseHelper

`src/HttpResponseHelper.php` stellt kleine, wiederverwendbare HTTP-Antworten für Symcon-Module mit WebHooks, OAuth-Callbacks oder eigenen HTTP-Endpunkten bereit. Der Helper setzt den HTTP-Status und sicherheitsorientierte Standard-Header, ohne von einem konkreten Modul oder Dienst abhängig zu sein.

Neben Klartext-Antworten unterstützt der Helper sicher maskierte
HTML-Text-Antworten für Callback-Seiten. Freies HTML oder JSON bleiben bewusst
außerhalb des Helpers, bis dafür ein gemeinsamer Anwendungsfall besteht.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/HttpResponseHelper.php';

use Burki24\SymconModuleHelper\HttpResponseHelper;

class ExampleModule extends IPSModuleStrict
{
    use HttpResponseHelper;

    public function ProcessHookData(): void
    {
        $this->SendPlainTextResponse(200, 'OK');
    }

    public function ProcessOAuthData(): void
    {
        $this->SendHtmlTextResponse(200, 'Connection successful.');
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `SendPlainTextResponse()` | Sendet eine Klartext-Antwort mit HTTP-Status, UTF-8-Content-Type, Cache-Schutz und `nosniff`-Header. |
| `SendHtmlTextResponse()` | Sendet sicher HTML-maskierten Text mit HTTP-Status und denselben Standard-Headern. |

## SymconOAuthHelper

`src/SymconOAuthHelper.php` kapselt den wiederverwendbaren OAuth-Ablauf für Anbieter, die zentral beim Symcon-OAuth-Dienst registriert sind. Der Helper erzeugt die lizenzbezogene Autorisierungs-URL, tauscht weitergeleitete Autorisierungscodes gegen Tokens und erneuert Access-Tokens über ein gespeichertes Refresh-Token.

Client-ID und Client-Secret des Anbieters bleiben auf dem Symcon-OAuth-Backend
und werden weder im Modul noch in Benutzerinstanzen gespeichert. Der Helper
verwendet ausschließlich die feste Basis `https://oauth.ipmagic.de` und
validiert Identifier, Transportantwort, HTTP-Status, JSON-Struktur,
Bearer-Token und Refresh-Token.

Der HTTP-Transport wird als Callable injiziert. Dadurch bleibt der Helper
unabhängig von konkreten HTTP-Clients und kann den im Consumer bereits
vorhandenen, für `oauth.ipmagic.de` abgesicherten Transport wiederverwenden.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/SymconOAuthHelper.php';

use Burki24\SymconModuleHelper\SymconOAuthClient;

$trustedHttpClient = $this->CreateTrustedOAuthHttpClient();
$oauth = new SymconOAuthClient(
    static function (string $method, string $url, array $headers, string $body) use ($trustedHttpClient): array {
        $response = $trustedHttpClient->request($method, $url, $headers, $body);

        return [
            'statusCode' => $response->statusCode,
            'body'       => $response->body
        ];
    },
    'example_provider',
    'Example Provider'
);

$authorizationUrl = $oauth->getAuthorizationUrl((string) IPS_GetLicensee());
$tokens = $oauth->exchangeAuthorizationCode($authorizationCode);
$renewedTokens = $oauth->refreshAccessToken($tokens['refreshToken']);
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `getAuthorizationUrl()` | Erzeugt die Autorisierungs-URL des registrierten Symcon-OAuth-Identifiers für ein Lizenzkonto. |
| `exchangeAuthorizationCode()` | Tauscht einen weitergeleiteten Autorisierungscode gegen normalisierte Access- und Refresh-Tokens. |
| `refreshAccessToken()` | Erneuert den Access-Token und behält den bisherigen Refresh-Token, wenn der Anbieter ihn nicht rotiert. |

## VariableHelper

`src/VariableHelper.php` kapselt den wiederverwendbaren Zugriff auf Variablen
unterhalb von Symcon-Objekten. Ohne explizite Parent-ID löst der Helper Variablen
wie bisher relativ zur aktuellen `InstanceID` auf. Optional kann eine andere
Parent-ID angegeben werden, beispielsweise um Variablen einer verbundenen oder
referenzierten Instanz auszulesen.

Ein Lookup liefert nur dann eine positive ID zurück, wenn der gefundene Ident
tatsächlich zu einer vorhandenen Symcon-Variable gehört. Fehlende Idents,
ungültige IDs und andere Objekttypen werden einheitlich auf `0` normalisiert.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VariableHelper.php';

use Burki24\SymconModuleHelper\VariableHelper;

class ExampleModule extends IPSModuleStrict
{
    use VariableHelper;

    public function HasTemperatureVariable(): bool
    {
        return $this->VariableExists('Temperature');
    }

    public function GetExternalLastSynchronization(int $calendarInstanceID): int
    {
        return $this->GetIntegerVariableValueByIdent('LastSynchronization', $calendarInstanceID);
    }

    public function GetPumpPower(): float
    {
        return $this->GetFloatVariableValueByIdent('PumpPower');
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `GetVariableIDByIdent()` | Liefert die ID einer Variablen unterhalb der aktuellen Modulinstanz oder einer optional angegebenen Parent-ID; andernfalls `0`. |
| `VariableExists()` | Prüft, ob eine Variable mit dem angegebenen Ident vorhanden ist. |
| `GetVariableValueByIdent()` | Liest den Rohwert einer Variablen typunverändert. |
| `GetBooleanVariableValueByIdent()` | Liest Boolean-Werte sowie numerische 0/Nicht-0-Zustände. |
| `GetFloatVariableValueByIdent()` | Liest Integer- oder Float-Werte und normalisiert sie auf Float. |
| `GetIntegerVariableValueByIdent()` | Liest ausschließlich native Integer-Werte. |
| `GetStringVariableValueByIdent()` | Liest ausschließlich native String-Werte. |

## DateHelper

`src/DateHelper.php` formatiert Datumswerte aus APIs oder Konfigurationsquellen einheitlich. Der Helper verwendet standardmäßig die deutsche Datumsdarstellung `d.m.Y`, erlaubt aber auch ein frei wählbares `DateTime`-Ausgabeformat.

Nicht interpretierbare Datumsstrings werden unverändert zurückgegeben. Leere
oder Nicht-String-Werte ergeben einen leeren String. Damit eignet sich der Helper
besonders für externe Daten, bei denen ein ungültiger oder unbekannter
Originalwert nicht stillschweigend verloren gehen soll.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/DateHelper.php';

use Burki24\SymconModuleHelper\DateHelper;

class ExampleModule extends IPSModuleStrict
{
    use DateHelper;

    public function FormatApiDate(string $date): string
    {
        return $this->FormatDate($date);
    }

    public function FormatApiMonth(string $date): string
    {
        return $this->FormatDate($date, 'm/Y');
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `FormatDate()` | Formatiert einen Datumsstring mit `d.m.Y` oder einem angegebenen `DateTime`-Format; nicht interpretierbare Strings bleiben unverändert. |

## PersistentJsonCacheHelper

`src/PersistentJsonCacheHelper.php` stellt einen persistenten, modul-internen JSON-Cache auf Basis von Symcon-Attributen bereit.

Symcon-Attribute sind persistent und ausschließlich für die interne Verwaltung
durch ein Modul vorgesehen. Damit eignen sie sich für strukturierte API-Daten,
Metadaten und andere Cache-Inhalte, die nicht als Statusvariable im Objektbaum
sichtbar sein sollen.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/PersistentJsonCacheHelper.php';

use Burki24\SymconModuleHelper\PersistentJsonCacheHelper;

class ExampleModule extends IPSModuleStrict
{
    use PersistentJsonCacheHelper;

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPersistentJsonCache('CachedData');
    }

    public function UpdateCache(array $data): void
    {
        if ($this->WritePersistentJsonCache('CachedData', $data)) {
            // Der persistierte Inhalt hat sich tatsächlich geändert.
        }
    }

    public function GetCachedData(): array
    {
        return $this->ReadPersistentJsonCache('CachedData');
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `RegisterPersistentJsonCache()` | Registriert ein String-Attribut mit JSON-Defaultwert. |
| `ReadPersistentJsonCache()` | Liest und dekodiert den Cache als Array. |
| `WritePersistentJsonCache()` | Schreibt nur bei tatsächlicher Änderung und liefert dann `true`. |
| `ClearPersistentJsonCache()` | Setzt den Cache auf ein leeres Array zurück. |

Die Serialisierung verwendet `JSON_THROW_ON_ERROR`, erhält Unicode und Schrägstriche lesbar und bewahrt `1.0` als Float.

## Einbindung in Modul-Repositories

Empfohlen ist, nur die benötigten Helper-Dateien direkt in das jeweilige Repository zu übernehmen, zum Beispiel:

```text
libs/
└── helper/
    ├── ChunkedJsonTransferHelper.php
    ├── ConfigurationFormHelper.php
    ├── DateHelper.php
    ├── HelperTranslationHelper.php
    ├── HttpResponseHelper.php
    ├── IPSViewColorPaletteHelper.php
    ├── IPSViewFontCatalogHelper.php
    ├── IPSViewHTMLPageHelper.php
    ├── IPSViewStyleHelper.php
    ├── IPSViewStylePresetHelper.php
    ├── IPSViewStyleProfileHelper.php
    ├── translations/
    │   ├── IPSViewHTMLPageHelper.json
    │   └── IPSViewStyleHelper.json
    ├── ParentConnectionHelper.php
    ├── PersistentJsonCacheHelper.php
    ├── SymconOAuthHelper.php
    ├── VariableHelper.php
    ├── VariablePresentationHelper.php
    └── VisualizationAssetHelper.php
```

Damit bleibt die Symcon-Library vollständig eigenständig. Git-Submodules oder Downloads zur Laufzeit sind nicht erforderlich.

## Dokumentationsregel

Ändert ein Arbeitspaket sichtbare Konfiguration, öffentliche Helper-API oder
Anwenderfunktionen, gehört die zugehörige README zum selben Arbeitspaket. Vor
der Auslieferung werden deshalb neben Tests und StylePHP auch die betroffenen
README-Abschnitte gegen den tatsächlich ausgelieferten Stand geprüft.

## Tests

```bash
php tests/run.php
```

Der zentrale Runner führt alle Helper-Testgruppen aus; gemeinsame Testfunktionen liegen in `tests/bootstrap.php`, während jeder Helper eine eigene Testdatei besitzt.

GitHub Actions prüft zusätzlich die PHP-Syntax und den vollständigen Testlauf mit PHP 8.5 sowie den Symcon-PHP-Stil über `symcon/action-style@v3`.

## Lizenz

MIT

## Automatischer Vendor-Sync

`manifest.json` ist die maschinenlesbare Quelle für Version und SHA-256 aller Helper. Die Consumer-Repositories deklarieren ihre verwendeten Helper über `.helper-sync.json`.

Bei Änderungen unter `src/` erzeugt `.github/workflows/helper-sync.yml` automatisch einen Update-Branch und einen Pull Request gegen den jeweiligen `dev`-Branch eines abonnierten Repositories. Der Sync aktualisiert das abonnierte Helper-Bundle einschließlich deklarierter Abhängigkeiten und Assets sowie `libs/helper/manifest.json` und `libs/helper/README.md`. Übersetzungskataloge werden dadurch gemeinsam mit dem zugehörigen Helper verteilt.

Für den repositoryübergreifenden Zugriff wird eine GitHub App verwendet. Im Repository `Symcon_ModuleHelper` werden dafür benötigt:

- Repository-Variable `HELPER_SYNC_APP_CLIENT_ID`
- Repository-Secret `HELPER_SYNC_APP_PRIVATE_KEY`

Die GitHub App wird auf den Consumer-Repositories installiert und benötigt dort nur `Contents: Read and write` sowie `Pull requests: Read and write`.

Die zentrale Consumer-Konfiguration aktiviert standardmäßig `SQUASH`-Auto-Merge. Vor der Aktivierung prüft der Sync, dass der Pull Request von einem GitHub-App-Bot stammt, einen `helper-sync/`-Branch gegen den konfigurierten Zielbranch verwendet und ausschließlich die für das konkrete Helper-Bundle erzeugten Dateien verändert. Enthält ein PR zusätzliche Modul-, Test-, Workflow- oder Dokumentationsdateien außerhalb des generierten Bundles, wird Auto-Merge abgelehnt und der Sync-Lauf schlägt sichtbar fehl.

Damit GitHub den PR erst nach der CI zusammenführt, müssen in jedem Consumer unter **Settings → General → Pull Requests** `Allow auto-merge` und `Allow squash merging` aktiviert sein. Für den jeweiligen `dev`-Branch müssen außerdem die gewünschten Tests als erforderliche Statusprüfungen in einer Branch Protection Rule oder einem Ruleset hinterlegt sein. Ohne unerfüllte Merge-Anforderung stellt GitHub Auto-Merge nicht bereit; der Sync führt niemals einen direkten Merge als Fallback aus.

Ein manueller Lauf ist über `workflow_dispatch` möglich; dabei kann ein einzelner Helpername oder `all` angegeben werden.
