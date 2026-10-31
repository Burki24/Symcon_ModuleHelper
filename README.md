# Symcon_ModuleHelper

Wiederverwendbare Helper für die Entwicklung eigener Symcon-PHP-Module.

Die Helper sind bewusst fachlich klar abgegrenzt, unabhängig von konkreten Geräten oder Diensten und können direkt in eine Modul-Library übernommen werden. Das Repository selbst ist **keine Symcon-Library** und erzeugt keine zusätzliche Laufzeitabhängigkeit.

## Zielplattform

Die Helper werden für aktuelle `IPSModuleStrict`-Module entwickelt und mit **PHP 8.5 / Symcon 9.0** getestet.

## VariablePresentationNormalizationHelper

`src/VariablePresentationNormalizationHelper.php` stellt eine gemeinsame lesende
Darstellungsnormalisierung für die Libraries JSLive und SymconECharts bereit:

- `Capture(int $variableID): array` erfasst aktuelle native Darstellung,
  System-/Custom-Profildefinitionen, Template-Werte und Objekt-Icon. Es wird
  keine Variable oder Darstellung verändert.
- `Normalize(int $variableType, array $presentation, array $profiles = []): array`
  erzeugt `original`, `effective`, `native` und `conversion`. Profile stehen
  nach Namen indiziert in `profiles`. Explizite native Parameter überschreiben
  die aufgelöste Profilbasis, auch bei nullähnlichen Werten wie false, 0 oder
  leeren Zeichenfolgen. Ungültige Typen werden nicht stillschweigend konvertiert.
- `ToSDK(array $snapshot): array` kodiert SDK-Listen wie `OPTIONS` und
  `INTERVALS` erst an der SDK-Grenze als JSON-Strings. Ein blockierter Snapshot
  darf nicht als gültige native Darstellung ausgegeben werden.

Wertanzeige, Slider und Enumeration behalten ihre vollständigen unterstützten
Parameter. Gewöhnliche Legacy-Zahlenprofile sowie Boolean-/String-Zustände und
konstante numerische Assoziationen werden in profilunabhängige native Werte
übersetzt. Numerische Assoziationen verwenden die dokumentierten Schwellen
bis zum nächsten Eintrag. Präfix-/Suffix-Leerzeichen, Typen, Farben und Icons
bleiben erhalten; Schrittweiten der Wertanzeige bleiben Metadaten. Native
Prozentformatierung ist von einem bewusst gesetzten `PERCENTAGE=false`
für reine Chart-Suffixe getrennt. Keine Messwertnormalisierung oder neue Action.

Das im Symcon-9.1-Export beobachtete ganzzahlige `CONTENT_COLOR` bleibt neben
`COLOR` eigenständig erhalten: unverändert unter `native.parameters` und bei
`ToSDK()`, zusätzlich als `effective.contentColor` mit eigener Feldbilanz.
Auch `0`, `-1` und 32-Bit-Farbwerte bleiben unverändert; fehlende Parameter
werden nicht ergänzt und ungültige Typen bleiben blockiert. Dieser
Kompatibilitätsfall beruht auf der gemeldeten Live-Exportprüfung vom 31.10.2026;
die unten verlinkte SDK-Tabelle führt `CONTENT_COLOR` derzeit noch nicht auf.

