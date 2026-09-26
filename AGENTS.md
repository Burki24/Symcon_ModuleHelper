# Projektregeln für Symcon_ModuleHelper

Lies zuerst `../SymconDevelopment/AGENTS.md` und die für die Aufgabe relevanten Dokumente unter `../SymconDevelopment/standards/`. Diese zentrale Basis gilt mit den nachfolgenden projektspezifischen Ergänzungen und Ausnahmen.

## Rolle und Struktur

- Dieses Repository ist die zentrale, versionierte Quelle der Burki24-Symcon-Helper. Es ist selbst keine Symcon-Library.
- Helper-Quellen liegen hier bewusst unter `src/`. Erst der Vendor-Sync schreibt abonnierte Bundles in `libs/helper` der Consumer-Repositories. Verschiebe die Quellen dieses Repositories nicht nach `libs/helper`.
- `README.md` dokumentiert die Helper und ihre öffentlichen Verträge zentral. Die Modulstruktur mit `library.json`, `module.json` und einzelnen Modul-READMEs ist hier nicht anwendbar.
- Zielplattform ist PHP 8.5 unter IP-Symcon 9.0. Abweichende Anforderungen einzelner Consumer werden zusätzlich in deren Repository geprüft.
- Tests liegen unter `tests`; `.tests` und `.shared` werden nicht eingeführt.

## Vor jeder Änderung

1. Lies `README.md`, `manifest.json`, den betroffenen Helper unter `src/` und seine Tests.
2. Lies bei IPSView-Arbeiten zusätzlich `PROJECT_STATUS.md`; bei Verteilungs-, Manifest- oder Automationsarbeiten zusätzlich `HELPER_SYNC_SETUP.md`, `.github/helper-consumers.json` und die betroffenen Workflows und Skripte.
3. Prüfe Aufrufer, Consumer, Helper-Abhängigkeiten, Assets und Übersetzungskataloge, bevor du einen öffentlichen oder protected Vertrag änderst.
4. Bewahre fremde Änderungen und den bestehenden Branch-Stand. Helper-Sync, Releases und repositoryübergreifende Aktionen werden nur auf ausdrücklichen Auftrag ausgelöst.

## Verträge und Dokumentation

- Öffentliche und protected Helper-APIs bleiben abwärtskompatibel, sofern eine brechende Änderung nicht ausdrücklich beschlossen und versioniert wurde.
- Sichtbare Konfiguration, Helper-APIs und Anwenderverhalten werden im selben Arbeitspaket in `README.md` und den direkten PHPDoc-Blöcken dokumentiert.
- Neue oder geänderte sichtbare Texte folgen dem vorhandenen Übersetzungsquellen- und Katalogverfahren.
- `repository_version`, Builds, Datumswerte, Helper-Versionen und SHA-256-Werte werden durch die vorhandene Metadatenautomatisierung gepflegt. Manuelle Metadatenänderungen erfolgen nur, wenn die konkrete Aufgabe oder das bestehende Verfahren sie erfordert.
- Bei wesentlichen IPSView-Teilabschnitten wird `PROJECT_STATUS.md` aktualisiert; allgemeine Helper-Änderungen erzeugen dort keine fachfremden Fortschrittsangaben.

## Qualität und CI

- Maßgeblich sind die vorhandenen Workflows unter `.github/workflows/`, die Manifest- und Übersetzungsvalidatoren, die Python-Tests, PHP-Syntaxprüfung, `php tests/run.php` und `symcon/action-style@v3`.
- Die projektspezifische Sync-, Metadaten- und Test-CI ersetzt hier `Symcon_ModuleCI` und `validate_structure`; führe diese Werkzeuge nicht allein zur formalen Angleichung ein.
- `.style/.php-cs-fixer.php` und der Style-Workflow bleiben die Stilquelle. Führe keine neue Composer-Infrastruktur nur für einen lokalen Style-Lauf ein.
- Prüfe Änderungen an Sync oder Auto-Merge zusätzlich gegen die vorhandenen Sicherheits- und Bundle-Tests. Tests dürfen keine echten Consumer-Repositories verändern.
- Nenne zum Abschluss die tatsächlich verwendete PHP-Version. Ein lokaler Lauf unter einer älteren Version ersetzt die CI-Prüfung mit PHP 8.5 nicht.

## Branch- und Release-Modell

- `dev` ist der dauerhafte Entwicklungs- und Integrationsbranch; `main` enthält ausschließlich freigegebene und verteilbare Stände.
- Fachliche Änderungen werden nicht direkt auf `main` vorgenommen. Freigaben erfolgen nach vollständiger Prüfung von `dev` nach `main`.
- Automatische Versionierung und Consumer-Verteilung laufen ausschließlich auf `main`.
- Nach einer erfolgreichen Freigabe darf die vorhandene Automation `dev` nur per sicherem Fast-Forward an `main` angleichen. Force-Pushes und das Löschen von `dev` sind ausgeschlossen.
