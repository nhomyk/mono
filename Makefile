SHELL := /bin/bash
PWD := $(shell pwd)

.PHONY: build up down init-db composer-install lint test analyze e2e clean

build:
	docker-compose build

up:
	docker-compose up --build -d

down:
	docker-compose down

init-db:
	docker-compose exec app php scripts/init_db.php

composer-install:
	# Uses official composer image to install dependencies into the project
	docker run --rm -v $(PWD):/app -w /app composer:2 install --no-interaction --prefer-dist

lint:
	# Run PHP lint across project using php image
	docker run --rm -v $(PWD):/app -w /app php:8.1-cli bash -lc "find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l"

analyze:
	# Run PHPStan (requires vendor/phpstan or global install)
	docker run --rm -v $(PWD):/app -w /app composer:2 run analyze

test:
	# Run PHPUnit via php image (assumes vendor is present)
	docker run --rm -v $(PWD):/app -w /app php:8.1-cli bash -lc "vendor/bin/phpunit --colors=never --log-junit=phpunit.xml || true"

e2e:
	# Run Playwright E2E locally (requires node + playwright installed)
	cd tests/e2e && npm ci && npx playwright install --with-deps
	# Wait for local server health before running tests
	./tests/e2e/wait-for-server.sh http://127.0.0.1:8080/health 60
	cd tests/e2e && npx playwright test

clean:
	rm -rf vendor
