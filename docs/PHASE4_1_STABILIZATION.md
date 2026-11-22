# Phase 4.1 — SPA session stabilization

This small, reviewable hardening patch fixes the CI failure introduced in Phase 4 and reduces notification-heavy duplicate CI runs.

## Security behavior

- \`GET /app/api/session\` now resolves **only the Laravel web guard**. A Sanctum API bearer token is not a browser login.
- Browser login and registration store a per-user session security version. A stale session version causes logout, invalidation and a guest session response. This is important for non-database session drivers too.
- An email change always logs out the matching web guard identity even when the API call is considered stateless. When the request has a session, the session is explicitly invalidated and the CSRF token regenerated.
- Existing bearer-token API clients remain supported; their tokens are revoked by the existing email-change service.
- The existing React dashboard and admin routes are preserved. The error notification is raised above modal overlays so users can see validation failures.

## Tests

The Laravel session tests cover:
1. Web-session login into Sanctum API v2.
2. New account registration and queued verification.
3. Email change causing browser logout.
4. API bearer tokens not granting an SPA browser session.
5. Stale session-version rejection.
6. Wrong password rejection.

The existing frontend type checks, React tests, production asset build, SQLite suite, fresh MySQL migrations, full Sail smoke test, database queue and scheduler checks must pass on the **latest PR SHA** before the patch is merged.

## Fewer GitHub notification emails

Workflow triggering is deliberately limited to pull requests against \`development\`/\`main\` and pushes to those long-lived branches. In-progress older PR runs are canceled by a concurrency group. The patch does **not** control GitHub notification preferences or send project emails. If notifications still arrive, configure them from GitHub Settings → Notifications. Do not automatically rerun failed jobs; inspect the failure once and batch fixes into a single follow-up commit.

## Rollout

Use a normal draft PR into \`development\`. Do not merge to \`main\` without explicit approval. Verify real browser cookies/CSRF using a safe local or staging mailer before production use. Never publish email tokens or queue payloads.
