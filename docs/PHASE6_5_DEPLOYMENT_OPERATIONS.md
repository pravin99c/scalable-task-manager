# TaskForge — Phase 6.5: Production architecture, monitoring and disaster-recovery runbook

**Status: proposed release architecture; NOT deployed or certified.** The Phase 6.5 PR adds a private, read-only readiness CLI and a release operations checklist. It does not configure a host, provision a database, send email, or restore backups.

## Architecture boundaries

```text
  User browser
      |
  HTTPS + managed certificate / reverse proxy (proposed)
      |
  Laravel application (PHP-FPM/web processes) + built React assets
      |
      +-- MySQL (durable projects, tasks, users, queue receipts)
      +-- Redis (cache / optional queue)
      +-- Private file storage (uploads, if applicable)
      +-- Supervised queue workers (mail queue, other queues as configured)
      +-- Scheduler (single coordinated schedule runner)
      +-- Optional authorized Reverb WebSocket service (NOT installed today)
```

Provision separate nonproduction and production credentials, network access and storage. Never publicly expose MySQL, Redis, private uploaded files, Docker daemon, or administrative diagnostics. Terminate TLS at a trusted reverse proxy and configure trusted hosts/proxies, CSRF, HTTP-only secure cookies, and HTTPS redirects. Determine exact host names and infrastructure provider only after a reviewed deployment plan.

## Read-only readiness checks

`php artisan taskforge:readiness-check` is for private CLI/admin operator access. It checks configured database connectivity using `SELECT 1`, a non-mutating cache read, and refuses `sync`/`null` queues or debug-enabled configuration in the production environment. It only prints short PASS/FAIL messages and never exposes connection errors, credentials, or token material. It does **not** check queue worker liveness, mail transport, backup recoverability, or certificate validity.

```bash
php artisan taskforge:readiness-check
php artisan taskforge:operations-check
php artisan schedule:list
php artisan migrate:status
```

The existing `taskforge:operations-check` evaluates stale mail jobs, failed jobs and prune-scheduler heartbeat. It assumes the expected database-backed mail queue/receipt tables and compatible cache configuration; an operational failure must be triaged rather than bypassed. Do not expose these commands via unauthenticated HTTP endpoints or publicly readable logs. The built-in `/up` route is a lightweight liveness check, **not** proof of database/queue/worker readiness.

## Monitoring and alerting (must be provisioned separately)

| Signal | Collection & suggested response |
| --- | --- |
| HTTP 5xx and latency | Aggregate server/reverse-proxy metrics; investigate sustained errors and P95/P99 regression |
| MySQL connections, storage and slow queries | Provider/host metrics; alert on saturation, disk pressure and prolonged replication lag if applicable |
| Redis memory, eviction and latency | Alert before capacity exhaustion; isolate public network access |
| Mail/default queues, failed jobs and oldest job age | Periodically run private operations check; inspect supervised workers and retry only after idempotency review |
| Scheduler heartbeat | Monitor age of `taskforge:prune_last_run`; escalate if beyond expected daily window |
| TLS certificate expiry, HTTP redirects and private routes | Monitor externally from approved source with no credentials in URLs |
| Backup job success, verified encrypted archive and restore drills | Alert on missing backup, checksum mismatch, outdated recovery test or unachievable RPO/RTO |

No metrics exporter, pager integration, uptime monitor, external notifications, or recurring production job is installed by this PR. Assign owners, set alert destinations and thresholds only in a reviewed operations deployment. Sensitive data must be redacted from logs and traces.

## Production release sequence (manual, reviewed)

1. Confirm protected `main` and `development` branches, reviewed PRs, complete green CI, pinned npm/composer dependencies, vulnerability triage, security review, and approved maintainer sign-off.
2. Prepare infrastructure with least-privilege access and separate encrypted secrets. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URLs, secure session cookies, durable database and supervised queues.
3. Run the Phase 6 configuration preflight against a restricted candidate configuration; run private readiness checks against the provisioned isolated staging environment.
4. Create and verify encrypted staging backups. Practice restoration to a **different disposable destination** with no production mail/notifications and no inbound public traffic.
5. Review pending migrations for **existing-data safety**. Never use `migrate:fresh`, `down -v`, or uncontrolled `db:wipe` against valuable data.
6. Perform a controlled deployment with reviewed build artifact, restricted migration permissions, supervised queue/scheduler startup, smoke tests, and manual browser/accessibility checks.
7. Observe error rates, queue age, migration status, user flows, TLS and backup telemetry; roll back application release when safe, and plan forward-only schema remediation rather than assuming SQL rollback is safe.

**This sequence is only a runbook. No step has been performed against production.**

## Disaster recovery exercise

Set and approve service recovery objectives (RPO/RTO) and backup frequency/retention based on business needs. Include MySQL state, uploaded assets and other durable external storage, and document how to take a mutually consistent snapshot. Protect backup keys separately; test access to them by an authorized recovery operator. Verify checksum plus independently authenticated storage provenance; a checksum alone does not prevent malicious replacement.

Conduct an isolated, offline-style drill using synthetic or appropriately protected data. Restore to a disposable database and storage namespace, with firewall-denied production integrations, `MAIL_MAILER=array`, disabled workers and browser sessions, and unrelated credentials. Verify row counts, schema, membership authorization, attachment retrieval and key user flows. Measure actual recovery time and data loss against targets; collect redacted evidence and approvals. Do **not** perform the drill on production or overwrite source backups.

## Open release blockers

- [ ] PR #23 dependency/backup helpers merged and independently validated
- [ ] Real reviewed `package-lock.json`, clean `npm ci` and vulnerability triage
- [ ] Approved host/network/TLS design, secrets and least-privilege access
- [ ] Supervised queue/scheduler services, alerts and outage response owner
- [ ] Encrypted backup storage and proven isolated restore drill with measured RPO/RTO
- [ ] Manual staging rollout, auth isolation and accessibility/security checks
- [ ] Protected branches, release review and explicit production approval

Do not merge into `main`, deploy, send bulk email, or run destructive DB commands as part of this phase.
