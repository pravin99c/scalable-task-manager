# TaskForge API v2 — Pagination & Invitations

**Status: Phase 3 feature branch under review** (not yet merged). Existing `/api/v1` endpoints remain unchanged. Only endpoints below use the version 2 envelope.

## Version 2 API response contract

Successful item responses:

```json
{
  "data": {"id": 1, "name": "Example"},
  "meta": null,
  "links": null
}
```

Successful paginated responses:

```json
{
  "data": [],
  "meta": {"current_page": 1, "per_page": 15, "last_page": 1, "total": 0, "from": null, "to": null},
  "links": {"first": "...", "last": "...", "prev": null, "next": null}
}
```

Errors returned by v2 routes use `data: null`, `message`, `errors`, `meta: null`, and `links: null`. Status codes distinguish validation (422), unauthenticated (401), forbidden (403), not-found (404), and throttled (429) requests. This excludes unhandled server errors and routes outside `/api/v2` until explicitly verified.

## Endpoints

| Method | Route | Access |
| --- | --- | --- |
| GET | `/api/v2/projects` | Authenticated; only owned/shared projects, default 15 per page |
| POST | `/api/v2/projects` | Authenticated account |
| GET, PUT/PATCH, DELETE | `/api/v2/projects/{project}` | Existing project policy |
| GET | `/api/v2/tasks` | Authenticated; only accessible tasks |
| POST | `/api/v2/tasks` | Owner/editor of specified project |
| GET, PUT/PATCH, DELETE | `/api/v2/tasks/{task}` | Existing task policy |
| GET | `/api/v2/projects/{project}/invitations` | Project owner only |
| POST | `/api/v2/projects/{project}/invitations` | Project owner only |
| DELETE | `/api/v2/projects/{project}/invitations/{invitation}` | Project owner only, matching project |
| POST | `/api/v2/invitations/accept` | Authenticated invitee with verified matching email |
| POST | `/api/v2/auth/email/verification/send` | Authenticated unverified user requests a new code |
| POST | `/api/v2/auth/email/verify` | Authenticated user confirms email ownership with a one-time code |

For lists, `page` is an integer 1–10,000 and `per_page` is 1–50. Projects also accept `search` (name, max 120 characters); tasks accept `project_id`, `status`, and `priority`. Lists use stable ascending `id` ordering. No implicit global collection is returned.

## Invitation lifecycle

1. Project owner POSTs `{"email":"colleague@example.test","role":"editor"}`. Allowed roles: **editor** and **viewer** only.
2. Server generates a cryptographically random 80-character token, stores only its SHA-256 hash, and sends the token by configured email transport. API **never** returns the plaintext token.
3. Invitee registers or signs in with the **same email address**, then POSTs `{"token":"..." }` to `/api/v2/invitations/accept`.
4. Token expires after **seven days**. Successful acceptance adds project membership in one database transaction. Expired, revoked, mismatched, or previously accepted invitations are rejected.
5. Owner can revoke outstanding invitations. Issuing another invitation to the same email/project revokes earlier pending tokens.
6. Direct `/api/v1/projects/{project}/members` endpoints remain available for existing clients; invitation acceptance does not override an existing member's role.

## Phase 3: verified email and durable mail queue

1. New accounts register through the existing v1 API and receive an email-ownership challenge automatically. Verification tokens are SHA-256 hashed in the database, are single-use and expire in **30 minutes**.
2. An authenticated account may request another token at `POST /api/v2/auth/email/verification/send` (up to three times per 10 minutes per user and route-level IP throttling). Previously pending verification challenges are invalidated.
3. The account submits the code via `POST /api/v2/auth/email/verify` with `{"token":"..." }`. Verification succeeds only for the matching account and matching unchanged email.
4. Only verified accounts may accept project invitations. Existing v1 direct membership writes also refuse to add unverified recipient accounts.
5. Invitation issuance now requires a verified project owner and is rate-limited to **3 per recipient email per 24 hours**, **20 per project per 24 hours**, and **50 per issuing owner per 24 hours**. Existing route-level throttling still applies.
6. Invitation tokens and verification tokens are delivered via the durable **database** queue with a ciphertext-only job payload; the enqueue operation shares the SQL transaction which creates the token record, so neither is visible before commit. Do **not** run a mail worker on an untrusted database.
7. The `DeliverTaskForgeMail` job re-checks token status before sending and retries transport failures (five total attempts with backoff). Failed jobs are retained in the application's `failed_jobs` table for operator review. A token may be emailed more than once if transport succeeds before a retry; the token itself remains single-use.
8. `php artisan invitations:prune` marks expired invitations and removes completed/expired records older than 30 days. The scheduler runs it daily at 03:10 **only while the Laravel scheduler is running**.

### Local operations

```sh
./vendor/bin/sail artisan queue:work database --queue=mail --tries=5
./vendor/bin/sail artisan schedule:work
./vendor/bin/sail artisan invitations:prune
./vendor/bin/sail artisan queue:failed
```

Mail dispatch is **queued, not synchronous**. For a local log mailer, `storage/logs/laravel.log` can contain the emailed one-time token; protect logs, rotate them and never publish them or include them in public bug reports. Configure a real outbound email service before offering invitations to others.

The initial email-verification workflow is API-only; there is no frontend or public deployment yet. A production mailer, scheduler/worker supervision, recovery of failed delivery jobs, monitoring, abuse telemetry, trusted proxy settings, and staging upgrade testing remain required. UI clients must explain that verification email delivery is asynchronous.

## Validation

GitHub Actions performs PHP syntax checks, disposable MySQL/Redis startup tests, clean MySQL migration and PHPUnit feature tests. These checks do **not** certify real-world email deliverability, staging-data migration safety, or production readiness.

Existing `/api/v1` response formats and routes remain unchanged for compatibility.
