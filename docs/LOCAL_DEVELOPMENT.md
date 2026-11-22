# TaskForge local environment (Phase 1)

**Supported here:** Laravel 11, PHP 8.4 Sail runtime, MySQL 8.4, Redis 7.4. The canonical Compose file is `compose.yaml`; `docker-compose.yml` is a compatibility wrapper, not a second configuration.

## Requirements
- Docker Desktop / Docker Engine with **Docker Compose v2.20+**.
- Git and Composer (and PHP 8.2+) on the host, **or** use a temporary Composer Docker container to install `vendor`.
- Available ports: `8080` (web), `5173` (Vite), `33060` (MySQL), and `63790` (Redis). All host port bindings are on **127.0.0.1** only.

## First-time local setup

```sh
git clone https://github.com/pravin99c/scalable-task-manager.git
cd scalable-task-manager
git switch fix/phase1-docker-api-validation
cp .env.example .env
```

Edit your **untracked** `.env` and set two **different**, random passwords (at least 20 characters):

```dotenv
DB_PASSWORD=<insert-a-unique-local-password>
DB_ROOT_PASSWORD=<insert-a-different-unique-local-root-password>
```

For example, generate each value locally with `openssl rand -hex 24` and paste it into `.env`. Never commit or share this file.

Install dependencies with host PHP and Composer:

```sh
composer install
```

If PHP/Composer are not installed on the host, install dependencies with a temporary Composer container (Unix shell / macOS and Linux example):

```sh
docker run --rm -v "$(pwd):/app" -w /app composer:2 composer install --no-interaction
```

The container may generate files owned by root on Linux; fix ownership locally if needed.

Check the Compose file and start the local app:

```sh
docker compose -f compose.yaml config --quiet
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
```

The app is served at **http://localhost:8080**. The current root URL redirects to the administrator login; it is not a public self-service dashboard yet. Run `./vendor/bin/sail artisan queue:work database --queue=mail --tries=5` in a separate supervised process to deliver email-ownership and invitation messages, plus `./vendor/bin/sail artisan schedule:work` for expiry cleanup. For other app jobs run a separate queue worker for the configured Redis queue. See [API v2 Phase 3 documentation](API_V2.md). Optional frontend assets may be built with `npm install && npm run build` (requires Node.js) or the Sail Node command.

No administrator account is created automatically. See [security validation](SECURITY_VALIDATION.md) for the **explicit local-only** administrator bootstrap process; use unique local credentials.

## Verify / troubleshoot

```sh
docker compose -f compose.yaml ps
docker compose -f compose.yaml logs --tail=80 mysql redis laravel.test
./vendor/bin/sail artisan route:list --path=api
```

If connection errors occur, confirm `DB_HOST=mysql`, `REDIS_HOST=redis`, passwords are configured in local `.env`, and MySQL/Redis are healthy. `mysql` and `redis` are Docker service hostnames, not host-machine addresses. Do not publish host ports to `0.0.0.0` on an untrusted network.

For a fresh empty local database only, use `./vendor/bin/sail artisan migrate:fresh`; **never** run it against a shared or production database.

Stop the app without deleting data:

```sh
./vendor/bin/sail down
```

To delete the local database **irreversibly** use `./vendor/bin/sail down -v` only after manually confirming that its volumes contain no data you need.

## CI and limits

GitHub Actions checks PHP syntax, fresh MySQL migrations, the SQLite-backed Laravel test suite, Compose syntax (canonical + compatibility alias), and disposable **MySQL/Redis** container health and SQL access. It does **not** currently build or smoke-test the full Sail PHP web container, run browser end-to-end tests, or validate upgrade migrations on existing production data.

The sample `.env.example` is intended for a *local development environment*. It must not be reused for production deployment. Redis is intentionally not password-protected inside the isolated local network; only loopback-bound host ports are published.
