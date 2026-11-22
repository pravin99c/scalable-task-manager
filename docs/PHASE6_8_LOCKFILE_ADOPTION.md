# TaskForge — Phase 6.8: Genuine npm lockfile and dependency review

**Status: lockfile artifact generation prepared; lockfile not yet committed or release certified.**

## Why this phase

The project's current development branch has `composer.lock` but no `package-lock.json`. Its consolidated CI currently runs `npm install`, which resolves packages using the configured npm registry. A generated lockfile must come from actual dependency resolution, not generated placeholder integrity hashes.

The Phase 6.8 workflow keeps the established full CI tests and adds:

1. A check that `package-lock.json` created by npm is valid JSON with a recognized lockfile version and root package entry.
2. A short-lived GitHub Actions artifact containing that registry-resolved lockfile, named `taskforge-npm-lockfile-<commit-sha>`, retained for seven days. No release, commit or push is performed by the workflow.
3. Read-only `composer audit --locked --no-interaction` and `npm audit --package-lock-only --audit-level=high` reports. They report warnings rather than failing the entire development CI, since a new advisory, registry outage or preexisting vulnerability requires triage, not a silent test bypass.

**Important:** A green CI status is not a clean dependency security bill of health. Review the full advisory step output before approving release; do not silently ignore vulnerabilities.

## Maintainer lockfile adoption

After Phase 6.8 PR validation passes:

1. Open the successful Actions workflow run on the phase branch, select the `taskforge-npm-lockfile-<sha>` artifact, and download it.
2. Confirm that its `package-lock.json` matches the workflow's commit and reviewed `package.json`. Inspect registry origins, dependency scripts, resolved versions and integrity fields. Do not trust artifacts from unreviewed external PRs.
3. In an isolated, trusted checkout of the same commit, install the artifact lockfile and execute `npm ci --no-audit --no-fund`, `npm run typecheck`, `npm test`, `npm run build`, and `npm audit --package-lock-only --audit-level=high`. Only use a test environment with no application secrets because package lifecycle scripts can execute during installation.
4. Commit the reviewed real lockfile on a separate feature branch and PR targeting `development`. Once merged and CI green, remove the temporary `npm install` fallback and require `npm ci` everywhere.
5. Run and triage both ecosystems' security audit findings; blocked critical/high issues must have an explicit remediation/acceptance decision before a production release.

When the lockfile is already checked in, the artifact is generated from that exact file after `npm ci`; this step does not modify package metadata.

## Additional review gates

- [ ] Full PR CI passes (PHP, Laravel, React, Playwright, MySQL, Sail, queue/scheduler and Python synthetic safety checks).
- [ ] Lockfile artifact originates from trusted, reviewed CI commit and is committed via a separate reviewed PR.
- [ ] npm and Composer advisory results are triaged, with independently reviewed fixes.
- [ ] Existing-data migrations and encrypted backup restore verified on isolated nonproduction infrastructure.
- [ ] Staging TLS, sessions, access controls, worker supervision, alerts, branch protections and rollback readiness verified.
- [ ] Explicit approval before `main` merge, production deployment, real mail delivery, destructive migrations or running load tests on live environments.

No production databases, servers or email integrations are accessed by this phase.
