#!/usr/bin/env python3
"""Open/update helper synchronization pull requests in subscribed repositories."""

from __future__ import annotations

import base64
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path, PurePosixPath
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
API = "https://api.github.com"
TOKEN = os.environ.get("HELPER_SYNC_TOKEN", "")
SOURCE_SHA = os.environ.get("SOURCE_SHA", "")
BEFORE_SHA = os.environ.get("BEFORE_SHA", "")
EVENT_NAME = os.environ.get("EVENT_NAME", "")
REQUESTED_HELPER = os.environ.get("REQUESTED_HELPER", "all").strip() or "all"
OWNER = "Burki24"
VERSION_PATTERN = re.compile(r"@version\s+([0-9]+\.[0-9]+\.[0-9]+)")
AUTO_MERGE_METHODS = {"MERGE", "SQUASH", "REBASE"}
AUTO_MERGE_DIRECT_FALLBACK_MESSAGES = (
    "is in clean status",
    "protected branch rules not configured",
)
AUTO_MERGE_TRANSIENT_MESSAGES = (
    "is in unstable status",
)
AUTO_MERGE_ENABLE_ATTEMPTS = 24
AUTO_MERGE_RETRY_DELAY_SECONDS = 5
PULL_REQUEST_HEAD_REFRESH_ATTEMPTS = 8
DIRECT_MERGE_ATTEMPTS = 3


def api(method: str, path: str, payload: Any | None = None) -> Any:
    if not TOKEN:
        raise SystemExit("HELPER_SYNC_TOKEN is missing.")
    data = None if payload is None else json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(
        API + path,
        method=method,
        data=data,
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {TOKEN}",
            "X-GitHub-Api-Version": "2026-03-10",
            "User-Agent": "Burki24-Helper-Sync",
        },
    )
    try:
        with urllib.request.urlopen(request) as response:
            raw = response.read()
            return None if not raw else json.loads(raw.decode("utf-8"))
    except urllib.error.HTTPError as error:
        body = error.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"GitHub API {method} {path} failed: {error.code} {body}") from error


def graphql(query: str, variables: dict[str, Any]) -> dict[str, Any]:
    result = api("POST", "/graphql", {"query": query, "variables": variables})
    if not isinstance(result, dict):
        raise RuntimeError("Unexpected GitHub GraphQL response.")
    errors = result.get("errors")
    if errors:
        messages = "; ".join(str(error.get("message", error)) for error in errors)
        raise RuntimeError(f"GitHub GraphQL request failed: {messages}")
    data = result.get("data")
    if not isinstance(data, dict):
        raise RuntimeError("GitHub GraphQL response is missing data.")
    return data


def optional_api(method: str, path: str) -> Any | None:
    try:
        return api(method, path)
    except RuntimeError as error:
        if " 404 " in str(error):
            return None
        raise


def content(repo: str, path: str, ref: str) -> tuple[bytes, str] | None:
    encoded_path = urllib.parse.quote(path, safe="/")
    encoded_ref = urllib.parse.quote(ref, safe="")
    result = optional_api("GET", f"/repos/{repo}/contents/{encoded_path}?ref={encoded_ref}")
    if result is None:
        return None
    if not isinstance(result, dict) or result.get("type") != "file":
        raise RuntimeError(f"Unexpected content response for {repo}:{path}")
    raw = base64.b64decode(result.get("content", ""))
    return raw, str(result["sha"])


def create_blob(repo: str, body: bytes) -> str:
    result = api(
        "POST",
        f"/repos/{repo}/git/blobs",
        {"content": base64.b64encode(body).decode("ascii"), "encoding": "base64"},
    )
    return str(result["sha"])


