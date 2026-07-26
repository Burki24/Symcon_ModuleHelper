# Symcon_ModuleHelper

Wiederverwendbare Helper für die Entwicklung eigener Symcon-PHP-Module.

Die Helper sind bewusst klein, unabhängig von konkreten Geräten oder Diensten und können direkt in eine Modul-Library übernommen werden. Das Repository selbst ist **keine Symcon-Library** und erzeugt keine zusätzliche Laufzeitabhängigkeit.

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

`src/VariablePresentationHelper.php` erzeugt wiederverwendbare native Symcon-Darstellungen für Statusvariablen. Der Helper kapselt die Array-Strukturen der aktuellen Darstellungen und benötigt keine Legacy-Profile.

Enthalten sind Hilfen für boolesche Beschriftungen, Text, Webinhalt sowie Datum/Uhrzeit. Die Standardwerte entsprechen den Darstellungen, die ursprünglich in IPS_LMNB verwendet wurden; Webinhalt und Datum/Uhrzeit können bei Bedarf parametrisiert werden.

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

        $this->RegisterVariableInteger(
            'LastUpdate',
            'Last update',
            $this->DateTimePresentation()
        );
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `BooleanPresentation()` | Wertanzeige für Boolean-Werte mit frei definierbaren Beschriftungen. |
| `TextPresentation()` | Ein- oder mehrzeilige native Textdarstellung. |
| `WebContentPresentation()` | Webinhalt als HTML oder Webseite mit steuerbarem Padding. |
| `DateTimePresentation()` | Parametrisierbare native Datum-/Uhrzeitdarstellung. |
| `DateTimeTemplatePresentation()` | Datum/Uhrzeit mit einer nativen Symcon-Vorlage, um bestehendes Darstellungsverhalten exakt beizubehalten. |

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
    ├── ParentConnectionHelper.php
    ├── PersistentJsonCacheHelper.php
    └── VariablePresentationHelper.php
```

Damit bleibt die Symcon-Library vollständig eigenständig. Git-Submodules oder Downloads zur Laufzeit sind nicht erforderlich.

## Tests

```bash
php tests/run.php
```

GitHub Actions prüft die Helper zusätzlich mit PHP 8.5.

## Lizenz

MIT
