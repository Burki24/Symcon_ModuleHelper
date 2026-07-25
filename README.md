# Symcon_ModuleHelper

Wiederverwendbare Helper für die Entwicklung eigener Symcon-PHP-Module.

Die Helper sind bewusst klein, unabhängig von konkreten Geräten oder Diensten und können direkt in eine Modul-Library übernommen werden. Das Repository selbst ist **keine Symcon-Library** und erzeugt keine zusätzliche Laufzeitabhängigkeit.

## Zielplattform

Die Helper werden für aktuelle `IPSModuleStrict`-Module entwickelt und mit **PHP 8.5 / Symcon 9.0** getestet.

## PersistentJsonCacheHelper

`src/PersistentJsonCacheHelper.php` stellt einen persistenten, modul-internen JSON-Cache auf Basis von Symcon-Attributen bereit.

Symcon-Attribute sind persistent und ausschließlich für die interne Verwaltung durch ein Modul vorgesehen. Damit eignen sie sich für strukturierte API-Daten, Metadaten und andere Cache-Inhalte, die nicht als Statusvariable im Objektbaum sichtbar sein sollen.

### Verwendung

```php
require_once __DIR__ . '/libs/helper/PersistentJsonCacheHelper.php';

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

Empfohlen ist, die benötigte Helper-Datei direkt in das jeweilige Repository zu übernehmen, zum Beispiel:

```text
libs/
└── helper/
    └── PersistentJsonCacheHelper.php
```

Damit bleibt die Symcon-Library vollständig eigenständig. Git-Submodules oder Downloads zur Laufzeit sind nicht erforderlich.

## Tests

```bash
php tests/run.php
```

GitHub Actions prüft den Helper zusätzlich mit PHP 8.5.

## Lizenz

MIT
