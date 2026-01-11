# Release draft — php-monolith-starter v0.1.0

Production-ready PHP monolith with SQL, Docker, CI, and Playwright E2E.

## Summary

This initial release demonstrates a compact, production-oriented PHP monolith including:

- Single-file front controller (`public/index.php`) with CSRF protection and PDO-backed persistence.
- Database helper (`src/Database.php`) supporting SQLite by default and configurable via `DB_DSN` for MySQL/Postgres.
- Idempotent DB initialization script: `scripts/init_db.php`.
- Health endpoint for readiness checks: `public/health.php`.
- Unit tests (PHPUnit) and E2E tests (Playwright) with CI integration.
- Docker Compose + nginx + php-fpm setup and a multi-stage `Dockerfile` for production images.
- GitHub Actions workflows for static analysis, unit tests, and Playwright E2E with artifact uploads.

## Highlights

- Small, easy-to-review codebase ideal for technical interviews or portfolio demos.
- Demonstrates end-to-end engineering skills: testing, CI, containers, and basic security practices.

## How to run

Follow the README.md in the repo root. Quickstart:

```bash
# build & run services
docker-compose up --build -d
# initialize DB
docker-compose exec app php scripts/init_db.php
# open UI
open http://127.0.0.1:8080
```

## Changelog (v0.1.0)

- Initial project scaffold
- Add index, DB helper, and init script
- Add unit + e2e tests
- Add Dockerfiles, nginx config, and docker-compose
- Add GitHub Actions for CI and Playwright E2E

## Notes for reviewer

- For deterministic Playwright installs in CI, a `tests/e2e/package-lock.json` is included; run `npm ci` locally inside `tests/e2e` if desired.
- Public health endpoint currently returns DB error details for debugging; consider restricting in production.

---

(You can use this draft to create a GitHub release via the web UI; I cannot create the release automatically from this environment.)
