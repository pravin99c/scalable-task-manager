"""Offline lockfile checker regression tests without npm or network."""
import json
import pathlib
import sys
import tempfile
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from validate_npm_lockfile import audit_files

class LockfileValidationTest(unittest.TestCase):
    def evaluate(self, manifest, lock):
        with tempfile.TemporaryDirectory() as d:
            p = pathlib.Path(d)
            (p / "package.json").write_text(json.dumps(manifest))
            (p / "package-lock.json").write_text(json.dumps(lock))
            return audit_files(p / "package.json", p / "package-lock.json")

    def base(self):
        manifest = {"dependencies": {"example": "^1.0.0"}}
        lock = {"lockfileVersion": 3, "packages": {
            "": {"dependencies": {"example": "^1.0.0"}},
            "node_modules/example": {
                "resolved": "https://registry.npmjs.org/example/-/example-1.0.0.tgz",
                "integrity": "sha512-"+("A"*60)
            }
        }}
        return manifest, lock

    def test_valid_synthetic_lock_passes(self):
        self.assertEqual(self.evaluate(*self.base()), [])

    def test_changed_manifest_detected(self):
        manifest, lock = self.base()
        manifest["dependencies"]["example"] = "^2.0.0"
        self.assertIn("Root dependencies differs from package.json", self.evaluate(manifest, lock))

    def test_missing_integrity_rejected(self):
        manifest, lock = self.base()
        del lock["packages"]["node_modules/example"]["integrity"]
        self.assertIn("Package missing resolved origin or hash", self.evaluate(manifest, lock))

    def test_insecure_or_credential_url_rejected(self):
        for origin in ("http://registry.npmjs.org/example.tgz",
                       "https://token:secret@registry.npmjs.org/example.tgz"):
            with self.subTest(origin=origin):
                manifest, lock = self.base()
                lock["packages"]["node_modules/example"]["resolved"] = origin
                self.assertIn("Package uses an unapproved tarball URL shape",
                              self.evaluate(manifest, lock))

if __name__ == "__main__":
    unittest.main()
