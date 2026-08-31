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
- `LMNB`
- `OpenCalendar`
- `OpenHomeAlarm`
- `WolfWSR`
- `OpenHotWaterCirculationControl`
- `OpenShutterButtonControl`
- `IPSViewAssistant`

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

Jedes Consumer-Repository beschreibt in `.helper-sync.json`, welche Helper es verwendet und wohin diese kopiert werden. Der maßgebliche Zielbranch wird zentral pro Consumer in `.github/helper-consumers.json` über `branch` festgelegt. Dasselbe Repository kann dort mehrfach mit unterschiedlichen Zielbranches eingetragen werden, wenn mehrere Entwicklungsstände parallel versorgt werden sollen. Eine zusätzliche Angabe `base_branch` in `.helper-sync.json` ist optional; wenn sie vorhanden ist, muss sie exakt mit dem zentral konfigurierten Zielbranch übereinstimmen. Bei einem Widerspruch bricht der Sync für diesen Consumer ab.

Bei einer Änderung unter `src/` oder an `manifest.json`:

1. prüft der zentrale Workflow das Manifest,
2. liest die Subscription der Consumer aus dem jeweils in `.github/helper-consumers.json` konfigurierten Zielbranch,
3. überspringt nicht abonnierte oder bereits aktuelle Helper,
4. erzeugt für veraltete Helper einen Branch `helper-sync/...`; für den normalen `dev`-Zielbranch bleibt das bisherige Namensschema unverändert, zusätzliche Zielbranches erhalten einen eigenen Branch-Pfad wie `helper-sync/dev-9-1/...`, damit parallele Syncs desselben Repositories nicht kollidieren,
5. schreibt das vollständige Helper-Bundle einschließlich Abhängigkeiten und Assets sowie `libs/helper/manifest.json` und `libs/helper/README.md` in einem Commit,
6. dokumentiert transitive Abhängigkeiten innerhalb des abonnierten Helper-Eintrags, sodass `.helper-sync.json` ausschließlich die bewusst abonnierten Helper enthält,
7. eröffnet einen Pull Request gegen den zentral konfigurierten Zielbranch,
8. prüft bei vorhandenem `base_branch` in `.helper-sync.json`, dass dieser mit dem zentralen Zielbranch übereinstimmt,
9. prüft Bot-Autor, `helper-sync/`-Branch, Zielbranch und den tatsächlichen Dateiumfang,
10. aktiviert bei einem reinen Helper-PR automatisch Squash-Auto-Merge.

GitHub führt den PR bei konfigurierten Branch-Protection-Regeln oder Rulesets erst zusammen, wenn die vorgeschriebenen Bedingungen erfüllt sind. Kann Auto-Merge nicht aktiviert werden, weil GitHub den PR bereits als `clean` meldet oder keine Branch-Protection-Regel vorhanden ist, darf der Sync nur den zuvor vollständig validierten Helper-PR direkt zusammenführen. Dabei werden Bot-Autor, Ziel- und Quellbranch, erwarteter Head-SHA, Merge-Methode und tatsächlicher Dateiumfang erneut geprüft. Sobald eine nicht zum erzeugten Helper-Bundle gehörende Datei im PR auftaucht, wird Auto-Merge beziehungsweise der direkte Fallback-Merge verweigert.

### Erforderliche Consumer-Einstellungen

In jedem Consumer-Repository:

1. **Settings → General → Pull Requests → Allow auto-merge** aktivieren.
2. **Allow squash merging** aktiviert lassen.
3. Für jeden in `.github/helper-consumers.json` konfigurierten Zielbranch eine Branch Protection Rule oder ein Ruleset mit den gewünschten **Required status checks** einrichten.
4. Optional **Automatically delete head branches** aktivieren, damit der `helper-sync/...`-Branch nach dem Merge entfernt wird.

Die globale Vorgabe steht in `.github/helper-consumers.json`:

```json
"auto_merge": {
  "enabled": true,
  "merge_method": "SQUASH"
}
```

Ein einzelner Consumer kann Auto-Merge mit `"auto_merge": false` deaktivieren oder über `"merge_method"` eine andere im Repository erlaubte Methode wählen. Der Eintrag `branch` im selben Consumer-Objekt ist die zentrale Branch-Vorgabe für Subscription, Helper-Branch-Basis und Pull-Request-Ziel. Für parallele Zielbranches desselben Repositories empfiehlt sich während einer Migration, Auto-Merge auf dem zusätzlichen Entwicklungsbranch zunächst zu deaktivieren und die erzeugten Helper-PRs bewusst zu prüfen.

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


## Automatische Metadaten-Versionierung

Nach jedem fachlichen Push auf `main` aktualisiert der Workflow
`update-helper-metadata.yml` zunächst Repository-Version, Build, Datum,
betroffene Helper-Versionen und SHA-256-Prüfsummen. Erst der anschließend
erzeugte Commit `CHORE: Update helper metadata ...` wird mit dem
repository-eigenen `GITHUB_TOKEN` geschrieben. Anschließend startet der
Workflow den Consumer-Sync ausdrücklich per `workflow_dispatch`. Die
Helper-Sync-App wird dadurch nur noch für die Consumer-Repositories benötigt.
Automatische Helper-PRs erhalten immer einen konsistenten, bereits
versionierten Manifeststand.
