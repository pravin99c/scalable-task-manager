# TaskForge Phase 4 — React + TypeScript workspace

This feature branch adds the first **working TaskForge team workspace** at \`/app\`. The pre-existing Blade administrator interface at \`/admin\` is intentionally preserved and remains accessible only to authorized superadmins.

## Scope

- Same-origin React 18 + TypeScript application, Vite and responsive dark UI.
- Session-backed login, registration, logout. The browser receives a session cookie with HTTP-only protection, and the in-memory CSRF token is refreshed after session regeneration. **No bearer token is persisted in localStorage or sessionStorage.**
- Project discovery, pagination/search, create/update/delete (owner only).
- Real Kanban task board with task creation/update/delete, native drag-and-drop and keyboard-accessible status selection. Owner/editor editing; viewer read-only task details. Task deletions require the project owner.
- Project member lists, role updates, removals (owner only) and owner invitation issuance/revocation.
- Verified-email workflow, invitation acceptance and email-change warnings.
- API loading, validation errors, permission failures, network errors and confirmation of destructive operations.
- React component/API tests and Laravel session integration tests.

## Installation

From a clean checkout:

\`\`\`sh
cp .env.example .env
composer install
npm install
php artisan key:generate
# Configure local MySQL/Redis credentials; see docs/LOCAL_DEVELOPMENT.md
php artisan migrate
npm run build
php artisan serve
\`\`\`

Open **http://localhost:8000/app** (or your configured Sail address, normally **http://localhost:8080/app**).

During development, run \`npm run dev\` and the Laravel app simultaneously, or use Sail to run the corresponding commands. \`@viteReactRefresh\` enables hot module replacement. React source lives under \`resources/js/taskforge\`, with the independent public entry \`resources/views/taskforge.blade.php\`. Existing \`resources/js/app.js\` and Blade admin assets remain in the build.

## Authentication / API contracts

The frontend uses Laravel's existing \`web\` session middleware for JSON routes at \`/app/api/session\`, \`/app/api/login\`, \`/app/api/register\` and \`/app/api/logout\`. The session endpoint supplies a CSRF token. Requests use same-origin cookies, the \`X-CSRF-TOKEN\` header, and \`X-Requested-With: XMLHttpRequest\`. Successful login/registration/logout rotates the token.

To authorize API requests, the Laravel application enables Sanctum stateful API middleware and uses the existing \`/api/v2/projects\`, \`/api/v2/tasks\` and invitation/email-verification endpoints. Existing \`/api/v1/projects/{project}/members\` endpoints supply member lists and owner-managed roles; legacy clients continue to work.

\`/api/v2\` pagination limits list results to 50 per page. The Kanban loader requests subsequent pages (up to 20 pages/1,000 tasks); it warns when more results remain. Very large projects need a dedicated server-backed filter/virtualization iteration.

## Security and limitations

- All authorization rules are enforced by Laravel policies, **not merely hidden buttons**. The UI hides edit actions for viewers and destructive actions for non-owners.
- The full-app CI must pass the frontend typecheck, component tests, \`npm run build\`, PHP tests, Docker startup and queue checks before PR approval.
- The frontend requires the Laravel API and database; it is **not a standalone static mockup**.
- The included concept image in the README is still labeled as a concept, not a screenshot of a running app. Replace it only after verifying actual frontend screenshots.
- Invitation and email-verification messages require a running database queue mail worker and a configured mail transport; local \`MAIL_MAILER=log\` can log single-use codes. These logs are sensitive and must not be published.
- The realtime Echo bootstrap runs only when a Reverb application key and host have been configured. Phase 4 does not claim that authorized realtime channels are deployed.
- The current UI uses browser-native drag-and-drop with a keyboard-accessible status select. Touch-first drag/drop, complete accessible modal focus management, browser-level E2E, accessibility auditing and production mail/queue operations still need dedicated validation.

## Validation commands

\`\`\`sh
npm install
npm run typecheck
npm test
npm run build
php artisan test
\`\`\`

CI builds the full Sail application and runs MySQL-backed integration checks as well. Never use \`migrate:fresh\` against an existing user database.

See [API v2](API_V2.md), [local development](LOCAL_DEVELOPMENT.md) and [queue integration checks](MAIL_QUEUE_INTEGRATION.md).
