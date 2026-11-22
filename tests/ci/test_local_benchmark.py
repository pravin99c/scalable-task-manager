"""Synthetic network-free tests for bounded local benchmark."""
import pathlib
import sys
import unittest
from unittest.mock import patch
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts"))
import local_benchmark

class LocalBenchmarkTest(unittest.TestCase):
    def test_rejects_non_local_and_unsafe_urls(self):
        for url in (
            "https://127.0.0.1:8080/up", "http://localhost:8080/up",
            "http://0.0.0.0:8080/up", "http://169.254.169.254:8080/up",
            "http://127.0.0.1:8080/api/v2/projects",
            "http://127.0.0.1:8080/up?token=secret",
            "http://user:pass@127.0.0.1:8080/up",
            "http://127.0.0.1:80/up",
        ):
            with self.subTest(url=url), self.assertRaises(ValueError):
                local_benchmark.validate_target(url)

    def test_percentiles(self):
        self.assertEqual(local_benchmark.percentile([10., 20., 30.], 0.5), 20.)
        self.assertAlmostEqual(local_benchmark.percentile([10., 20., 30.], 0.95), 29.)

    def test_success_and_failure(self):
        with patch.object(local_benchmark, "probe", side_effect=[(200, 10.), (503, 20.), (200, 30.)]):
            report = local_benchmark.benchmark("http://127.0.0.1:8080/up", count=3)
        self.assertEqual(report["successes"], 2)
        self.assertEqual(report["failures"], 1)
        self.assertEqual(report["p50_ms"], 20.)

    def test_bounded(self):
        for count in (0, 51):
            with self.assertRaises(ValueError):
                local_benchmark.benchmark("http://127.0.0.1:8080/up", count=count)

    def test_connection_failure_hides_details(self):
        with patch.object(local_benchmark, "probe", side_effect=ConnectionRefusedError("private detail")):
            report = local_benchmark.benchmark("http://127.0.0.1:8080/up", count=1)
        self.assertEqual(report["failures"], 1)
        self.assertIsNone(report["p95_ms"])
        self.assertNotIn("private detail", str(report))

if __name__ == "__main__":
    unittest.main()
