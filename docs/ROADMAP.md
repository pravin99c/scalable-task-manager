# TaskForge — Engineering Roadmap

> This plan is based on the current `development` branch (Laravel 11 source). It describes **planned work**, not completed functionality or a verified deployment.

## Priorities

### P0 · Establish a trustworthy baseline
- [ ] Verify the entire dependency install, migrations and existing PHPUnit suite on a clean machine.
- [ ] Consolidate the duplicate Docker Compose files into a documented, reproducible setup.
- [ ] Record exact installed PHP/Laravel dependencies and baseline test results.
- [ ] Eliminate fixed demo administrator passwords and avoid seeding predictable production credentials.
- [ ] Fix project/task object authorization and verify that unauthorised users receive 403/404 responses.
- [ ] Scope project/task queries and Redis cache keys by authorized user/workspace.
- [ ] Restrict broadcast channel access to actual project members.
- [ ] Align direct `users.role` checks and role/permission membership into a consistent policy layer.
- [ ] Ensure protected admin actions check appropriate permissions.

**Acceptance:** authenticated users cannot read or change another account's private projects, tasks, comments, logs or events; clean setup, migration and test runs are reproducible.

### P1 · Complete domain and API
- [ ] Define the ownership model (workspaces, memberships, role assignments and invitations).
- [ ] Add policy-protected CRUD with validated assignee and project relationships.
- [ ] Implement pagination, filtering and stable API resources.
- [ ] Introduce clear task status transitions and transactional updates.
- [ ] Add auditable task changes and consistent error formats.
- [ ] Publish versioned OpenAPI documentation, examples and migration notes.

**Acceptance:** authorization/validation tests cover positive, negative and cross-workspace scenarios; documented API examples match real routes.

### P2 · Improve experience and background workflows
- [ ] Plan a responsive React + TypeScript frontend, migrating Blade features incrementally.
- [ ] Implement dashboards, list/board views, task details, search and accessible forms.
- [ ] Add queue-backed notifications and retries with failure tests.
- [ ] Add project-scoped real-time broadcasts after authorization checks are complete.
- [ ] Include actionable UI error states and loading/accessibility checks.

**Acceptance:** core user flows work end-to-end against a seeded demo environment.

### P3 · Measurable quality and public showcase
- [ ] Run unit, feature, integration and end-to-end tests in GitHub Actions.
- [ ] Add formatting, static analysis, dependency/security checks and documented contribution guidelines.
- [ ] Benchmark realistic loads with published environment, data volume, tooling and P95/P99 values.
- [ ] Add architecture diagrams, real UI screenshots, and a demo environment without real credentials or private data.
- [ ] Publish tagged releases and a changelog when exit criteria pass.

**Acceptance:** CI is green, setup is reproducible, known P0 vulnerabilities are resolved, and all published performance claims have linked evidence.

## Branch strategy
- `main`: public landing documentation until verified work is ready.
- `development`: current application and development baseline.
- Feature/fix branches: focused changes reviewed via pull requests.
- Do not merge `development` into `main` merely to make the profile look fuller; complete security checks first.

## Release policy
This repository is **work in progress**, not production certified. Any previous claims of "production-ready", "immutable" audit logs, reliable broadcast delivery, or numerical P99 gains must be independently verified before reuse in public project copy.

## Security
Never commit `.env`, application keys, access tokens, third-party credentials, broker information, real customer data, or fixed production passwords. Report potential vulnerabilities privately to the maintainer rather than opening public proof-of-concept issues.