Nicht belegte Sonderprofile, dynamische printf-Texte, unbekannte Parameter und
inkompatible Kombinationen werden samt Originaldaten als `blocked` zurückgegeben.
Sie werden nicht als leere Einheit oder generische Zahlenanzeige kaschiert.
`conversion.fieldCoverage` nennt Herkunft und native Zielpfade. Die Regeln
beziehen sich auf den Exportvertrag 1.0; reale SDK-/Visualisierungsabnahme bleibt
von lokalen Konvertertests getrennt. Quellen:
[native Wertanzeige](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/value-presentation/),
[Legacy-Assoziationen](https://www.symcon.de/en/service/documentation/basics/variables/variable-profiles/).

## RasterImageHelper

`src/RasterImageHelper.php` stellt `PrepareRasterImage(string $content): array`
für lokale Base64-Bilduploads bereit. Der Helper ist unabhängig von Symcon-Objekten
und benötigt keine Dateien, externen Dienste oder zusätzlichen Programme.
Die Zentralisierung vor einer zweiten nutzenden Library ist eine ausdrücklich
vom Benutzer beschlossene Ausnahme; die allgemeine Zwei-Libraries-Regel bleibt
bestehen. Erster Consumer ist SymconiCloud.

- Eingabe: PNG, JPEG, GIF oder WebP; maximal 10 MiB, 8192 Pixel pro Seite und
  16 Millionen Pixel. Grenzen werden vor dem GD-Decodieren geprüft.
- Ausgabe: maximal 512 Pixel pro Seite und 128 KiB. Seitenverhältnis und
  Transparenz bleiben erhalten, ohne Beschnitt oder Hochskalieren. Bereits
  passende Bilder bleiben unverändert. Bei einer erforderlichen Umwandlung
  animierter Bilder wird das erste Bild verwendet.
- GD skaliert und komprimiert lokal, bevorzugt als WebP; JPEG/PNG sind Fallbacks.
  JPEG-EXIF-Ausrichtung wird bei verfügbarer EXIF-Erweiterung berücksichtigt.
  Keine Metadatenübernahme bei neu codierten Bildern.
- Ergebnisfelder: `content` (Base64), `mime`, `width`, `height`, `changed` und
  `error`. Ein leeres `error` bedeutet Erfolg, auch bei absichtlich leerem Bild.
  Fehlercodes: `input_size`, `format`, `pixels`, `unavailable`, `conversion`.
  Übersetzung und Benutzerhinweise gehören dem aufrufenden Modul.
- Ohne GD bleiben bereits passende Bilder nutzbar; notwendige Umwandlungen
  melden `unavailable`. Consumer speichern/cache-en aufbereitete Ergebnisse,
  statt bei jedem Datenupdate neu zu komprimieren. Das Original wird nicht
  verändert. Fehler enthalten keine Bilddaten.

Der Helper ist ohne weitere Abhängigkeiten und Ressourcen im Manifest registriert.
Verhaltensprüfungen mit echten Bilddaten benötigen PHP GD; EXIF ist optional.

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

## DebugFlowHelper

`src/DebugFlowHelper.php` ergänzt den bestehenden `DebugHelper` um die im
OpenHomeAlarm-Piloten abgenommene Ablaufstruktur. Öffentliche Modul-Einstiege
verwenden `DebugFlowStart(__FUNCTION__, __LINE__, $safeContext)` und im
`finally`-Block `DebugFlowEnd(__FUNCTION__, __LINE__, $result, $failed, $void)`.
Verschachtelte Aufrufe teilen eine Vorgangskennung und erhalten getrennte
Aufrufnummern. Die Ereignisse `START`, `ENDE`, `AKTION`, `SENDEN`, `EMPFANG`
und `FEHLER` enthalten die Felder `Instanz`, `Vorgang`, `Aufruf`, `Meldung`
und `Bezug`. Deutsche Fachmeldungen und die Auswahl ungefährlicher Werte
bleiben Aufgabe des jeweiligen Moduls; technische Identifier bleiben erhalten.

`DebugFlowEvent()` erzeugt eine auf 4096 Bytes begrenzte und bereinigte
Nutzlast. `DebugFlowException()` übernimmt ausschließlich Fehlerklasse,
Fehlerzeile und Dateiname, niemals beliebige Exception-Texte oder Argumente.
Text- und Struktur-Rückgaben werden am Funktionsende nur nach Umfang
beschrieben. Boolean-Ergebnisse werden neutral als Ja/Nein angegeben, da
auch eine reguläre Abfrage `false` liefern kann.

Für ausdrücklich geprüfte, nicht vertrauliche Gerätewerte stehen
`DebugFlowRequestAction($id, $value, __FUNCTION__, __LINE__)`,
`DebugFlowWriteValue($ident, $value, __FUNCTION__, __LINE__, $includeValue)`
und `DebugFlowReadValue($id, __FUNCTION__, __LINE__, $includeValue)` bereit.
Jeder native Aufruf erfolgt genau einmal. Aktionsannahme bestätigt keine
Gerätewirkung. Bei privaten Textquellen oder Statuspayloads muss
`$includeValue` auf `false` gesetzt werden; Zugangsdaten und unkontrollierte
Bodies werden nicht als Kontext übergeben. Schreibresultate und geworfene
Ausnahmen bleiben erhalten. Ein ausfallender Debug-Kanal wird abgefangen.

Der Helper überschreibt keine nativen Modulmethoden und setzt keine Timer,
Properties oder Runtime-Attribute. Er wird über die bestehende Subscription
und sein Bundle einschließlich `DebugHelper` eingebunden. Die Einführung
verändert den abgenommenen OHA-Piloten nicht.

## ReverseGeocodingHelper

`src/ReverseGeocodingHelper.php` provides optional server-side reverse geocoding
through a configured private Nominatim service or the Geoapify API. Consumers own opt-in and serialized
background scheduling. Coordinates, format/language parameters and, for Geoapify,
the provider API key leave Symcon; no device IDs, names or Apple credentials are accepted. A GitHub fork is source
code, not a running API endpoint. Public OSM Nominatim is excluded for device tracking.

- Call `RegisterReverseGeocodingCache()` in `Create()`.
- `NormalizeNominatimServer($url)` validates an HTTP(S) base URL with an optional
  path prefix. Credentials, query parameters and fragments are rejected.
- `ReverseGeocodingPrecision($accuracy)` selects conservative detail from a positive
  horizontal accuracy in metres: up to and including 20 m building (`zoom=18`),
  up to 100 m street (`17`), up to 1000 m area (`14`), otherwise city (`10`).
  Missing, zero, negative or non-finite accuracy uses city detail and level `unknown`.
  The result contains `zoom`, `level`, `approximate` and normalized `accuracy`.
  These are display limits, not a guarantee of a correct house number.
- `ReadNominatimAddress($server, $latitude, $longitude, $language = 'de', $zoom = 18)` never
  performs I/O: `null` means pending/expired; `''` means a cached missing result
  or a provider cooldown.
- `ResolveNominatimAddress(..., $language = 'de', $zoom = 18)` performs at most one request. Positive results
  expire after 24 hours; missing coverage after one hour. HTTP/response failures
  impose a five-minute cooldown per server/language. Consumers schedule retries.
- `ClearReverseGeocodingCache()` removes addresses and cooldowns, for example on
  disable. Persistent instance attributes contain private location data; protect
  configuration files and backups accordingly.

Accuracy-aware consumers pass the policy zoom to both calls and provide localized
approximation notices. Existing callers retain building detail by default. Zoom
must be an integer from 0 to 18. Both providers use structured address fields at
every level; neither `display_name` nor `formatted` is used as a fallback.
Missing components are omitted; no usable structured fields produce an empty result.

The common compact layout is **street house number, postcode place, country**.
At building detail, redundant neighbourhood/county/state hierarchies are omitted.
Street detail removes the house number; area detail uses at most one neighbourhood
instead of a street; city detail omits neighbourhood and postcode. The existing
20/100/1000-metre policy is unchanged. Rural places and missing city fields use
village/town/municipality/hamlet or a permitted broader fallback. Empty and duplicate
components are omitted and whitespace normalized. This is a display format, not
postal validation or a guarantee that two providers return identical source data.

Cache keys separate server/language/detail and round coordinates to six decimals.
Legacy keys, including the previous `detail-v1` format, are not reused; addresses
are resolved again as consumers schedule them. The format migration does not reset
Geoapify usage; exhausted budgets continue to defer uncached positions.
Provider cooldowns remain shared across detail tiers. At most
512 positions and 16 cooldowns are retained. The API uses `/reverse`, `format=jsonv2`,
`addressdetails=1`, `layer=address`, `zoom` and `accept-language` (`de`, `en`, `de-DE`, etc.). TLS is verified;
redirects are never followed. Connection/total timeout: two/four seconds; response
limit: 64 KiB. Provider error details are never exposed. Addresses are plain data;
render them through `textContent` or equivalent escaping. Nominatim returns the
closest suitable OSM object, which may differ from the exact street/house number.

`PersistentJsonCacheHelper` is a manifest dependency included in automatic bundles.
There are no additional resources or translation catalogs in this helper.

### Geoapify and local request budgets

- `NormalizeGeoapifyKey($apiKey)` validates the credential without including it in
  exceptions. `NormalizeGeoapifyLanguage($language)` accepts two-letter languages
  and maps regional variants such as `de-DE` to `de`.
- `ReadGeoapifyAddress($apiKey, $latitude, $longitude, $language = 'de', $zoom = 18)`
  has the same cache-only null/empty contract as Nominatim. Credential fingerprints,
  language, coordinates and detail separate entries; plaintext keys are not stored.
- `ResolveGeoapifyAddress(..., $language = 'de', $zoom = 18, $dailyLimit = 2500)`
  performs at most one request to the fixed HTTPS reverse endpoint. It selects
  building/street/locality/city and uses the shared compact formatter at every detail level.
  It shares the bounded TLS transport, TTLs and five-minute failure cooldown.
  Existing Nominatim calls and their defaults remain compatible.
- `GeoapifyDailyUsage($dailyLimit)` returns `day` (UTC), `used`, `remaining`,
  `available` and the Unix timestamp `nextReset`. Limits range from 0 to 1,000,000;
  zero stops new requests but permits cached addresses. Usage is persisted before
  HTTP, includes failures, and survives restarts, key changes and address-cache
  clearing. Corrupt counters fail closed; a worker contains them for that UTC day.
- Resolve calls **must be serialized per instance**, including quota reservation.
  Budgets are instance-local, not provider account quotas. For shared API keys,
  coordinate the sum of limits across instances, systems and other applications.
  Consumers should defer pending work until UTC midnight when the budget is spent.
- Consumers select Geoapify explicitly and retain server-side credentials. Do not
  include keys or request URLs in visualization state, logs, exceptions or examples.
  The fixed source credit `Powered by Geoapify` linked to
  `https://www.geoapify.com/` and OpenStreetMap attribution must accompany displayed
  results. API keys/configuration and backups need normal Symcon access protection.

Provider reference: [Geoapify reverse API](https://apidocs.geoapify.com/docs/geocoding/reverse-geocoding/).
No API account is created or contacted by the offline tests. No new dependency or
map resource is required; the existing ReverseGeocodingHelper subscription contains
the complete implementation and its PersistentJsonCacheHelper dependency.

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

        $form['elements'] = $this->SetFormFieldVisibility(
            $form['elements'],
            ['CustomRangeValue', 'CustomRangeUnit'],
            $this->ReadPropertyString('Range') === 'custom'
        );

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
| `SetFormFieldVisibility()` | Setzt `visible` für Felder mit exakt passendem `name` auch in verschachtelten `items`; andere Felder bleiben unverändert. Die Eingabeliste wird nicht verändert. |

## SVGPreviewHelper

`src/SVGPreviewHelper.php` stellt die gemeinsame technische Basis für
SVG-Vorschauen in Symcon-Konfigurationsformularen bereit. Der Helper kodiert
vertrauenswürdig erzeugtes SVG als Base64-Data-URI, maskiert dynamische Texte
XML-sicher und initialisiert ein eindeutig benanntes `Image`-Formularfeld auch
innerhalb verschachtelter Panels oder Popups.

Die konkrete Vorschau bleibt Aufgabe des Consumers. Der Helper kennt weder
Diagrammtypen noch Farben, Layouts oder fachliche Werte. `dataUri()` prüft den
SVG-Dokumentanfang, ist aber ausdrücklich kein Sanitizer für beliebiges SVG.
Consumer erzeugen deshalb ausschließlich kontrolliertes, eigenständiges
Markup und übergeben dynamische Werte zuvor an `escape()`.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/SVGPreviewHelper.php';

use Burki24\SymconModuleHelper\ConfigurationFormHelper;
use Burki24\SymconModuleHelper\SVGPreviewHelper;

class ExampleModule extends IPSModuleStrict
{
    use ConfigurationFormHelper;

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $label = SVGPreviewHelper::escape($this->ReadPropertyString('Title'));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
            . '<text>' . $label . '</text></svg>';
        $form = SVGPreviewHelper::withImage($form, 'PreviewImage', $svg);

        return $this->EncodeConfigurationForm($form);
    }

    public function UpdatePreview(string $Title): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><text>'
            . SVGPreviewHelper::escape($Title)
            . '</text></svg>';
        $this->UpdateFormField('PreviewImage', 'image', SVGPreviewHelper::dataUri($svg));
    }
}
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `dataUri()` | Prüft den SVG-Dokumentanfang und erzeugt eine Base64-kodierte `image/svg+xml`-Data-URI. |
| `escape()` | Maskiert dynamische Texte und Attributwerte für XML-/SVG-Markup. |
| `withImage()` | Liefert eine Kopie des Formulars mit genau einem eindeutig benannten `Image`-Feld, dessen `image`-Wert die SVG-Data-URI enthält. |

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

## DebugHelper

`src/DebugHelper.php` formatiert strukturierte Symcon-Debugausgaben und maskiert
bekannte Zugangsdaten. JSON-Objekte und -Listen, die als String oder als
Base64-kodierter JSON-String übergeben werden, werden vor der Ausgabe ebenfalls
rekursiv bereinigt. `Passwort` und Feldnamen mit der Endung `passwort`, etwa
`SMTP_PASSWORT`, werden wie `password` maskiert. Groß-/Kleinschreibung und
übliche Trennzeichen ändern die Erkennung nicht. Zusätzliche sensible
Feldnamen gelten sowohl in Strukturen als auch bei Textzuweisungen.

Freitext-Zuweisungen wie `password="zwei geheime Worte"` oder
`Passwort: zwei geheime Worte, status=ok` werden vollständig maskiert.
Anführungszeichen, maskierte Anführungszeichen, mehrzeilige zitierte Werte
und abgebrochene zitierte Werte werden berücksichtigt. Unzitierte Werte
reichen vorsichtshalber bis zur nächsten erkennbaren Feldzuweisung oder
zum Zeilenende; dabei kann auch erläuternder Text entfallen. URL-Parameter
werden einschließlich URL-kodierter Feldnamen geprüft. Bei einem Fehler
der Musterprüfung wird der betroffene Text durch einen neutralen Platzhalter
ersetzt, statt unbereinigte Eingaben auszugeben.

