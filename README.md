# Symcon_ModuleHelper

Wiederverwendbare Helper für die Entwicklung eigener Symcon-PHP-Module.

Die Helper sind bewusst fachlich klar abgegrenzt, unabhängig von konkreten Geräten oder Diensten und können direkt in eine Modul-Library übernommen werden. Das Repository selbst ist **keine Symcon-Library** und erzeugt keine zusätzliche Laufzeitabhängigkeit.

## Zielplattform

Die Helper werden für aktuelle `IPSModuleStrict`-Module entwickelt und mit **PHP 8.5 / Symcon 9.0** getestet.

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

## VariablePresentationHelper

`src/VariablePresentationHelper.php` erzeugt wiederverwendbare native Symcon-Darstellungen für Variablen. Version 2.0.0 führt den bisherigen Helper mit den allgemein nutzbaren Teilen des ursprünglich universell angelegten Presentation-Helpers aus `IPS_Wolf_WSR1` zusammen.

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
        return $this->VisualizationAsset('template.html');
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

## HttpResponseHelper

`src/HttpResponseHelper.php` stellt kleine, wiederverwendbare HTTP-Antworten für Symcon-Module mit WebHooks, OAuth-Callbacks oder eigenen HTTP-Endpunkten bereit. Der Helper setzt den HTTP-Status und sicherheitsorientierte Standard-Header, ohne von einem konkreten Modul oder Dienst abhängig zu sein.

Neben Klartext-Antworten unterstützt der Helper sicher maskierte HTML-Text-Antworten für Callback-Seiten. Freies HTML oder JSON bleiben bewusst außerhalb des Helpers, bis dafür ein gemeinsamer Anwendungsfall besteht.

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


## VariableHelper

`src/VariableHelper.php` kapselt den wiederverwendbaren Zugriff auf Variablen unterhalb von Symcon-Objekten. Ohne explizite Parent-ID löst der Helper Variablen wie bisher relativ zur aktuellen `InstanceID` auf. Optional kann eine andere Parent-ID angegeben werden, beispielsweise um Variablen einer verbundenen oder referenzierten Instanz auszulesen.

Ein Lookup liefert nur dann eine positive ID zurück, wenn der gefundene Ident tatsächlich zu einer vorhandenen Symcon-Variable gehört. Fehlende Idents, ungültige IDs und andere Objekttypen werden einheitlich auf `0` normalisiert.

Dadurch müssen Module direkte `IPS_GetObjectIDByIdent()`- und `IPS_VariableExists()`-Prüfungen nicht mehrfach selbst absichern. Der Helper bleibt bewusst auf Variablenzugriffe beschränkt; allgemeine String- oder JSON-Konvertierungen gehören nicht zu seiner Verantwortung.

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
        return $this->GetVariableIDByIdent('LastSynchronization', $calendarInstanceID);
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `GetVariableIDByIdent()` | Liefert die ID einer Variablen unterhalb der aktuellen Modulinstanz oder einer optional angegebenen Parent-ID; andernfalls `0`. |
| `VariableExists()` | Prüft, ob eine Variable mit dem angegebenen Ident unterhalb der aktuellen Modulinstanz oder einer optional angegebenen Parent-ID existiert. |

## DateHelper

`src/DateHelper.php` formatiert Datumswerte aus APIs oder Konfigurationsquellen einheitlich. Der Helper verwendet standardmäßig die deutsche Datumsdarstellung `d.m.Y`, erlaubt aber auch ein frei wählbares `DateTime`-Ausgabeformat.

Nicht interpretierbare Datumsstrings werden unverändert zurückgegeben. Leere oder Nicht-String-Werte ergeben einen leeren String. Damit eignet sich der Helper besonders für externe Daten, bei denen ein ungültiger oder unbekannter Originalwert nicht stillschweigend verloren gehen soll.

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

Symcon-Attribute sind persistent und ausschließlich für die interne Verwaltung durch ein Modul vorgesehen. Damit eignen sie sich für strukturierte API-Daten, Metadaten und andere Cache-Inhalte, die nicht als Statusvariable im Objektbaum sichtbar sein sollen.

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
    ├── ConfigurationFormHelper.php
    ├── DateHelper.php
    ├── HttpResponseHelper.php
    ├── ParentConnectionHelper.php
    ├── PersistentJsonCacheHelper.php
    ├── VariableHelper.php
    ├── VariablePresentationHelper.php
    └── VisualizationAssetHelper.php
```

Damit bleibt die Symcon-Library vollständig eigenständig. Git-Submodules oder Downloads zur Laufzeit sind nicht erforderlich.

## Tests

```bash
php tests/run.php
```

Der zentrale Runner führt alle Helper-Testgruppen aus; gemeinsame Testfunktionen liegen in `tests/bootstrap.php`, während jeder Helper eine eigene Testdatei besitzt.

GitHub Actions prüft zusätzlich die PHP-Syntax und den vollständigen Testlauf mit PHP 8.5 sowie den Symcon-PHP-Stil über `symcon/action-style@v3`.

## Lizenz

MIT