def create_sync_commit(
    repo: str,
    base_branch: str,
    branch: str,
    files: dict[str, bytes],
    message: str,
) -> str:
    encoded_base = urllib.parse.quote(f"heads/{base_branch}", safe="/")
    base_ref = api("GET", f"/repos/{repo}/git/ref/{encoded_base}")
    base_sha = str(base_ref["object"]["sha"])
    base_commit = api("GET", f"/repos/{repo}/git/commits/{base_sha}")
    base_tree_sha = str(base_commit["tree"]["sha"])

    tree_entries = []
    for path, body in sorted(files.items()):
        tree_entries.append({
            "path": path,
            "mode": "100644",
            "type": "blob",
            "sha": create_blob(repo, body),
        })
    tree = api("POST", f"/repos/{repo}/git/trees", {"base_tree": base_tree_sha, "tree": tree_entries})
    commit = api(
        "POST",
        f"/repos/{repo}/git/commits",
        {"message": message, "tree": tree["sha"], "parents": [base_sha]},
    )
    commit_sha = str(commit["sha"])

    encoded_branch = urllib.parse.quote(f"heads/{branch}", safe="/")
    existing = optional_api("GET", f"/repos/{repo}/git/ref/{encoded_branch}")
    if existing is None:
        api("POST", f"/repos/{repo}/git/refs", {"ref": f"refs/heads/{branch}", "sha": commit_sha})
    else:
        api("PATCH", f"/repos/{repo}/git/refs/{encoded_branch}", {"sha": commit_sha, "force": True})
    return commit_sha


def load_json_content(repo: str, path: str, ref: str) -> dict[str, Any] | None:
    current = content(repo, path, ref)
    if current is None:
        return None
    decoded = json.loads(current[0].decode("utf-8"))
    if not isinstance(decoded, dict):
        raise RuntimeError(f"{repo}:{path} must contain a JSON object.")
    return decoded


def dependency_order(manifest: dict[str, Any], helper: str) -> list[str]:
    helpers = manifest["helpers"]
    ordered: list[str] = []
    visiting: set[str] = set()
    visited: set[str] = set()

    def visit(name: str) -> None:
        if name in visited:
            return
        if name in visiting:
            raise RuntimeError(f"Circular helper dependency involving {name}.")
        if name not in helpers:
            raise RuntimeError(f"Unknown helper dependency: {name}")
        visiting.add(name)
        for dependency in helpers[name].get("dependencies", []):
            visit(str(dependency))
        visiting.remove(name)
        visited.add(name)
        ordered.append(name)

    visit(helper)
    return ordered


def expand_dependents(manifest: dict[str, Any], names: set[str]) -> set[str]:
    helpers = manifest["helpers"]
    expanded = set(names)
    changed = True
    while changed:
        changed = False
        for helper, meta in helpers.items():
            dependencies = {str(item) for item in meta.get("dependencies", [])}
            if dependencies & expanded and helper not in expanded:
                expanded.add(helper)
                changed = True
    return expanded


