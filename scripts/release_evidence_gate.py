#!/usr/bin/env python3
"""Fail-closed validation of human-produced release candidate evidence; performs no operations."""
from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import datetime
from pathlib import Path
from urllib.parse import urlsplit

COMMIT = re.compile(r"^[0-9a-f]{40}(?:[0-9a-f]{24})?$")
SHA256 = re.compile(r"^[0-9a-f]{64}$")


def is_timestamp(value: object) -> bool:
    if not isinstance(value, str):
        return False
    try:
        parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
        return parsed.tzinfo is not None and parsed.utcoffset() is not None
    except ValueError:
        return False


def is_https_url(value: object) -> bool:
    if not isinstance(value, str):
        return False
    parsed = urlsplit(value)
    return parsed.scheme == "https" and bool(parsed.hostname) and not parsed.username and not parsed.password


def evaluate(data: object) -> dict[str, bool]:
    if not isinstance(data, dict):
        return {"evidence document is a JSON object": False}
    checks: dict[str, bool] = {}
    current = data.get("release_commit")
    previous = data.get("previous_release_commit")
    checks["release commit is a full SHA"] = isinstance(current, str) and bool(COMMIT.fullmatch(current))
    checks["previous release commit is a distinct full SHA"] = (
        isinstance(previous, str) and bool(COMMIT.fullmatch(previous))
        and isinstance(current, str) and previous != current
    )
    checks["built artifact SHA-256 is recorded"] = (
        isinstance(data.get("artifact_sha256"), str) and bool(SHA256.fullmatch(data["artifact_sha256"]))
    )

    staging = data.get("staging")
    checks["staging regression evidence is passed and timestamped"] = (
        isinstance(staging, dict) and staging.get("status") == "passed"
        and is_timestamp(staging.get("checked_at"))
        and is_https_url(staging.get("origin"))
    )
    regression = data.get("regression")
    checks["CI regression run is linked and passed"] = (
        isinstance(regression, dict) and regression.get("status") == "passed"
        and is_timestamp(regression.get("checked_at"))
        and is_https_url(regression.get("ci_run_url"))
    )
    backup = data.get("backup")
    checks["backup integrity evidence is verified"] = (
        isinstance(backup, dict) and backup.get("status") == "verified"
        and is_timestamp(backup.get("checked_at"))
        and isinstance(backup.get("manifest_sha256"), str)
        and bool(SHA256.fullmatch(backup["manifest_sha256"]))
    )
    restore = data.get("restore_drill")
    checks["restore drill passed in isolated environment with measured RPO/RTO"] = (
        isinstance(restore, dict) and restore.get("status") == "passed"
        and restore.get("isolated_environment") is True
        and is_timestamp(restore.get("completed_at"))
        and type(restore.get("rpo_seconds")) is int and restore["rpo_seconds"] >= 0
        and type(restore.get("rto_seconds")) is int and restore["rto_seconds"] > 0
        and isinstance(restore.get("evidence_reference"), str)
        and bool(restore["evidence_reference"].strip())
    )
    rollback = data.get("rollback")
    checks["application rollback was rehearsed with safe database strategy"] = (
        isinstance(rollback, dict) and rollback.get("status") == "tested"
        and is_timestamp(rollback.get("tested_at"))
        and isinstance(rollback.get("method"), str) and bool(rollback["method"].strip())
        and rollback.get("database_strategy") in ("forward_fix", "isolated_restore")
        and rollback.get("production_database_touched") is False
    )
    approval = data.get("approval")
    checks["release has explicit named human approval"] = (
        isinstance(approval, dict) and approval.get("status") == "approved"
        and isinstance(approval.get("approver"), str) and bool(approval["approver"].strip())
        and is_timestamp(approval.get("approved_at"))
    )
    return checks


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate a completed TaskForge release evidence JSON file")
    parser.add_argument("--evidence", type=Path, required=True)
    args = parser.parse_args()
    try:
        data = json.loads(args.evidence.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError):
        print("BLOCKED: evidence file missing, unreadable, or invalid JSON")
        return 2
    checks = evaluate(data)
    for label, passed in checks.items():
        print(("PASS: " if passed else "BLOCKED: ") + label)
    blocked = sum(not value for value in checks.values())
    print(f"Release evidence: {len(checks) - blocked} passed; {blocked} blocked.")
    print("Evidence validation only; this tool does not deploy, approve, roll back, or contact infrastructure.")
    return 1 if blocked else 0


if __name__ == "__main__":
    sys.exit(main())
