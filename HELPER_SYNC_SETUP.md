# Helper Vendor Sync – einmalige Einrichtung

Der automatische Vendor-Sync wird ausschließlich aus `Burki24/Symcon_ModuleHelper` gesteuert. Die Consumer-Repositories enthalten nur ihre Subscription (`.helper-sync.json`), ein lokales Helper-Manifest und Integritätsprüfungen.

## 1. GitHub App anlegen

Unter **GitHub → Settings → Developer settings → GitHub Apps → New GitHub App** eine App anlegen, zum Beispiel `Burki24 Helper Sync`.

Empfohlene Einstellungen:

- Webhook: deaktiviert
- Repository permissions:
  - **Contents: Read and write**
  - **Pull requests: Read and write**
- Installation: nur für den eigenen Account

Danach einen Private Key erzeugen und die App für genau diese Repositories installieren:

- `IPS_LMNB`
- `OpenCalendar`
- `IPS_Wolf_WSR1`

## 2. Symcon_ModuleHelper konfigurieren

Unter **Symcon_ModuleHelper → Settings → Secrets and variables → Actions** anlegen:

### Variable

`HELPER_SYNC_APP_CLIENT_ID`

Wert: die **Client ID** der GitHub App.

### Secret

`HELPER_SYNC_APP_PRIVATE_KEY`

Wert: der vollständige Inhalt der erzeugten `.pem`-Datei einschließlich BEGIN/END-Zeilen.

## 3. Funktionsweise

`manifest.json` enthält für jeden Helper die Upstream-Version und den SHA-256-Hash.

Jedes Consumer-Repository beschreibt in `.helper-sync.json`, welche Helper es verwendet und wohin diese kopiert werden.

Bei einer Änderung unter `src/` oder an `manifest.json`:

1. prüft der zentrale Workflow das Manifest,
2. liest die Subscription der drei Consumer aus deren `dev`-Branch,
3. überspringt nicht abonnierte oder bereits aktuelle Helper,
4. erzeugt für veraltete Helper einen Branch `helper-sync/...`,
5. schreibt Helper-Datei, `libs/helper/manifest.json` und `libs/helper/README.md` in einem Commit,
6. eröffnet einen Pull Request gegen `dev`.

Die Consumer-CI prüft den PR. Der Merge bleibt bewusst manuell.

## 4. Manueller Lauf

Unter **Symcon_ModuleHelper → Actions → Sync Helpers to Consumers → Run workflow** kann der Sync jederzeit manuell gestartet werden.

Als Helper kann entweder ein konkreter Name, zum Beispiel `VariablePresentationHelper`, oder `all` angegeben werden.

Direkt nach der Einrichtung sollte ein Lauf mit `all` keine Änderungen erzeugen, sofern alle Consumer bereits auf dem aktuellen Stand sind.