Messwerte und eindeutig getrennte Statusfelder bleiben erhalten. Unbeschriftete
Geheimnisse, Personennamen, E-Mail-Adressen, interne IP-Adressen und beliebige
Skript-/Medieninhalte werden nicht allgemein erkannt. Solche Inhalte muss das
Modul vor der Übergabe auslassen oder über zusätzliche sensible Feldnamen
schützen. Auch die Debug-Kategorie `$message` bleibt Verantwortung des Aufrufers.

`SendSafeDebug()` begrenzt einzelne Ausgaben standardmäßig auf 16 KiB. Für
bewusst vollständige Diagnoseausgaben kann der Aufrufer `PHP_INT_MAX` als
`$maxLength` übergeben. Diese Option sollte nur hinter einer expliziten
Debug-Aktivierung verwendet werden, weil große Payloads viele Ressourcen im
Symcon-Debugkanal belegen können. Die Maskierung bekannter Zugangsdaten bleibt
dabei aktiv.

## ChunkedJsonTransferHelper

`src/ChunkedJsonTransferHelper.php` zerlegt große JSON-Listen in kurzlebige, größenbegrenzte Seiten für mehrstufige Symcon-Modulaufrufe. Damit können Child-, Splitter- und Parent-Module Datenmengen austauschen, die Symcons festes 1-MiB-Limit für PHP- und Datenflussausgaben überschreiten würden.

Ab Version 1.1.0 liegen die Seiten in einer temporären, mit AES-256-GCM verschlüsselten Datei je Transfer, nicht mehr gemeinsam in Instanzpuffern. Dort bleiben nur kleine Metadaten und der flüchtige Schlüssel. Vor jedem nichtleeren Bufferschreibzugriff wird die gesamte Instanzbelegung inklusive anderer Helper geprüft (Budget 240 KiB). Die Standard-Seitengröße bleibt 192 KiB, maximal 240 KiB. Pro Instanz sind höchstens 16 gleichzeitig gespeicherte Transfers mit jeweils maximal 64 MiB Dateibelegung zulässig. Das Seitenprotokoll bleibt unverändert.

Voraussetzungen sind PHP-OpenSSL mit AES-256-GCM, ein beschreibbares System-Tempverzeichnis sowie `GetBufferList()`. Dateien enthalten keine unverschlüsselten Kalenderdaten; unter Unix erhalten sie Rechte 0600. Name und Seitenauthentifizierung binden den Inhalt an Instanz, Transfer und Seite. Instanzweite Symcon-Semaphoren schützen die Metadaten bei konkurrierenden Aufrufen. Der Helper definiert keine fachlichen Operationen oder Response-Hüllen; diese bleiben Aufgabe des jeweiligen Moduls.

Transfers laufen standardmäßig nach fünf Minuten ab und müssen nach erfolgreichem Empfang explizit gelöscht werden; dabei wird auch die Datei entfernt. Fehlgeschlagene Erzeugungen werden bereinigt. Nach einem Symcon-Neustart sind die Schlüssel verloren und alte Dateien nicht mehr lesbar. Verwaiste verschlüsselte Dateien werden nach Ablauf bei der nächsten Transfererzeugung derselben Instanz entfernt; ohne weitere Aufrufe können sie bis zur System-Tempbereinigung verbleiben. Alte, bereits laufende Buffertransfers der Version 1.0.0 bleiben bis zu ihrem Abschluss oder Ablauf lesbar. Ein einzelnes Listenelement muss weiterhin vollständig in eine Seite passen.

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

## VariableSearchHelper

`src/VariableSearchHelper.php` durchsucht einen begrenzten Teil des Symcon-
Objektbaums nach Variablen. Der Helper filtert nach Namensbestandteil und
Variablentyp, berücksichtigt ausgeblendete Objekte, Tiefen-, Objekt- und
Treffergrenzen und liefert ausschließlich neutrale Metadaten aus Variablen-ID,
Name, Pfad und Typ. Bereits konfigurierte Variablen werden allein anhand ihrer
ID ausgeschlossen und gezählt.

Die Formularoberfläche und fachliche Standardwerte bleiben beim Consumer.
Vor dem Übernehmen einer Mehrfachauswahl wiederholt der Consumer dieselbe
Suche und lässt die übermittelten IDs mit
`ResolveSelectedVariableIDs()` gegen dieses frische Ergebnis prüfen. Dadurch
werden veraltete oder manipulierte Auswahldaten kontrolliert abgewiesen.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/VariableSearchHelper.php';

use Burki24\SymconModuleHelper\VariableSearchHelper;

$result = VariableSearchHelper::Search(
    rootObjectID: 0,
    nameContains: 'Fenster',
    variableTypes: [0],
    includeHidden: false,
    excludedVariableIDs: [12345]
);

