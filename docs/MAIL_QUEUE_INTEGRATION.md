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
