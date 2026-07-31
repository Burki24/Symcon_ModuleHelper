#!/usr/bin/env python3
"""Automatically update repository and helper metadata in manifest.json."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
from pathlib import Path
from typing import Any, Iterable

SEMVER_PATTERN = re.compile(r"^(\d+)\.(\d+)\.(\d+)$")
HELPER_VERSION_PATTERN = re.compile(r"(@version\s+)(\d+\.\d+\.\d+)")
SHA_PATTERN = re.compile(r"^[0-9a-fA-F]{40}$")
METADATA_COMMIT_PREFIX = "CHORE: Update helper metadata"


def parse_arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Update Symcon helper repository versions, hashes, build and date."
    )
    parser.add_argument("--root", type=Path, default=Path.cwd())
    parser.add_argument("--base-ref", required=True)
    parser.add_argument("--sha", required=True)
    parser.add_argument("--date", required=True, type=int)
    return parser.parse_args()


def parse_semver(value: object, field: str = "version") -> tuple[int, int, int]:
    if not isinstance(value, str):
        raise SystemExit(f"{field} must be a semantic version string.")
    match = SEMVER_PATTERN.fullmatch(value)
    if match is None:
        raise SystemExit(f"{field} must use the format major.minor.patch.")
    return tuple(int(part) for part in match.groups())


def format_semver(version: tuple[int, int, int]) -> str:
    return ".".join(str(part) for part in version)


def bump_semver(version: object, level: str) -> str:
    major, minor, patch = parse_semver(version)
    if level == "major":
        return f"{major + 1}.0.0"
    if level == "minor":
        return f"{major}.{minor + 1}.0"
    if level == "patch":
        return f"{major}.{minor}.{patch + 1}"
    if level == "none":
        return f"{major}.{minor}.{patch}"
    raise SystemExit(f"Unsupported bump level: {level}")


def max_semver(*versions: object) -> str:
    parsed = [(parse_semver(version), str(version)) for version in versions]
    return max(parsed, key=lambda item: item[0])[1]


def determine_bump_level(messages: Iterable[str]) -> str:
    level = "none"
    priority = {"none": 0, "patch": 1, "minor": 2, "major": 3}
    for raw_message in messages:
        message = raw_message.strip()
        if message == "" or message.startswith(METADATA_COMMIT_PREFIX):
            continue
        upper = message.upper()
        candidate = "patch"
        if "BREAKING CHANGE" in upper or upper.startswith("BREAKING:") or re.match(r"^[A-Z]+!:", upper):
            candidate = "major"
        elif upper.startswith("FEAT:"):
            candidate = "minor"
        if priority[candidate] > priority[level]:
            level = candidate
    return level


def calculate_build(commit_sha: str) -> int:
    if SHA_PATTERN.fullmatch(commit_sha) is None:
        raise SystemExit("--sha must be a complete 40-character Git commit SHA.")
    return int(commit_sha[:7], 16)


def git(root: Path, *args: str) -> str:
    result = subprocess.run(
        ["git", *args],
        cwd=root,
        check=True,
        capture_output=True,
        text=True,
    )
    return result.stdout


def load_json(path: Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise SystemExit(f"Missing JSON file: {path}") from exc
    except json.JSONDecodeError as exc:
        raise SystemExit(f"Invalid JSON in {path}: {exc}") from exc
    if not isinstance(value, dict):
        raise SystemExit(f"{path} must contain a JSON object.")
    return value


def load_json_from_git(root: Path, ref: str, path: str) -> dict[str, Any]:
    try:
        raw = git(root, "show", f"{ref}:{path}")
    except subprocess.CalledProcessError as exc:
        raise SystemExit(f"Unable to read {path} from base ref {ref}.") from exc
    value = json.loads(raw)
    if not isinstance(value, dict):
        raise SystemExit(f"{ref}:{path} must contain a JSON object.")
    return value


def changed_paths(root: Path, base_ref: str, sha: str) -> set[str]:
    raw = git(root, "diff", "--name-only", base_ref, sha)
    return {line.strip() for line in raw.splitlines() if line.strip()}


def commit_messages(root: Path, base_ref: str, sha: str) -> list[str]:
    raw = git(root, "log", "--format=%B%x00", f"{base_ref}..{sha}")
    return [message.strip() for message in raw.split("\x00") if message.strip()]


def helper_version_from_source(source: str, path: Path) -> str:
    match = HELPER_VERSION_PATTERN.search(source)
    if match is None:
        raise SystemExit(f"Missing @version marker in {path}.")
    return match.group(2)


def replace_helper_version(source: str, version: str, path: Path) -> str:
    updated, count = HELPER_VERSION_PATTERN.subn(
        lambda match: match.group(1) + version,
        source,
        count=1,
    )
    if count != 1:
        raise SystemExit(f"Unable to replace @version marker in {path}.")
    return updated


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def helper_changed(
    name: str,
    current_meta: dict[str, Any],
    previous_meta: dict[str, Any] | None,
    paths: set[str],
) -> bool:
    source_path = str(current_meta.get("file", ""))
    if source_path in paths:
        return True

    current_assets = current_meta.get("assets", [])
    previous_assets = [] if previous_meta is None else previous_meta.get("assets", [])
    asset_paths = {
        str(asset.get("file", ""))
        for asset in [*current_assets, *previous_assets]
        if isinstance(asset, dict)
    }
    if asset_paths & paths:
        return True

    if previous_meta is None:
        return False

    ignored = {"version", "sha256"}
    current_structure = {key: value for key, value in current_meta.items() if key not in ignored}
    previous_structure = {key: value for key, value in previous_meta.items() if key not in ignored}
    return current_structure != previous_structure


def update_metadata(
    root: Path,
    previous_manifest: dict[str, Any],
    paths: set[str],
    bump_level: str,
    commit_sha: str,
    commit_date: int,
) -> dict[str, Any]:
    manifest_path = root / "manifest.json"
    manifest = load_json(manifest_path)
    helpers = manifest.get("helpers")
    previous_helpers = previous_manifest.get("helpers", {})
    if not isinstance(helpers, dict) or not helpers:
        raise SystemExit("manifest.json does not contain helper entries.")
    if not isinstance(previous_helpers, dict):
        raise SystemExit("Previous manifest does not contain a valid helper map.")

    previous_repository_version = previous_manifest.get(
        "repository_version",
        manifest.get("repository_version"),
    )
    current_repository_version = manifest.get("repository_version", previous_repository_version)
    if bump_level == "none":
        repository_version = max_semver(previous_repository_version, current_repository_version)
    else:
        repository_version = max_semver(
            current_repository_version,
            bump_semver(previous_repository_version, bump_level),
        )

    changed_helpers: list[str] = []
    for name, meta_value in sorted(helpers.items()):
        if not isinstance(meta_value, dict):
            raise SystemExit(f"Manifest entry for {name} must be an object.")
        meta = meta_value
        previous_meta_value = previous_helpers.get(name)
        previous_meta = previous_meta_value if isinstance(previous_meta_value, dict) else None
        source_path = root / str(meta.get("file", ""))
        if not source_path.is_file():
            raise SystemExit(f"Missing helper source: {source_path}")

        source = source_path.read_text(encoding="utf-8")
        source_version = helper_version_from_source(source, source_path)
        current_manifest_version = meta.get("version", source_version)
        if previous_meta is None:
            selected_version = max_semver(source_version, current_manifest_version)
        elif helper_changed(name, meta, previous_meta, paths) and bump_level != "none":
            previous_version = previous_meta.get("version")
            selected_version = max_semver(
                source_version,
                current_manifest_version,
                bump_semver(previous_version, bump_level),
            )
            changed_helpers.append(name)
        else:
            selected_version = max_semver(source_version, current_manifest_version)

        if source_version != selected_version:
            source_path.write_text(
                replace_helper_version(source, selected_version, source_path),
                encoding="utf-8",
                newline="\n",
            )

        meta["version"] = selected_version
        meta["sha256"] = digest(source_path)

        assets = meta.get("assets", [])
        if not isinstance(assets, list):
            raise SystemExit(f"Assets for {name} must be an array.")
        for asset in assets:
            if not isinstance(asset, dict):
                raise SystemExit(f"Asset entries for {name} must be objects.")
            asset_path = root / str(asset.get("file", ""))
            if not asset_path.is_file():
                raise SystemExit(f"Missing helper asset for {name}: {asset_path}")
            asset["sha256"] = digest(asset_path)

    manifest["repository_version"] = repository_version
    if bump_level == "none" and isinstance(manifest.get("repository_build"), int):
        repository_build = manifest["repository_build"]
    else:
        repository_build = calculate_build(commit_sha)
    if bump_level == "none" and isinstance(manifest.get("repository_date"), int):
        repository_date = manifest["repository_date"]
    else:
        repository_date = commit_date
    manifest["repository_build"] = repository_build
    manifest["repository_date"] = repository_date
    manifest_path.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )

    return {
        "repository_version": repository_version,
        "bump_level": bump_level,
        "changed_helpers": changed_helpers,
    }


def main() -> None:
    args = parse_arguments()
    root = args.root.resolve()
    if args.date < 0:
        raise SystemExit("--date must be a non-negative Unix timestamp.")

    previous_manifest = load_json_from_git(root, args.base_ref, "manifest.json")
    paths = changed_paths(root, args.base_ref, args.sha)
    messages = commit_messages(root, args.base_ref, args.sha)
    bump_level = determine_bump_level(messages)
    result = update_metadata(
        root=root,
        previous_manifest=previous_manifest,
        paths=paths,
        bump_level=bump_level,
        commit_sha=args.sha,
        commit_date=args.date,
    )

    helpers = ", ".join(result["changed_helpers"]) or "none"
    print(
        "Updated helper metadata: "
        f"repository v{result['repository_version']}, "
        f"bump={result['bump_level']}, helpers={helpers}, "
        f"build={calculate_build(args.sha)}, date={args.date}"
    )


if __name__ == "__main__":
    main()
