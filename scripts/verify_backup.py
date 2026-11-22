#!/usr/bin/env python3
"""Read-only integrity check of a backup file against a separately stored SHA-256.

This verifies corruption detection, not encryption, backup authenticity,
database consistency, or recovery correctness.
"""
from __future__ import annotations

import argparse
import hashlib
import hmac
import pathlib
import re
import sys


def verify_backup(backup: pathlib.Path, checksum: pathlib.Path) -> bool:
    if not backup.is_file() or not checksum.is_file():
        raise ValueError("Both backup and checksum must be existing regular files.")

    # A single digest per file avoids trusting paths embedded inside checksum manifests.
    expected = checksum.read_text(encoding="ascii").strip().lower()
    if not re.fullmatch(r"[0-9a-f]{64}", expected):
        raise ValueError("Checksum file must contain exactly one SHA-256 hex digest.")

    digest = hashlib.sha256()
    with backup.open("rb") as source:
        for block in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(block)
    return hmac.compare_digest(expected, digest.hexdigest())


def main() -> int:
    parser = argparse.ArgumentParser(description="Verify backup SHA-256 without modifying its contents.")
    parser.add_argument("backup", type=pathlib.Path, help="Path to encrypted backup archive")
    parser.add_argument("sha256", type=pathlib.Path, help="Path to file containing only the expected 64-character hex digest")
    args = parser.parse_args()

    try:
        valid = verify_backup(args.backup, args.sha256)
    except (ValueError, OSError, UnicodeError) as exc:
        print(f"FAIL: Cannot validate backup: {exc}", file=sys.stderr)
        return 2
    if not valid:
        print("FAIL: Backup SHA-256 mismatch. Treat the backup as unverified.", file=sys.stderr)
        return 1
    print("PASS: Backup SHA-256 matches. A separate isolated restore drill is still required.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
