# TaskForge · Scalable Task Manager

> Laravel project and task management API with explicit access policies and a growing collaboration model.

[![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-0B7285)](LICENSE)
![Status](https://img.shields.io/badge/Status-Work%20in%20progress-F59E0B)

## Planned dashboard design

![TaskForge dashboard design concept](https://raw.githubusercontent.com/pravin99c/scalable-task-manager/main/docs/images/taskforge-dashboard-concept.webp)

**Design concept only.** This is a proposed UI, **not a screenshot from the deployed application**. The existing UI uses Blade; the React dashboard, collaborative Kanban board, charts and polished visuals are future milestones.

## Current foundation

- Laravel 11, Sanctum, project and task CRUD, comments, activity logs and admin views.
- Project policies and task policies limit access by ownership or explicit membership.
- A project has one **owner** (`projects.created_by`). The owner can invite an existing registered user as **editor** or **viewer**.
- **Editor** can read the project and manage tasks, but cannot rename/delete the project or manage members.
- **Viewer** can read projects and tasks, but cannot modify them or add comments.
- **Unrelated users** have no access to private project data or broadcast channels.
- Task assignment is restricted to a project's owner or editors.
- When a member is removed or downgraded to viewer, existing task assignments to that user are cleared.
- Existing global administrator UI remains restricted to explicitly assigned superadmins.

**The collaboration API is under review on this feature branch.** Automated tests and code review must pass before merge. Do not use this branch as a public production service.

## Project membership endpoints

All routes are under `/api/v1` and require Sanctum authentication.

| Method | Endpoint | Who can use it |
| --- | --- | --- |
| `GET` | `/projects/{project}/members` | Owner, editor, viewer |
| `POST` | `/projects/{project}/members` | Owner only |
| `DELETE` | `/projects/{project}/members/{user}` | Owner only |
| `GET/POST` | `/projects` | Own/membership projects or create new |
| `GET/POST` | `/tasks` | Accessible tasks / owner-or-editor task creation |
| `GET/PATCH/DELETE` | `/tasks/{task}` | Role-protected operations |

An owner can add or change a member's project role by posting `{"user_id": 123, "role": "editor"}` or `"viewer"`. The target must be an **existing registered account**. Email invitations and workspaces are not implemented yet.

## Development and quality

```bash
git clone https://github.com/pravin99c/scalable-task-manager.git
cd scalable-task-manager
git switch feat/project-membership-foundation
composer install
cp .env.example .env
php artisan key:generate
# Configure an isolated local database in .env before migrations.
php artisan migrate
php artisan test
```

The CI workflow validates PHP syntax, a **fresh disposable MySQL migration**, and the Laravel test suite. This does not certify a production upgrade on an existing database.

## Roadmap

1. Complete membership and authorization tests, validate all broadcast paths, and review existing-data migrations.
2. Add invitation flows, role lifecycle policies, audit events, and pagination.
3. Build the responsive React + TypeScript UI and convert approved UI concepts to real screenshots.
4. Validate queue workers, notifications, deployment and real-world performance.

See [security validation guidance](docs/SECURITY_VALIDATION.md) and [API documentation](API_USAGE.md). Some prior documentation predates the project membership feature and will be reconciled in subsequent pull requests.

## Contribute

Review the implementation, propose small improvements, and add tests for authorization and abuse cases. Please **do not post credentials or exploit details in public issues**.

## License

[MIT](LICENSE).
