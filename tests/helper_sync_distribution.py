#!/usr/bin/env python3
"""Exercise distribution failure isolation without contacting GitHub."""

import contextlib
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "helper_distribution", ROOT / ".github/scripts/sync_helper_consumers.py"
)
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


class DistributionTests(unittest.TestCase):
    def run_distribution(self, fail_first=False):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / ".github").mkdir()
            (root / "manifest.json").write_text(
                json.dumps({"helpers": {"DateHelper": {"version": "1.0.0"}}}),
                encoding="utf-8",
            )
            (root / ".github/helper-consumers.json").write_text(json.dumps({
                "consumers": [
                    {"repository": "Burki24/First", "branch": "dev"},
                    {"repository": "Burki24/First", "branch": "dev_9.1"},
                    {"repository": "Burki24/Second", "branch": "dev"},
                ],
            }), encoding="utf-8")
            summary = root / "summary.md"
            calls = []

            def synchronize(repo, branch, *_args):
                calls.append((repo, branch))
                if fail_first and len(calls) == 1:
                    raise RuntimeError("Pull request is in unstable status")

            with patch.object(MODULE, "ROOT", root), patch.object(
                MODULE, "changed_helpers", return_value=["DateHelper"]
            ), patch.object(MODULE, "sync", side_effect=synchronize), patch.dict(
                os.environ, {"GITHUB_STEP_SUMMARY": str(summary)}
            ), contextlib.redirect_stdout(io.StringIO()), contextlib.redirect_stderr(io.StringIO()):
                if fail_first:
                    with self.assertRaisesRegex(RuntimeError, "after processing all consumers"):
                        MODULE.main()
                else:
                    MODULE.main()
            return calls, summary.read_text(encoding="utf-8")

    def test_failure_does_not_block_other_branches_or_repositories(self):
        calls, summary = self.run_distribution(fail_first=True)
        self.assertEqual(calls, [
            ("Burki24/First", "dev"),
            ("Burki24/First", "dev_9.1"),
            ("Burki24/Second", "dev"),
        ])
        self.assertIn("Burki24/First@dev | Failed", summary)
        self.assertIn("Burki24/Second@dev | Processed", summary)

    def test_processed_does_not_claim_merged(self):
        calls, summary = self.run_distribution()
        self.assertEqual(len(calls), 3)
        self.assertNotIn("Failed", summary)
        self.assertIn("merge may still be pending", summary)


if __name__ == "__main__":
    unittest.main()
