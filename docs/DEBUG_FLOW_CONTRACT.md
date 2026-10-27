# Interner Vertrag: DebugFlowHelper-Erweiterung

Stand: 27.10.2026. Ergänzung zum vorhandenen DebugFlowHelper; keine
Anwenderanleitung und keine neue Debug-Dokumentation in Library-READMEs.

`DebugFlowCall($function, $line, $callback, $void = false, $safeContext = [],
$booleanResults = null)` führt einen synchronen Modulaufruf genau einmal aus.
Start/Ende bleiben auch bei früher Rückgabe und Ausnahme ausgeglichen. Resultat
und ursprüngliche Ausnahme werden unverändert weitergegeben. Optionale feste
Beschreibungen für false/true erklären das Ergebnis gemäß dem Consumer-Vertrag.
Argumente, Ergebnisinhalte und beliebige Exception-Texte werden nicht übergeben.

Verschachtelte Aufrufe derselben Instanz teilen einen Vorgang. Neue äußere
Aufrufe beginnen mit neuer Kennung und Aufrufnummer eins. Nach dem letzten Ende
wird der Vorgang zurückgesetzt; Einzelereignisse außerhalb eines aktiven Aufrufs
erhalten eine eigene Kennung und Aufrufnummer null. Es gibt keine automatische
Übertragung von Korrelation über Instanz-/Transportgrenzen.

`DebugFlowWriteValue($ident, $value, $function, $line, $includeValue = true,
$safeContext = [])` erlaubt zusätzliche bereits freigegebene Metadaten wie
Bedeutung und Einheit. Der Kontext darf Ziel-Identifier und tatsächlichen Wert
nicht überschreiben. Der native Aufruf erfolgt einmal; sein Resultat bleibt
erhalten. Der Consumer muss private Werte vor Übergabe ausschließen.

Formatierung, Maskierung, Längenlimit und Fehlerisolation bleiben in den
bestehenden zentralen Helpern. Deutsche Fachmeldungen gehören zum Consumer.
Die Regressionen in tests/debug-flow.php prüfen verschachtelte Vorgänge,
Ausnahmeidentität, geschützte Ergebnisse, Kanalfehler, Standalone-Korrelation
und genau einen nativen Schreibvorgang mit unverändertem Resultat.
