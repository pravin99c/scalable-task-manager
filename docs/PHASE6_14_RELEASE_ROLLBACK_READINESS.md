# TaskForge — Phase 6.14: release-candidate regression and rollback readiness

**Status: evidence gate and runbook only. No deployment, production rollback, database restore, or release approval has been performed.**

## 1. Release evidence gate

Once the release owner has collected real evidence from the protected staging environment, create a restricted JSON evidence file and validate it:

```bash
python3 scripts/release_evidence_gate.py --evidence /secure/release-candidate/evidence.json
```

The gate requires:
- Full current and previous release commit SHAs and the built artifact SHA-256
- Passed staging regression with HTTPS origin and timestamp
- Passed CI regression run URL and timestamp
- Verified backup manifest checksum and timestamp
- Successful restore drill in a disposable isolated environment, measured RPO/RTO and evidence reference
- Rehearsed application rollback, explicit safe database strategy, and confirmation production database was not touched
- Named human release approver and approval timestamp

All timestamps must include a timezone. The command is read-only and does not fetch URLs, inspect infrastructure, deploy, approve, or perform rollback. It verifies the completeness/shape of evidence supplied by the operator; it cannot prove the evidence is genuine. Store evidence securely and independently review its provenance.

## 2. Final regression sequence (staging only)

1. Pin the candidate commit and immutable build artifact digest. Record the currently deployed release commit and artifact digest.
2. Confirm the full CI suite passed for that exact candidate SHA; inspect test reports, PHP lint, npm lockfile validation, `npm ci`, typecheck, frontend tests/build, Laravel tests, browser tests and dependency audit outcomes.
3. Deploy only to protected staging through the organization's reviewed deployment process. No production URL or credentials belong in this repository.
4. Run the explicit-target read-only health check from Phase 6.13, then complete authenticated role/permission, task CRUD, pagination/filter, invitation/session, API authorization, browser, accessibility and mobile regression using synthetic staging accounts.
5. Confirm queue workers, scheduler, logs, database connectivity, storage and mail sink using non-delivery test transport. Do not send real customer mail.
6. Verify backup integrity and perform a restore rehearsal to a new disposable database/storage namespace. Record actual RPO and RTO; checksum-only verification is insufficient.
7. Rehearse application rollback to the previous immutable artifact. Keep database changes forward-compatible; do not assume rolling back schema migrations is safe. If schema recovery is necessary, use a separately reviewed isolated recovery plan.
8. Capture redacted evidence, unresolved risks, operator and independent approver. Run the evidence gate. A passing gate is a completeness check, not automatic authorization.

## 3. Rollback decision notes

- Prefer rolling back the application artifact while retaining backward-compatible database schema.
- Use expand/contract migrations: add compatible schema first, deploy code that supports old/new shape, migrate data safely, and remove obsolete schema only in a later reviewed release.
- Do not run `migrate:rollback`, `migrate:fresh`, `db:wipe`, destructive volume commands or database overwrite against production as an emergency shortcut.
- Define the decision owner, trigger thresholds, communication channel, read-only/maintenance behavior, data reconciliation and forward-fix owner before deployment.
- Treat irreversible data transformations and external side effects as separate recovery plans.

## 4. Hard release boundary

This phase does not merge to `main`, deploy, change DNS/secrets/branch protection, send real mail, restore production backups, or execute rollback. Those actions require a separate reviewed operational plan and explicit owner authorization. The user/maintainer remains the release approver.
