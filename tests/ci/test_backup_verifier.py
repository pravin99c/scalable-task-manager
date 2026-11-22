"""Stdlib-only tests for the non-destructive backup verification utility."""
import hashlib
import pathlib
import sys
import tempfile
import unittest

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from verify_backup import verify_backup


class VerifyBackupTest(unittest.TestCase):
    def test_valid_checksum_passes(self):
        with tempfile.TemporaryDirectory() as root:
            backup = pathlib.Path(root) / "fixture.enc"
            check = pathlib.Path(root) / "fixture.sha256"
            backup.write_bytes(b"synthetic disposable test backup")
            check.write_text(hashlib.sha256(backup.read_bytes()).hexdigest(), encoding="ascii")
            self.assertTrue(verify_backup(backup, check))

    def test_modified_backup_fails(self):
        with tempfile.TemporaryDirectory() as root:
            backup = pathlib.Path(root) / "fixture.enc"
            check = pathlib.Path(root) / "fixture.sha256"
            backup.write_bytes(b"original")
            check.write_text(hashlib.sha256(backup.read_bytes()).hexdigest(), encoding="ascii")
            backup.write_bytes(b"tampered")
            self.assertFalse(verify_backup(backup, check))

    def test_invalid_manifest_rejected(self):
        with tempfile.TemporaryDirectory() as root:
            backup = pathlib.Path(root) / "fixture.enc"
            check = pathlib.Path(root) / "fixture.sha256"
            backup.write_bytes(b"hello")
            check.write_text("not-a-sha256  ../untrusted-path", encoding="ascii")
            with self.assertRaises(ValueError):
                verify_backup(backup, check)

    def test_missing_backup_rejected(self):
        with tempfile.TemporaryDirectory() as root:
            check = pathlib.Path(root) / "fixture.sha256"
            check.write_text("0" * 64, encoding="ascii")
            with self.assertRaises(ValueError):
                verify_backup(pathlib.Path(root) / "missing.enc", check)


if __name__ == "__main__":
    unittest.main()
