#!/usr/bin/env python3
"""Verify backup file SHA-256 values against a JSON manifest; never restore or modify files."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path, PurePosixPath


def verify(root: Path, manifest_name: str = "manifest.json") -> list[str]:
    root = root.resolve()
    manifest_path = root / manifest_name
    if not manifest_path.is_file() or manifest_path.is_symlink():
        return ["manifest missing or is not a regular file"]
    try:
        payload = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError):
        return ["manifest is unreadable or invalid JSON"]
    entries = payload.get("files") if isinstance(payload, dict) else None
    if not isinstance(entries, list) or not entries:
        return ["manifest must contain a non-empty files array"]

    failures: list[str] = []
    seen: set[str] = set()
    for index, entry in enumerate(entries):
        if not isinstance(entry, dict):
            failures.append(f"entry {index}: invalid entry")
            continue
        name, expected = entry.get("path"), entry.get("sha256")
        if (not isinstance(name, str) or not name or name in seen
                or not isinstance(expected, str) or not re.fullmatch(r"[0-9a-fA-F]{64}", expected)):
            failures.append(f"entry {index}: invalid or duplicate path/checksum")
            continue
        seen.add(name)
        relative = PurePosixPath(name)
        if relative.is_absolute() or ".." in relative.parts or "\\" in name:
            failures.append(f"entry {index}: unsafe relative path")
            continue
        candidate = root.joinpath(*relative.parts)
        try:
            resolved = candidate.resolve(strict=True)
            resolved.relative_to(root)
            if candidate.is_symlink() or not resolved.is_file():
                raise ValueError("not a regular file")
            digest = hashlib.sha256(resolved.read_bytes()).hexdigest()
        except (OSError, ValueError):
            failures.append(f"entry {index}: file missing, unsafe, or unreadable")
            continue
        if digest.lower() != expected.lower():
            failures.append(f"entry {index}: SHA-256 mismatch")
    return failures


def main() -> int:
    parser = argparse.ArgumentParser(description="Read-only SHA-256 backup integrity verifier")
    parser.add_argument("--root", type=Path, required=True, help="isolated backup directory")
    parser.add_argument("--manifest", default="manifest.json", help="manifest path relative to backup root")
    args = parser.parse_args()
    if Path(args.manifest).is_absolute() or ".." in PurePosixPath(args.manifest).parts:
        parser.error("--manifest must be a safe relative path")
    failures = verify(args.root, args.manifest)
    for failure in failures:
        print("BLOCKED: " + failure)
    if failures:
        print(f"FAIL: {len(failures)} backup integrity issue(s); no restore was attempted.")
        return 1
    print("PASS: all manifest-listed backup files match their SHA-256 checksums; no restore was attempted.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
