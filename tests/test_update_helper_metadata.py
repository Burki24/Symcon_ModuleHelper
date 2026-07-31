#!/usr/bin/env python3
"""Regression tests for automatic helper metadata versioning."""

from __future__ import annotations

import importlib.util
import json
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / ".github" / "scripts" / "update_helper_metadata.py"

spec = importlib.util.spec_from_file_location("update_helper_metadata", SCRIPT)
if spec is None or spec.loader is None:
    raise SystemExit("Unable to load metadata updater.")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def assert_equal(actual: object, expected: object, message: str) -> None:
    if actual != expected:
        raise AssertionError(f"{message}: expected {expected!r}, got {actual!r}")


def write_helper(root: Path, version: str, body: str = "return true;") -> None:
    source = root / "src" / "ExampleHelper.php"
    source.parent.mkdir(parents=True, exist_ok=True)
    source.write_text(
        "<?php\n\n/**\n * @version " + version + "\n */\ntrait ExampleHelper\n{\n    public function Test(): bool\n    {\n        " + body + "\n    }\n}\n",
        encoding="utf-8",
    )


def create_manifest(root: Path, repository_version: str, helper_version: str) -> dict[str, object]:
    manifest = {
        "schema": 2,
        "repository": "Burki24/Symcon_ModuleHelper",
        "repository_version": repository_version,
        "helpers": {
            "ExampleHelper": {
                "file": "src/ExampleHelper.php",
                "version": helper_version,
                "sha256": "outdated",
            }
        },
    }
    (root / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
    return manifest


def test_semver() -> None:
    assert_equal(module.bump_semver("1.2.3", "patch"), "1.2.4", "Patch bump")
    assert_equal(module.bump_semver("1.2.3", "minor"), "1.3.0", "Minor bump")
    assert_equal(module.bump_semver("1.2.3", "major"), "2.0.0", "Major bump")


def test_commit_classification() -> None:
    assert_equal(module.determine_bump_level(["FIX: Correct issue"]), "patch", "Fix classification")
    assert_equal(module.determine_bump_level(["FEAT: Add helper"]), "minor", "Feature classification")
    assert_equal(module.determine_bump_level(["FEAT!: Replace API"]), "major", "Breaking classification")
    assert_equal(
        module.determine_bump_level(["CHORE: Update helper metadata to v1.0.1"]),
        "none",
        "Metadata commit classification",
    )


def test_helper_and_repository_update() -> None:
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory)
        write_helper(root, "1.0.0", "return false;")
        previous = create_manifest(root, "3.4.1", "1.0.0")
        result = module.update_metadata(
            root=root,
            previous_manifest=previous,
            paths={"src/ExampleHelper.php"},
            bump_level="minor",
            commit_sha="0123456789abcdef0123456789abcdef01234567",
            commit_date=1785474000,
        )

        manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        source = (root / "src" / "ExampleHelper.php").read_text(encoding="utf-8")
        assert_equal(result["repository_version"], "3.5.0", "Repository version")
        assert_equal(manifest["helpers"]["ExampleHelper"]["version"], "1.1.0", "Helper version")
        assert "@version 1.1.0" in source
        assert_equal(manifest["repository_build"], int("0123456", 16), "Repository build")
        assert_equal(manifest["repository_date"], 1785474000, "Repository date")
        assert manifest["helpers"]["ExampleHelper"]["sha256"] == module.digest(
            root / "src" / "ExampleHelper.php"
        )


def test_non_helper_change_keeps_helper_version() -> None:
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory)
        write_helper(root, "1.0.0")
        previous = create_manifest(root, "3.4.1", "1.0.0")
        module.update_metadata(
            root=root,
            previous_manifest=previous,
            paths={"README.md"},
            bump_level="patch",
            commit_sha="abcdef0123456789abcdef0123456789abcdef01",
            commit_date=1785475000,
        )

        manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        assert_equal(manifest["repository_version"], "3.4.2", "Repository patch version")
        assert_equal(manifest["helpers"]["ExampleHelper"]["version"], "1.0.0", "Unchanged helper version")



def test_metadata_commit_preserves_source_build_and_date() -> None:
    with tempfile.TemporaryDirectory() as directory:
        root = Path(directory)
        write_helper(root, "1.0.1")
        previous = create_manifest(root, "3.4.1", "1.0.0")
        current = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        current["repository_version"] = "3.4.2"
        current["repository_build"] = 123456
        current["repository_date"] = 1785475000
        current["helpers"]["ExampleHelper"]["version"] = "1.0.1"
        (root / "manifest.json").write_text(json.dumps(current), encoding="utf-8")

        module.update_metadata(
            root=root,
            previous_manifest=previous,
            paths={"manifest.json", "src/ExampleHelper.php"},
            bump_level="none",
            commit_sha="fedcba9876543210fedcba9876543210fedcba98",
            commit_date=1785476000,
        )

        manifest = json.loads((root / "manifest.json").read_text(encoding="utf-8"))
        assert_equal(manifest["repository_version"], "3.4.2", "Metadata repository version")
        assert_equal(manifest["repository_build"], 123456, "Metadata source build")
        assert_equal(manifest["repository_date"], 1785475000, "Metadata source date")
        assert_equal(manifest["helpers"]["ExampleHelper"]["version"], "1.0.1", "Metadata helper version")


def test_metadata_workflow_uses_repository_token_and_dispatches_sync() -> None:
    workflow = (ROOT / ".github" / "workflows" / "update-helper-metadata.yml").read_text(
        encoding="utf-8"
    )
    assert "actions/create-github-app-token" not in workflow
    assert "actions: write" in workflow
    assert "gh workflow run helper-sync.yml" in workflow
    assert '--ref "${GITHUB_REF_NAME}"' in workflow
    assert "-f helper=all" in workflow


def main() -> None:
    test_semver()
    test_commit_classification()
    test_helper_and_repository_update()
    test_non_helper_change_keeps_helper_version()
    test_metadata_commit_preserves_source_build_and_date()
    test_metadata_workflow_uses_repository_token_and_dispatches_sync()
    print("Automatic helper metadata tests passed.")


if __name__ == "__main__":
    main()
