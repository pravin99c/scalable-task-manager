# TaskForge · Scalable Task Manager

> A Laravel project and task management API exploring secure collaboration, role-aware access, and maintainable backend architecture.

[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-0B7285)](LICENSE)
![Project maturity](https://img.shields.io/badge/Status-Development%20%2F%20Security%20Review-F59E0B)

## Dashboard preview

![TaskForge proposed dark dashboard and Kanban design](docs/images/taskforge-dashboard-concept.webp)

> **Design concept — not a screenshot of a working UI.** The current application includes a Blade admin interface. The dashboard and Kanban design above illustrates a planned React/TypeScript experience.

## What is implemented

The codebase contains a Laravel 11 API, Laravel Sanctum authentication, project/task CRUD, comments, activity logging, PHP unit and feature tests, and Docker development configuration. The project-membership foundation defines **owner**, **editor**, and **viewer** access levels.

| Role | Read projects and tasks | Create / edit tasks | Manage members | Delete project |
| --- | --- | --- | --- | --- |
| Owner | Yes | Yes | Yes | Yes |
| Editor | Yes | Yes | No | No |
| Viewer | Yes | No | No | No |
| Non-member | No | No | No | No |

Project membership uses explicitly authorized existing users. **Invitation emails, full workspace tenancy, polished React UI, and production deployment are not complete.** The legacy global administrator UI remains restricted to explicitly assigned superadmins.

## Development quick start

```bash
git clone https://github.com/pravin99c/scalable-task-manager.git
cd scalable-task-manager
composer install
cp .env.example .env
php artisan key:generate
# Configure an isolated local database in .env first.
php artisan migrate
php artisan test
```

For a local Docker setup, follow the [TaskForge local development guide](docs/LOCAL_DEVELOPMENT.md) for the canonical Compose file, required non-default passwords, and repeatable commands. See also the [security validation guide](docs/SECURITY_VALIDATION.md). Avoid running destructive migration commands against real data.

## API overview

Authenticated endpoints include `/api/v1/projects`, `/api/v1/tasks`, `/api/v1/tasks/{task}/comments`, and `/api/v1/projects/{project}/members`. Owners may grant an existing user **editor** or **viewer** membership; every action is checked server-side.

See [API_USAGE.md](API_USAGE.md) for current route examples, [docs/ROADMAP.md](docs/ROADMAP.md) for future work, and [repository governance](docs/REPOSITORY_GOVERNANCE.md) for the branch and review policy.

## Engineering and quality

- **Security:** resource policies, project-scoped list queries, role-checked operations and authorization for private project channels.
- **Data:** Eloquent models and migrations, MySQL support and an explicit project membership pivot.
- **Automation:** GitHub Actions checks PHP syntax, a fresh disposable MySQL migration and the Laravel test suite.
- **Work in progress:** strengthen membership regression tests, finish authorization review, verify upgrades against existing database backups, and integrate secure real-time delivery.

**Release status:** This repository is a demonstration and active development project, **not a production-certified service**. CI success is necessary but does not replace independent security testing or deployment validation. No unverified throughput or latency claims are made.

## Contribute and follow progress

Review [CONTRIBUTING.md](CONTRIBUTING.md) before submitting changes. Report credentials or security issues privately rather than opening a public exploit report. Documentation, accessibility, reproducible bug reports, and security-focused tests are especially useful.

If the architecture or project is helpful to you, consider starring the repository to follow its progress.

## License

[MIT](LICENSE).
