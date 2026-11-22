# Phase 6 — Release readiness (in progress)

This phase is **not** a release, production deployment, or security certification. As of 2026-11-22, PR #19 (Phase 5) is merged into `development`, and its post-merge CI run [45015195721](https://github.com/pravin99c/scalable-task-manager/actions/runs/45015195721) passed. `main` is **not** to be merged or deployed automatically.

## First increment: read-only configuration preflight

Run against a separately created candidate production configuration, **never** a committed secret:

```bash
php scripts/production-preflight.php /secure/path/to/candidate.env
php artisan test --filter=ProductionPreflightTest
```

Checks include production/debug settings, HTTPS application URL, 32-byte Laravel application key shape, secure and HTTP-only cookies, CSRF-compatible SameSite settings, persistent sessions/queues, nontrivial database password, log level, explicit mail transport and sender, and optional Reverb TLS/configuration. No network access, real emails, database writes, migrations or deployments are performed. The report contains **check names and pass/fail only**, not secret values.

This is a *static* configuration check: it cannot prove that secrets are unique, outbound email domains are verified, TLS certificates are valid, load balancers trust the correct proxies, workers are supervised, or backups are restorable. If WebSockets are disabled, polling remains available.

## Deployment gates (not yet certified)

- [ ] PR CI including new preflight unit tests is green; run on an isolated disposable environment.
- [ ] Generate a committed, reproducible npm lockfile and change CI from `npm install` to `npm ci` after testing; currently `package-lock.json` is absent.
- [ ] Review `composer.lock` and npm dependencies for known vulnerabilities; triage findings before release.
- [ ] Enable `main` and `development` protection/rulesets (required status checks, review, force-push/delete restrictions). Repository rulesets are currently empty; owner action required.
- [ ] Validate server TLS, reverse proxy, trusted hosts/proxies, session/cookie settings, per-environment secrets, rate limits, and least-privilege database access.
- [ ] Design an **encrypted**, monitored, restorable backup workflow, and run a controlled restore drill on nonproduction infrastructure. Do not use destructive database resets on real data.
- [ ] Validate existing-data migrations, forward-only rollback plans, retention/pruning for activity/notifications, storage monitoring and alarms.
- [ ] Validate scheduler, queue workers, failed-job handling, idempotent mail delivery and restart behavior using a safe non-delivery transport before enabling production mail.
- [ ] Test phase-5 API revocation/isolation, WebSocket membership changes and reconnection in staging; Reverb is **not installed** and must not be assumed operational.
- [ ] Perform manual accessibility, mobile, cross-browser, performance and basic incident/recovery tests with reproducible evidence.
- [ ] Capture genuine staging UI screenshots, document deployment/rollback instructions, and prepare a release checklist; do not label concept art as screenshots.
- [ ] Obtain explicit maintainer approval before any merge to `main`, real email delivery, or production deployment.

## Run discipline

Use a feature branch targeting `development`; produce a single draft PR for review and avoid manual CI reruns unless a failure requires it. Existing production data and branch history remain untouched. Do not send email notifications through the application while validating this phase.

## Second increment: HTML workspace response hardening

The TaskForge HTML routes `/app` and `/app/{path}` (excluding `/app/api/*`) now attach conservative defense-in-depth response headers through `SecureWorkspaceResponse`: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, and a restrictive camera/microphone/geolocation `Permissions-Policy`. Existing values are preserved if an upstream middleware already supplied them. The middleware is deliberately scoped to workspace HTML so current admin and JSON API behavior is unchanged.

This is **not** a complete browser security policy. A reviewed Content-Security-Policy (including development Vite and production asset/nonces), HSTS at the HTTPS reverse proxy, certificate monitoring, and explicit trusted-proxy/host configuration remain release gates. Do not enable HSTS for localhost or HTTP development. Feature tests exercise root and nested workspace routes plus API isolation; CI is the source of truth for validation.
