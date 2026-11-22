# Phase 3 follow-up: mail queue and full Docker integration

This branch adds automated integration checks to the verified-email and invitation foundation in main.

## Real queue checks

The test suite uses a real database queue (not Queue::fake) to verify:

- Issuing an email verification challenge writes both challenge and encrypted queued job.
- A genuine queue pop executes the mailable and removes the job.
- Revoked and expired tokens are not emailed when their job is eventually processed.
- A rolled-back transaction removes both the challenge and queued job.
- A mail transport failure propagates to the worker's retry mechanism; retry/backoff configuration is checked.
- Unauthorized identities cannot accept invitations intended for someone else.

All tokens are disposable test values. Production credentials and emails are not used.

## Container checks

CI starts MySQL 8.4 and Redis 7.4, then builds and boots the actual PHP 8.4 Sail app. It validates application migrations and expects:
- The root HTTP endpoint to respond 200/302.
- An unauthenticated API v2 projects call to respond 401.
- Database-backed queue integration tests against disposable MySQL.

This complements the SQLite feature tests. CI must pass on the **latest commit**, not merely a previously successful branch commit.

## Threats to review separately

- **Transport idempotency:** SMTP may deliver successfully before the worker acknowledges the job, resulting in duplicate emails. Single-use tokens still prevent replay after acceptance.
- **Concurrency:** Per-recipient invitation limits currently lock the issuing owner, not a global recipient row; simultaneous invitations by different owners may exceed the intended recipient rate. Add a cross-owner concurrency limiter before internet exposure.
- **Email-change and session lifecycle:** Current account email changes clear verification, but existing sessions, memberships and outstanding invitations require a defined revocation/reverification policy.
- **Mailbox logs:** Local log mailer logs one-time codes in plaintext; protect logs and never include them in public artifacts.
- **Operations:** Configure trusted reverse proxies, worker supervision, retry alerts, scheduler uptime, failed-job review, and a non-log production mailer.
- **Existing database migrations:** A fresh CI MySQL migration does not prove rollback or safety for a real installed database. Verify against a backed-up staging copy.

No feature on this branch is certified for production deployment.

## Phase 3.5 review additions

The Phase 3.5 feature branch introduces a **shared invitation-rate-limit lock table**. Each canonicalized recipient, issuing owner and project has a unique SHA-256 lock key. All issuing owners acquire the same recipient row in sorted key order and evaluate the past 24-hour quota while holding the row locks, instead of only locking their individual issuer accounts. The database transaction retries deadlocks up to three times. MySQL integration exercises real table-backed quota enforcement.

**Account email changes:** An authenticated \`PATCH /api/v2/auth/email\` request requires the current password and a new unique email address. The change is atomic, clears verification, queues a fresh proof challenge and revokes existing Sanctum personal access tokens, database sessions, membership entries, task assignments to projects where the account was a member, pending invitations addressed to the old mailbox, pending invitations issued by that account and old verification codes. Admin user editing uses the same change service. Admin web sessions also carry a session-version check. Existing owned projects are retained. All clients must sign in again; changing an email can deliberately revoke team access.

**Mail delivery receipt:** \`delivered_at\` is persisted after a successful send. A later serialized retry skips jobs with a receipt or invalid/expired tokens. This provides *best-effort duplicate suppression*, NOT exactly-once SMTP semantics. A process crash after provider acceptance but before recording delivery can still cause a duplicate email. Production needs delivery-provider reconciliation/alerting and a suitable risk/UX policy.

**Operations:** \`php artisan invitations:prune\` now writes a scheduler heartbeat. \`php artisan taskforge:operations-check\` checks for a stale mail job backlog, failed jobs and an absent prune heartbeat, returning a nonzero exit code on problems. In CI, the heartbeat uses a shared MySQL cache store so independent command invocations observe the same value. Use a persistent cache in deployment, and supervise both scheduler and mail worker. Do not expose this command publicly.

**Remaining validation:** The automated checks demonstrate MySQL migrations and application startup on a disposable database, not upgrade safety on real datasets or live mail deliverability. Review concurrency across independent MySQL connections and transport crash windows before production.
