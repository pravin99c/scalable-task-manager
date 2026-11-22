# TaskForge API — current development contract

This document reflects the Laravel routes in `routes/api.php` on the `development` branch. Authentication requires Laravel Sanctum bearer tokens.

## Endpoints

| Method | URL | Purpose |
| --- | --- | --- |
| POST | `/api/v1/auth/register` | Register |
| POST | `/api/v1/auth/login` | Login and issue token |
| POST | `/api/v1/auth/logout` | Revoke active user's tokens (authenticated) |
| GET | `/api/v1/user` | Current authenticated user |
| GET, POST | `/api/v1/projects` | List own projects / create |
| GET, PUT/PATCH, DELETE | `/api/v1/projects/{project}` | Authorized project operations |
| GET, POST | `/api/v1/tasks` | List tasks in own projects / create |
| GET, PUT/PATCH, DELETE | `/api/v1/tasks/{task}` | Authorized task operations |
| GET, POST | `/api/v1/tasks/{task}/comments` | Authorized comment access |

All non-login/non-registration endpoints require the correct authenticated user. Project/task cross-user access is forbidden until a dedicated project-membership feature exists.

## Authentication example

Replace the sample values with a **locally created, non-privileged test account**.

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"your-local-test-user@example.test","password":"YOUR_UNIQUE_LOCAL_PASSWORD"}'
```

Use the returned `data.access_token` only in local testing; never paste access tokens into commits, public issues, screenshots, or CI logs.

```bash
curl http://127.0.0.1:8000/api/v1/projects \
  -H 'Authorization: Bearer YOUR_LOCAL_TOKEN'
```

## Scope and maturity

The current branch has Laravel 11 services, project/task CRUD, comments, and foundations for queues and broadcasting. Existing documentation may describe features not fully validated; do not assume production readiness. See [Phase 1 security validation](docs/SECURITY_VALIDATION.md) and the [GitHub Actions workflow](.github/workflows/laravel-validation.yml).

Task updates trigger a broadcast event and queue logic, but outbound notifications and reliable real-time delivery remain future integration work.
