"""Unit tests never invoke real npm, Composer, registry or the network."""
import pathlib
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import Mock
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
from release_security_gate import COMMANDS, audit

class ReleaseSecurityGateTests(unittest.TestCase):
    def with_locks(self):
        workspace = tempfile.TemporaryDirectory()
        root = pathlib.Path(workspace.name)
        (root / "package-lock.json").write_text('{"lockfileVersion":3}', encoding="utf-8")
        (root / "composer.lock").write_text("{}", encoding="utf-8")
        return workspace, root

    def test_missing_lockfiles_block_without_external_commands(self):
        with tempfile.TemporaryDirectory() as d:
            runner = Mock()
            failures = audit(pathlib.Path(d), runner=runner)
            self.assertEqual(len(failures), 2)
            runner.assert_not_called()

    def test_clean_both_ecosystems_pass(self):
        directory, root = self.with_locks()
        with directory:
            runner = Mock(return_value=subprocess.CompletedProcess([], 0, "", ""))
            self.assertEqual(audit(root, runner=runner, which=lambda _: "/fake/executable"), [])
            self.assertEqual(runner.call_count, len(COMMANDS))
            self.assertEqual(runner.call_args.kwargs["timeout"], 180)

    def test_vulnerability_or_registry_outage_fails_closed(self):
        directory, root = self.with_locks()
        with directory:
            runner = Mock(side_effect=[
                subprocess.CompletedProcess([], 0, "ok", ""),
                subprocess.CompletedProcess([], 1, "private package information", ""),
            ])
            failures = audit(root, runner=runner, which=lambda _: "/fake/executable")
            self.assertEqual(len(failures), 1)
            self.assertIn("npm", failures[0])
            self.assertNotIn("private", repr(failures))

    def test_composer_failure_does_not_skip_npm_check(self):
        directory, root = self.with_locks()
        with directory:
            runner = Mock(side_effect=[
                subprocess.CompletedProcess([], 2, "", "registry error"),
                subprocess.CompletedProcess([], 0, "", ""),
            ])
            failures = audit(root, runner=runner, which=lambda _: "/fake/executable")
            self.assertEqual(len(failures), 1)
            self.assertEqual(runner.call_count, 2)

    def test_missing_binary_and_timeout_block(self):
        directory, root = self.with_locks()
        with directory:
            runner = Mock(side_effect=subprocess.TimeoutExpired("npm", 1))
            problems = audit(root, runner=runner, which=lambda exe: None if exe == "composer" else "/fake/npm")
            self.assertEqual(len(problems), 2)
            self.assertIn("missing", problems[0])
            self.assertIn("timed out", problems[1])

if __name__ == "__main__":
    unittest.main()
