# TaskForge Phase 4.2 — Browser E2E and frontend resilience

This change introduces real Chromium browser tests against a running Laravel application, rather than relying solely on simulated network requests.

## Coverage

- Anonymous session, API access denial, and real CSRF-mismatch rejection (HTTP 419)
- Web-session login/logout, persistence across page reloads, and HTTP-only cookie
- New account registration and the unverified state
- Project creation and API-backed Kanban task create, edit, and status movement
- Invitation creation, member access and viewer read-only restrictions
- Email verification using a synthetic hashed challenge, then one-time invitation acceptance
- Safe queuing of a verification request without delivering an actual email
- Narrow-screen navigation and horizontal Kanban usability

## Fixture safety

\`TaskForgeE2eSeeder\` is **never registered with the default application seeder**. It will refuse to run unless BOTH \`APP_ENV=testing\` and \`TASKFORGE_ALLOW_E2E_SEED=1\`. CI creates a disposable SQLite file at \`database/taskforge_e2e.sqlite\`, runs migrations only against that isolated file, then seeds four synthetic \`example.test\` users. No real addresses or credentials are used.

The browser server runs in \`APP_ENV=local\` against the SAME disposable SQLite database, so Laravel's normal CSRF middleware remains active during browser tests. \`MAIL_MAILER=array\` and a database queue without a worker ensure **no external email is sent**. The synthetic verification and invitation tokens are fixtures (not secrets); their hashes are stored exactly as in the production flow.

The data seeder is not intended to be run on shared or valuable local databases. Never use E2E data to reset staging or production datasets.

## Run locally

From a private disposable checkout with PHP, Composer, Node 24 and Chromium available:

\`\`\`sh
composer install
npm install
npm run build
php artisan key:generate
npx playwright install chromium

touch database/taskforge_e2e.sqlite
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/taskforge_e2e.sqlite" \
  TASKFORGE_ALLOW_E2E_SEED=1 QUEUE_CONNECTION=database MAIL_MAILER=array \
  php artisan migrate:fresh --force

APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/taskforge_e2e.sqlite" \
  TASKFORGE_ALLOW_E2E_SEED=1 QUEUE_CONNECTION=database MAIL_MAILER=array \
  php artisan db:seed --class=TaskForgeE2eSeeder --force

APP_ENV=local DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/taskforge_e2e.sqlite" \
  SESSION_DRIVER=database QUEUE_CONNECTION=database MAIL_MAILER=array \
  npm run test:e2e
\`\`\`

Only run \`migrate:fresh\` against this isolated disposable SQLite database. Set \`APP_URL=http://127.0.0.1:8085\` when running Playwright locally if your environment overrides it.

## Frontend resilience improvements

- Modal focus is trapped and restored on close, including Escape handling and a locked body scroll.
- Changing projects clears the previous project's tasks and membership data instead of briefly showing stale permissions.
- CSRF failures refresh the session token and retry **once only**. Persistent failures surface a visible error instead of silently retrying.
- Off-screen mobile navigation is not keyboard-focusable while hidden; task status can be changed by a standard select control as a touch/keyboard alternative to drag/drop.

## CI efficiency and diagnostics

The existing Laravel workflow now installs one Chromium browser, creates the disposable SQLite fixture, runs Playwright once, and continues to the full Sail build and MySQL/queue checks. The Sail step has a bounded 14-minute timeout and emits Compose diagnostics on failure. The previous successful Phase 4.1 run showed the main delay came from building the full Sail image on a fresh runner. The worker/scheduler checks remain unchanged.

The existing CI trigger policy runs feature changes only via PRs (not additional feature-branch pushes) and cancels older in-progress runs on the same PR. After approval, a merge to \`development\` naturally runs its separate push CI. GitHub notification preferences are managed on GitHub, not by this application.

## Remaining validation

This suite does not replace accessibility auditing with assistive technologies, cross-browser testing (Firefox/WebKit), touch-first drag-and-drop on physical devices, an end-to-end staging mail provider, or deployment security review. CI outcomes must be confirmed for the latest PR commit before merge.
