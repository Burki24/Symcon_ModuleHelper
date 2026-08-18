# DebugHelper

`DebugHelper` kapselt Symcons native `SendDebug()`-Ausgabe für wiederverwendbare Moduldiagnosen. Der Helper formatiert Arrays und Objekte als kompaktes JSON, maskiert typische Zugangsdaten rekursiv und begrenzt übergroße Ausgaben. Er ist für Klassen gedacht, die von `IPSModule` oder `IPSModuleStrict` abgeleitet sind.

## Verwendung

```php
require_once __DIR__ . '/../libs/helper/DebugHelper.php';

use Burki24\SymconModuleHelper\DebugHelper;

class ExampleModule extends IPSModuleStrict
{
    use DebugHelper;

    private function DebugSynchronization(array $state): void
    {
        $this->SendSafeDebug('Synchronization', $state);
    }
}
```

Typische Schlüssel wie `password`, `accessToken`, `refreshToken`, `clientSecret`, `apiKey`, `authorization`, `cookie` und auf `token`, `secret`, `password` oder `apikey` endende Schlüssel werden als `***` ausgegeben. Bearer-/Basic-Header, Cookies, URL-Passwörter und bekannte Secret-Queryparameter werden auch in freien Texten maskiert.

Modulspezifische Schlüssel können pro Aufruf ergänzt werden:

```php
$this->SendSafeDebug(
    'Connection',
    $data,
    16384,
    ['devicePin']
);
```

Für Exceptions steht `SendSafeDebugException()` zur Verfügung. Es werden Typ, bereinigte Meldung, Fehlercode, Datei und Zeile ausgegeben, aber bewusst kein Stacktrace mit möglichen Argumentwerten.

## Methoden

| Methode | Aufgabe |
| --- | --- |
| `SendSafeDebug()` | Bereinigt und formatiert Daten und sendet sie über Symcons `SendDebug(..., 0)`. |
| `SendSafeDebugException()` | Sendet eine kompakte, bereinigte Exception-Beschreibung ohne Stackargumente. |
| `FormatSafeDebugData()` | Liefert die bereinigte Textdarstellung zurück, ohne sie zu senden. |

Der Standardgrenzwert liegt bei 16 KiB pro Debug-Ausgabe. Der Helper ersetzt keine bewusste Auswahl sinnvoller Diagnosedaten: vollständige Provider-Antworten, Kalenderinhalte oder große Binärdaten sollten weiterhin nicht pauschal in das Debug geschrieben werden.
