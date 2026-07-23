.PHONY: help install up setup serve stop test reset down

help:
	@echo "Available commands:"
	@echo "  make setup   Docker (mysql) up + composer install + create dev/test databases (first-time bootstrap)"
	@echo "  make serve   Start the app at http://127.0.0.1:8000 (PHP built-in server)"
	@echo "  make stop    Stop the background server started with 'make serve'"
	@echo "  make test    Run the full PHPUnit suite"
	@echo "  make reset   Wipe the MySQL container/volume and recreate it from scratch"
	@echo "  make down    Stop the MySQL container (keeps the data volume)"

install:
	composer install

up:
	docker compose up -d
	@echo "Waiting for MySQL to be healthy..."
	@until [ "$$(docker inspect --format='{{.State.Health.Status}}' $$(docker compose ps -q mysql))" = "healthy" ]; do sleep 1; done
	@echo "MySQL is healthy."

setup: up install
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:database:create --if-not-exists --env=test
	php bin/console app:db:init
	php bin/console app:db:init --env=test

serve:
	php -S 127.0.0.1:8000 -t public public/index.php

stop:
	@pkill -f "php -S 127.0.0.1:8000" 2>/dev/null || echo "No server running on 127.0.0.1:8000"

test:
	php bin/phpunit

reset:
	docker compose down -v
	$(MAKE) up
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:database:create --if-not-exists --env=test
	php bin/console app:db:init
	php bin/console app:db:init --env=test

down:
	docker compose down
