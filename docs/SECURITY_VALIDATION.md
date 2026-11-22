# Phase 1 Security Validation Guide

This document describes security-hardening changes proposed in the draft PR based on `development`. It is not a declaration of production readiness.

## Scope of fixes

- Project ownership policies enforce that only a project's creator can view, edit, or delete it.
- Task ownership is inherited from the parent project. Comments require task authorization.
- Collection endpoints use project-owner-scoped database queries; global Redis list-cache keys are removed.
- Task creation validates that the target project belongs to the request user.
- Until a membership system is designed, tasks may be assigned only to the current user.
- Private project broadcast subscriptions validate project ownership.
- The legacy global admin UI is explicitly **superadmin-only** until its own per-resource permissions model is completed.
- Demo administrator accounts no longer have publicly known fixed passwords.
- Historical role/permission migrations are preserved while parent-table creation is moved before foreign-key pivots.

## Fresh local setup

Do not point any commands below at production, a shared database, or real user data.

1. Check out `fix/p0-authorization-and-validation` (or the PR branch).
2. Install dependencies in a supported PHP 8.2+ Laravel 11 environment.
3. Copy `.env.example` to `.env`, set a **local-only** database connection, and generate an `APP_KEY`.
4. Run `php artisan migrate:fresh` **only for an empty disposable local development database**.
5. To provision a local superadmin, create a long, unique password and put these settings in local `.env` (never commit it):
   - `TASKFORGE_BOOTSTRAP_ADMIN_EMAIL=<unique-local-address>`
   - `TASKFORGE_BOOTSTRAP_ADMIN_PASSWORD=<random-at-least-16-characters>`
6. Execute `php artisan db:seed --class=RolePermissionSeeder` followed by `php artisan db:seed --class=AdminUserSeeder`.
7. Run `php artisan test` and examine the CI workflow for MySQL migration and syntax verification.

**No default credentials are created** by `AdminUserSeeder`; it only supports `APP_ENV=local` and requires explicitly configured secrets. If any old sample password was used on a real system, rotate it and invalidate affected sessions. Old seeded accounts are not automatically rotated by this patch.

## Known intentional restrictions and follow-up

- Cross-project collaboration is not available yet: the account that owns a project is the only authorized actor. Workspace membership and sharing need separate schema, policies and tests.
- Administrative users with only the old `users.role=admin` marker no longer have access to global admin pages. Only an explicit `superadmin` role pivot grants admin console access.
- Existing clients expecting cross-user access or global task lists need to adjust; do not weaken authorization to preserve old behavior.
- The administrator UI still needs broader functional tests, scoped manager roles and a secure role-management design.
- Task queues currently simulate assignment email delivery; not a production notification system.
- Global app secrets, production passwords, and third-party credentials must never be committed.
- CI performs a clean **disposable MySQL migration**, syntax checks and SQLite-backed Laravel tests. It does not validate migrations against an existing production database.

## Required checks before merge

- GitHub Actions must pass on the PR's latest commit.
- Review authorization checks (project CRUD, task CRUD, comments, admin access, broadcast channel).
- Verify safe upgrade on a backed-up staging copy before migrating any real MySQL data.
- Confirm existing seeded roles and user-to-role pivots are coherent, and review any account recovery requirements.
- Re-run tests with queue workers and broadcasting enabled during a later integration phase.
- Require human code review; do not mark this PR as production-ready from CI alone.