$selectedIDs = VariableSearchHelper::ResolveSelectedVariableIDs(
    $submittedCandidateRows,
    $freshResult['rows']
);
```

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `Search()` | Durchsucht einen begrenzten Objektbaum und liefert passende, noch nicht konfigurierte Variablen sowie Zähler und Begrenzungsstatus. |
| `ResolveSelectedVariableIDs()` | Liefert eindeutige ausgewählte IDs, sofern jede davon weiterhin im frischen Suchergebnis enthalten ist. |

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
| `OptionsPresentation()` | Read-only-Wertanzeige für Boolean-/String-Zustände über `OPTIONS`. |
| `IntegerOptionsPresentation()` | Read-only-Integer-Zustände über exakte Zahlenintervalle mit Textkonstanten. |
| `WebContentPresentation()` | Webinhalt als HTML oder Webseite mit steuerbarem Padding. |
| `DateTimePresentation()` | Parametrisierbare native Datum-/Uhrzeitdarstellung. |
| `DateTimeTemplatePresentation()` | Datum/Uhrzeit mit einer nativen Symcon-Vorlage, um bestehendes Darstellungsverhalten exakt beizubehalten. |

Die Methoden geben ausschließlich native Symcon-Presentation-Arrays zurück. Sie führen keine Geräteerkennung durch, lesen keine Modul-Properties und übersetzen keine Beschriftungen. Dadurch kann dieselbe Helper-Datei unverändert in unterschiedlichen Symcon-Libraries vendort werden.

Bei `IntegerOptionsPresentation()` müssen Optionslisten ausschließlich eindeutige
Integer-Werte und String-Beschriftungen enthalten. Jeder Wert erhält ein eigenes
Intervall mit identischem Minimum und Maximum sowie `ConstantActive=true` und
`ConstantValue=Caption`. So erscheinen beispielsweise `-1`, `0`, `1` als
**Nicht verfügbar**, **Nein**, **Ja**; nicht aufgeführte Werte werden nicht
versehentlich einem benachbarten Zustand zugeordnet. Die gespeicherten Werte und
Variablentypen ändern sich nicht. `IconActive`/`IconValue` und
`ColorActive`/`ColorValue` werden auf die entsprechenden Intervalle übertragen.
Die vorhandene `OptionsPresentation()`-API bleibt unverändert. Eine Integer-Variable
mit nativer Wertanzeige unterstützt laut [offiziellem SDK](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/value-presentation/)
keine `OPTIONS`; diese sind ausschließlich für Boolean und String vorgesehen.

### Vollständige Wertanzeige-Optionen unter Symcon 9.0

`BooleanPresentation()` und `OptionsPresentation()` ergänzen in Boolean-/String-
Zeilen ausschließlich fehlende Override-Felder. `Value`, `Caption`, Reihenfolge,
Datentypen und sämtliche ausdrücklich gesetzten oder zusätzlichen Felder bleiben
erhalten. Auch `false`, eine schwarze Farbe als `0` und deaktivierte, gespeicherte
Overrides werden nicht durch Defaults ersetzt. Eingabearrays werden nicht verändert.

| Feld | Typ | Ergänzung, wenn das Feld fehlt |
| --- | --- | --- |
| `IconActive` | bool | `false` |
| `IconValue` | string | `''` |
| `ColorActive` | bool | `false` |
| `ColorValue` | int | `-1` |
| `ContentColorActive` | bool | `false` |
| `ContentColorValue` | int | `-1` |

Die ersten vier Felder sind in der [offiziellen Wertanzeige-Dokumentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/wertanzeige/)
beschrieben. Die Textfarbfelder und die direkten Flag-Zugriffe sind zusätzlich
im vom Benutzer veröffentlichten Formular von **Symcon 9.0 Windows, Build
5b5346950c61 vom 21.10.2026** belegt: [Version](https://community.symcon.de/t/beta-modul-symconicloud/144792/56),
[Formularfehler](https://community.symcon.de/t/beta-modul-symconicloud/144792/61).
Die Regression prüft diesen Feldzugriff ohne fehlende Array-Schlüssel. Das ist
ein Offline-Nachweis aus dem gemeldeten Formular, kein eigener Laufzeittest auf
diesem Build und kein belegter Vergleich mit Symcon 9.1.

Es werden keine Darstellungs-, Icon- oder Farb-Overrides aktiviert. Aufzählungen,
numerische Intervalle und bisherige numerische Optionszeilen ändern sich nicht.
Der Helper erzeugt nur Definitionsarrays und schreibt keine Variablen oder
benutzerdefinierten Darstellungen. Bestehende Instanzen benötigen nach regulärer
Helper-Verteilung eine erneute Registrierung der nativen Darstellung durch ihr
Modul; Werte, Idents und Objekt-IDs müssen dabei erhalten bleiben. Eine eigene
Darstellung hat weiterhin Vorrang und wird nicht automatisch überschrieben.
Ein bereits gespeichertes, unvollständiges benutzerdefiniertes `OPTIONS`-Array
wird von diesem reinen Builder nicht nachträglich repariert. Die tatsächliche
Formular- und WebFront-Abnahme auf dem betroffenen 9.0-Build bleibt erforderlich.

## GeofenceHelper

`AggregateGeofencePresence(list<int> $states): int` bildet die gemeinsame
Anwesenheit unabhängiger Auswertungen: mindestens eine 1 ergibt 1; eine
nichtleere Liste ausschließlich aus 0 ergibt 0; sonst -1. Zulässig sind nur
die ganzzahligen Zustände -1, 0 und 1. Leere Auswahl ist unbekannt; ungültige
Zustände oder assoziative Eingaben werfen `InvalidArgumentException`. Die
Funktion verändert weder die Einzelzustände noch deren Zeitmessung.

Reine, anbieterunabhängige Auswertung kreisförmiger Zonen ohne I/O, Timer oder
Symcon-Abhängigkeit. `NormalizeGeofenceZones(array $zones)` prüft eine Liste von
1 bis 64 Zonen mit eindeutiger `id`, `name`, `latitude`, `longitude` und positivem
`radius` in Metern. `GeofenceDistanceMeters()` berechnet Luftlinienentfernungen
auf einer Kugel mit mittlerem Erdradius 6.371.008,8 m; Datumsgrenze und Pole
werden berücksichtigt.

`EvaluateGeofence($zones, $reference, $location, $state, $now, $settings)` benötigt
eine bestehende Referenzzonen-ID, einen expliziten Unix-Zeitpunkt und optional
eine Position mit `latitude`, `longitude`, positivem `accuracy`, ganzzahligem
`timestamp` sowie `isOld`/`isInaccurate`. Einstellungen: `maxAge=900` s,
`maxAccuracy=100` m, `hysteresis=25` m, `confirmSeconds=30` s und `maxSpeed=300`
km/h (0 deaktiviert den Geschwindigkeitsfilter). Der Zonenradius muss größer
als der Grenzpuffer sein. Konfigurationsfehler werfen `InvalidArgumentException`.

Zonen können `hysteresis`, `maxAccuracy` und `confirmSeconds` überschreiben;
fehlende oder null gesetzte Felder erben den globalen Wert. `customGPS=false`
erzwingt die globale Übernahme und ignoriert ruhende eigene Werte. Ohne dieses
Flag werden vorhandene eigene Werte berücksichtigt. Die normalisierte Struktur
alter Zonen ohne aktive Überschreibungen bleibt unverändert, damit bestehende
Quellidentitäten und Aufenthalte erhalten bleiben.
`ResolveGeofenceZoneSettings($zones, $settings)` liefert die geprüften wirksamen
Einstellungen je Zonen-ID. Radius und Puffer werden je Zone zusammen geprüft;
Standortalter und Geschwindigkeitsfilter bleiben global.

Ein Wechsel benötigt die Qualität der Herkunfts- und der Zielzone und die längere
der beiden Bestätigungszeiten. Für Eintritt aus „außerhalb“ bzw. Austritt ins
Freie gilt jeweils nur die vorhandene Zone. Ein neuer Cacheaufruf derselben
Position bestätigt weiterhin keinen Wechsel. Bei Qualitätsverlust merkt sich
der Zustand die zuletzt bestätigte Herkunftszone in `qualityZone`; auch spätere
ungenaue Positionen oder eine Serialisierung können deren Austrittsregeln nicht
umgehen. Ein verlässlicher Neustart des Aufenthalts erfordert eine neue Position.
Ein Wechsel in eine andere Zone muss auch nach Unbekannt die längere
Bestätigungszeit abwarten; bis zur Bestätigung bleibt das Ergebnis unbekannt.
Alte persistierte Zustände ohne dieses zusätzliche Feld bleiben lesbar.

Eine weit entfernte Zielzone darf ihre eigene großzügigere Qualitätsgrenze
nutzen, wenn der gesamte Genauigkeitskreis eindeutig außerhalb der Referenzzone
liegt und keine strengere Herkunftsregel verletzt wird. Im Grenzbereich der
Referenzzone kann eine großzügigere andere Zone keine falsche Abwesenheit
begründen. Die Priorität einer eindeutig passenden Referenzzone bei überlappenden
Zonen bleibt erhalten; Qualitäts- und Bestätigungsregeln gelten auch dort.

Das Ergebnis enthält `presence` (-1 unbekannt, 0 außerhalb der Referenzzone,
1 innerhalb), `zone` (null unbekannt, leer außerhalb, sonst Zonen-ID), `since`,
`duration`, `distance` zur Referenzzone (null bei unbrauchbarem Standort),
`pending`, `reason` und den vom Aufrufer zu persistierenden `state`.
Eine eindeutig passende Referenzzone hat Vorrang vor anderen Zonen; sonst gilt
die Listenreihenfolge. Der Grenzbereich hält einen bestehenden Zustand; ohne
Vorgeschichte bleibt er unbekannt. Unterschiedliche neue Beobachtungen und
abgelaufene Bestätigungszeit sind für einen Wechsel nötig. Die erste klare
Position wird sofort angenommen. Wiederholte Zeitstempel bestätigen keine
Wechsel; widersprüchliche oder rückläufige Beobachtungen werden verworfen.

Schlechte, alte, zukünftige oder unplausible Positionen erzeugen unbekannte
Anwesenheit und keine aktuelle Entfernung. Der Geschwindigkeitsfilter zieht
die Unsicherheit beider Positionen von der Bewegung ab. Datenlücken größer
als `maxAge` unterbrechen einen Aufenthalt. `InvalidateGeofenceState($state)`
verwirft bei Quellfehlern die Anwesenheit, behält aber die zuletzt beobachtete
Position als Marke: ein alter Cachetreffer darf keine Wiederaufnahme bestätigen.
Der Aufrufer trennt Zustände verschiedener Geräte/Konten/Konfigurationen und
übersetzt die festen `reason`-Codes selbst. `duration` zählt beobachtete Sekunden
innerhalb einer bestätigten Zone; außerhalb oder bei Unbekannt ist sie 0.

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

Im WebHook-Kontext kann Symcon die Modulklasse mit einem synthetischen Reflection-Dateinamen auswerten. Module mit HTTP-Ausgabe überschreiben deshalb den zentralen Verzeichnis-Hook:

```php
protected function VisualizationAssetDirectory(): string
{
    return __DIR__ . '/visualization';
}
```

Ohne Override bleibt die bisherige Reflection-Auflösung erhalten. `VisualizationHTTPOriginScript()` liefert den gemeinsam verteilten Browser-Baustein `visualization/http-origin.js`. Dessen `SymconHTTPOrigin.resolve(window)` ermittelt einen HTTP(S)-Ursprung aus eigener Adresse, `document.baseURI`, zugänglichem Elternfenster, `ancestorOrigins` oder `document.referrer`, in dieser Reihenfolge. Native IPSView-HTMLBoxen können nur ein vom Client eingefügtes HTTP(S)-Basiselement besitzen; dieses genügt auch bei fehlenden Eltern-/Referrerinformationen. Echte HTTP-Seiten behalten immer ihren eigenen Ursprung. Pfade, Query und Fragment werden nicht übernommen; nicht unterstützte Schemata und URLs mit Zugangsdaten werden abgelehnt. Ohne geeigneten Ursprung liefert er `null`; nur native Ansichten ohne sämtliche geeigneten Herkunftsinformationen benötigen weiterhin eine explizite Serveradresse. Der aufrufende Loader behandelt eine ausdrücklich konfigurierte Adresse vorrangig.

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

Ab Version 1.5.0 speichert der Helper die Objekt-ID je registriertem Ausgabe-Ident
im Attribut `IPSViewHTMLVariableIDs`. Nach der erstmaligen Übernahme dürfen
WebContent-Variablen im Objektbaum verschoben oder umbenannt werden; Aktualisierung,
Neugenerierung und die ausdrücklich bestätigte Löschaktion verwenden weiterhin
dieselbe ID. Direkte Kindvariablen älterer Versionen werden automatisch übernommen.
Bereits zuvor verschobene oder duplizierte Ausgaben werden nicht global gesucht:
Ihre Zuordnung muss vor der Übernahme eindeutig unter der Besitzerinstanz
wiederhergestellt werden. Consumer können bestehende Inhalte über
`IPSViewHTMLVariableID($ident)` statt über eine Kindobjekt-Suche lesen.

`src/IPSViewHTMLPageHelper.php` vereinheitlicht die technische Erzeugung nativer HTML-SDK-Seiten und eigenständiger IPSView-WebContent-Seiten. Der Helper lädt immer dieselbe Asset-Struktur aus `visualization/index.html`, `visualization/style.css` und `visualization/app.js`, erzeugt einen gemeinsamen Bootstrap-Vertrag und ersetzt einen festen Satz validierter Template-Platzhalter. Version 1.4.1 stellt außerdem den gemeinsamen Formularbutton zur manuellen Neugenerierung aller registrierten IPSView-WebContent-Variablen bereit.

Template-Platzhalter werden ausschließlich im Template geprüft und genau einmal ersetzt. Platzhalterähnliche Texte in Daten, Übersetzungen oder Ersatzwerten bleiben unverändert und werden nicht als weitere Template-Anweisungen ausgewertet.

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
`UpdateIPSViewHTMLVariable()` je Ident auf. Für die gemeinsame manuelle
Neugenerierung stellt das Modul zusätzlich `GetIPSViewHTML()` bereit und leitet
`RequestAction()` zuerst an `HandleIPSViewHTMLPageAction()` weiter. Die fachlichen Daten können dabei
einmal aufgebaut und anschließend getrennt für Symcon und IPSView gerendert
werden. Beim Deaktivieren werden vorhandene IPSView-Variablen automatisch im
Konfigurationsformular als beibehalten erkannt. Erst die dortige bestätigte
Löschaktion entfernt sie über `UnregisterVariable()`; ein einfaches Deaktivieren
oder ein Modulupdate löscht keine Variablen.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `RegisterIPSViewHTMLPageProperties()` | Registriert `EnableIPSView` sowie die internen Eigenschaften und Attribute für sichere, bestätigte Löschaufträge. |
| `HandleIPSViewHTMLPageAction()` | Verarbeitet die gemeinsamen Formularaktionen zur Neugenerierung und bestätigten Löschung. |
| `IPSViewHTMLPageFormItems()` | Liefert die zentral übersetzte Checkbox, Hinweise, den Button zur Neugenerierung und bei beibehaltenen Variablen den Bestätigungsdialog zur Löschung. |
| `InsertIPSViewHTMLPageFormItems()` | Ersetzt einen verschachtelten Formular-Marker durch die gemeinsamen IPSView-Ausgabeeinstellungen. |
| `IsIPSViewHTMLPageEnabled()` | Liefert den aktuellen Zustand der gemeinsamen IPSView-Aktivierung. |
| `MaintainIPSViewHTMLVariable()` | Legt eine optionale Stringvariable mit WebContent-Darstellung an, behält sie beim Deaktivieren und löscht sie nur nach ausdrücklicher Bestätigung. |
| `UpdateIPSViewHTMLVariable()` | Aktualisiert eine vorhandene optionale IPSView-Variable nur bei aktivierter Ausgabe. |
| `RegenerateIPSViewHTMLPages()` | Rendert über `GetIPSViewHTML()` neu und aktualisiert alle registrierten, vorhandenen IPSView-Variablen. |
| `RenderVisualizationHTMLPage()` | Lädt Template, CSS und JavaScript, erzeugt den gemeinsamen Bootstrap und rendert das vollständige HTML-Dokument. |
| `EncodeVisualizationHTMLJSON()` | Kodiert JSON mit zentralen Schutzflags für die sichere Einbettung in ein `script`-Element. |
| `IPSViewTranslationsFromLocale()` | Liest alle Quelltexte aus der `locale.json` des konkreten Moduls und wählt ihre Übersetzung ausdrücklich anhand der aktiven Symcon-Sprache. Die Modulpfadauflösung folgt dabei demselben `VisualizationAssetDirectory()`-Override wie die Visualisierungsdateien, sodass Sprache und Übersetzungen auch bei synthetischen WebHook-Dateipfaden stabil bleiben. |
| `IPSViewTranslationsFor()` | Übersetzt einen explizit übergebenen Satz von Quelltexten. |

Der Helper bezieht `HelperTranslationHelper` und `VisualizationAssetHelper` als zentrale Abhängigkeiten. Die helper-eigenen Formulartexte werden aus `translations/IPSViewHTMLPageHelper.json` geladen; Consumer benötigen dafür keine zusätzlichen `locale.json`-Einträge. `VisualizationThemeHelper` und `IPSViewStyleHelper` bleiben eigenständige Bausteine; deren CSS wird dem Seiten-Helper lediglich übergeben.

Module können die im erweiterten IPSView-Farbeditor sichtbaren nativen Steuerungsfamilien mit einer geschützten Methode `IPSViewStyleNativeFamilyNames()` einschränken. Der zentrale Katalog und der verlustfreie Theme-Transport bleiben dabei vollständig erhalten; der Hook steuert ausschließlich die modulspezifische Formularanzeige.

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

## ResponsiveVisualizationHelper

`src/ResponsiveVisualizationHelper.php` bildet die gemeinsame Grundlage für responsive native HTML-SDK-Kacheln. Er richtet den Root-Container einer Visualisierung als Container-Query-Kontext ein. Dadurch reagiert ein Modul auf die tatsächlich verfügbare Kachelbreite statt auf die oft davon abweichende Browser- oder App-Breite.

Die konkrete Darstellung bleibt bewusst im jeweiligen Modul. Dort werden die Layoutregeln mit `@container symcon-visualization` definiert. Das funktioniert gleichermaßen in der Symcon-Konsole, im Browser und in den Symcon-Apps; User-Agent-Erkennung ist nicht erforderlich.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ResponsiveVisualizationHelper.php';

use Burki24\SymconModuleHelper\ResponsiveVisualizationHelper;

class ExampleModule extends IPSModuleStrict
{
    use ResponsiveVisualizationHelper;

    public function GetVisualizationHtml(): string
    {
        return '<style>' . $this->ResponsiveVisualizationCSS('#example-app') . '</style>';
    }
}
```

