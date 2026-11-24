"""Offline tests for the release evidence gate."""
import copy
import json
import pathlib
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from release_evidence_gate import evaluate

VALID = {
    "release_commit": "a" * 40,
    "previous_release_commit": "b" * 40,
    "artifact_sha256": "c" * 64,
    "staging": {"status": "passed", "checked_at": "2026-11-24T04:00:00Z",
                "origin": "https://staging.example.test"},
    "regression": {"status": "passed", "checked_at": "2026-11-24T04:10:00+00:00",
                   "ci_run_url": "https://github.com/example/repo/actions/runs/123"},
    "backup": {"status": "verified", "checked_at": "2026-11-24T04:15:00Z",
               "manifest_sha256": "d" * 64},
    "restore_drill": {"status": "passed", "isolated_environment": True,
                      "completed_at": "2026-11-24T04:30:00Z", "rpo_seconds": 300,
                      "rto_seconds": 900, "evidence_reference": "restricted://drills/2026-11-24"},
    "rollback": {"status": "tested", "tested_at": "2026-11-24T04:45:00Z",
                 "method": "Repoint application to previous immutable artifact",
                 "database_strategy": "forward_fix", "production_database_touched": False},
    "approval": {"status": "approved", "approver": "release-owner",
                 "approved_at": "2026-11-24T05:00:00Z"},
}


class ReleaseEvidenceGateTests(unittest.TestCase):
    def test_complete_evidence_passes(self):
        results = evaluate(VALID)
        self.assertTrue(results)
        self.assertTrue(all(results.values()))

    def test_missing_evidence_blocks_closed(self):
        self.assertFalse(all(evaluate({}).values()))

    def test_unisolated_restore_and_production_db_touch_block(self):
        evidence = copy.deepcopy(VALID)
        evidence["restore_drill"]["isolated_environment"] = False
        evidence["rollback"]["production_database_touched"] = True
        results = evaluate(evidence)
        self.assertFalse(results["restore drill passed in isolated environment with measured RPO/RTO"])
        self.assertFalse(results["application rollback was rehearsed with safe database strategy"])

    def test_missing_approval_and_failed_ci_block(self):
        evidence = copy.deepcopy(VALID)
        evidence["approval"]["status"] = "pending"
        evidence["regression"]["status"] = "failed"
        results = evaluate(evidence)
        self.assertFalse(results["release has explicit named human approval"])
        self.assertFalse(results["CI regression run is linked and passed"])

    def test_invalid_urls_checksums_and_unzoned_timestamps_block(self):
        evidence = copy.deepcopy(VALID)
        evidence["staging"]["origin"] = "http://staging.example.test"
        evidence["backup"]["manifest_sha256"] = "bad"
        evidence["restore_drill"]["completed_at"] = "2026-11-24T04:30:00"
        self.assertFalse(all(evaluate(evidence).values()))

    def test_same_release_and_previous_commit_block(self):
        evidence = copy.deepcopy(VALID)
        evidence["previous_release_commit"] = evidence["release_commit"]
        self.assertFalse(evaluate(evidence)["previous release commit is a distinct full SHA"])

    def test_cli_does_not_accept_partial_file_as_ready(self):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory) / "evidence.json"
            path.write_text(json.dumps({"release_commit": "a" * 40}), encoding="utf-8")
            result = subprocess.run([sys.executable,
                str(pathlib.Path(__file__).resolve().parents[2] / "scripts" / "release_evidence_gate.py"),
                "--evidence", str(path)], capture_output=True, text=True, check=False)
            self.assertEqual(result.returncode, 1)
            self.assertIn("does not deploy", result.stdout)


if __name__ == "__main__":
    unittest.main()
