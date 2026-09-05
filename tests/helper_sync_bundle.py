#!/usr/bin/env python3
"""Verify that dependency bundles stay compatible with consumer manifests."""

from __future__ import annotations

import importlib.util
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

subscriptions = {"IPSViewStyleHelper", "IPSViewHTMLPageHelper"}
if subscriptions != set(style_entries) | set(page_entries):
    raise SystemExit("Consumer subscriptions and top-level manifest entries must remain identical.")

print("Helper dependency bundle manifests verified.")
