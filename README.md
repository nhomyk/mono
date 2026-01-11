# php-monolith-starter

Production-ready PHP monolith with SQL, Docker, CI, and Playwright E2E.

A minimal PHP monolith that accepts a single text input from a form and stores it in a SQL database. The project is intentionally dependency-light for runtime (only PHP + a SQL database required). It includes unit tests (PHPUnit), E2E tests (Playwright), Docker support, and CI workflows for static analysis and tests.

## Architecture

- Public web root: `public/index.php` — single front-controller serving the form, handling POST submissions, and rendering recent entries.
- Database helper: `src/Database.php` — returns a PDO instance using environment variables (`DB_DSN`, `DB_USER`, `DB_PASS`). Defaults to SQLite at `data/db.sqlite` when `DB_DSN` is not set.
- DB init/migrations: `scripts/init_db.php` — idempotent script that creates the `items` table.
- Health: `public/health.php` — lightweight JSON healthcheck that verifies DB connectivity.
- Tests:
  - Unit: `tests/Unit` (PHPUnit)
  - E2E: `tests/e2e` (Playwright). Includes a readiness `wait-for-server.sh` script and artifact capture on failure.
- Dev tooling: `Makefile` provides common targets (`build`, `up`, `init-db`, `composer-install`, `lint`, `test`, `e2e`, `clean`).
- Docker: `Dockerfile` (multi-stage production build) and `docker-compose.yml` (php-fpm + nginx) with a `healthcheck` for readiness.
- CI: `.github/workflows/ci.yml` (PHPStan + PHPUnit + coverage) and `.github/workflows/playwright.yml` (starts server, waits for `/health`, runs Playwright tests). The Playwright workflow attempts `npm ci` then falls back to `npm install`.

## Files of interest

- `public/index.php` — main app and form
- `public/health.php` — readiness endpoint used by CI
- `src/Database.php` — PDO creation from env
- `scripts/init_db.php` — create `items` table
- `tests/Unit/DatabaseTest.php` — PHPUnit example
- `tests/e2e/tests/example.spec.ts` — Playwright example
- `tests/e2e/package.json` & `tests/e2e/package-lock.json` — e2e deps
- `.github/workflows/*` — CI configuration
- `Dockerfile`, `docker-compose.yml`, `docker/nginx.conf` — containerized deploy/dev

## Running locally

Prereqs: PHP >= 8.0 and/or Docker. Node only required for Playwright E2E.

### Using Docker (recommended)

1. Build and start services:

```bash
docker-compose up --build -d
```

2. Initialize DB (runs `scripts/init_db.php` inside the container):

```bash
docker-compose exec app php scripts/init_db.php
```

3. Open the site:

```bash
open http://127.0.0.1:8080
```

4. Run E2E (locally or via Makefile):

```bash
make e2e
```

### Native (no Docker)

1. Install PHP dev deps:

```bash
composer install
```

2. Initialize DB:

```bash
php scripts/init_db.php
```

3. Start the PHP dev server:

```bash
php -S 127.0.0.1:8080 -t public
```

4. Run unit tests and static analysis:

```bash
composer test
composer run analyze
```

5. Run Playwright E2E (requires Node):

```bash
cd tests/e2e
npm install
npx playwright install --with-deps
# start server in background (if not using Docker)
tests/e2e/wait-for-server.sh http://127.0.0.1:8080/health 60
npx playwright test
```

## CI

- `ci.yml` runs PHP lint, PHPStan static analysis, and PHPUnit with coverage (uploads to Codecov if configured).
- `playwright.yml` builds a test environment, starts the PHP server, waits for `/health`, and runs Playwright tests. It prefers `npm ci` but falls back to `npm install` if no lockfile exists.

## Production notes

- Set `DB_DSN`, `DB_USER`, and `DB_PASS` for production database connectivity (MySQL/Postgres). Example MySQL DSN: `mysql:host=host;dbname=name;charset=utf8mb4`.
- The provided `Dockerfile` is a simple multi-stage production build. For production deployments, consider adding config for secrets, proper logging, environment-specific configuration, and a process manager.
- `public/health.php` returns DB error messages for debugging; remove or reduce detail in production.

## Next steps & suggestions

- Add more unit tests and E2E scenarios covering edge cases.
- Harden PHPStan rules and add a `phpstan.neon` configuration.
- Add artifact upload of Playwright screenshots/trace to CI for easier debugging (I can add this if you want).

---

If you'd like, I can commit a final pass to run `composer install` and produce a full `tests/e2e/package-lock.json` from a Node environment, or add automated artifact uploads to the Playwright workflow.
# Simple Monolith

Run locally (PHP >=8.0):

- Install dev tools: `composer install`
- Initialize DB: `php scripts/init_db.php`
- Start dev server: `php -S 127.0.0.1:8080 -t public`
- Open `http://127.0.0.1:8080`

Set DB_DSN/DB_USER/DB_PASS environment variables for production.

Docker (recommended for reproducible dev):

1. Build and start services:

```bash
docker-compose up --build
```

2. Open `http://127.0.0.1:8080`

Notes:
- The app uses SQLite by default with `data/db.sqlite` when `DB_DSN` is not set.
- To run migrations inside the container:

```bash
docker-compose exec app php scripts/init_db.php
```

If Docker is not available, run the app and tests locally using PHP/Composer directly:

```bash
# Install PHP dev deps
composer install

# Initialize DB
php scripts/init_db.php

# Run unit tests
composer test

# Start dev server
php -S 127.0.0.1:8080 -t public
```
