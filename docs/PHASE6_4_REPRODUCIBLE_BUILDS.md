# Phase 6.4 — Reproducible JavaScript build and recovery validation gates

Status: **prepared for review, not certified for deployment**. This increment deliberately does not fabricate or commit a lockfile. Actual dependency resolution requires trusted npm registry access.

## Verified starting point

- PR #22 (Sail build caching) is merged into `development` and the preceding CI optimization requires post-merge timing confirmation.
- PR #23 (dependency audit and SHA-256 backup verification) was open at the start of this increment and should remain independently reviewable.
- `composer.lock` is tracked. **`package-lock.json` is missing.** The current workflow uses `npm install`, so dependency resolution can vary between runs.

## Generate and review an authentic lockfile

Use an isolated workspace with Node.js 24 and a trusted npm registry. Never use an environment with production application secrets.

```bash
bash scripts/prepare-npm-lockfile.sh
npm audit --package-lock-only --audit-level=high
git diff -- package-lock.json
git status --short
```

The script refuses to overwrite an existing lockfile, generates it using npm registry metadata (no dependency scripts during metadata resolution), then runs an actual clean `npm ci`, TypeScript checks, Vitest, and production Vite build. **The `npm ci` step can execute package lifecycle scripts**—only run this on a disposable trusted development/CI runner. It does not commit or push. The lockfile will be untracked until a reviewer adds it to a separate PR. Review resolved versions, integrity hashes, registry origins, scripts and npm audit results. Do not disable the audit or ignore known critical findings just to pass.

The existing consolidated CI now supports both states: when `package-lock.json` is checked in it uses `npm ci --no-audit --no-fund`, otherwise the legacy `npm install --no-audit --no-fund` remains available to keep PR validation operational. **Once a reviewed lockfile is committed, remove the fallback** in a follow-up change so missing lockfiles fail closed. This two-step migration is deliberate, not a claim of reproducibility today.

## Recovery validation requirements

Phase 6.3 contains a read-only checksum verifier and synthetic unit tests. After PR #23 merges, run those tests and checksum verification on **synthetic fixtures** before an isolated encrypted-backup restore drill. The full restore drill is not implemented or performed by this increment. Required release evidence:

- Backup scope: MySQL database plus required uploaded files, with application-consistent snapshot/transaction boundary
- Encryption key custody and least-privilege access, protected retention, offsite copy, failure alerts and regularly verified checksums
- Restore to isolated nonproduction infrastructure only; no production secrets, public network ingress, workers, notifications or real email delivery
- Schema, counts, attachment checks, project/member authorization, smoke tests, observed RPO/RTO and documented failure/rollback procedure
- Named owner approval before any production restore or destructive operation

Never run `migrate:fresh`, `docker compose down -v`, or destructive restore commands against real application data.

## Exit gates

- [ ] Prepare an authentic npm lockfile from registry resolution
- [ ] Review and commit the lockfile in a separate PR, then test `npm ci` on clean CI
- [ ] Remove fallback `npm install` after lockfile adoption is verified
- [ ] Complete and triage Composer/npm dependency security audits
- [ ] Merge Phase 6.3 and run backup-verifier unit tests
- [ ] Complete a separately approved isolated encrypted backup restore drill
- [ ] Confirm full CI green and compare warm-cache Sail runtime before claiming an improvement
- [ ] Explicit approval required before `main` merge, real emails or production deployment
