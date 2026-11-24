# TaskForge — Phase 6.13: staging smoke checks and backup evidence

**Status:** tooling and offline tests only. This phase does not certify a staging deployment, execute a backup, restore a database, or approve a release.

## 1. Read-only staging smoke check

After a maintainer has provisioned an isolated staging environment and configured DNS/TLS, run:

```bash
TASKFORGE_STAGING_URL=https://staging.example.test python3 scripts/staging_smoke.py
```

The tool requires an explicit origin; it never guesses a target. It performs exactly one unauthenticated `GET /up`, requires HTTP 200, uses the platform's normal TLS certificate verification, has a bounded timeout, and does not follow a user-provided path. Non-local HTTP, URL credentials, query strings and fragments are rejected. It does not log response bodies, cookies or headers and does not mutate the application.

Do not put credentials or private tokens in the URL. Run it from a trusted operator workstation or approved protected runner. This is a basic reachability/health check, not an authenticated workflow, penetration test, full browser review or proof that queues and mail delivery work.

## 2. Backup manifest integrity check

Create a manifest next to backup artifacts in a restricted, isolated backup directory. Example:

```json
{
  "files": [
    {"path": "taskforge-db-2026-11-24.sql.enc", "sha256": "<64-character lowercase SHA-256 hex digest>"},
    {"path": "uploads-2026-11-24.tar.enc", "sha256": "<64-character lowercase SHA-256 hex digest>"}
  ]
}
```

Generate checksums only after the backup artifact is finalized and stored. Protect the manifest independently (for example, signed or stored in a separate immutable location); a checksum manifest stored beside a file detects accidental corruption but is not proof against an attacker who can replace both.

Verify using:

```bash
python3 scripts/verify_backup_manifest.py --root /secure/backup/snapshot-2026-11-24
```

The verifier reads files and checks SHA-256 values. It rejects traversal, symlinks, duplicate paths, missing files and malformed manifests. It never decrypts, extracts, overwrites, migrates or restores anything. Keep backup encryption keys separate from the backup storage and restrict access.

## 3. Required human recovery drill (not automated here)

A maintainer must arrange an isolated disposable recovery environment and record:

- Backup identifier, creation time, encryption status and independently protected checksum evidence
- Database engine/version and application commit compatible with the backup
- Restore start/end timestamps and measured recovery time (RTO)
- Data-loss window measured against the last successful backup (RPO)
- Migration compatibility, attachment/object-storage integrity, and application-level spot checks
- Operator, approver, evidence location, issues and cleanup confirmation

Only restore into a newly provisioned disposable database/storage namespace. Never point a drill at production or an irreplaceable developer database. Do not use `migrate:fresh`, volume deletion or reset commands against real data. A successful checksum check is not a successful restore drill.

## 4. CI and release boundaries

Offline synthetic tests run through the existing Python CI test discovery. Ordinary CI does not contact staging or require staging secrets. The staging script runs only when an operator explicitly invokes it with a URL. The backup verifier requires a local backup directory and does not run against production storage in CI.

Before release approval, still require a real protected staging review, authenticated browser/device checks, supervised queue/scheduler checks, a successful isolated restore drill with measured RPO/RTO, reviewed dependency advisories, protected branch rules and explicit human release authorization. No deployment, real mail, destructive database action or production release is performed by these tools.