```css
@container symcon-visualization (max-width: 42rem) {
    .toolbar { padding-inline: var(--symc-responsive-padding); }
}
```

| Methode | Aufgabe |
| --- | --- |
| `ResponsiveVisualizationCSS()` | Richtet einen Root-Selektor als Container-Query-Kontext ein und liefert gemeinsame Abstands- und Touch-Target-Tokens. Mit dem optionalen dritten Parameter `true` wird ein bildschirmfüllender Flex-Rahmen ohne äußeres Scrollen erzeugt. |
| `ResponsiveVisualizationLayoutOptions(bool $nativeTile = true)` | Liefert `tileHeaderVisible` und `tileHeaderInset` für die native Kachelüberschrift sowie `tileMaximizeVisible` und `tileActionInset` für den Maximieren-Button. Sichtbarer Titel oder fehlgeschlagene Objekterkennung reservieren 58 CSS-Pixel oben. Bei verborgenem Titel und sichtbarem Maximieren-Button werden stattdessen 44 Pixel rechts in der oberen Bedienleiste reserviert. Beide ausgeblendet oder IPSView (`false`): keine Reservierung. |

Für bildschirmfüllende Karten und Diagramme ruft das Modul beispielsweise `ResponsiveVisualizationCSS('#example-app', 'example-view', true)` auf und übergibt die Layoutoptionen an seinen Client. Der Client setzt `--symc-tile-header-inset` am Root-Element auf den gelieferten Pixelwert. Der gemeinsame Rahmen berücksichtigt diesen Abstand einmal als oberen Innenabstand. Modul-CSS darf keinen zweiten Titelabstand und keine feste gemeinsame Höhenvorgabe ergänzen; fachliche Aufteilung, Listen und Breakpoints bleiben im Modul. Bestehende Aufrufe mit zwei Parametern behalten ihr bisheriges CSS unverändert.

Die obere Bedienleiste wird mit `data-symc-tile-toolbar` markiert; der Client
setzt zusätzlich `--symc-tile-action-inset` auf den gelieferten `tileActionInset`
in CSS-Pixeln. Nur diese markierte Leiste erhält den rechten Innenabstand.
Karte, Diagramm und Geräteliste behalten ihre volle Breite. Die beiden
Sichtbarkeitsangaben stammen aus derselben `IPS_GetObject`-Abfrage
(`ObjectIsHiddenTitle`/`ObjectIsHiddenMaximize`, ab Symcon 9.1). Fehlende Felder
gelten konservativ als sichtbar; IPSView liest keine Objektflags und reserviert
keine nativen Bedienelemente. Bestehende Consumer ohne Markierung bleiben
unverändert; neue Metadatenfelder ergänzen den bisherigen Vertrag.

## VisualizationThemeConfigurationHelper

`src/VisualizationThemeConfigurationHelper.php` ergänzt den nativen HTML-SDK-Stil optional um eine in allen Modulen identische Bearbeitungsmaske. Standardmäßig bleiben Symcons dynamische Inhalts-, Kachel- und Akzentfarben einschließlich Light-/Dark-Umschaltung aktiv. Die Option **Eigene Kachelfarben verwenden** wertet die semantischen Rollen für Text, Überschriften, Unterüberschriften, Hintergrund, Akzent sowie Information, Erfolg, Warnung und Gefahr einzeln aus. Nur Werte, die von ihrem jeweiligen Helper-Standard abweichen, werden als feste CSS-Overrides ausgegeben; alle unveränderten Rollen folgen weiterhin dem nativen Symcon-Schema. Das Zurücksetzen einer Farbe auf ihren Standard entfernt damit automatisch auch deren Override.

Der Helper besitzt seine deutschen und englischen Formulartexte selbst. Consumer müssen daher weder Beschriftungen duplizieren noch eigene `locale.json`-Einträge für diese Maske pflegen.

```php
require_once __DIR__ . '/../libs/helper/VisualizationThemeConfigurationHelper.php';

use Burki24\SymconModuleHelper\VisualizationThemeConfigurationHelper;

class ExampleModule extends IPSModuleStrict
{
    use VisualizationThemeConfigurationHelper;

    public function Create(): void
    {
        parent::Create();
        $this->RegisterVisualizationThemeProperties();
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $this->InsertVisualizationThemeFormItems($form['elements']);

        return json_encode($form);
    }
}
```

Das statische Formular enthält am gewünschten Einfügepunkt lediglich folgenden Marker:

```json
{
  "type": "Label",
  "caption": "Configure the shared Symcon tile theme used by the HTML-SDK visualization."
}
```

