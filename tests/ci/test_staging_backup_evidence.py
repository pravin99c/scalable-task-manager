"""Offline tests for staging smoke and backup integrity tools."""
import hashlib
import json
import pathlib
import sys
import tempfile
import unittest
from unittest.mock import Mock

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from staging_smoke import check, validate_base_url
from verify_backup_manifest import verify


class FakeResponse:
    status = 200
    def __enter__(self): return self
    def __exit__(self, *args): return False
    def getcode(self): return self.status


class StagingSmokeTests(unittest.TestCase):
    def test_https_origin_gets_only_health_path(self):
        response = FakeResponse()
        opener = Mock(return_value=response)
        ok, message = check("https://staging.example.test", opener=opener)
        self.assertTrue(ok, message)
        request = opener.call_args.args[0]
        self.assertEqual(request.full_url, "https://staging.example.test/up")
        self.assertEqual(request.get_method(), "GET")

    def test_rejects_plain_http_nonlocal_and_credentials(self):
        for value in ("http://staging.example.test", "https://user:secret@staging.example.test",
                      "https://staging.example.test/?token=secret", "https://staging.example.test/path"):
            with self.subTest(value=value), self.assertRaises(ValueError):
                validate_base_url(value)

    def test_local_http_allowed_and_network_failure_is_safe(self):
        self.assertEqual(validate_base_url("http://127.0.0.1:8080"), "http://127.0.0.1:8080")
        ok, message = check("https://staging.example.test", opener=Mock(side_effect=TimeoutError()))
        self.assertFalse(ok)
        self.assertNotIn("secret", message)


class BackupManifestTests(unittest.TestCase):
    def test_valid_manifest_passes_without_modifying_backup(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            data = b"encrypted-backup-fixture"
            (root / "db.sql.enc").write_bytes(data)
            manifest = {"files": [{"path": "db.sql.enc", "sha256": hashlib.sha256(data).hexdigest()}]}
            (root / "manifest.json").write_text(json.dumps(manifest), encoding="utf-8")
            before = (root / "db.sql.enc").read_bytes()
            self.assertEqual(verify(root), [])
            self.assertEqual((root / "db.sql.enc").read_bytes(), before)

    def test_checksum_mismatch_and_missing_file_block(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            (root / "backup.bin").write_bytes(b"changed")
            (root / "manifest.json").write_text(json.dumps({"files": [
                {"path": "backup.bin", "sha256": "0" * 64},
                {"path": "missing.bin", "sha256": "1" * 64},
            ]}), encoding="utf-8")
            failures = verify(root)
            self.assertEqual(len(failures), 2)

    def test_path_traversal_and_empty_manifest_block(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            (root / "manifest.json").write_text(json.dumps({"files": [
                {"path": "../outside", "sha256": "a" * 64}
            ]}), encoding="utf-8")
            self.assertIn("unsafe relative path", verify(root)[0])
            (root / "manifest.json").write_text('{"files":[]}', encoding="utf-8")
            self.assertTrue(verify(root))


if __name__ == "__main__":
    unittest.main()
