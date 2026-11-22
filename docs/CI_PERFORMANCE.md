# CI performance: cached Laravel Sail build

## Measured bottleneck

The successful [development workflow run 45015195721](https://github.com/pravin99c/scalable-task-manager/actions/runs/45015195721) on 2026-11-22 took roughly 6 minutes 20 seconds. Step timestamps show:

| Step | Duration |
| --- | ---: |
| PHP setup | 3 s |
| Composer install | 7 s |
| PHP syntax checks | 5 s |
| Laravel feature/unit tests (SQLite) | 1 s |
| Fresh MySQL migration | 1 s |
| Full Sail app Docker build/start/integration smoke | 4 min 20 s |

The Sail step also runs migrations and HTTP checks, so the entire 4m20s cannot be assumed to be compilation. In the prior workflow it combined `docker compose up --build`, container readiness, and HTTP smoke checks, hiding their individual durations.

## Change

The consolidated PR workflow now uses `docker/setup-buildx-action@v3` and `docker/build-push-action@v6` to build the **existing** PHP 8.4 Sail Dockerfile with GitHub Actions BuildKit layer caching (`scope=taskforge-sail-php84`), loads it onto the runner, and then runs the **unchanged** Sail container/migration/HTTP checks through Compose with `--no-build`. This prevents Compose from silently repeating a second build. Images are **not** pushed to any external registry. The cache stores Docker build layers, never `.env` or application secrets. The existing vendor Sail Dockerfile and application bind mount remain unchanged.

The first run has a cold cache and may not be faster. Subsequent runs **may** improve, depending on cache hits and upstream Sail image changes. The BuildKit cache export is best-effort (`ignore-error=true`); an unavailable cache should not bypass tests. No test suite was disabled, no sensitive data written into the Dockerfile, and existing development/production environments are not modified.

## Validation required before claiming improvement

1. Confirm the PR run builds the image, starts the complete Sail app, migrates the disposable test database, and passes unauthenticated API/HTTP smoke checks.
2. Record durations for the cached Docker build step and the Sail application smoke step for the first PR run, the next normal `development` run and a later warm-cache run.
3. Compare total workflow durations, including time spent transferring cache layers; do not equate a faster Sail smoke step alone with an overall faster workflow.
4. Ensure cache misses rebuild the original Sail Dockerfile; if Buildx is unreliable, revert the two build steps and restore `up -d --build`.
5. Keep all Laravel, Playwright, frontend, MySQL queue and scheduler tests. No CI bypass or `main` merge without approval.

A committed npm lockfile and `npm ci` remain separate follow-up tasks. This change does not claim to resolve them.