| Methode | Aufgabe |
| --- | --- |
| `RegisterVisualizationThemeProperties()` | Registriert den Opt-in-Schalter und die neun gemeinsamen Farbeingenschaften. |
| `VisualizationThemeFormItems()` | Liefert die vollständige, übersetzte Konfigurationsmaske. |
| `InsertVisualizationThemeFormItems()` | Ersetzt den Marker rekursiv durch die gemeinsame Maske. |
| `VisualizationThemeCSS()` | Liefert den nativen Basisstil und – falls aktiviert – die konfigurierten Overrides. |
| `VisualizationThemeColorOverrides()` | Liefert die aktiven semantischen Overrides als CSS-Token-Map. |

## HelperTranslationHelper

`src/HelperTranslationHelper.php` stellt helper-eigene Übersetzungen bereit. Sichtbare Beschriftungen und Hinweise eines zentralen Helpers werden damit bereits im Helper übersetzt; Consumer-Module benötigen dafür keine Einträge in ihrer eigenen `locale.json`.

Die Übersetzungen liegen als versionierte JSON-Kataloge unter `src/translations/`.
Der deutsche Katalog des `IPSViewStyleHelper` wird beim Vendor-Sync zusammen mit
dem Helper und seinen Abhängigkeiten verteilt:

```text
libs/helper/
├── HelperTranslationHelper.php
├── IPSViewControlThemeHelper.php
├── IPSViewFontCatalogHelper.php
├── IPSViewStyleConfigurationHelper.php
├── IPSViewStylePresetHelper.php
├── IPSViewStyleProfileHelper.php
├── IPSViewStyleHelper.php
└── translations/
    ├── IPSViewStyleConfigurationHelper.json
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
die kanonischen Werte normalisiert. Der zentrale Helper liefert die zugehörigen
TTF-Dateien samt Lizenzhinweisen aus und bettet in eigenständige HTML-Seiten nur
den jeweils ausgewählten Schriftschnitt als `@font-face`-Regel ein. Damit bleiben
die Seiten offline-fähig und zeigen nicht unbemerkt eine Browser-Ersatzschrift.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `catalog()` | Liefert den vollständigen Schriftkatalog. |
| `families()` | Liefert alle kanonischen Fontfamilien. |
| `options()` | Liefert Select-Optionen mit Beschriftung und kanonischem Wert. |
| `capabilities()` / `styles()` | Liefert die verfügbaren Schriftschnitte einer Familie. |
| `normalizeFamily()` / `normalizeStyle()` | Normalisiert bekannte Familien- und Schnitt-Aliase. |
| `isValidFamily()` / `isValidStyle()` | Prüft kanonische Familie beziehungsweise Schriftschnitt. |
| `cssFamily()` | Liefert die kanonische CSS-Familie mit generischem Fallback. |
| `fontFaceCSS()` | Bettet den ausgewählten Katalog-Schnitt als offline-fähige `@font-face`-Regel ein. |

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

## IPSViewControlThemeHelper

`src/IPSViewControlThemeHelper.php` ist der kanonische Übersetzer zwischen den
semantischen Style-Profile-Farben und den konkreten nativen IPSView-Farbfeldern.
Der Katalog umfasst derzeit **109 bekannte Farbfelder in 15 Familien**: Basis,
Assoziationen, Tabs, Schalter, Slider, Fortschrittsbalken, Kreisregler, Flow,
Gauge, Schatten/Raster, Dialoge, Charts, Zeitpläne, Events und Kalender.

Jedes bekannte native Feld ist genau einem semantischen Style-Feld und – soweit
eindeutig – einer Preset-Rolle zugeordnet. Dadurch bleibt das portable
`Style Profile V1` klein, während eine vollständige IPSView-Farbbelegung daraus
reproduzierbar erzeugt werden kann. Individuelle native Overrides ersetzen nur
das jeweilige Feld; unbekannte zukünftige IPSView-Farbfelder und frei benannte
Farben werden verlustfrei getrennt transportiert.

Native IPSView-Farbobjekte werden nicht auf RGB reduziert. Der Helper erhält
Alpha, Typ, Pattern, `IsEmpty`, Name sowie optionale zweite Farbkanäle
`A2/R2/G2/B2` für Verläufe. `ColorView` und `ColorPage` besitzen ausdrücklich
getrennte Semantik: **`ColorView` ist der View-Hintergrund, `ColorPage` der
Seitenhintergrund.** Fehlt `ColorView`, ändert das niemals die Bedeutung von
`ColorPage`.

Beim Anwenden auf bestehende Dokumente verändert `apply()` standardmäßig nur
bereits vorhandene native Felder. Mit `createMissing=true` können beim Erzeugen
einer neuen vollständigen View auch fehlende bekannte Felder angelegt werden.

### Methoden

| Methode | Aufgabe |
| --- | --- |
| `fields()` / `families()` | Liefert die 109 bekannten Felder beziehungsweise die 15 Familien in kanonischer Reihenfolge. |
| `catalog()` / `definition()` | Liefert Mapping-Metadaten für alle beziehungsweise ein einzelnes natives Feld. |
| `fieldsForFamily()` | Liefert alle nativen Felder einer Familie. |
| `fieldsForStyleField()` | Liefert alle nativen Felder, die von einem semantischen Style-Feld abgeleitet werden. |
| `fieldsForPresetRole()` | Liefert alle nativen Felder einer Preset-Rolle. |
| `styleFieldForDocument()` / `presetRoleForDocument()` | Löst die stabile semantische Bedeutung eines nativen Feldes auf. |
| `fromStyleColors()` | Erzeugt aus den semantischen Farben und optionalen Overrides ein vollständiges natives Theme. |
| `createColor()` / `normalizeColor()` | Erzeugt beziehungsweise normalisiert native IPSView-Farbobjekte einschließlich Alpha und optionalem zweiten Farbkanal. |
| `extract()` | Liest bekannte, unbekannte und frei benannte Farben aus einem IPSView-Dokument. |
| `normalizeTheme()` / `decode()` / `encode()` | Validiert und serialisiert den kanonischen nativen Theme-Transport. |
| `apply()` | Überträgt ein natives Theme kontrolliert auf ein IPSView-Dokument. |
| `colorToHex()` / `alphaPercent()` | Liefert RGB beziehungsweise Alpha eines normalisierten nativen Farbobjekts. |

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
kritischen Status. Sekundäre und schwach hervorgehobene Texte behalten die
Farbtemperatur des Primärtexts, solange sie zur Seitenfläche einen sinnvollen
Hell-/Dunkelkontrast besitzen. Liegen Text und Fläche im gleichen
Helligkeitsbereich, verwendet der Helper für diese beiden abgeleiteten Rollen
automatisch Schwarz oder Weiß; ausdrücklich konfigurierte Primär-, Aktiv- und
Labeltexte bleiben dabei unverändert. Typografie wird unter anderem über

```css
--ipsview-font-family
--ipsview-font-size
--ipsview-font-style
--ipsview-font-weight
--ipsview-font-scale
```

bereitgestellt. Die bisherigen Alias-Tokens wie `--ipsview-surface`,
`--ipsview-success` und `--ipsview-danger` bleiben aus Kompatibilitätsgründen
erhalten. Zusätzlich bindet der IPSView-Stil `--symc-font-family` an die
ausgewählte IPSView-Schrift. Damit übernehmen auch Elemente, die das gemeinsame
`VisualizationThemeHelper`-Grundthema verwenden, dieselbe Schriftfamilie. Für
Schriften aus dem festen IPSView-Katalog hängt der CSS-Block außerdem die
passende, selbst enthaltene `@font-face`-Regel an.

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
| `IPSViewResolvedStyle()` | Liefert die vollständig aufgelösten universellen Stilwerte einschließlich der eigenständigen semantischen Schattenfarbe. |
| `IPSViewStyleCSSVariables()` | Rendert die aufgelösten Werte als gemeinsame `--ipsview-*`-CSS-Variablen. |

## IPSViewStyleConfigurationHelper

`src/IPSViewStyleConfigurationHelper.php` erweitert `IPSViewStyleHelper` um die
vollständige native IPSView-Farbkonfiguration. Der Helper übernimmt bewusst
dieselben Basis-Properties und dieselben zentralen Methoden wie
`IPSViewStyleHelper`; bestehende Consumer-Konfigurationen müssen deshalb beim
Umstieg **nicht umbenannt oder neu initialisiert** werden.

Zusätzlich registriert der Helper 15 JSON-Properties – eine pro nativer
Farbfamilie – und ergänzt die gemeinsame Konfigurationsmaske um den eingeklappten
Bereich **Erweiterte IPSView-Farben**. Dort werden alle 109 nativen Felder mit
ihrem semantischen Ursprung angezeigt. Im benutzerdefinierten Stil gilt:

- Ohne Override erbt jedes native Feld immer die aktuell wirksame semantische
  Grundfarbe.
- Ein Override betrifft ausschließlich das ausgewählte native IPSView-Feld.
- Wird ein Override wieder deaktiviert, ist die Abweichung nicht mehr wirksam und
  das native Feld folgt wieder der abgeleiteten Grundfarbe.
- Nicht benutzerdefinierte Stilquellen zeigen die aufgelösten nativen Farben
  schreibgeschützt an.

Für `.ipsView`-Medienquellen korrigiert der ConfigurationHelper zusätzlich die
verifizierte View-/Page-Semantik: `ColorView` bleibt unabhängig von `ColorPage`.
Wenn `ColorView` im Dokument fehlt, wird für den View-Hintergrund der IPSView-
Standard **`#404040`** verwendet; `ColorPage` bleibt ausschließlich der
Seitenhintergrund.

