# Phase 6.9 — Deterministic build adoption and npm provenance review

**Status: draft validation increment; no production deploy or release certification.**

## Authentic artifact retrieved

PR #29's workflow produced a genuine `package-lock.json` in GitHub Actions artifact `taskforge-npm-lockfile-e41d73ac135e17432b5358664061bb2090ae2e16` from run [45035787742](https://github.com/pravin99c/scalable-task-manager/actions/runs/45035787742). It contains 335 package entries, uses lockfile version 3, matches the current direct dependency and development dependency declarations, and has HTTPS resolved package URLs with integrity fields. The artifact is short-lived, so preserve it in a separately reviewed commit after verification.

It is **not yet part of this branch**: the repository contents API requires complete file contents and this phase does not silently reconstruct or invent registry metadata. Download it from the GitHub Actions artifact, verify its origin and exact commit, then open a separate focused lockfile PR against `development`.

## Added quality controls

`python3 scripts/validate_npm_lockfile.py` performs a read-only, offline structural check. It compares root dependency declarations with `package.json`, requires npm lockfile v3, checks HTTPS package origins without embedded credentials, and checks presence of standard integrity metadata. Synthetic tests are exercised by existing consolidated CI. When a reviewed lockfile is committed, CI now runs this checker before the existing TypeScript, Vitest and build tests; CI already selects `npm ci` when `package-lock.json` exists.

This tool cannot prove that an npm package is trustworthy or that the digest matches an official registry distribution; review origin provenance, execute `npm ci` in isolated CI, and inspect npm and Composer audit findings separately.

## Next migration steps

1. Download the genuine GitHub artifact from the trusted Phase 6.8 run and inspect it; do not trust artifacts from unreviewed forks.
2. In a clean disposable checkout of the same `package.json`, add the original artifact as `package-lock.json`, run the offline validator, then `npm ci --no-audit --no-fund`, `npm run typecheck`, `npm test`, and `npm run build`.
3. Run `npm audit --package-lock-only --audit-level=high` and `composer audit --locked`; review findings and remediation before approval.
4. Commit the real lockfile in a separate feature branch, obtain full CI green, then remove the temporary `npm install` fallback in another reviewed change.
5. Independently finish staging browser/accessibility review, private readiness diagnostics, encrypted backup restoration drills and branch protection. Explicit user approval remains required for production or merging to `main`.

No tests were disabled and no production database, external mail, or live load test was performed.
