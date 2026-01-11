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