Neue Module, die die gemeinsame IPSView-Stilmaske vollständig anbieten sollen,
verwenden daher bevorzugt `IPSViewStyleConfigurationHelper`. Consumer, die nur
die semantischen CSS-Rollen benötigen und bewusst keine nativen Einzel-Overrides
anbieten, können weiterhin direkt `IPSViewStyleHelper` einsetzen.

### Verwendung

```php
require_once __DIR__ . '/../libs/helper/ConfigurationFormHelper.php';
require_once __DIR__ . '/../libs/helper/IPSViewStyleConfigurationHelper.php';

use Burki24\SymconModuleHelper\ConfigurationFormHelper;
use Burki24\SymconModuleHelper\IPSViewStyleConfigurationHelper;

class ExampleModule extends IPSModuleStrict
{
    use ConfigurationFormHelper;
    use IPSViewStyleConfigurationHelper;

    public function Create(): void
    {
        parent::Create();
        $this->RegisterIPSViewStyleProperties();
    }

    public function GetConfigurationForm(): string
    {
        $form = $this->LoadConfigurationForm();
        $this->InsertIPSViewStyleFormItems($form['elements']);

        return $this->EncodeConfigurationForm($form);
    }
}
```

### Zusätzliche Methoden

Die geerbten Methoden von `IPSViewStyleHelper` bleiben unverändert verfügbar.
Der ConfigurationHelper ergänzt folgende native APIs:

| Methode | Aufgabe |
| --- | --- |
| `IPSViewStyleNativeColorOverrides()` | Liefert ausschließlich aktivierte native Overrides des benutzerdefinierten Stils. |
| `IPSViewStyleNativeTheme()` | Erzeugt das vollständige bekannte native Theme aus dem aktuell wirksamen Stil. |
| `ApplyIPSViewStyleNativeTheme()` | Überträgt das aufgelöste native Theme kontrolliert auf ein IPSView-Dokument. |
| `IPSViewStyleNativeOverrideProperties()` | Liefert die Zuordnung der 15 Familien zu ihren gespeicherten JSON-Properties. |

## IPSViewColorPaletteHelper

`src/IPSViewColorPaletteHelper.php` bleibt vorerst als kompatibler Vorgänger für bereits migrierte Consumer erhalten. Neue Integrationen mit vollständiger IPSView-Konfigurationsmaske sollen `IPSViewStyleConfigurationHelper` verwenden; rein semantische Consumer können weiterhin direkt `IPSViewStyleHelper` einsetzen. Der ältere Helper verwaltet nur neun Farben und besitzt weder Style Profile, zentrale Presets noch die gemeinsamen Typografie-, Rahmen-, Schatten-, Opacity- und Verlaufswerte.

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
    ├── IPSViewControlThemeHelper.php
    ├── IPSViewFontCatalogHelper.php
    ├── IPSViewHTMLPageHelper.php
    ├── IPSViewStyleConfigurationHelper.php
    ├── IPSViewStyleHelper.php
    ├── IPSViewStylePresetHelper.php
    ├── IPSViewStyleProfileHelper.php
    ├── translations/
    │   ├── IPSViewHTMLPageHelper.json
    │   ├── IPSViewStyleConfigurationHelper.json
    │   └── IPSViewStyleHelper.json
    ├── ParentConnectionHelper.php
    ├── PersistentJsonCacheHelper.php
    ├── SVGPreviewHelper.php
    ├── SymconOAuthHelper.php
    ├── VariableHelper.php
    ├── VariablePresentationHelper.php
    └── VisualizationAssetHelper.php
