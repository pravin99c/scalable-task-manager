# Phase 6.7 — Automated CI quality and staging release gates

**Status: development readiness controls, not an approved release or deployment.**

## Continuous integration

The existing consolidated Laravel GitHub Actions workflow now runs all committed `tests/ci/test_*.py` standard-library unit tests. These are synthetic and network-free. This specifically gives the Phase 6.6 local benchmark URL restrictions regression coverage in CI and adds tests of the static release-file gate. It does **not** issue benchmark HTTP requests, touch production infrastructure, or turn on any actual monitoring.

## Release artifact preflight

```bash
python3 scripts/release_quality_gate.py
python3 scripts/release_quality_gate.py --strict
```

The default report mode prints PASS/BLOCKED and exits successfully, so an incomplete release checklist does not block normal feature development. The explicit `--strict` mode fails when required release artifacts are missing or `package-lock.json` has an invalid basic structure. It does not parse credentials, modify files, install dependencies, run migrations, or contact any host.

**Known blocker:** the JavaScript lockfile is not committed yet. The tool intentionally reports that as BLOCKED; CI must not label a report-mode pass as release approval. Even strict success is only a *static artifact* check and never overrides successful dependency audits, green CI, staging verification, isolated restore evidence, secure infrastructure, or explicit maintainer approval.

## Proposed staging validation (manual, not executed)

1. Confirm branch protection, green consolidated CI and a genuine reviewed npm lockfile with deterministic `npm ci` installation.
2. Run dependency vulnerability audits; triage findings, and use `php scripts/production-preflight.php` against an approved candidate configuration with secrets stored outside Git.
3. On private staging infrastructure only, run `php artisan taskforge:readiness-check`, `php artisan taskforge:operations-check`, `php artisan migrate:status` and `php artisan schedule:list`; capture only redacted outcomes.
4. Verify HTTPS, secure cookies, CSRF/session handling, project membership isolation, invitation privacy and worker/scheduler supervision.
5. Perform isolated backup-restore drill with no real outbound email or production credentials; validate application records and recoverability and record RTO/RPO evidence.
6. Record observed latency/errors against synthetic staging data; do not infer production SLA from the loopback-only `/up` microbenchmark.
7. Obtain explicit maintainer release approval for any production action or `main` merge. Stop on failed gates; never auto-deploy from this PR.

## Remaining blockers

- [ ] Merge and validate independent dependency/backup PR #23
- [ ] Real `package-lock.json`, `npm ci`, lockfile audit and dependency security triage
- [ ] Branch rulesets and required review/check policies
- [ ] Staging environment, encrypted backups and a documented isolated restoration exercise
- [ ] External monitoring/alerts provisioned and assigned to an owner
- [ ] Separate, explicit authorization before merging to `main` or production deployment

No application emails, production load tests, database resets, real backup restores, or deployment actions are performed by the release-report tooling.
