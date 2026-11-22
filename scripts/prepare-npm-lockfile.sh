#!/usr/bin/env bash
# TaskForge: prepare a genuine npm lockfile on a trusted, disposable machine.
# No commits, pushes, deployments, external application emails or database actions.
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f package.json ]]; then
  echo "ERROR: run from the TaskForge repository; package.json is missing." >&2
  exit 2
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm is required." >&2
  exit 2
fi
if [[ -e package-lock.json ]]; then
  echo "ERROR: package-lock.json already exists. Review it; do not overwrite automatically." >&2
  exit 2
fi

# Avoid executing dependency lifecycle scripts while RESOLVING metadata.
# This still requires access to a trusted, configured npm registry.
npm install --package-lock-only --ignore-scripts --no-audit --no-fund

# Validate actual installation FROM the generated dependency graph. npm ci
# intentionally executes normal install lifecycle scripts, so run ONLY in
# a disposable, trusted development or CI environment with no credentials.
npm ci --no-audit --no-fund
npm run typecheck
npm test
npm run build

echo "PASS: generated package-lock.json and validated npm ci, tests and build."
echo "Review package-lock.json, registry URLs, package scripts and npm audit before committing."
