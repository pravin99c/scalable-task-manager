# TaskForge API v2 — Pagination & Invitations

**Status: Proposed feature branch** (not yet merged). Existing `/api/v1` endpoints remain unchanged. Only endpoints below use the version 2 envelope.

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
| POST | `/api/v2/invitations/accept` | Authenticated invitee using same account email |

For lists, `page` is an integer 1–10,000 and `per_page` is 1–50. Projects also accept `search` (name, max 120 characters); tasks accept `project_id`, `status`, and `priority`. Lists use stable ascending `id` ordering. No implicit global collection is returned.

## Invitation lifecycle

1. Project owner POSTs `{"email":"colleague@example.test","role":"editor"}`. Allowed roles: **editor** and **viewer** only.
2. Server generates a cryptographically random 80-character token, stores only its SHA-256 hash, and sends the token by configured email transport. API **never** returns the plaintext token.
3. Invitee registers or signs in with the **same email address**, then POSTs `{"token":"..." }` to `/api/v2/invitations/accept`.
4. Token expires after **seven days**. Successful acceptance adds project membership in one database transaction. Expired, revoked, mismatched, or previously accepted invitations are rejected.
5. Owner can revoke outstanding invitations. Issuing another invitation to the same email/project revokes earlier pending tokens.
6. Direct `/api/v1/projects/{project}/members` endpoints remain available for existing clients; invitation acceptance does not override an existing member's role.

**No invitation frontend and no account verification system are implemented in this PR.** Authentication must be secured with email ownership verification **before** using this invitation flow with a public registration service. Requiring an equal database email alone does not establish that the account holder owns the mailbox. This is a release blocker.

Email delivery is synchronous in this initial implementation. The local `MAIL_MAILER=log` development setting logs the message including its bearer invitation token to local application logs; protect those logs. Before production deployment, add an outbox / after-commit delivery design, a real email provider, rate controls for invitation abuse, monitoring, and an end-to-end verification flow.

## Validation

GitHub Actions performs PHP syntax checks, disposable MySQL/Redis startup tests, clean MySQL migration and PHPUnit feature tests. These checks do **not** certify real-world email deliverability, staging-data migration safety, or production readiness.

Existing `/api/v1` response formats and routes remain unchanged for compatibility.
