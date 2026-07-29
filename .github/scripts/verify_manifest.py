#!/usr/bin/env python3
"""Validate manifest.json against helper sources, dependencies and assets."""

from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path, PurePosixPath
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
MANIFEST = ROOT / "manifest.json"
VERSION_PATTERN = re.compile(r"@version\s+([0-9]+\.[0-9]+\.[0-9]+)")


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def validate_dependencies(helpers: dict[str, Any]) -> None:
    visiting: set[str] = set()
    visited: set[str] = set()

    def visit(name: str) -> None:
        if name in visited:
            return
        if name in visiting:
            raise SystemExit(f"Circular helper dependency involving {name}.")
        visiting.add(name)
        dependencies = helpers[name].get("dependencies", [])
        if not isinstance(dependencies, list) or not all(isinstance(item, str) for item in dependencies):
            raise SystemExit(f"Dependencies for {name} must be a string array.")
        for dependency in dependencies:
            if dependency not in helpers:
                raise SystemExit(f"Unknown dependency for {name}: {dependency}")
            visit(dependency)
        visiting.remove(name)
        visited.add(name)

    for helper in helpers:
        visit(helper)


def validate_assets(name: str, meta: dict[str, Any]) -> None:
    assets = meta.get("assets", [])
    if not isinstance(assets, list):
        raise SystemExit(f"Assets for {name} must be an array.")
    targets: set[str] = set()
    for asset in assets:
        if not isinstance(asset, dict):
            raise SystemExit(f"Asset entries for {name} must be objects.")
        source = ROOT / str(asset.get("file", ""))
        target = str(asset.get("target", ""))
        if not source.is_file():
            raise SystemExit(f"Missing helper asset for {name}: {source}")
        target_path = PurePosixPath(target)
        if target == "" or target_path.is_absolute() or ".." in target_path.parts:
            raise SystemExit(f"Invalid target path for {name}: {target}")
        if target in targets:
            raise SystemExit(f"Duplicate asset target for {name}: {target}")
        targets.add(target)
        actual = digest(source)
        if actual != asset.get("sha256"):
            raise SystemExit(
                f"SHA-256 mismatch for {name} asset {source}: source={actual}, manifest={asset.get('sha256')}"
            )


def main() -> None:
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    if manifest.get("schema") not in (1, 2):
        raise SystemExit("Unsupported helper manifest schema.")
    helpers = manifest.get("helpers", {})
    if not isinstance(helpers, dict) or not helpers:
        raise SystemExit("manifest.json does not contain helper entries.")

    source_names = {path.stem for path in (ROOT / "src").glob("*Helper.php")}
    manifest_names = set(helpers)
    if source_names != manifest_names:
        missing = sorted(source_names - manifest_names)
        extra = sorted(manifest_names - source_names)
        raise SystemExit(f"Helper manifest mismatch. Missing={missing}, extra={extra}")

    validate_dependencies(helpers)

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
        actual = digest(source_path)
        if actual != meta.get("sha256"):
            raise SystemExit(f"SHA-256 mismatch for {name}: source={actual}, manifest={meta.get('sha256')}")
        validate_assets(name, meta)

    print(f"Helper manifest verified ({len(helpers)} helpers).")


if __name__ == "__main__":
    main()
