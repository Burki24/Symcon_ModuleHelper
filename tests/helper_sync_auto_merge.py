#!/usr/bin/env python3
"""Verify guarded helper synchronization and auto-merge contracts."""

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
PARALLEL_BRANCH = "dev_9.1"
HEAD_BRANCH = "helper-sync/date-helper-v1.0.2"
PARALLEL_HEAD_BRANCH = "helper-sync/dev-9-1/date-helper-v1.0.2"
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


if MODULE.helper_sync_branch(BASE_BRANCH, "DateHelper", "1.0.2") != HEAD_BRANCH:
    raise SystemExit("The legacy dev helper-sync branch name changed unexpectedly.")
if MODULE.helper_sync_branch(PARALLEL_BRANCH, "DateHelper", "1.0.2") != PARALLEL_HEAD_BRANCH:
    raise SystemExit("A parallel target branch did not receive an isolated helper-sync branch path.")
if MODULE.helper_sync_branch("release/9.1", "DateHelper", "1.0.2") != (
    "helper-sync/release-9-1/date-helper-v1.0.2"
):
    raise SystemExit("Target branch names are not normalized safely for helper-sync branches.")
if HEAD_BRANCH == PARALLEL_HEAD_BRANCH:
    raise SystemExit("Parallel target branches would collide on the same helper-sync branch.")


MODULE.validate_consumer_branch_config(REPOSITORY, BASE_BRANCH, {})
MODULE.validate_consumer_branch_config(
    REPOSITORY,
    BASE_BRANCH,
    {"base_branch": BASE_BRANCH},
)

try:
    MODULE.validate_consumer_branch_config(
        REPOSITORY,
        BASE_BRANCH,
        {"base_branch": "dev-popup"},
    )
except RuntimeError as error:
    if "does not match centrally configured target branch 'dev'" not in str(error):
        raise
else:
    raise SystemExit("Helper sync accepted a conflicting consumer base_branch.")

try:
    MODULE.validate_consumer_branch_config(
        REPOSITORY,
        BASE_BRANCH,
        {"base_branch": ""},
    )
except RuntimeError as error:
    if "must be a non-empty string" not in str(error):
        raise
else:
    raise SystemExit("Helper sync accepted an empty consumer base_branch.")


original_load_json_content = MODULE.load_json_content
original_bundle_files = MODULE.bundle_files
MODULE.load_json_content = lambda _repo, _path, _ref: {
    "source_repository": "Burki24/Symcon_ModuleHelper",
    "base_branch": "dev-popup",
    "helpers": {"DateHelper": {"target": "libs/helper/DateHelper.php"}},
}
MODULE.bundle_files = lambda *_args, **_kwargs: (_ for _ in ()).throw(
    AssertionError("Branch mismatch must stop before building the helper bundle.")
)
try:
    MODULE.sync(
        REPOSITORY,
        BASE_BRANCH,
        "DateHelper",
        {"version": "1.0.2", "sha256": "unused"},
        {"helpers": {}},
        False,
        "SQUASH",
    )
except RuntimeError as error:
    if "does not match centrally configured target branch 'dev'" not in str(error):
        raise
else:
    raise SystemExit("Helper sync did not stop on a conflicting consumer base_branch.")
finally:
    MODULE.load_json_content = original_load_json_content
    MODULE.bundle_files = original_bundle_files


parallel_calls: dict[str, object] = {}
original_content = MODULE.content
original_create_sync_commit = MODULE.create_sync_commit
original_open_pull_request = MODULE.open_pull_request
MODULE.load_json_content = lambda _repo, path, _ref: (
    {
        "source_repository": "Burki24/Symcon_ModuleHelper",
        "base_branch": PARALLEL_BRANCH,
        "readme_language": "en",
        "helpers": {"DateHelper": {"target": "libs/helper/DateHelper.php"}},
    }
    if path == ".helper-sync.json"
    else {"schema": 1, "source_repository": "Burki24/Symcon_ModuleHelper", "helpers": {}}
)
MODULE.bundle_files = lambda *_args, **_kwargs: (
    {"libs/helper/DateHelper.php": b"helper"},
    {"DateHelper": {"version": "1.0.2", "sha256": "digest", "path": "libs/helper/DateHelper.php"}},
)
MODULE.content = lambda *_args, **_kwargs: None

