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

REPOSITORY = "Burki24/LMNB"
BASE_BRANCH = "dev"
HEAD_BRANCH = "helper-sync/date-helper-v1.0.2"
EXPECTED_HEAD_SHA = "0123456789abcdef0123456789abcdef01234567"
EXPECTED_FILES = {
    "libs/helper/DateHelper.php",
    "libs/helper/manifest.json",
    "libs/helper/README.md",
}


def pull_request(
    *,
    author_type: str = "Bot",
    head_sha: str = EXPECTED_HEAD_SHA,
) -> dict[str, object]:
    return {
        "number": 17,
        "node_id": "PR_kwDOExample",
        "draft": False,
        "title": "CHORE: Update DateHelper to v1.0.2",
        "user": {"type": author_type},
        "base": {"ref": BASE_BRANCH},
        "head": {
            "ref": HEAD_BRANCH,
            "sha": head_sha,
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
        {"libs/helper/DateHelper.php", "LMNB Station/module.php"},
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
    if method == "GET" and path == f"/repos/{REPOSITORY}/pulls/17":
        return pull_request()
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
    EXPECTED_HEAD_SHA,
)

if len(graphql_calls) != 2:
    raise SystemExit(f"Expected GraphQL state query and mutation, got {len(graphql_calls)} calls.")
if graphql_calls[-1][1].get("mergeMethod") != "SQUASH":
    raise SystemExit("Auto-merge mutation did not use the configured squash method.")

direct_merge_calls: list[tuple[str, str, object | None]] = []


def fake_clean_api(method: str, path: str, payload: object | None = None) -> object:
    direct_merge_calls.append((method, path, payload))
    if path == f"/repos/{REPOSITORY}":
        return {
            "allow_auto_merge": True,
            "allow_merge_commit": True,
            "allow_squash_merge": True,
            "allow_rebase_merge": True,
        }
    if method == "GET" and path == f"/repos/{REPOSITORY}/pulls/17":
        return pull_request()
    if path == f"/repos/{REPOSITORY}/pulls/17/files?per_page=100&page=1":
        return [
            {"filename": "libs/helper/DateHelper.php"},
            {"filename": "libs/helper/manifest.json"},
        ]
    if method == "PUT" and path == f"/repos/{REPOSITORY}/pulls/17/merge":
        return {"merged": True, "message": "Pull Request successfully merged"}
    raise AssertionError(f"Unexpected API call: {method} {path}")


def fake_clean_graphql(query: str, variables: dict[str, object]) -> dict[str, object]:
    if "query($pullRequestId" in query:
        return {"node": {"autoMergeRequest": None}}
    if "enablePullRequestAutoMerge" in query:
        raise RuntimeError(
            "GitHub GraphQL request failed: Pull request Pull Request is in clean status"
        )
    raise AssertionError("Unexpected GraphQL operation.")


MODULE.api = fake_clean_api
MODULE.graphql = fake_clean_graphql
MODULE.enable_auto_merge(
    REPOSITORY,
    pull_request(),
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    "SQUASH",
    EXPECTED_HEAD_SHA,
)

merge_calls = [call for call in direct_merge_calls if call[0] == "PUT"]
if len(merge_calls) != 1:
    raise SystemExit(f"Expected one direct clean-PR merge, got {len(merge_calls)}.")
if merge_calls[0][2] != {
    "sha": EXPECTED_HEAD_SHA,
    "merge_method": "squash",
}:
    raise SystemExit(f"Direct merge was not pinned to the expected head SHA: {merge_calls[0][2]}")


def fake_unprotected_graphql(query: str, variables: dict[str, object]) -> dict[str, object]:
    if "query($pullRequestId" in query:
        return {"node": {"autoMergeRequest": None}}
    if "enablePullRequestAutoMerge" in query:
        raise RuntimeError(
            "GitHub GraphQL request failed: "
            "Pull request Protected branch rules not configured for this branch"
        )
    raise AssertionError("Unexpected GraphQL operation.")


direct_merge_calls.clear()
MODULE.graphql = fake_unprotected_graphql
MODULE.enable_auto_merge(
    REPOSITORY,
    pull_request(),
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    "SQUASH",
    EXPECTED_HEAD_SHA,
)
if len([call for call in direct_merge_calls if call[0] == "PUT"]) != 1:
    raise SystemExit("A repository without protected branch rules did not use direct merge.")

stale_head_sha = "abcdefabcdefabcdefabcdefabcdefabcdefabcd"
refresh_count = 0
merge_attempts = 0


def fake_propagating_api(method: str, path: str, payload: object | None = None) -> object:
    global merge_attempts, refresh_count
    direct_merge_calls.append((method, path, payload))
    if path == f"/repos/{REPOSITORY}":
        return {
            "allow_auto_merge": True,
            "allow_merge_commit": True,
            "allow_squash_merge": True,
            "allow_rebase_merge": True,
        }
    if method == "GET" and path == f"/repos/{REPOSITORY}/pulls/17":
        refresh_count += 1
        return pull_request(head_sha=stale_head_sha if refresh_count == 1 else EXPECTED_HEAD_SHA)
    if path == f"/repos/{REPOSITORY}/pulls/17/files?per_page=100&page=1":
        return [
            {"filename": "libs/helper/DateHelper.php"},
            {"filename": "libs/helper/manifest.json"},
        ]
    if method == "PUT" and path == f"/repos/{REPOSITORY}/pulls/17/merge":
        merge_attempts += 1
        if merge_attempts == 1:
            raise RuntimeError(
                "GitHub API PUT /repos/Burki24/LMNB/pulls/17/merge failed: "
                "409 Head branch was modified. Review and try the merge again."
            )
        return {"merged": True, "message": "Pull Request successfully merged"}
    raise AssertionError(f"Unexpected API call: {method} {path}")


direct_merge_calls.clear()
MODULE.api = fake_propagating_api
MODULE.time.sleep = lambda _seconds: None
MODULE.enable_auto_merge(
    REPOSITORY,
    pull_request(head_sha=stale_head_sha),
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    "SQUASH",
    EXPECTED_HEAD_SHA,
)
if refresh_count != 3:
    raise SystemExit(f"Expected one stale and two current PR refreshes, got {refresh_count}.")
propagation_merges = [call for call in direct_merge_calls if call[0] == "PUT"]
expected_merge_payload = {"sha": EXPECTED_HEAD_SHA, "merge_method": "squash"}
if len(propagation_merges) != 2 or any(
    call[2] != expected_merge_payload for call in propagation_merges
):
    raise SystemExit("The propagated PR head was not merged with the expected SHA guard.")

consumer_config = json.loads((ROOT / ".github/helper-consumers.json").read_text(encoding="utf-8"))
auto_merge = consumer_config.get("auto_merge", {})
if auto_merge != {"enabled": True, "merge_method": "SQUASH"}:
    raise SystemExit(f"Unexpected global auto-merge configuration: {auto_merge}")

print("Guarded helper pull request auto-merge verified.")
