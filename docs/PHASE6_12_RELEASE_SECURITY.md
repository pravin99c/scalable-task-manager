# TaskForge — Phase 6.12: Release-candidate dependency security gate

**Status: release gating code implemented, not a release approval, vulnerability-free certification, staging deployment, or backup recovery drill.**

## Current baseline

- Phase 6.10 **PR #31** adds the real npm lockfile; its consolidated PR CI passed, but it was not merged when this phase started.
- Phase 6.11 **PR #32** adds strict `npm ci` and static release artifact checks; its consolidated PR CI passed, but it too was not merged when this phase started.
- This stacked Phase 6.12 branch is based on Phase 6.11 and must be reviewed/merged *after* PR #31 and PR #32.

## Automatic release-candidate audit

The existing consolidated workflow now includes a dedicated `Release candidate locked dependency audit` job. It runs for **pull requests with a source branch beginning `release/`** targeting `development` or `main`, never for normal `feat/` branches. It uses read-only GitHub permissions and checks out only the source. It:

1. Validates the committed npm v3 lockfile against `package.json` and requires all static release artifact files.
2. Runs `composer audit --locked --no-interaction` for PHP advisories.
3. Runs `npm audit --package-lock-only --audit-level=high` for high and critical npm advisories.
4. Returns a nonzero CI status on **any** nonzero audit response, missing lockfile/tool, or timeout; registry outages block a release candidate rather than silently passing.
5. Keeps audit stdout and stderr out of the wrapper's own output so credentials or private package URLs are not accidentally repeated. CI still has its normal provider-managed logs and must be access restricted.

Execute the same release-specific audit manually on a trusted isolated workspace:

```bash
python3 scripts/release_security_gate.py
python3 scripts/release_quality_gate.py --strict
python3 -m unittest discover -s tests/ci -p 'test_release_security_gate.py' -v
```

The audit contacts the configured upstream registries for advisory metadata but does **not** install or update packages, send application emails, access databases, migrate, restore backups, or deploy. A passing audit checks only advisories currently returned by those registries, not all possible security defects. The npm threshold is **high or critical**; moderate/low findings must still be triaged in release review. Composer's locked audit follows Composer's default advisory policy.

## Release-candidate branch workflow

1. After both prerequisite PRs are merged and development CI is green, create a reviewed `release/<version>` source branch from the correct reviewed `development` commit.
2. Open a PR targeting `development` for final staging checks, or `main` only with explicit authorization. The release-candidate security job activates automatically on that PR.
3. Require successful full Laravel, MySQL, Sail, Playwright, React and Python CI **and** the new release-candidate audit; inspect advisory outputs and record any mitigations/accepted risk.
4. Run static config preflight on a protected candidate environment, and staging readiness and authorization checks. Do not put production secrets in GitHub Actions artifacts.
5. Require a separately approved isolated encrypted backup restore drill with measured RPO/RTO, and documented supervised queue/scheduler, alerts, TLS, accessibility, and rollback testing.
6. Obtain explicit human approval before merging to `main`, deploying production, running real email, or taking destructive action. The new gate does none of these operations automatically.

## Remaining release blockers

- [ ] Merge PR #31 and PR #32 to `development` in order
- [ ] Verify Phase 6.12 consolidated PR CI and merged development CI
- [ ] Audit results reviewed with remediation decisions and known limitations
- [ ] Actual protected staging environment and owner approvals
- [ ] Encrypted monitored backup storage plus isolated successful restore drill
- [ ] Branch protection / required checks and deployment restrictions configured by repository maintainer
- [ ] Explicit authorization before release to `main` or production
