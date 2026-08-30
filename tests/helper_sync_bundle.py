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
    "IPSViewStyleProfileHelper",
]:
    raise SystemExit(f"Unexpected style dependencies: {style_dependencies}")

expected_style_files = {
    "libs/helper/IPSViewStyleHelper.php",
    "libs/helper/HelperTranslationHelper.php",
    "libs/helper/IPSViewFontCatalogHelper.php",
    "libs/helper/IPSViewStyleProfileHelper.php",
    "libs/helper/translations/IPSViewStyleHelper.json",
}
if not expected_style_files.issubset(style_files):
    raise SystemExit(f"Missing style bundle files: {sorted(expected_style_files - set(style_files))}")

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
