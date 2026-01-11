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

# php-monolith-starter

Production-ready PHP monolith focusing on simplicity and auditability. The app
accepts a single text input from a web form and stores it in a SQL database.
Runtime dependencies are minimal (PHP + a SQL database); developer tooling
includes PHPUnit, Playwright, Docker, and GitHub Actions for CI.

## Technologies

- PHP 8.x (recommended)
- PDO for database access (SQLite by default; configurable via `DB_DSN`)
- SQLite (default dev DB) — supports easy local testing
- MySQL/Postgres (configurable via `DB_DSN`) for production
- nginx + php-fpm (Docker deployment)
- Docker & docker-compose for reproducible dev and CI environments
- Composer for dependency management
- PHPUnit for unit/integration tests
- Playwright (Node) for E2E tests
- GitHub Actions for CI (PHP lint, PHPStan, PHPUnit, Playwright)
- PHPStan for static analysis
- Makefile for common developer tasks
- Dependabot for dependency updates

## Architecture overview

- `public/index.php` — single front-controller: renders the form, validates
  input, performs CSRF verification, inserts into the `items` table, and
  renders recent entries (escaped with `htmlspecialchars()` to prevent XSS).
- `src/Database.php` — helper that returns a PDO instance based on
  environment variables: `DB_DSN`, `DB_USER`, `DB_PASS`. Defaults to
  `data/db.sqlite` when `DB_DSN` is unset.
- `scripts/init_db.php` — idempotent script that creates the `items` table.
- `public/health.php` — JSON health endpoint used by CI to verify readiness.
  It logs DB errors server-side and returns minimal JSON to clients.
- Tests live under `tests/Unit` (PHPUnit) and `tests/e2e` (Playwright).

## Security & hardening

- CSRF: `public/index.php` generates and verifies a session-backed CSRF token
  for POST submissions.
- XSS: all user values rendered to the page are escaped with
  `htmlspecialchars()`.
- Health endpoint: avoids leaking raw DB errors to clients; errors are
  written to server logs instead.
- Audit: no known secrets are committed; `.gitignore` excludes common
  artifacts (node_modules, Playwright traces, logs).

## Files of interest

- `public/index.php` — application entry and form
- `public/health.php` — readiness endpoint for CI
- `src/Database.php` — PDO creation from environment
- `scripts/init_db.php` — creates `items` table
- `tests/Unit/*` — PHPUnit tests (validation, CSRF, XSS, integration)
- `tests/e2e/*` — Playwright tests and config (`playwright.config.ts`)
- `.github/workflows/*` — CI workflows (unit tests, static analysis, E2E)
- `Dockerfile`, `docker-compose.yml`, `docker/nginx.conf` — container
  runtime and nginx config

## Running locally

Prerequisites: PHP >= 8.0 and Composer for native runs; Docker is
recommended for reproducible environments. Node is required only for
Playwright E2E tests.

### Using Docker (recommended)

1. Build and start services (php-fpm + nginx):

```bash
docker-compose up --build -d
```

2. Initialize the DB inside the app container:

```bash
docker-compose exec app php scripts/init_db.php
```

3. Open the site at http://127.0.0.1:8080

4. Run Playwright E2E (from host or CI):

```bash
cd tests/e2e
npm ci || npm install
npx playwright install --with-deps
npx playwright test
```

### Native (no Docker)

1. Install PHP dev dependencies:

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
npm ci || npm install
npx playwright install --with-deps
# ensure server is healthy before running tests
./wait-for-server.sh http://127.0.0.1:8080/health 60
npx playwright test
```

## CI

- Unit & static analysis workflow: `.github/workflows/phpunit.yml` — sets up
  PHP, lints PHP files (`php -l`), installs Composer deps and runs PHPUnit
  and PHPStan.
- Playwright workflow: `.github/workflows/playwright.yml` — prepares Node,
  installs Playwright, starts the PHP server, waits for `/health`, runs
  E2E tests and uploads artifacts (screenshots/traces) on failure.

Notes: the workflow uses `shivammathur/setup-php@v2` and caches Composer
dependencies for speed; Playwright install prefers `npm ci` with a fallback
to `npm install`.

## Testing details

- PHPUnit tests run in isolated environments and use file-backed SQLite
  DSNs during tests so multiple PDO connections share the same database file.
- The test suite includes checks for validation, CSRF handling, XSS
  escaping, integration flow, and a SQL injection test that verifies the
  app uses prepared statements.

## Production notes

- Configure `DB_DSN`, `DB_USER`, and `DB_PASS` for your production DB.
- For MySQL set a DSN like: `mysql:host=host;dbname=name;charset=utf8mb4`.
- The provided `Dockerfile` is a minimal multi-stage build; for production
  deployments, consider adding secure secret management, logging, and a
  process supervisor.

