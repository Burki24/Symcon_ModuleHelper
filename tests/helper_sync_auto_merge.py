#!/usr/bin/env python3
"""Verify the guarded auto-merge contract for generated helper pull requests."""

from __future__ import annotations

import importlib.util
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / ".github/scripts/sync_helper_consumers.py"
SPEC = importlib.util.spec_from_file_location("sync_helper_consumers_auto_merge", SCRIPT)
if SPEC is None or SPEC.loader is None:
    raise SystemExit("Unable to load helper synchronization script.")
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

REPOSITORY = "Burki24/OpenLMNB"
BASE_BRANCH = "dev"
HEAD_BRANCH = "helper-sync/date-helper-v1.0.2"
EXPECTED_FILES = {
    "libs/helper/DateHelper.php",
    "libs/helper/manifest.json",
    "libs/helper/README.md",
}


def pull_request(*, author_type: str = "Bot") -> dict[str, object]:
    return {
        "number": 17,
        "node_id": "PR_kwDOExample",
        "draft": False,
        "title": "CHORE: Update DateHelper to v1.0.2",
        "user": {"type": author_type},
        "base": {"ref": BASE_BRANCH},
        "head": {
            "ref": HEAD_BRANCH,
            "repo": {"full_name": REPOSITORY},
        },
    }


node_id = MODULE.validate_auto_merge_candidate(
    pull_request(),
    REPOSITORY,
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    {"libs/helper/DateHelper.php", "libs/helper/manifest.json"},
)
if node_id != "PR_kwDOExample":
    raise SystemExit("Valid helper pull request did not return its GraphQL node ID.")

try:
    MODULE.validate_auto_merge_candidate(
        pull_request(),
        REPOSITORY,
        BASE_BRANCH,
        HEAD_BRANCH,
        EXPECTED_FILES,
        {"libs/helper/DateHelper.php", "OpenLMNB Station/module.php"},
    )
except RuntimeError as error:
    if "unexpected files" not in str(error):
        raise
else:
    raise SystemExit("Auto-merge accepted a pull request containing a module file.")

try:
    MODULE.validate_auto_merge_candidate(
        pull_request(author_type="User"),
        REPOSITORY,
        BASE_BRANCH,
        HEAD_BRANCH,
        EXPECTED_FILES,
        {"libs/helper/DateHelper.php"},
    )
except RuntimeError as error:
    if "not a GitHub App bot" not in str(error):
        raise
else:
    raise SystemExit("Auto-merge accepted a pull request not authored by a bot.")

api_calls: list[tuple[str, str, object | None]] = []
graphql_calls: list[tuple[str, dict[str, object]]] = []


def fake_api(method: str, path: str, payload: object | None = None) -> object:
    api_calls.append((method, path, payload))
    if path == f"/repos/{REPOSITORY}":
        return {
            "allow_auto_merge": True,
            "allow_merge_commit": True,
            "allow_squash_merge": True,
            "allow_rebase_merge": True,
        }
    if path == f"/repos/{REPOSITORY}/pulls/17/files?per_page=100&page=1":
        return [
            {"filename": "libs/helper/DateHelper.php"},
            {"filename": "libs/helper/manifest.json"},
        ]
    raise AssertionError(f"Unexpected API call: {method} {path}")


def fake_graphql(query: str, variables: dict[str, object]) -> dict[str, object]:
    graphql_calls.append((query, variables))
    if "query($pullRequestId" in query:
        return {"node": {"autoMergeRequest": None}}
    if "enablePullRequestAutoMerge" in query:
        return {"enablePullRequestAutoMerge": {"pullRequest": {"number": 17}}}
    raise AssertionError("Unexpected GraphQL operation.")


MODULE.api = fake_api
MODULE.graphql = fake_graphql
MODULE.enable_auto_merge(
    REPOSITORY,
    pull_request(),
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    "SQUASH",
)

if len(graphql_calls) != 2:
    raise SystemExit(f"Expected GraphQL state query and mutation, got {len(graphql_calls)} calls.")
if graphql_calls[-1][1].get("mergeMethod") != "SQUASH":
    raise SystemExit("Auto-merge mutation did not use the configured squash method.")

consumer_config = json.loads((ROOT / ".github/helper-consumers.json").read_text(encoding="utf-8"))
auto_merge = consumer_config.get("auto_merge", {})
if auto_merge != {"enabled": True, "merge_method": "SQUASH"}:
    raise SystemExit(f"Unexpected global auto-merge configuration: {auto_merge}")

print("Guarded helper pull request auto-merge verified.")
