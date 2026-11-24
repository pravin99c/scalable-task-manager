#!/usr/bin/env bash
# TaskForge dependency audit. Read-only; no install, upgrade, deploy or mail.
set -uo pipefail
cd "$(dirname "$0")/.."

if ! command -v composer >/dev/null 2>&1; then
  echo "FAIL: Composer is required for the PHP dependency audit." >&2
  exit 2
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "FAIL: npm is required for the frontend dependency audit." >&2
  exit 2
fi
if [[ ! -f composer.lock ]]; then
  echo "FAIL: composer.lock is missing; cannot audit pinned PHP dependencies." >&2
  exit 2
fi

status=0
echo "Checking pinned PHP dependencies (composer.lock)..."
# Composer audit reads advisory metadata; it does not install or update packages.
composer audit --locked --no-interaction || status=1

if [[ -f package-lock.json ]]; then
  echo "Checking pinned frontend dependencies (package-lock.json)..."
  npm audit --package-lock-only --audit-level=high || status=1
else
  echo "FAIL: package-lock.json is missing. npm vulnerability audit cannot be reproduced." >&2
  echo "Generate it using npm install --package-lock-only on a trusted network, review the diff, then use npm ci in CI." >&2
  status=1
fi

if [[ "$status" -eq 0 ]]; then
  echo "PASS: both installed ecosystems passed the configured advisory checks."
else
  echo "FAIL: one or more audits failed, or the JavaScript lockfile is absent."
fi
exit "$status"
