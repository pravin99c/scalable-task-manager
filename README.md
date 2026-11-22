# TaskForge — Secure Project & Task Management

> A full-stack team workspace built with Laravel, React and TypeScript. Manage projects, move tasks across a Kanban board, collaborate with role-based permissions, and invite teammates through verified email.

[![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React 18](https://img.shields.io/badge/React-18-149ECA?logo=react&logoColor=white)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-Frontend-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-0B7285)](LICENSE)
[![CI · development](https://github.com/pravin99c/scalable-task-manager/actions/workflows/laravel-validation.yml/badge.svg?branch=development)](https://github.com/pravin99c/scalable-task-manager/actions/workflows/laravel-validation.yml?query=branch%3Adevelopment)

**Project status:** Active development. The application and automated browser tests are implemented; production deployment and independent security review are **not** certified.

## Workspace and dashboard

**The React + TypeScript workspace is implemented at `/app`.** It uses real Laravel APIs, not hard-coded demo cards.

![TaskForge dark dashboard design concept](docs/images/taskforge-dashboard-concept.webp)

> **Design concept, not a live application screenshot.** The image above illustrates the visual direction. Actual verified screenshots should be added after a staging/browser review.

### What works today

- **Authentication:** register, sign in, sign out, persistent first-party Laravel web sessions, CSRF protection, and session invalidation after email changes.
- **Projects:** create, view, search, paginate, edit and delete projects with server-enforced permissions.
- **Kanban board:** task creation/editing, priorities, assignees, due dates, progress indicators and `TODO` / `IN_PROGRESS` / `DONE` columns. Change status via drag-and-drop **or** a keyboard/touch-friendly selection control.
- **Teams:** owner/editor/viewer project roles, owner-managed membership, permission-aware controls and read-only access for viewers.
- **Secure invitations:** invite by email, list/revoke invitations, verify address ownership and accept an invitation with a single-use token. Tokens are hashed in storage and expire.
- **Queued mail:** after-commit database-queued verification/invitation messages, bounded retries, expiry cleanup and operational checks. Delivery still requires a correctly configured worker and mail transport.
- **Usability:** responsive dark layout, mobile navigation, loading and error states, dialog focus management, and validation feedback.
- **Legacy interfaces:** the superadmin-only Blade UI and version 1 API continue to exist alongside the new workspace and API v2.

### Permission model

| Role | View project/tasks | Create or update tasks | Manage members/invitations | Delete project |
| --- | :---: | :---: | :---: | :---: |
| Owner | Yes | Yes | Yes | Yes |
| Editor | Yes | Yes | No | No |
| Viewer | Yes | No | No | No |
| Non-member | No | No | No | No |

**Authorization is enforced in Laravel policies and request validation**, not only by hidden frontend buttons.

## Technology

| Layer | Stack |
| --- | --- |
| Frontend | React 18, TypeScript, Vite, responsive CSS, Lucide icons |
| Backend | Laravel 11, PHP 8.4 in CI, Laravel Sanctum |
| Data and queues | MySQL 8.4, Redis 7.4; database-backed durable mail queue |
| Local environment | Laravel Sail, Docker Compose |
| Testing | PHPUnit/Laravel features, Vitest + Testing Library, Playwright (Chromium) |
| Automation | GitHub Actions: frontend, backend, MySQL, full Sail, browser, queue and scheduler validation |

## Run locally with Docker

**Prerequisites:** Git, Docker with Compose v2, PHP/Composer for dependency installation, and Node.js/npm (CI uses Node 24). Docker publishes the development ports to `127.0.0.1` only.

```bash
git clone https://github.com/pravin99c/scalable-task-manager.git
cd scalable-task-manager
git switch development

composer install
cp .env.example .env
```

Before starting the containers, edit your **untracked** `.env` and set two **different, strong random local passwords**:

```dotenv
DB_PASSWORD=your-unique-local-database-password
DB_ROOT_PASSWORD=your-different-unique-root-password
```

These are placeholders, not passwords to reuse. Do not commit `.env`. Then:

```bash
npm install
npm run build

docker compose -f compose.yaml config --quiet
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

Open **http://127.0.0.1:8080/app** to register and use TaskForge. The legacy administrator login is **http://127.0.0.1:8080/admin/login** and requires a separately authorized administrator account.

For frontend hot reload, run `npm run dev` alongside the Laravel environment. For troubleshooting, alternate Composer setup and safe Docker shutdown, see [local development](docs/LOCAL_DEVELOPMENT.md).

> Never run `migrate:fresh`, `sail down -v`, or database-reset commands against real or irreplaceable data. Use fresh disposable databases for destructive tests.

### Email and background jobs

The example environment uses `MAIL_MAILER=log`: local outgoing messages are recorded in application logs, **not delivered to real inboxes**. Treat logged verification/invitation tokens as sensitive. To process the application's database mail queue and expiry scheduler in a suitable local environment, run these in separate terminals:

```bash
./vendor/bin/sail artisan queue:work database --queue=mail --tries=5
./vendor/bin/sail artisan schedule:work
```

Production mail delivery requires an explicit mail-provider configuration, a supervised worker, scheduler monitoring and alerting. Queue receipt/idempotency checks provide best-effort duplicate suppression; **exactly-once external email delivery is not guaranteed**. See [mail queue integration](docs/MAIL_QUEUE_INTEGRATION.md).

## API overview

The React workspace uses same-origin Laravel session cookies and CSRF tokens. Separate API clients can continue to use the Sanctum token-based `/api/v1` interface.

| Area | Representative routes |
| --- | --- |
| Browser session | `/app/api/session`, `/app/api/login`, `/app/api/register`, `/app/api/logout` |
| API v2 projects and tasks | `/api/v2/projects`, `/api/v2/tasks` |
| Team membership (v1 compatibility) | `/api/v1/projects/{project}/members` |
| Invitations | `/api/v2/projects/{project}/invitations`, `/api/v2/invitations/accept` |
| Email ownership | `/api/v2/auth/email/verification/send`, `/api/v2/auth/email/verify`, `/api/v2/auth/email` |

API v2 list responses use a consistent `data` / `meta` / `links` envelope and bounded pagination. See [API v2 reference](docs/API_V2.md) and [v1 usage examples](API_USAGE.md). Some older documents retain historical phase wording; the current code and CI are the source of truth for implemented behavior.

## Testing and CI

Run the fast checks locally after dependencies are installed:

```bash
npm run typecheck
npm test
npm run build
./vendor/bin/sail artisan test
```

The GitHub workflow **Laravel security validation** additionally checks PHP syntax, isolated SQLite Laravel tests, fresh MySQL migrations, MySQL/Redis health, a complete Sail application HTTP smoke test, database queue integration and scheduler operations. It also runs **9 Playwright Chromium end-to-end browser tests** for sign-in/out, sessions and CSRF, registration, projects, Kanban tasks, permissions, invitations, email verification and mobile navigation.

The browser suite uses a separately named **disposable SQLite database**, synthetic `example.test` accounts and hashed test tokens, with `MAIL_MAILER=array` and no sending worker. **No external email is sent by those tests.** Never run its database reset/seed commands against an ordinary application database.

For the exact isolated E2E procedure, see [Phase 4.2 browser validation](docs/PHASE4_2_BROWSER_VALIDATION.md). For the prior workspace setup, see [Phase 4 frontend notes](docs/FRONTEND_PHASE4.md).

## Current milestone and next steps

**Implemented through Phase 4.2:** API validation and membership; verified-email invitation flow and secure mail queue; React workspace; session hardening; and browser E2E coverage. [Phase 4.2 PR #15](https://github.com/pravin99c/scalable-task-manager/pull/15) passed the full PR CI run, including Sail and queue checks.

**Priorities before the next release:**

1. Confirm latest post-merge CI on both `development` and `main`, and require passing checks for release PRs.
2. Configure GitHub branch rulesets for both branches: review/required checks, block force pushes and prevent deletion. These settings require repository-owner action.
3. Perform manual staging and device-based browser/accessibility testing, with real but controlled queue/worker/scheduler validation.
4. Add a reproducible JavaScript dependency lockfile, dependency/security auditing, deployment documentation, verified real UI screenshots and a release checklist.
5. **Phase 5 (planned):** authorized real-time task collaboration and notifications via Redis/Reverb, with permission tests and operational monitoring.

Real-time broadcasting, production hosting, performance benchmarks and cross-browser accessibility certification are **not claimed as completed**.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md), [repository governance](docs/REPOSITORY_GOVERNANCE.md) and the [historical engineering roadmap](docs/ROADMAP.md). Please report sensitive vulnerabilities privately rather than publishing credentials or exploit details in an issue.

**License:** [MIT](LICENSE).
