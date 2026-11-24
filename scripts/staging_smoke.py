#!/usr/bin/env python3
"""Read-only HTTPS smoke check for an explicitly configured TaskForge staging URL."""
from __future__ import annotations

import argparse
import os
import sys
import urllib.error
import urllib.request
from urllib.parse import urlsplit


def validate_base_url(value: str) -> str:
    parsed = urlsplit(value)
    if parsed.scheme not in ("https", "http") or not parsed.hostname:
        raise ValueError("staging URL must be an absolute HTTP(S) URL")
    if parsed.username or parsed.password or parsed.query or parsed.fragment:
        raise ValueError("staging URL must not contain credentials, query, or fragment")
    host = parsed.hostname.lower()
    if parsed.scheme != "https" and host not in ("localhost", "127.0.0.1", "::1"):
        raise ValueError("non-local staging URLs must use HTTPS")
    if parsed.path not in ("", "/"):
        raise ValueError("provide the origin only; the health path is appended automatically")
    return value.rstrip("/")


def check(base_url: str, *, opener=urllib.request.urlopen, timeout: int = 10) -> tuple[bool, str]:
    try:
        base = validate_base_url(base_url)
        request = urllib.request.Request(
            base + "/up",
            headers={"Accept": "text/plain, application/json", "User-Agent": "TaskForge-Staging-Smoke/1.0"},
            method="GET",
        )
        with opener(request, timeout=timeout) as response:
            status = getattr(response, "status", response.getcode())
            if status != 200:
                return False, f"health endpoint returned HTTP {status}"
            return True, "health endpoint returned HTTP 200"
    except (ValueError, OSError, urllib.error.URLError, TimeoutError) as exc:
        # Do not print response bodies, headers, or URL values that could contain secrets.
        return False, f"staging health check failed ({type(exc).__name__})"


def main() -> int:
    parser = argparse.ArgumentParser(description="Read-only TaskForge staging GET /up smoke check")
    parser.add_argument("--url", default=os.environ.get("TASKFORGE_STAGING_URL"),
                        help="staging origin; defaults to TASKFORGE_STAGING_URL")
    parser.add_argument("--timeout", type=int, default=10)
    args = parser.parse_args()
    if not args.url:
        parser.error("set TASKFORGE_STAGING_URL or pass --url; no target is guessed")
    if not 1 <= args.timeout <= 30:
        parser.error("--timeout must be between 1 and 30 seconds")
    ok, message = check(args.url, timeout=args.timeout)
    print(("PASS: " if ok else "BLOCKED: ") + message)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
