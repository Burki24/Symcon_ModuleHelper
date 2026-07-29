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
files, entries = MODULE.bundle_files(
    manifest,
    "IPSViewStyleHelper",
    "libs/helper/IPSViewStyleHelper.php",
)

if set(entries) != {"IPSViewStyleHelper"}:
    raise SystemExit(f"Unexpected top-level consumer manifest entries: {sorted(entries)}")

style_entry = entries["IPSViewStyleHelper"]
dependencies = style_entry.get("dependencies", [])
if [entry.get("name") for entry in dependencies] != ["HelperTranslationHelper"]:
    raise SystemExit(f"Unexpected bundled dependencies: {dependencies}")

expected_files = {
    "libs/helper/IPSViewStyleHelper.php",
    "libs/helper/HelperTranslationHelper.php",
    "libs/helper/translations/IPSViewStyleHelper.json",
}
if not expected_files.issubset(files):
    raise SystemExit(f"Missing bundle files: {sorted(expected_files - set(files))}")

subscriptions = {"IPSViewStyleHelper"}
if subscriptions != set(entries):
    raise SystemExit("Consumer subscription and top-level manifest entries must remain identical.")

print("Helper dependency bundle manifest verified.")
