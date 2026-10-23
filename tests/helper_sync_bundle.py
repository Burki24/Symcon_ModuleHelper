#!/usr/bin/env python3
"""Verify that dependency bundles stay compatible with consumer manifests."""

from __future__ import annotations

import importlib.util
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / ".github/scripts/sync_helper_consumers.py"
SPEC = importlib.util.spec_from_file_location("sync_helper_consumers", SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise SystemExit("Unable to load helper synchronization script.")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

manifest = json.loads((ROOT / "manifest.json").read_text(encoding="utf-8"))

style_files, style_entries = MODULE.bundle_files(
    manifest,
    "IPSViewStyleHelper",
    "libs/helper/IPSViewStyleHelper.php",
)

if set(style_entries) != {"IPSViewStyleHelper"}:
    raise SystemExit(f"Unexpected top-level style manifest entries: {sorted(style_entries)}")

style_entry = style_entries["IPSViewStyleHelper"]
style_dependencies = style_entry.get("dependencies", [])
if [entry.get("name") for entry in style_dependencies] != [
    "HelperTranslationHelper",
    "IPSViewFontCatalogHelper",
    "IPSViewStylePresetHelper",
    "IPSViewStyleProfileHelper",
]:
    raise SystemExit(f"Unexpected style dependencies: {style_dependencies}")

expected_style_files = {
    "libs/helper/IPSViewStyleHelper.php",
    "libs/helper/HelperTranslationHelper.php",
    "libs/helper/IPSViewFontCatalogHelper.php",
    "libs/helper/IPSViewStylePresetHelper.php",
    "libs/helper/IPSViewStyleProfileHelper.php",
    "libs/helper/translations/IPSViewStyleHelper.json",
    "libs/helper/fonts/NOTICE.md",
    "libs/helper/fonts/Segment7-Regular.ttf",
}
if not expected_style_files.issubset(style_files):
    raise SystemExit(f"Missing style bundle files: {sorted(expected_style_files - set(style_files))}")


preset_files, preset_entries = MODULE.bundle_files(
    manifest,
    "IPSViewStylePresetHelper",
    "libs/helper/IPSViewStylePresetHelper.php",
)

if set(preset_entries) != {"IPSViewStylePresetHelper"}:
    raise SystemExit(f"Unexpected top-level preset manifest entries: {sorted(preset_entries)}")

preset_entry = preset_entries["IPSViewStylePresetHelper"]
if preset_entry.get("dependencies", []) != []:
    raise SystemExit(f"Unexpected preset dependencies: {preset_entry.get('dependencies', [])}")

if "libs/helper/IPSViewStylePresetHelper.php" not in preset_files:
    raise SystemExit("Missing IPSViewStylePresetHelper from its consumer bundle.")


svg_preview_files, svg_preview_entries = MODULE.bundle_files(
    manifest,
    "SVGPreviewHelper",
    "libs/helper/SVGPreviewHelper.php",
)

if set(svg_preview_entries) != {"SVGPreviewHelper"}:
    raise SystemExit(
        f"Unexpected top-level SVG-preview manifest entries: {sorted(svg_preview_entries)}"
    )

svg_preview_entry = svg_preview_entries["SVGPreviewHelper"]
if svg_preview_entry.get("dependencies", []) != []:
    raise SystemExit(
        f"Unexpected SVG-preview dependencies: {svg_preview_entry.get('dependencies', [])}"
    )

if "libs/helper/SVGPreviewHelper.php" not in svg_preview_files:
    raise SystemExit("Missing SVGPreviewHelper from its consumer bundle.")


visualization_theme_files, visualization_theme_entries = MODULE.bundle_files(
    manifest,
    "VisualizationThemeConfigurationHelper",
    "libs/helper/VisualizationThemeConfigurationHelper.php",
)

if set(visualization_theme_entries) != {"VisualizationThemeConfigurationHelper"}:
    raise SystemExit(
        "Unexpected top-level visualization-theme manifest entries: "
        f"{sorted(visualization_theme_entries)}"
    )

visualization_theme_entry = visualization_theme_entries["VisualizationThemeConfigurationHelper"]
visualization_theme_dependencies = visualization_theme_entry.get("dependencies", [])
if [entry.get("name") for entry in visualization_theme_dependencies] != [
    "HelperTranslationHelper",
    "VisualizationThemeHelper",
]:
    raise SystemExit(
        f"Unexpected visualization-theme dependencies: {visualization_theme_dependencies}"
    )

expected_visualization_theme_files = {
    "libs/helper/VisualizationThemeConfigurationHelper.php",
    "libs/helper/HelperTranslationHelper.php",
    "libs/helper/VisualizationThemeHelper.php",
    "libs/helper/translations/VisualizationThemeConfigurationHelper.json",
}
if not expected_visualization_theme_files.issubset(visualization_theme_files):
    raise SystemExit(
        "Missing visualization-theme bundle files: "
        f"{sorted(expected_visualization_theme_files - set(visualization_theme_files))}"
    )


control_files, control_entries = MODULE.bundle_files(
    manifest,
    "IPSViewControlThemeHelper",
    "libs/helper/IPSViewControlThemeHelper.php",
)

if set(control_entries) != {"IPSViewControlThemeHelper"}:
    raise SystemExit(f"Unexpected top-level control-theme manifest entries: {sorted(control_entries)}")

control_entry = control_entries["IPSViewControlThemeHelper"]
control_dependencies = control_entry.get("dependencies", [])
if [entry.get("name") for entry in control_dependencies] != ["IPSViewStylePresetHelper"]:
    raise SystemExit(f"Unexpected control-theme dependencies: {control_dependencies}")

expected_control_files = {
    "libs/helper/IPSViewControlThemeHelper.php",
    "libs/helper/IPSViewStylePresetHelper.php",
}
if not expected_control_files.issubset(control_files):
    raise SystemExit(
        f"Missing control-theme bundle files: {sorted(expected_control_files - set(control_files))}"
    )


page_files, page_entries = MODULE.bundle_files(
    manifest,
    "IPSViewHTMLPageHelper",
    "libs/helper/IPSViewHTMLPageHelper.php",
)

if set(page_entries) != {"IPSViewHTMLPageHelper"}:
    raise SystemExit(f"Unexpected top-level page manifest entries: {sorted(page_entries)}")

page_entry = page_entries["IPSViewHTMLPageHelper"]
page_dependencies = page_entry.get("dependencies", [])
if [entry.get("name") for entry in page_dependencies] != [
    "HelperTranslationHelper",
    "VisualizationAssetHelper",
]:
    raise SystemExit(f"Unexpected page dependencies: {page_dependencies}")

expected_page_files = {
    "libs/helper/IPSViewHTMLPageHelper.php",
    "libs/helper/HelperTranslationHelper.php",
    "libs/helper/VisualizationAssetHelper.php",
    "libs/helper/translations/IPSViewHTMLPageHelper.json",
}
if not expected_page_files.issubset(page_files):
    raise SystemExit(f"Missing page bundle files: {sorted(expected_page_files - set(page_files))}")

# Every HTML-page consumer must receive both the reviewed source and its runtime build.
for asset in ["visualization/interaction.js", "visualization/interaction.min.js"]:
    target = f"libs/helper/{asset}"
    if page_files.get(target) != (ROOT / "src" / asset).read_bytes():
        raise SystemExit(f"Shared wheel resource missing or altered in page bundle: {target}")
if "IPSViewHTMLPageHelper" not in MODULE.expand_dependents(manifest, {"VisualizationAssetHelper"}):
    raise SystemExit("Interaction updates must select HTML-page consumers automatically.")

subscriptions = {"IPSViewStyleHelper", "IPSViewHTMLPageHelper"}
if subscriptions != set(style_entries) | set(page_entries):
    raise SystemExit("Consumer subscriptions and top-level manifest entries must remain identical.")

overlapping_subscriptions = {
    "IPSViewStyleConfigurationHelper": {
        "target": "libs/helper/IPSViewStyleConfigurationHelper.php"
    },
    "IPSViewStyleHelper": {"target": "libs/helper/IPSViewStyleHelper.php"},
}
overlapping_selection = ["IPSViewStyleConfigurationHelper", "IPSViewStyleHelper"]
if MODULE.synchronization_roots(
    manifest, overlapping_selection, overlapping_subscriptions
) != ["IPSViewStyleConfigurationHelper"]:
    raise SystemExit("Overlapping helper subscriptions would still create multiple pull requests.")

combined_files, combined_entries = MODULE.consumer_bundle_files(
    manifest,
    "IPSViewStyleConfigurationHelper",
    overlapping_subscriptions,
)
if set(combined_entries) != {
    "IPSViewStyleConfigurationHelper",
    "IPSViewStyleHelper",
}:
    raise SystemExit(
        f"Subscribed dependency manifest entries were not refreshed together: {sorted(combined_entries)}"
    )
if not {
    "libs/helper/IPSViewStyleConfigurationHelper.php",
    "libs/helper/IPSViewStyleHelper.php",
}.issubset(combined_files):
    raise SystemExit("The combined consumer bundle is missing subscribed helper files.")

independent_subscriptions = {
    "ConfigurationFormHelper": {
        "target": "libs/helper/ConfigurationFormHelper.php"
    },
    "DateHelper": {"target": "libs/helper/DateHelper.php"},
}
independent_selection = ["ConfigurationFormHelper", "DateHelper"]
if MODULE.synchronization_batch(
    manifest,
    independent_selection,
    independent_subscriptions,
    "ConfigurationFormHelper",
    False,
) != independent_selection:
    raise SystemExit("A manual multi-helper synchronization was not combined into one batch.")
if MODULE.synchronization_batch(
    manifest,
    independent_selection,
    independent_subscriptions,
    "DateHelper",
    False,
) != []:
    raise SystemExit("A later manual synchronization root would create a competing pull request.")
if MODULE.synchronization_batch(
    manifest,
    independent_selection,
    independent_subscriptions,
    "DateHelper",
    True,
) != []:
    raise SystemExit("Automatic synchronization would create competing helper pull requests.")

if MODULE.synchronization_batch(
    manifest, independent_selection, independent_subscriptions,
    "ConfigurationFormHelper", True,
) != independent_selection:
    raise SystemExit("Automatic synchronization did not combine all selected roots.")

independent_files, independent_entries = MODULE.combined_consumer_bundle_files(
    manifest,
    independent_selection,
    independent_subscriptions,
)
if set(independent_entries) != set(independent_selection):
    raise SystemExit(
        "The combined manual bundle is missing manifest entries: "
        f"{sorted(set(independent_selection) - set(independent_entries))}"
    )
if not {
    "libs/helper/ConfigurationFormHelper.php",
    "libs/helper/DateHelper.php",
}.issubset(independent_files):
    raise SystemExit("The combined manual bundle is missing independent helper files.")

map_files, map_entries = MODULE.bundle_files(
    manifest, "OpenFreeMapHelper", "libs/helper/OpenFreeMapHelper.php"
)
map_entry = map_entries["OpenFreeMapHelper"]
map_dependencies = {item["name"] for item in map_entry.get("dependencies", [])}
if map_dependencies != {"HelperTranslationHelper", "VisualizationAssetHelper"}:
    raise SystemExit(f"OpenFreeMap dependencies are incomplete: {sorted(map_dependencies)}")

expected_map_assets = {
    "maps/openfreemap.js",
    "maps/vendor/maplibre-gl.js",
    "maps/vendor/maplibre-gl.css",
    "maps/vendor/leaflet-maplibre-gl.js",
    "maps/vendor/MAPLIBRE-LICENSE.txt",
    "maps/vendor/BRIDGE-LICENSE",
    "maps/vendor/NOTICE.json",
    "translations/OpenFreeMapHelper.json",
}
actual_map_assets = {item["path"] for item in map_entry.get("assets", [])}
if actual_map_assets != {f"libs/helper/{path}" for path in expected_map_assets}:
    raise SystemExit("OpenFreeMap bundle must contain every renderer, license and translation asset.")
source_map_assets = {
    path.relative_to(ROOT / "src").as_posix()
    for path in (ROOT / "src/maps").rglob("*") if path.is_file()
}
if not source_map_assets.issubset(expected_map_assets):
    raise SystemExit("New map resources must be covered by the distribution contract.")
for path in expected_map_assets | {
    "OpenFreeMapHelper.php", "HelperTranslationHelper.php", "VisualizationAssetHelper.php",
    "visualization/http-origin.js",
}:
    target = f"libs/helper/{path}"
    if map_files.get(target) != (ROOT / "src" / path).read_bytes():
        raise SystemExit(f"OpenFreeMap bundle is missing or changes source bytes: {target}")
if "OpenFreeMapHelper" not in MODULE.expand_dependents(manifest, {"VisualizationAssetHelper"}):
    raise SystemExit("Visualization asset updates must also select the OpenFreeMap consumer root.")

verifier_spec = importlib.util.spec_from_file_location(
    "verify_manifest", ROOT / ".github/scripts/verify_manifest.py"
)
if verifier_spec is None or verifier_spec.loader is None:
    raise SystemExit("Unable to load the manifest validator.")
verifier = importlib.util.module_from_spec(verifier_spec)
verifier_spec.loader.exec_module(verifier)
try:
    verifier.validate_dependencies({"ExampleHelper": {"requires": ["MissingHelper"]}})
except SystemExit as error:
    if "use dependencies instead of requires" not in str(error):
        raise
else:
    raise SystemExit("The manifest validator silently accepted the unsupported requires field.")

geo_files, geo_entries = MODULE.bundle_files(
    manifest, "ReverseGeocodingHelper", "libs/helper/ReverseGeocodingHelper.php"
)
if set(geo_files) != {
    "libs/helper/ReverseGeocodingHelper.php", "libs/helper/PersistentJsonCacheHelper.php"
}:
    raise SystemExit("Reverse geocoding must distribute its complete persistent-cache dependency.")
if [entry["name"] for entry in geo_entries["ReverseGeocodingHelper"]["dependencies"]] != [
    "PersistentJsonCacheHelper"
]:
    raise SystemExit("Reverse-geocoding dependency metadata is incomplete.")
for target, raw in geo_files.items():
    if raw != (ROOT / "src" / Path(target).name).read_bytes():
        raise SystemExit(f"Reverse-geocoding bundle changed source bytes: {target}")
if "ReverseGeocodingHelper" not in MODULE.expand_dependents(manifest, {"PersistentJsonCacheHelper"}):
    raise SystemExit("Persistent-cache updates must select subscribed reverse-geocoding consumers.")

fence_files, fence_entries = MODULE.bundle_files(
    manifest, "GeofenceHelper", "libs/helper/GeofenceHelper.php"
)
fence_source = (ROOT / "src/GeofenceHelper.php").read_bytes()
if fence_files != {"libs/helper/GeofenceHelper.php": fence_source}:
    raise SystemExit("Geofence evaluation must distribute exactly its canonical helper source.")
if set(fence_entries) != {"GeofenceHelper"} or fence_entries["GeofenceHelper"].get("dependencies", []):
    raise SystemExit("Geofence evaluation has no additional runtime dependencies.")
if fence_entries["GeofenceHelper"]["sha256"] != hashlib.sha256(fence_source).hexdigest():
    raise SystemExit("Geofence bundle metadata does not match the distributed source.")
if "GeofenceHelper" not in MODULE.expand_dependents(manifest, {"GeofenceHelper"}):
    raise SystemExit("Geofence changes must remain selectable for automatic consumer updates.")

print("Helper dependency bundle manifests verified, including maps, reverse geocoding and geofences.")
