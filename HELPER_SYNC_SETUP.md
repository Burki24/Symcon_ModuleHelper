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

Danach einen Private Key erzeugen und die App für diese Repositories installieren:

- `Symcon_ModuleHelper` (für automatisch erzeugte Übersetzungs-PRs)
- `OpenLMNB`
- `OpenCalendar`
- `OpenHomeAlarm`
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

`manifest.json` enthält für jeden Helper die Upstream-Version und den SHA-256-Hash. Ab Schema 2 können zusätzlich Helper-Abhängigkeiten und zugehörige Assets wie Übersetzungskataloge deklariert werden.

Jedes Consumer-Repository beschreibt in `.helper-sync.json`, welche Helper es verwendet und wohin diese kopiert werden.

Bei einer Änderung unter `src/` oder an `manifest.json`:

1. prüft der zentrale Workflow das Manifest,
2. liest die Subscription der Consumer aus deren `dev`-Branch,
3. überspringt nicht abonnierte oder bereits aktuelle Helper,
4. erzeugt für veraltete Helper einen Branch `helper-sync/...`,
5. schreibt das vollständige Helper-Bundle einschließlich Abhängigkeiten und Assets sowie `libs/helper/manifest.json` und `libs/helper/README.md` in einem Commit,
6. dokumentiert transitive Abhängigkeiten innerhalb des abonnierten Helper-Eintrags, sodass `.helper-sync.json` ausschließlich die bewusst abonnierten Helper enthält,
7. eröffnet einen Pull Request gegen `dev`.

Die Consumer-CI prüft den PR. Der Merge bleibt bewusst manuell.

## 4. Manueller Lauf

Unter **Symcon_ModuleHelper → Actions → Sync Helpers to Consumers → Run workflow** kann der Sync jederzeit manuell gestartet werden.

Als Helper kann entweder ein konkreter Name, zum Beispiel `VariablePresentationHelper`, oder `all` angegeben werden.

Direkt nach der Einrichtung sollte ein Lauf mit `all` keine Änderungen erzeugen, sofern alle Consumer bereits auf dem aktuellen Stand sind.


## 5. Automatische Helper-Übersetzungen

Der Workflow **Update Helper Translations** verwendet GitHub Models mit dem repository-eigenen `GITHUB_TOKEN`. Dafür ist keine zusätzliche Übersetzungs-API oder ein weiteres Secret nötig; der Workflow benötigt lediglich die Berechtigung `models: read`. Für das Schreiben des Pull Requests wird bevorzugt die vorhandene Helper-Sync-App verwendet. Ist sie nicht auf `Symcon_ModuleHelper` installiert, fällt der Workflow auf das `GITHUB_TOKEN` zurück.

Neue sichtbare Helper-Texte werden in einer `_TRANSLATION_SOURCES`-Konstanten als englische Quelle hinterlegt. Nach einem Push auf `main`:

1. werden neue oder geänderte Quelltexte erkannt,
2. fehlende deutsche Übersetzungen über GitHub Models erzeugt,
3. nur die Katalogdateien in einem Bot-Branch aktualisiert,
4. ein Pull Request zur sprachlichen Kontrolle erstellt,
5. nach dem Merge der normale Helper-Sync für die betroffenen Consumer ausgelöst.

Vor der Verteilung validiert die CI, dass alle Pflichtsprachen und Platzhalter vollständig und synchron sind.