```

Damit bleibt die Symcon-Library vollständig eigenständig. Git-Submodules oder Downloads zur Laufzeit sind nicht erforderlich.

## Dokumentationsregel

Ändert ein Arbeitspaket sichtbare Konfiguration, öffentliche oder protected
Helper-API oder Anwenderfunktionen, gehört die zugehörige README zum selben
Arbeitspaket. Öffentliche und protected Helper-Methoden müssen außerdem einen
direkten PHPDoc-Block besitzen; Parameter und Rückgabewert werden dort vollständig
dokumentiert. Vor der Auslieferung werden deshalb neben Tests und StylePHP auch
die betroffenen README-Abschnitte und PHPDocs gegen den tatsächlich ausgelieferten
Stand geprüft.

## Tests

```bash
php tests/run.php
```

Der zentrale Runner führt alle Helper-Testgruppen aus; gemeinsame Testfunktionen liegen in `tests/bootstrap.php`, während jeder Helper eine eigene Testdatei besitzt. `tests/ipsview-documentation.php` schützt zusätzlich die README-Verträge und die vollständigen PHPDoc-Signaturen der nativen IPSView-Helper.

GitHub Actions prüft zusätzlich die PHP-Syntax und den vollständigen Testlauf mit PHP 8.5 sowie den Symcon-PHP-Stil über `symcon/action-style@v3`.

## Lizenz

MIT

## Automatischer Vendor-Sync

Helper-Abhängigkeiten werden im zentralen Manifest ausschließlich als `dependencies` deklariert. Das Feld `requires` wird als Fehler abgewiesen. Der CI-Bundletest prüft für `OpenFreeMapHelper` ausdrücklich beide benötigten Helper, alle Kartenressourcen und Übersetzungen sowie die unveränderten Quelldateien im erzeugten Paket.

`manifest.json` ist die maschinenlesbare Quelle für Version und SHA-256 aller Helper. Die Consumer-Repositories deklarieren ihre verwendeten Helper über `.helper-sync.json`.

Nach der Metadatenfreigabe auf `main` erzeugt `.github/workflows/helper-sync.yml` pro Consumer und konfiguriertem Zielbranch höchstens einen Update-Branch und Pull Request für die ausgewählten abonnierten Helper. Mehrere Helper werden auch bei aktiviertem Auto-Merge in einem gemeinsamen Bundle zusammengefasst, damit parallel wartende Helper-PRs nicht dasselbe Manifest überschreiben. Der Sync aktualisiert das abonnierte Helper-Bundle einschließlich deklarierter Abhängigkeiten und Assets sowie `libs/helper/manifest.json` und `libs/helper/README.md`. Übersetzungskataloge werden dadurch gemeinsam mit dem zugehörigen Helper verteilt.

Für den repositoryübergreifenden Zugriff wird eine GitHub App verwendet. Im Repository `Symcon_ModuleHelper` werden dafür benötigt:

- Repository-Variable `HELPER_SYNC_APP_CLIENT_ID`
- Repository-Secret `HELPER_SYNC_APP_PRIVATE_KEY`

Die GitHub App wird auf den Consumer-Repositories installiert und benötigt dort nur `Contents: Read and write` sowie `Pull requests: Read and write`.

Die zentrale Consumer-Konfiguration aktiviert standardmäßig `SQUASH`-Auto-Merge. Vor der Aktivierung prüft der Sync, dass der Pull Request von einem GitHub-App-Bot stammt, einen `helper-sync/`-Branch gegen den konfigurierten Zielbranch verwendet und ausschließlich die für das konkrete Helper-Bundle erzeugten Dateien verändert. Enthält ein PR zusätzliche Modul-, Test-, Workflow- oder Dokumentationsdateien außerhalb des generierten Bundles, wird Auto-Merge abgelehnt und der Sync-Lauf schlägt sichtbar fehl.

Damit GitHub den PR erst nach der CI zusammenführt, müssen in jedem Consumer unter **Settings → General → Pull Requests** `Allow auto-merge` und `Allow squash merging` aktiviert sein. Für den jeweiligen `dev`-Branch müssen außerdem die gewünschten Tests als erforderliche Statusprüfungen in einer Branch Protection Rule oder einem Ruleset hinterlegt sein. Meldet GitHub den vollständig validierten PR als `clean` oder fehlen Branch-Protection-Regeln, verwendet der bestehende Fallback einen direkten Merge mit fest erwarteter Head-SHA. Ein instabiler Status löst keinen direkten Fallback-Merge aus.

OpenCalendar verwendet diese automatische Merge-Policy sowohl für `dev` als auch
für `dev_9.1`; die frühere manuelle Migrationsausnahme für `dev_9.1` entfällt.
Die Helper-Sync-Branches bleiben je Zielbranch getrennt. Erforderliche
Statusprüfungen müssen für beide Zielbranches separat konfiguriert sein.
Änderungen werden weiterhin im Helper-Branch `dev` vorbereitet und erst nach
der Übernahme nach `main` über den bestehenden Sync-Workflow verteilt.

Ein manueller Lauf ist über `workflow_dispatch` möglich; dabei kann ein einzelner Helpername oder `all` angegeben werden.

Verteilungsläufe werden serialisiert. Bei instabilem GitHub-PR-Status wartet der Sync höchstens 72 Versuche mit fünf Sekunden Abstand (rund sechs Minuten zuzüglich API-Laufzeit). Ein Consumer-Fehler stoppt nur dessen Verarbeitung; andere Consumer und Zielbranches werden weiterhin bearbeitet. Die Actions-Zusammenfassung listet das Ergebnis je Zielbranch auf. Offene Fehler führen nach Abschluss aller Consumer weiterhin zu einem fehlgeschlagenen Lauf. „Processed“ bestätigt die Bearbeitung, nicht den erfolgreichen Merge. Nach Behebung der Ursache kann der bestehende Workflow erneut gestartet werden; bereits aktuelle Bundles werden übersprungen.


## OpenFreeMapHelper

`OpenFreeMapHelper` liefert unabhängige Kartenprofile, einen übersetzten OpenFreeMap-Menübereich und den persistenten Vektorkarten-Controller. Der Helper enthält keine Geräte- oder iCloud-Logik. Er verwendet `HelperTranslationHelper` und `VisualizationAssetHelper`.

| Methode | Vertrag |
| --- | --- |
| `RegisterOpenFreeMapProperties($prefix)` | Registriert ein Profil, etwa `Tile` oder `IPSView`. |
| `OpenFreeMapFormSection($prefix, $enabled)` | Eigener ExpansionPanel-Menübereich mit Theme, Stilen, Stil-URLs, Details, Sprache und Farbpaletten. |
| `ReadOpenFreeMapDesign($prefix)` | Normalisierte Optionen für den Browser. |
| `CopyOpenFreeMapProperties($source, $target)` | Kopiert gespeicherte Werte; ApplyChanges bleibt Aufgabe des Consumers. |
| `OpenFreeMapPropertyNames()` | Namen für dynamische Formularfreigaben. |
| `OpenFreeMapAssets($rendererPath, $serverURL)` | CSS und kleiner Bootstrap einschließlich Ursprungsermittlung; bindet den Renderer asynchron über den Consumer-Endpunkt ein. |
| `OpenFreeMapRendererAsset()` | gzip-Daten des unveränderten Renderers für einen öffentlichen GET-Endpunkt mit `Content-Encoding: gzip` und JavaScript-MIME-Typ. |

`maps/openfreemap.js` hält eine Leaflet-Vektorebene und eine GL-Karte über Daten-, Stil-, Farb- und Theme-Wechsel hinweg. Nur der Basisstil wird aktualisiert; Leaflet-Kamera, Marker und Auswahl bleiben beim Consumer. Höchstens sechs Basisstile werden im Seitenspeicher gehalten; ausstehende veraltete Stilabrufe werden abgebrochen. Stil-JSON-Kameravorgaben werden vor der Anwendung entfernt. Eigene Kartenfarben, Ebenensichtbarkeit und Namenübersetzungen werden auf einer Kopie angewendet, sodass deaktivierte Anpassungen die Originaldarstellung wiederherstellen.

`SymconOpenFreeMap.createViewState(key, window)` liefert `read()` und `write(value)` für die Wiederherstellung neu aufgebauter Karten-iframes. Der Consumer liefert einen stabilen instanz- und kontobezogenen Schlüssel und ein Objekt `{center: [latitude, longitude], zoom, selected, popup}`. Optional werden `filter` (String mit höchstens 256 Zeichen) und `scroll` (endliche Zahl von 0 bis 10000000) gespeichert. Filtersemantik bleibt beim Consumer; iCloud-Kontologik ist nicht Bestandteil dieses Helpers. Alte Speicherstände ohne diese Felder bleiben kompatibel; unbekannte Felder werden nicht übernommen. Der Helper trennt zusätzlich nach nativer Visualisierung und Benutzer; URL-Tokens und Passwörter werden nicht übernommen. Es werden ausschließlich validierte Kartenwerte in `sessionStorage` des aktuellen Browser-Tabs gespeichert, mit vier Stunden Ablaufzeit und maximal 4096 Zeichen beim Lesen. Ungültige, abgelaufene oder nicht verfügbare Speicherstände verhindern keine Kartenanzeige. Der Consumer stellt die Kamera vor dem ersten automatischen Einpassen wieder her, gleicht die Auswahl mit seinen aktuellen Daten ab und speichert abgeschlossene Ansichts-/Auswahländerungen. Geschlossene Tabs verlieren ihren Sitzungsspeicher; ein serverseitiger oder geräteübergreifender Zustand wird nicht angelegt.

Automatik verwendet den berechneten Designer-Hintergrund und beobachtet Theme-Änderungen. Explizit sind Hell und Dunkel möglich. Die fünf mitgelieferten Stil-Vorgaben und eigene HTTPS-Stil-URLs können je Theme gewählt werden. Farbgruppen: Land, Wasser, Grünflächen, Straßen, Gebäude und Texte; Ebenengruppen: Beschriftungen, POI, Gebäude und Grenzen. Eigene Stile müssen passende Vektorebenen enthalten. Attribution wird nicht ausgeblendet.

Die Renderer-Dateien werden kanonisch mit dem Helper verteilt: MapLibre GL JS 5.24.0 (BSD-3-Clause), MapLibre-GL-Leaflet 0.1.4 (ISC), unveränderte Quellen und Lizenzen sowie `maps/vendor/NOTICE.json` mit SHA-256. Wegen Symcons 1-MB-Ausgabebuffer darf der Renderer nicht vollständig in HTML eingebettet werden. Der Consumer stellt ihn über einen reinen Bibliotheks-Asset-Endpunkt bereit; Geräte- und Kontodaten gehören nicht in diesen Endpunkt. Browsereinsatz erfordert WebGL. Die OpenFreeMap-Stile und Kartenregionen werden vom Browser beim Anbieter abgerufen; Kontozugangsdaten und Transporttokens werden nicht weitergereicht.

## Gemeinsame Mausrad- und Frame-Interaktion

`VisualizationAssetHelper::VisualizationInteractionScript()` liefert die kleine, minifizierte Browserbibliothek `visualization/interaction.min.js`; die lesbare Quelle `interaction.js` wird ebenfalls manifestiert verteilt. `IPSViewHTMLPageHelper::RenderVisualizationHTMLPage()` fügt sie verbindlich vor dem Modulskript in native und IPSView-Ausgaben ein, auch bei einem eigenen Modulskript-Replacement. Eigenständige äußere HTML-Loader müssen dieselbe Methode einbinden. Mehrfache Einbindung erzeugt keine zusätzlichen globalen Listener.

Bei großen HTML-Ausgaben kann der Consumer mit `VisualizationInteractionScriptURL(): string` einen festen gleichursprünglichen `/hook/...`-Asset-Pfad bereitstellen. Enthält sein Template `{{INTERACTION_SCRIPT}}` im Kopf vor jedem Modulskript, liefert der Seiten-Helper dort ein parserblockierendes Script-Element statt des Inline-Payloads. Der GET-Endpunkt muss genau `VisualizationInteractionScript()` als JavaScript ausliefern. Ohne diesen optionalen Vertrag bleibt die automatische Inline-Einbindung erhalten. Fremde URLs und Pfadtraversierung werden abgewiesen. ECharts verwendet diesen Weg, damit seine großen Ausgaben unter Symcons 1-MB-Grenze bleiben.

`window.SymconVisualizationInteraction` stellt `point(event)`, `delta(event, target)`, `scroll(event, target)`, `registerWheelRegion(element, handler)` und `dispose()` bereit. `createRouter(environment)` ist der isolierbare Node-/Browser-Testeinstieg. Eine Region erhält normalisierte Pixel-Deltas und korrigierte Viewport-Koordinaten; sie signalisiert eine übernommene Aktion mit `true` oder `preventDefault()`. Fachlicher Karten-/Diagrammzoom bleibt beim Consumer. Ctrl-/Meta-Mausrad wird nicht übernommen. Normale überlaufende Scrollcontainer werden automatisch bedient und an ihren Grenzen vom äußeren Scrollen getrennt. Kalender können ihre modalspezifische Zielauswahl behalten und nur `scroll()` aufrufen.

Bei umadressierten IPSView-Ereignissen mit `(0, 0)` wird die letzte tatsächliche Mausposition verwendet; Blur, Pointer-Verlassen und Touch/Pointer-Wechsel verwerfen sie. Pixel-, Zeilen- und Seiten-Deltas sowie Shift-Scroll werden normalisiert. Bestehende grafische Komponenten erhalten bei umadressierten Ereignissen eine korrigierte Wheel-Weitergabe, ohne Rekursion.

Direkte Eltern-/Kind-Frames können ausschließlich UI-Eingaben austauschen: der Kind-Frame meldet sich an, Zeigerposition und relative Frame-Koordinaten berücksichtigen Skalierung, und der Eltern-Frame leitet Mausrad-Eingaben weiter. Empfänger akzeptieren nur die tatsächliche direkte Eltern-Window-Referenz; Eltern prüfen direkte iframe-Window-Referenzen. Ungültige Koordinaten, fremde Sender und übergroße Deltas werden verworfen. Nachrichten enthalten keine Zugangsdaten, Geräteinventare oder Modulaktionen. Opaque `data:`-Eltern benötigen `postMessage` mit `*`; die Zuordnung erfolgt deshalb über die exakte Window-Referenz und nicht über frei behauptete Herkunftsfelder.

Reproduzierbarer Build: `npx --yes esbuild@0.28.2 src/visualization/interaction.js --minify --target=es2018 --outfile=src/visualization/interaction.min.js`. Die CI vergleicht den Build bytegenau und prüft lesbare und ausgelieferte Quelle mit `node tests/visualization-interaction.js` und `node tests/visualization-interaction.js --minified`. Native App, Webclient und simulierte Ereignisweitergabe bleiben getrennte Praxisnachweise.
