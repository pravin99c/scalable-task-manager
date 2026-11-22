#!/usr/bin/env python3
"""Offline npm lockfile structural review. Does not install packages or access networks."""
import argparse
import json
import pathlib
import sys
from urllib.parse import urlsplit

def audit_files(manifest_path, lock_path):
    try:
        manifest = json.loads(pathlib.Path(manifest_path).read_text(encoding="utf-8"))
        lock = json.loads(pathlib.Path(lock_path).read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError):
        return ["Unreadable or invalid package manifest/lockfile"]
    if not isinstance(manifest, dict) or not isinstance(lock, dict):
        return ["Package manifest/lockfile must be JSON objects"]
    problems = []
    if lock.get("lockfileVersion") != 3:
        problems.append("Expected npm v3 lockfile")
    packages = lock.get("packages")
    if not isinstance(packages, dict) or not isinstance(packages.get(""), dict):
        return problems + ["Lockfile root packages entry missing"]
    for key in ("dependencies", "devDependencies", "optionalDependencies"):
        expected = manifest.get(key, {})
        recorded = packages[""].get(key, {})
        if not isinstance(expected, dict) or recorded != expected:
            problems.append(f"Root {key} differs from package.json")
    # Restrict remote tarball origins: git/HTTP and credential-bearing URLs are
    # not appropriate without explicit review. This is NOT a malware scanner.
    for path, item in packages.items():
        if path == "":
            continue
        if not isinstance(item, dict):
            problems.append("Malformed package entry")
            continue
        resolved = item.get("resolved")
        integrity = item.get("integrity")
        if not isinstance(resolved, str) or not isinstance(integrity, str) or not integrity.startswith(("sha512-", "sha384-", "sha256-")):
            problems.append("Package missing resolved origin or hash")
            continue
        uri = urlsplit(resolved)
        if (uri.scheme != "https" or not uri.hostname or uri.username or uri.password or uri.fragment):
            problems.append("Package uses an unapproved tarball URL shape")
    return problems

def main():
    parser = argparse.ArgumentParser(description="Offline, read-only npm lockfile review")
    parser.add_argument("--manifest", default="package.json")
    parser.add_argument("--lock", default="package-lock.json")
    args = parser.parse_args()
    problems = audit_files(args.manifest, args.lock)
    for p in sorted(set(problems)):
        print("FAIL:", p)
    if problems:
        return 1
    print("PASS: root dependencies, lockfile shape, resolved HTTPS origins and integrity fields.")
    print("Not a substitute for npm ci, npm audit, dependency provenance or code review.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
