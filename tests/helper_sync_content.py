#!/usr/bin/env python3
"""Read large helper assets correctly and avoid commits without tree changes."""

import base64
import contextlib
import hashlib
import importlib.util
import io
from pathlib import Path
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "helper_content", ROOT / ".github/scripts/sync_helper_consumers.py"
)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)
REPO = "Burki24/Example"
ASSET = "libs/helper/maps/vendor/maplibre-gl.js"


def blob_sha(raw):
    return hashlib.sha1(b"blob " + str(len(raw)).encode("ascii") + b"\0" + raw).hexdigest()


class ContentTests(unittest.TestCase):
    def test_actual_large_map_asset_uses_blob_fallback(self):
        raw = (ROOT / "src/maps/vendor/maplibre-gl.js").read_bytes()
        self.assertGreater(len(raw), 1024 * 1024)
        sha = blob_sha(raw)
        metadata = {"type": "file", "encoding": "none", "content": "", "sha": sha, "size": len(raw)}
        blob = {"encoding": "base64", "content": base64.encodebytes(raw).decode("ascii"), "sha": sha}
        with patch.object(MODULE, "api", side_effect=[metadata, blob]) as api:
            self.assertEqual(MODULE.content(REPO, ASSET, "dev"), (raw, sha))
        self.assertEqual(api.call_args_list[1].args, ("GET", f"/repos/{REPO}/git/blobs/{sha}"))

    def test_empty_file_is_distinct_from_omitted_large_content(self):
        metadata = {"type": "file", "encoding": "base64", "content": "", "sha": blob_sha(b""), "size": 0}
        with patch.object(MODULE, "api", return_value=metadata) as api:
            self.assertEqual(MODULE.content(REPO, "empty", "dev"), (b"", metadata["sha"]))
        self.assertEqual(api.call_count, 1)

    def test_inline_small_binary_content_remains_byte_exact(self):
        raw = b"\x00\xff\xfe\x80\r\nexample"
        metadata = {"type": "file", "encoding": "base64", "content": base64.encodebytes(raw).decode("ascii"), "sha": blob_sha(raw), "size": len(raw)}
        with patch.object(MODULE, "api", return_value=metadata):
            self.assertEqual(MODULE.content(REPO, "small-binary", "dev"), (raw, metadata["sha"]))

    def test_missing_file_stays_missing(self):
        with patch.object(MODULE, "optional_api", return_value=None):
            self.assertIsNone(MODULE.content(REPO, "absent", "dev"))

    def test_unknown_encoding_and_invalid_blob_are_rejected(self):
        sha = blob_sha(b"example")
        metadata = {"type": "file", "encoding": "none", "content": "", "sha": sha, "size": 7}
        for blob in [
            {"encoding": "none", "content": "", "sha": sha},
            {"encoding": "base64", "content": "not base64!", "sha": sha},
            {"encoding": "base64", "content": base64.b64encode(b"corrupt").decode("ascii"), "sha": sha},
            {"encoding": "base64", "content": base64.b64encode(b"example").decode("ascii"), "sha": "other"},
        ]:
            with self.subTest(blob=blob), patch.object(MODULE, "api", side_effect=[metadata, blob]):
                with self.assertRaises(RuntimeError):
                    MODULE.content(REPO, ASSET, "dev")
        with patch.object(MODULE, "api", return_value={**metadata, "encoding": "unsupported"}):
            with self.assertRaises(RuntimeError):
                MODULE.content(REPO, ASSET, "dev")

    def test_identical_git_tree_never_creates_commit_or_branch(self):
        calls = []

        def api(method, path, payload=None):
            calls.append((method, path, payload))
            if method == "GET" and path.endswith("/git/ref/heads/dev"):
                return {"object": {"sha": "base-commit"}}
            if method == "GET" and path.endswith("/git/commits/base-commit"):
                return {"tree": {"sha": "same-tree"}}
            if method == "POST" and path.endswith("/git/blobs"):
                return {"sha": "asset-blob"}
            if method == "POST" and path.endswith("/git/trees"):
                return {"sha": "same-tree"}
            self.fail(f"No-op tree created a commit or changed a ref: {method} {path}")

        with patch.object(MODULE, "api", side_effect=api):
            result = MODULE.create_sync_commit(REPO, "dev", "helper-sync/example", {ASSET: b"same"}, "Example")
        self.assertIsNone(result)
        self.assertEqual(len(calls), 4)

    def test_noop_tree_result_never_opens_or_merges_a_pull_request(self):
        config = {"source_repository": "Burki24/Symcon_ModuleHelper", "helpers": {"ExampleHelper": {"target": "libs/helper/ExampleHelper.php"}}}
        entry = {"version": "1.0.0", "sha256": "example", "path": "libs/helper/ExampleHelper.php"}
        with patch.object(MODULE, "load_json_content", side_effect=[config, None]), patch.object(
            MODULE, "combined_consumer_bundle_files", return_value=({"libs/helper/ExampleHelper.php": b"example"}, {"ExampleHelper": entry})
        ), patch.object(MODULE, "content", return_value=None), patch.object(
            MODULE, "create_sync_commit", return_value=None
        ), patch.object(MODULE, "open_pull_request") as open_pr, patch.object(
            MODULE, "enable_auto_merge"
        ) as auto_merge, contextlib.redirect_stdout(io.StringIO()) as output:
            MODULE.sync(REPO, "dev", "ExampleHelper", entry, {}, True, "SQUASH")
        open_pr.assert_not_called()
        auto_merge.assert_not_called()
        self.assertIn("no tree changes", output.getvalue())

    def test_existing_empty_pull_requests_still_fail_safety_validation(self):
        candidate = {
            "number": 7, "base": {"ref": "dev"},
            "head": {"ref": "helper-sync/example", "repo": {"full_name": REPO}},
            "user": {"type": "Bot"}, "title": "CHORE: Update ExampleHelper",
            "node_id": "PR_example",
        }
        with self.assertRaisesRegex(RuntimeError, "no changed files"):
            MODULE.validate_auto_merge_candidate(candidate, REPO, "dev", "helper-sync/example", {ASSET}, set())


if __name__ == "__main__":
    unittest.main()
