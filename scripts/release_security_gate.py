#!/usr/bin/env python3
"""Fail-closed, read-only release-candidate dependency audit.

This command never installs or upgrades dependencies, deploys, sends application
mail, starts services, or changes a database. Advisory registries are contacted
by Composer/npm unless a caller has configured trusted offline mirrors.
"""
from __future__ import annotations

import argparse
import pathlib
import shutil
import subprocess
import sys

COMMANDS = (
    ("Composer locked dependency advisories", ["composer", "audit", "--locked", "--no-interaction"]),
    ("npm high-and-critical locked dependency advisories", ["npm", "audit", "--package-lock-only", "--audit-level=high"]),
)

def audit(root: pathlib.Path, *, timeout: int = 180, runner=subprocess.run, which=shutil.which) -> list[str]:
    """Return *only* check identifiers and statuses; never expose full audit logs or credentials."""
    problems: list[str] = []
    for lockfile in ("composer.lock", "package-lock.json"):
        if not (root / lockfile).is_file():
            problems.append(f"missing required {lockfile}")
    if problems:
        return problems

    for label, argv in COMMANDS:
        if which(argv[0]) is None:
            problems.append(f"{label}: required executable missing")
            continue
        try:
            result = runner(argv, cwd=root, check=False,
                            capture_output=True, text=True, timeout=timeout)
        except (OSError, subprocess.TimeoutExpired):
            problems.append(f"{label}: execution failed or timed out")
            continue
        if result.returncode != 0:
            # npm/Composer return non-zero for either advisory findings or
            # registry failures. Both MUST block a release.
            problems.append(f"{label}: nonzero status (findings or registry failure)")
    return problems

def main() -> int:
    p = argparse.ArgumentParser(description="Fail-closed TaskForge release dependency audit (no writes)")
    p.add_argument("--root", type=pathlib.Path, default=pathlib.Path(__file__).resolve().parents[1])
    p.add_argument("--timeout", type=int, default=180,
                   help="Per-ecosystem maximum time in seconds, from 10 to 600")
    args = p.parse_args()
    if not 10 <= args.timeout <= 600:
        p.error("--timeout must be between 10 and 600 seconds")
    root = args.root.resolve()
    if not root.is_dir():
        p.error("--root must be an existing directory")
    issues = audit(root, timeout=args.timeout)
    for issue in issues:
        print(f"BLOCKED: {issue}")
    if issues:
        print("FAIL: Candidate cannot be released. Examine advisory output on a trusted machine; fix or explicitly review findings.")
        return 1
    print("PASS: Locked npm high/critical and Composer advisories returned success at the time of this run.")
    print("This does not certify staging readiness, production security, or backup restorability.")
    return 0

if __name__ == "__main__":
    sys.exit(main())