def changed_helpers(manifest: dict[str, Any]) -> list[str]:
    helpers = manifest["helpers"]
    if EVENT_NAME == "workflow_dispatch":
        if REQUESTED_HELPER == "all":
            return sorted(helpers)
        if REQUESTED_HELPER not in helpers:
            raise SystemExit(f"Unknown helper requested: {REQUESTED_HELPER}")
        return sorted(expand_dependents(manifest, {REQUESTED_HELPER}))

    if not BEFORE_SHA or set(BEFORE_SHA) == {"0"}:
        return sorted(helpers)

    import subprocess
    sync_paths = [
        "src",
        "manifest.json",
        ".github/helper-consumers.json",
        ".github/scripts/sync_helper_consumers.py",
        ".github/workflows/helper-sync.yml",
    ]
    result = subprocess.run(
        ["git", "diff", "--name-only", BEFORE_SHA, SOURCE_SHA, "--", *sync_paths],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    paths = {line.strip() for line in result.stdout.splitlines() if line.strip()}
    names = {Path(path).stem for path in paths if path.startswith("src/") and path.endswith("Helper.php")}
    if paths & {
        ".github/helper-consumers.json",
        ".github/scripts/sync_helper_consumers.py",
        ".github/workflows/helper-sync.yml",
    }:
        names.update(helpers)
    for path in paths:
        match = re.fullmatch(r"src/translations/([A-Za-z0-9_]+)\.json", path)
        if match:
            names.add(match.group(1))

    if "manifest.json" in paths:
        try:
            previous = subprocess.run(
                ["git", "show", f"{BEFORE_SHA}:manifest.json"],
                cwd=ROOT,
                check=True,
                capture_output=True,
                text=True,
            )
            old = json.loads(previous.stdout).get("helpers", {})
            for name, meta in helpers.items():
                if old.get(name) != meta:
                    names.add(name)
        except Exception:
            names.update(helpers)

    names = expand_dependents(manifest, names)
    return sorted(name for name in names if name in helpers)


def readme(manifest: dict[str, Any], language: str) -> bytes:
    helpers = manifest.get("helpers", {})
    if language.lower().startswith("de"):
        lines = [
            "# Vendored Symcon Module Helpers",
            "",
            "Die Dateien in diesem Verzeichnis stammen aus dem gemeinsamen Repository",
            "[`Burki24/Symcon_ModuleHelper`](https://github.com/Burki24/Symcon_ModuleHelper).",
            "",
            "| Datei | Upstream-Version | SHA-256 |",
            "| --- | --- | --- |",
        ]
        footer = "Die Kopien werden bewusst mit der Library ausgeliefert; zur Laufzeit besteht keine externe Abhängigkeit."
    else:
        lines = [
            "# Vendored Symcon Module Helpers",
            "",
            "The files in this directory are vendored from",
            "[`Burki24/Symcon_ModuleHelper`](https://github.com/Burki24/Symcon_ModuleHelper).",
            "",
            "| File | Upstream version | SHA-256 |",
            "| --- | --- | --- |",
        ]
        footer = "The copies are shipped with the library deliberately; there is no external runtime dependency."

    documented_paths: set[str] = set()
    for name, meta in sorted(helpers.items()):
        rows = [meta]
        rows.extend(
            dependency
            for dependency in meta.get("dependencies", [])
            if isinstance(dependency, dict)
        )
        for row in rows:
            target = str(row.get("path", f"libs/helper/{name}.php"))
            if target in documented_paths:
                continue
            documented_paths.add(target)
            lines.append(f"| `{Path(target).name}` | {row['version']} | `{row['sha256']}` |")
    lines.extend(["", footer, ""])
    return "\n".join(lines).encode("utf-8")


def open_pull_request(
    repo: str,
    base_branch: str,
    branch: str,
    helper: str,
    version: str,
    digest: str,
) -> dict[str, Any]:
    head = urllib.parse.quote(f"{OWNER}:{branch}", safe="")
    base = urllib.parse.quote(base_branch, safe="")
    pulls = api("GET", f"/repos/{repo}/pulls?state=open&head={head}&base={base}")
    title = f"CHORE: Update {helper} to v{version}"
    body = (
        f"Automated vendor sync from `Burki24/Symcon_ModuleHelper`.\n\n"
        f"- Helper: `{helper}`\n"
        f"- Version: `{version}`\n"
        f"- SHA-256: `{digest}`\n"
        f"- Source commit: `{SOURCE_SHA}`\n\n"
        "This pull request is eligible for automatic merging only when it contains "
        "the generated helper bundle exclusively and all required repository checks pass."
    )
    if pulls:
        number = int(pulls[0]["number"])
        api("PATCH", f"/repos/{repo}/pulls/{number}", {"title": title, "body": body})
        result = api("GET", f"/repos/{repo}/pulls/{number}")
        print(f"Updated PR #{number} in {repo} for {helper}.")
        return result
    result = api(
        "POST",
        f"/repos/{repo}/pulls",
        {"title": title, "head": branch, "base": base_branch, "body": body},
    )
    print(f"Created PR #{result['number']} in {repo} for {helper}.")
    return result


def pull_request_files(repo: str, number: int) -> set[str]:
    files: set[str] = set()
    page = 1
    while True:
        result = api("GET", f"/repos/{repo}/pulls/{number}/files?per_page=100&page={page}")
        if not isinstance(result, list):
            raise RuntimeError(f"Unexpected pull request files response for {repo}#{number}.")
        files.update(str(item["filename"]) for item in result)
        if len(result) < 100:
            break
        page += 1
    return files


def validate_auto_merge_candidate(
    pull_request: dict[str, Any],
    repo: str,
    base_branch: str,
    branch: str,
    expected_files: set[str],
    actual_files: set[str],
) -> str:
    number = int(pull_request.get("number", 0))
    if pull_request.get("draft") is True:
        raise RuntimeError(f"Refusing auto-merge for draft PR {repo}#{number}.")
    if str(pull_request.get("base", {}).get("ref", "")) != base_branch:
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: unexpected base branch.")
    if str(pull_request.get("head", {}).get("ref", "")) != branch:
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: unexpected head branch.")
    if not branch.startswith("helper-sync/"):
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: invalid helper-sync branch.")
    head_repo = str(pull_request.get("head", {}).get("repo", {}).get("full_name", ""))
    if head_repo != repo:
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: head repository differs.")
    author_type = str(pull_request.get("user", {}).get("type", ""))
    if author_type != "Bot":
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: PR author is not a GitHub App bot.")
    title = str(pull_request.get("title", ""))
    if not title.startswith("CHORE: Update "):
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: unexpected PR title.")
    if not actual_files:
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: pull request has no changed files.")
    unexpected = actual_files - expected_files
    if unexpected:
        raise RuntimeError(
            f"Refusing auto-merge for {repo}#{number}: unexpected files: {', '.join(sorted(unexpected))}"
        )
    node_id = str(pull_request.get("node_id", ""))
    if not node_id:
        raise RuntimeError(f"Refusing auto-merge for {repo}#{number}: missing GraphQL node ID.")
    return node_id


def refresh_pull_request_head(
    repo: str,
    number: int,
    expected_head_sha: str,
) -> dict[str, Any]:
    if not expected_head_sha:
        raise RuntimeError(f"Unable to refresh {repo}#{number}: expected head SHA is missing.")

    observed_head_sha = ""
    for attempt in range(PULL_REQUEST_HEAD_REFRESH_ATTEMPTS):
        pull_request = api("GET", f"/repos/{repo}/pulls/{number}")
        if not isinstance(pull_request, dict):
            raise RuntimeError(f"Unexpected pull request response for {repo}#{number}.")
        head = pull_request.get("head")
        observed_head_sha = str(head.get("sha", "")) if isinstance(head, dict) else ""
        if observed_head_sha == expected_head_sha:
            return pull_request
        if attempt + 1 < PULL_REQUEST_HEAD_REFRESH_ATTEMPTS:
            time.sleep(1)

    raise RuntimeError(
        f"Timed out waiting for {repo}#{number} to expose expected head SHA "
        f"{expected_head_sha}; last observed {observed_head_sha or 'none'}."
    )


def merge_validated_pull_request(
    repo: str,
    pull_request: dict[str, Any],
    merge_method: str,
    expected_head_sha: str,
) -> None:
    number = int(pull_request["number"])
    head = pull_request.get("head")
    head_sha = str(head.get("sha", "")) if isinstance(head, dict) else ""
    if head_sha != expected_head_sha:
        raise RuntimeError(f"Refusing direct merge for {repo}#{number}: unexpected head SHA.")

    result: Any = None
    for attempt in range(DIRECT_MERGE_ATTEMPTS):
        try:
            result = api(
                "PUT",
                f"/repos/{repo}/pulls/{number}/merge",
                {"sha": expected_head_sha, "merge_method": merge_method.lower()},
            )
            break
        except RuntimeError as error:
            message = str(error).lower()
            retryable = " 409 " in message and "head branch was modified" in message
            if not retryable or attempt + 1 >= DIRECT_MERGE_ATTEMPTS:
                raise
            pull_request = refresh_pull_request_head(repo, number, expected_head_sha)
            time.sleep(1)

    if not isinstance(result, dict) or result.get("merged") is not True:
        message = (
            str(result.get("message", "unknown response"))
            if isinstance(result, dict)
            else "invalid response"
        )
        raise RuntimeError(f"Direct merge failed for {repo}#{number}: {message}")

    print(f"Directly merged validated helper PR {repo}#{number} ({merge_method}).")


def enable_auto_merge(
    repo: str,
    pull_request: dict[str, Any],
    base_branch: str,
    branch: str,
    expected_files: set[str],
    merge_method: str,
    expected_head_sha: str,
) -> None:
    merge_method = merge_method.upper()
    if merge_method not in AUTO_MERGE_METHODS:
        raise RuntimeError(f"Unsupported auto-merge method for {repo}: {merge_method}")

    repository = api("GET", f"/repos/{repo}")
    if not bool(repository.get("allow_auto_merge")):
        raise RuntimeError(
            f"Auto-merge is not enabled in {repo}. Enable Settings > General > Allow auto-merge."
        )
    method_flag = {
        "MERGE": "allow_merge_commit",
        "SQUASH": "allow_squash_merge",
        "REBASE": "allow_rebase_merge",
    }[merge_method]
    if not bool(repository.get(method_flag)):
        raise RuntimeError(f"{repo} does not allow the configured {merge_method.lower()} merge method.")

    number = int(pull_request["number"])
    pull_request = refresh_pull_request_head(repo, number, expected_head_sha)
    actual_files = pull_request_files(repo, number)
    node_id = validate_auto_merge_candidate(
        pull_request, repo, base_branch, branch, expected_files, actual_files
    )

    state_query = """
        query($pullRequestId: ID!) {
          node(id: $pullRequestId) {
            ... on PullRequest {
              autoMergeRequest { mergeMethod }
            }
          }
        }
    """
    state = graphql(state_query, {"pullRequestId": node_id})
    node = state.get("node")
    current = node.get("autoMergeRequest") if isinstance(node, dict) else None
    if isinstance(current, dict) and str(current.get("mergeMethod", "")) == merge_method:
        print(f"Auto-merge already enabled for {repo}#{number} ({merge_method}).")
        return

    mutation = """
        mutation($pullRequestId: ID!, $mergeMethod: PullRequestMergeMethod!) {
          enablePullRequestAutoMerge(input: {
            pullRequestId: $pullRequestId,
            mergeMethod: $mergeMethod
          }) {
            pullRequest { number }
          }
        }
    """
    for attempt in range(AUTO_MERGE_ENABLE_ATTEMPTS):
        try:
            graphql(mutation, {"pullRequestId": node_id, "mergeMethod": merge_method})
        except RuntimeError as error:
            message = str(error).lower()
            if any(fragment in message for fragment in AUTO_MERGE_DIRECT_FALLBACK_MESSAGES):
                merge_validated_pull_request(repo, pull_request, merge_method, expected_head_sha)
                return
            retryable = any(fragment in message for fragment in AUTO_MERGE_TRANSIENT_MESSAGES)
            if not retryable or attempt + 1 >= AUTO_MERGE_ENABLE_ATTEMPTS:
                raise
            print(
                f"Waiting to enable auto-merge for {repo}#{number}: "
                f"GitHub still reports an unstable pull request status "
                f"({attempt + 1}/{AUTO_MERGE_ENABLE_ATTEMPTS})."
            )
            time.sleep(AUTO_MERGE_RETRY_DELAY_SECONDS)
            pull_request = refresh_pull_request_head(repo, number, expected_head_sha)
            actual_files = pull_request_files(repo, number)
            node_id = validate_auto_merge_candidate(
                pull_request, repo, base_branch, branch, expected_files, actual_files
            )
            continue
        print(f"Enabled {merge_method} auto-merge for {repo}#{number}.")
        return


def validate_source_helper(name: str, meta: dict[str, Any]) -> tuple[bytes, str]:
    source_path = ROOT / str(meta["file"])
    raw = source_path.read_bytes()
    digest = hashlib.sha256(raw).hexdigest()
    if digest != meta["sha256"]:
        raise RuntimeError(f"Local source hash for {name} does not match manifest.")
    version_match = VERSION_PATTERN.search(raw.decode("utf-8"))
    if version_match is None or version_match.group(1) != meta["version"]:
        raise RuntimeError(f"Local source version for {name} does not match manifest.")
    return raw, digest


def bundle_files(
    manifest: dict[str, Any],
    helper: str,
    target_path: str,
) -> tuple[dict[str, bytes], dict[str, dict[str, Any]]]:
    files: dict[str, bytes] = {}
    target_directory = PurePosixPath(target_path).parent
    primary_entry: dict[str, Any] | None = None
    dependency_entries: list[dict[str, Any]] = []

    for name in dependency_order(manifest, helper):
        meta = manifest["helpers"][name]
        raw, digest = validate_source_helper(name, meta)
        helper_target = (
            PurePosixPath(target_path)
            if name == helper
            else target_directory / PurePosixPath(str(meta["file"])).name
        )
        files[str(helper_target)] = raw
        entry: dict[str, Any] = {
            "version": meta["version"],
            "sha256": digest,
            "path": str(helper_target),
            "source_sha": SOURCE_SHA,
        }
        assets = []
        for asset in meta.get("assets", []):
            source_asset = ROOT / str(asset["file"])
            asset_raw = source_asset.read_bytes()
            asset_digest = hashlib.sha256(asset_raw).hexdigest()
            if asset_digest != asset["sha256"]:
                raise RuntimeError(f"Local asset hash for {name}:{asset['file']} does not match manifest.")
            asset_target = target_directory / PurePosixPath(str(asset["target"]))
            files[str(asset_target)] = asset_raw
            assets.append({
                "path": str(asset_target),
                "sha256": asset_digest,
            })
        if assets:
            entry["assets"] = assets

        if name == helper:
            primary_entry = entry
        else:
            dependency_entries.append({"name": name, **entry})

    if primary_entry is None:
        raise RuntimeError(f"Unable to build helper bundle for {helper}.")
    if dependency_entries:
        primary_entry["dependencies"] = dependency_entries

    return files, {helper: primary_entry}


def sync(
    repo: str,
    base_branch: str,
    helper: str,
    source_meta: dict[str, Any],
    manifest: dict[str, Any],
    auto_merge: bool,
    merge_method: str,
) -> None:
    config = load_json_content(repo, ".helper-sync.json", base_branch)
    if config is None:
        print(f"Skipping {repo}: no .helper-sync.json on {base_branch}.")
        return
    if config.get("source_repository") != "Burki24/Symcon_ModuleHelper":
        print(f"Skipping {repo}: unexpected source repository.")
        return
    subscriptions = config.get("helpers", {})
    if helper not in subscriptions:
        print(f"Skipping {repo}: {helper} is not subscribed.")
        return

    target_path = str(subscriptions[helper]["target"])
    source_files, target_entries = bundle_files(manifest, helper, target_path)
    up_to_date = True
    for path, raw in source_files.items():
        current = content(repo, path, base_branch)
        if current is None or hashlib.sha256(current[0]).hexdigest() != hashlib.sha256(raw).hexdigest():
            up_to_date = False
            break
    if up_to_date:
        print(f"{repo}: {helper} bundle already matches v{source_meta['version']}.")
        return

    branch = f"helper-sync/{re.sub(r'(?<!^)(?=[A-Z])', '-', helper).lower()}-v{source_meta['version']}"
    target_manifest = load_json_content(repo, "libs/helper/manifest.json", base_branch) or {
        "schema": 1,
        "source_repository": "Burki24/Symcon_ModuleHelper",
        "helpers": {},
    }
    target_helpers = target_manifest.setdefault("helpers", {})
    for dependency in target_entries[helper].get("dependencies", []):
        dependency_name = str(dependency.get("name", ""))
        if dependency_name and dependency_name not in subscriptions:
            target_helpers.pop(dependency_name, None)
    target_helpers.update(target_entries)

    files = dict(source_files)
    files["libs/helper/manifest.json"] = (
        json.dumps(target_manifest, indent=4, ensure_ascii=False) + "\n"
    ).encode("utf-8")
    files["libs/helper/README.md"] = readme(target_manifest, str(config.get("readme_language", "en")))

    commit_prefix = f"CHORE: Update {helper} to v{source_meta['version']}"
    expected_head_sha = create_sync_commit(repo, base_branch, branch, files, commit_prefix)
    pull_request = open_pull_request(
        repo, base_branch, branch, helper, source_meta["version"], source_meta["sha256"]
    )
    if auto_merge:
        enable_auto_merge(
            repo,
            pull_request,
            base_branch,
            branch,
            set(files),
            merge_method,
            expected_head_sha,
        )


def main() -> None:
    manifest = json.loads((ROOT / "manifest.json").read_text(encoding="utf-8"))
    consumer_config = json.loads(
        (ROOT / ".github/helper-consumers.json").read_text(encoding="utf-8")
    )
    consumers = consumer_config["consumers"]
    auto_merge_defaults = consumer_config.get("auto_merge", {})
    helpers = changed_helpers(manifest)
    if not helpers:
        print("No helper changes detected.")
        return
    print("Helpers selected for synchronization:", ", ".join(helpers))
    for helper in helpers:
        source_meta = manifest["helpers"][helper]
        for consumer in consumers:
            auto_merge = bool(consumer.get("auto_merge", auto_merge_defaults.get("enabled", False)))
            merge_method = str(
                consumer.get("merge_method", auto_merge_defaults.get("merge_method", "SQUASH"))
            )
            sync(
                str(consumer["repository"]),
                str(consumer["branch"]),
                helper,
                source_meta,
                manifest,
                auto_merge,
                merge_method,
            )


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"Helper sync failed: {error}", file=sys.stderr)
        raise
