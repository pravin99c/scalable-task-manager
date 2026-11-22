# TaskForge — Phase 6.3: Dependency security and backup recovery

Status: **implementation in progress**; no production readiness, vulnerability-free status, or verified restore is claimed.

## 1. Advisory audits (read-only)

From the project root, on a trusted machine with Composer/npm and network access:

```bash
bash scripts/audit-dependencies.sh
```

This script checks `composer.lock` with `composer audit --locked --no-interaction` and, **only when a real lockfile exists**, checks `package-lock.json` using `npm audit --package-lock-only --audit-level=high`. It neither installs dependencies nor rewrites files. Audit traffic goes to the configured Composer/npm registries; avoid uploading private package metadata to untrusted registry mirrors.

**Existing blocker:** `package-lock.json` is absent from the current `development` branch. Do **not** invent it, assume a green audit, or switch CI to `npm ci` before generating and reviewing an authentic lockfile:

```bash
npm install --package-lock-only --ignore-scripts --no-audit
npm ci --ignore-scripts --no-audit --no-fund
npm run typecheck
npm test
npm run build
```

Run the install/test sequence on a disposable trusted development machine; omit `--ignore-scripts` for an approved, reviewed build if a dependency requires install-time scripts. Review the lockfile, integrity fields, dependency tree, audit findings, and install scripts before committing. Lockfile changes require an independent draft PR and **one consolidated CI run** rather than repeated trial pushes.

A green advisory scan **does not** establish the absence of all vulnerabilities. Record package/advisory ID, severity, exploitability, patch options and mitigation; do not expose private package metadata or credentials in tickets.

## 2. Backup integrity (read-only helper)

The committed `scripts/verify_backup.py` calculates a SHA-256 digest of an existing backup and compares it against a separate file containing **one 64-character SHA-256 hex digest**, without modifying either file:

```bash
python3 scripts/verify_backup.py /secure/backup.enc /secure/backup.sha256
python3 -m unittest discover -s tests/ci -p 'test_*.py'
```

Write and protect the checksum **at backup creation time**. For sensitive data, create encrypted, access-restricted backups using approved database/storage tools and separate encryption key management; the script does **not** create, encrypt, authenticate or restore a backup. SHA-256 matching detects accidental corruption or a mismatch against a separately trusted digest, not malicious replacement when an attacker can alter both files.

### Required recovery drill — not yet performed

1. Identify database and user-upload files, point-in-time consistency requirements, retention, RPO/RTO objectives and secure offsite storage.
2. Use a least-privilege backup account and encryption in transit/at rest, with separately managed and tested recovery keys. Never commit plaintext archives or keys.
3. Record manifest, backup timestamp, database schema version, size and digest separately from the encrypted archive; monitor failures and unexpected size changes.
4. Restore into an **isolated disposable environment with no production credentials or outbound mail access**; use `MAIL_MAILER=array` or a safe no-delivery transport.
5. Verify schema/data counts, attachments, permissions, task/project membership and application smoke tests against synthetic or appropriately protected data. Compare recovery time with RTO.
6. Test queue/scheduler reactivation deliberately; keep live notifications, email and production integrations disabled.
7. Document failure modes, access to recovery keys, incident escalation, rollback and restore evidence. Obtain explicit release approval before relying on the backup.

Never execute `migrate:fresh`, `docker compose down -v`, or restore-overwrite operations against irreplaceable data. The recovery drill is an operational requirement, **not** something automated by this phase.

## 3. CI and branch safety

Phase 6.3 adds only opt-in, read-only tools and stdlib unit tests; it deliberately does not add a guaranteed-failing npm audit step to the main CI while the lockfile is absent. Follow-on CI integration should use a genuine pinned dependency graph and controlled audit-failure policy.

PR #22 (cached Sail image build) was open with successful PR CI when this work began. Keep this feature branch based on the latest `development`; do not merge PRs into `main`, deploy, send real email, or modify real data.

## Exit gates

- [ ] Lockfile generated and reviewed from registry resolution
- [ ] Both Composer and npm audits executed and findings triaged
- [ ] Deterministic `npm ci` validation passed
- [ ] Backup encryption, monitoring, access controls and retention implemented
- [ ] Isolated restore drill completed and documented
- [ ] Current PR CI passed and code review completed
