"""No-network tests for static release artifact checks."""
import json
import pathlib
import sys
import tempfile
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from release_quality_gate import REQUIRED_FILES, evaluate

class ReleaseQualityGateTest(unittest.TestCase):
    def test_empty_workspace_has_blockers(self):
        with tempfile.TemporaryDirectory() as root:
            checks = evaluate(pathlib.Path(root))
            self.assertEqual(len(checks), len(REQUIRED_FILES))
            self.assertTrue(all(not v for v in checks.values()))

    def test_valid_synthetic_artifacts_pass(self):
        with tempfile.TemporaryDirectory() as root:
            root = pathlib.Path(root)
            for name in REQUIRED_FILES:
                target = root / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text("synthetic", encoding="utf-8")
            (root / "package-lock.json").write_text(json.dumps({
                "name": "synthetic", "lockfileVersion": 3, "packages": {"": {}}
            }), encoding="utf-8")
            self.assertTrue(all(evaluate(root).values()))

    def test_invalid_npm_manifest_blocks_release(self):
        with tempfile.TemporaryDirectory() as root:
            root = pathlib.Path(root)
            (root / "package-lock.json").write_text("{malformed", encoding="utf-8")
            self.assertFalse(evaluate(root)["npm lockfile has expected shape"])

    def test_malformed_lockfile_version_blocks_release(self):
        with tempfile.TemporaryDirectory() as root:
            root = pathlib.Path(root)
            (root / "package-lock.json").write_text(json.dumps({
                "lockfileVersion": 1, "packages": {}
            }), encoding="utf-8")
            self.assertFalse(evaluate(root)["npm lockfile has expected shape"])

if __name__ == "__main__":
    unittest.main()
