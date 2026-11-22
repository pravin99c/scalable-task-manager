#!/usr/bin/env python3
"""Read-only static TaskForge release-file checks; never deploys or inspects secrets."""
from __future__ import annotations

import argparse
import json
from pathlib import Path

REQUIRED_FILES = (
    "composer.lock",
    "package-lock.json",
    "scripts/production-preflight.php",
    "scripts/prepare-npm-lockfile.sh",
    "docs/PHASE6_RELEASE_READINESS.md",
    "docs/PHASE6_5_DEPLOYMENT_OPERATIONS.md",
    "docs/PHASE6_6_OBSERVABILITY.md",
)

def evaluate(root: Path) -> dict:
    checks = {f"tracked artifact present: {path}": (root / path).is_file()
              for path in REQUIRED_FILES}
    # Verify the presence and basic shape of a genuine npm lockfile without
    # resolving packages, installing anything, or leaking dependency contents.
    path = root / "package-lock.json"
    if path.is_file():
        try:
            manifest = json.loads(path.read_text(encoding="utf-8"))
            valid = (isinstance(manifest, dict)
                     and manifest.get("lockfileVersion") in (2, 3)
                     and isinstance(manifest.get("packages"), dict)
                     and "" in manifest["packages"])
        except (ValueError, OSError, UnicodeError):
            valid = False
        checks["npm lockfile has expected shape"] = valid
    return checks

def main() -> int:
    p = argparse.ArgumentParser(description="Read-only TaskForge release artifact preflight")
    p.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    p.add_argument("--strict", action="store_true",
                   help="Fail if release artifact prerequisites are absent")
    args = p.parse_args()
    checks = evaluate(args.root)
    for name, ok in checks.items():
        print(f"{'PASS' if ok else 'BLOCKED'}: {name}")
    blocked = sum(not ok for ok in checks.values())
    print(f"Release artifact checks: {len(checks) - blocked} passed; {blocked} blocked.")
    print("This only checks static files; CI, audit, staging, backup restore and approval remain separate gates.")
    return 1 if blocked and args.strict else 0

if __name__ == "__main__":
    raise SystemExit(main())
