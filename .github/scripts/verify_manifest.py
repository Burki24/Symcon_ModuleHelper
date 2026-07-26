#!/usr/bin/env python3
"""Validate manifest.json against the helper source files."""

from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "manifest.json"
VERSION_PATTERN = re.compile(r"@version\s+([0-9]+\.[0-9]+\.[0-9]+)")


def main() -> None:
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    helpers = manifest.get("helpers", {})
    if not isinstance(helpers, dict) or not helpers:
        raise SystemExit("manifest.json does not contain helper entries.")

    source_names = {path.stem for path in (ROOT / "src").glob("*Helper.php")}
    manifest_names = set(helpers)
    if source_names != manifest_names:
        missing = sorted(source_names - manifest_names)
        extra = sorted(manifest_names - source_names)
        raise SystemExit(f"Helper manifest mismatch. Missing={missing}, extra={extra}")

    for name, meta in sorted(helpers.items()):
        source_path = ROOT / str(meta["file"])
        if not source_path.is_file():
            raise SystemExit(f"Missing helper source: {source_path}")
        source = source_path.read_text(encoding="utf-8")
        match = VERSION_PATTERN.search(source)
        if match is None:
            raise SystemExit(f"Missing @version marker in {source_path}")
        version = match.group(1)
        if version != meta.get("version"):
            raise SystemExit(f"Version mismatch for {name}: source={version}, manifest={meta.get('version')}")
        digest = hashlib.sha256(source_path.read_bytes()).hexdigest()
        if digest != meta.get("sha256"):
            raise SystemExit(f"SHA-256 mismatch for {name}: source={digest}, manifest={meta.get('sha256')}")

    print(f"Helper manifest verified ({len(helpers)} helpers).")


if __name__ == "__main__":
    main()