def fake_create_sync_commit(
    repo: str,
    base_branch: str,
    branch: str,
    files: dict[str, bytes],
    message: str,
) -> str:
    parallel_calls["commit"] = (repo, base_branch, branch, set(files), message)
    return EXPECTED_HEAD_SHA


def fake_open_pull_request(
    repo: str,
    base_branch: str,
    branch: str,
    helper: str,
    version: str,
    digest: str,
) -> dict[str, object]:
    parallel_calls["pr"] = (repo, base_branch, branch, helper, version, digest)
    return pull_request()

MODULE.create_sync_commit = fake_create_sync_commit
MODULE.open_pull_request = fake_open_pull_request
try:
    MODULE.sync(
        REPOSITORY,
        PARALLEL_BRANCH,
        "DateHelper",
        {"version": "1.0.2", "sha256": "digest"},
        {"helpers": {}},
        False,
        "SQUASH",
    )
finally:
    MODULE.load_json_content = original_load_json_content
    MODULE.bundle_files = original_bundle_files
    MODULE.content = original_content
    MODULE.create_sync_commit = original_create_sync_commit
    MODULE.open_pull_request = original_open_pull_request

commit_call = parallel_calls.get("commit")
pr_call = parallel_calls.get("pr")
if not isinstance(commit_call, tuple) or commit_call[2] != PARALLEL_HEAD_BRANCH:
    raise SystemExit(f"Parallel helper commit used an unexpected branch: {commit_call}")
if not isinstance(pr_call, tuple) or pr_call[1:3] != (PARALLEL_BRANCH, PARALLEL_HEAD_BRANCH):
    raise SystemExit(f"Parallel helper PR used unexpected base/head branches: {pr_call}")


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

unstable_attempts = 0
api_calls.clear()


def fake_unstable_graphql(query: str, variables: dict[str, object]) -> dict[str, object]:
    global unstable_attempts
    if "query($pullRequestId" in query:
        return {"node": {"autoMergeRequest": None}}
    if "enablePullRequestAutoMerge" in query:
        unstable_attempts += 1
        if unstable_attempts < 3:
            raise RuntimeError(
                "GitHub GraphQL request failed: Pull request Pull request is in unstable status"
            )
        return {"enablePullRequestAutoMerge": {"pullRequest": {"number": 17}}}
    raise AssertionError("Unexpected GraphQL operation.")


MODULE.api = fake_api
MODULE.graphql = fake_unstable_graphql
MODULE.time.sleep = lambda _seconds: None
MODULE.enable_auto_merge(
    REPOSITORY,
    pull_request(),
    BASE_BRANCH,
    HEAD_BRANCH,
    EXPECTED_FILES,
    "SQUASH",
    EXPECTED_HEAD_SHA,
)
if unstable_attempts != 3:
    raise SystemExit(
        f"Expected unstable auto-merge status to be retried twice before success, got {unstable_attempts} attempts."
    )
if any(call[0] == "PUT" for call in api_calls):
    raise SystemExit("An unstable pull request must never fall back to a direct merge.")

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

open_calendar_consumers = [
    consumer
    for consumer in consumer_config.get("consumers", [])
    if consumer.get("repository") == "Burki24/OpenCalendar"
]
open_calendar_by_branch = {str(consumer.get("branch")): consumer for consumer in open_calendar_consumers}
if set(open_calendar_by_branch) != {"dev", "dev_9.1"}:
    raise SystemExit(f"Unexpected OpenCalendar helper-sync targets: {sorted(open_calendar_by_branch)}")
if open_calendar_by_branch["dev"].get("auto_merge", True) is not True:
    raise SystemExit("The established OpenCalendar dev helper sync must keep global auto-merge behavior.")
if open_calendar_by_branch["dev_9.1"].get("auto_merge") is not False:
    raise SystemExit("OpenCalendar dev_9.1 helper sync must require manual review during migration.")

print("Guarded helper synchronization and pull request auto-merge verified.")
