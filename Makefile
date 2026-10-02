DC      = docker compose
EXEC    = $(DC) exec app
PHP     = $(EXEC) php
CONSOLE = $(PHP) bin/console

# Modules that own a migration namespace and a Postgres schema (spec §6, §8)
MODULES = Authentication Authorization Audit Subscription Driver Tower TowRequest Bidding Job Review Penalty Notification

.DEFAULT_GOAL := help

.PHONY: help build up down restart install bash logs ps composer update \
	db-create db-drop migrate migration db-reset \
	test test-unit test-application test-integration test-coverage \
	phpstan cs cs-fix deptrac deptrac-generate test-policy qa \
	console cache-clear worker-restart worker-logs openapi jwt-keys prod-build

help: ## Show this help
	@grep -hE '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

##@ Setup & running
build: ## Build the Docker images
	$(DC) build

up: ## Start all containers and wait until they are healthy
	$(DC) up -d --wait

down: ## Stop all containers
	$(DC) down

restart: down up ## Restart all containers

install: build up ## Build, start, install dependencies, reset databases, generate JWT keys
	$(EXEC) composer install
	$(MAKE) db-reset jwt-keys worker-restart

bash: ## Open a shell in the app container
	$(EXEC) bash

logs: ## Follow logs (s=<service> for one service)
	$(DC) logs -f $(s)

ps: ## Show container status
	$(DC) ps

##@ Dependencies
composer: ## Run any Composer command (c="require foo/bar")
	$(EXEC) composer $(c)

update: ## composer update
	$(EXEC) composer update

##@ Database
db-create: ## Create the dev and test databases (with PostGIS)
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis"
	$(CONSOLE) doctrine:database:create --if-not-exists --env=test
	$(CONSOLE) dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis" --env=test

db-drop: ## Drop the dev and test databases
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:drop --force --if-exists --env=test

migrate: ## Run all module migrations (dev database)
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration

migration: ## Generate a migration for one module (m=TowRequest)
	@if [ -z "$(m)" ]; then \
		echo "Usage: make migration m=<Module>   (modules: $(MODULES))"; exit 1; \
	fi
	@if ! echo " $(MODULES) " | grep -q " $(m) "; then \
		echo "Unknown module '$(m)'. Modules: $(MODULES)"; exit 1; \
	fi
	$(eval SCHEMA := $(shell echo '$(m)' | sed -E 's/([a-z0-9])([A-Z])/\1_\2/g' | tr '[:upper:]' '[:lower:]'))
	$(CONSOLE) doctrine:migrations:diff -n --namespace='Migrations\$(m)' --filter-expression='/^$(SCHEMA)\.(?!processed_event$$|rate_limit$$)/'

db-reset: db-drop db-create ## Drop, create and migrate the dev and test databases
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration --env=test

##@ Testing
test: ## Run all three suites (separate processes, stops at the first failure)
	@$(MAKE) --no-print-directory test-unit test-application test-integration

test-unit: ## Run the unit suite (f=<name> to filter)
	$(PHP) vendor/bin/phpunit --testsuite unit $(if $(f),--filter $(f))

test-application: ## Run the application suite (f=<name> to filter)
	$(PHP) vendor/bin/phpunit --testsuite application $(if $(f),--filter $(f))

test-integration: ## Reset the test DB and WireMock, then run the integration suite (f=<name> to filter)
	$(CONSOLE) doctrine:database:drop --force --if-exists --env=test
	$(CONSOLE) doctrine:database:create --env=test
	$(CONSOLE) dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis" --env=test
	$(CONSOLE) doctrine:migrations:migrate -n --allow-no-migration --env=test
	$(DC) exec wiremock curl -fsS -X POST http://localhost:8080/__admin/reset
	$(PHP) vendor/bin/phpunit --testsuite integration $(if $(f),--filter $(f))

test-coverage: ## Run all suites with PCOV and write an HTML report to var/coverage/
	$(PHP) -d pcov.enabled=1 -d pcov.directory=src vendor/bin/phpunit --coverage-html var/coverage

##@ Quality
phpstan: ## Static analysis
	$(CONSOLE) cache:warmup --env=test
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Check coding style
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix coding style
	$(PHP) vendor/bin/php-cs-fixer fix

deptrac: ## Check architecture rules
	$(PHP) vendor/bin/deptrac analyse --no-progress

deptrac-generate: ## Regenerate deptrac.yaml from the module folders
	$(PHP) tools/deptrac/generate.php

test-policy: ## Check every endpoint, command, contract method and public method has its required tests
	$(PHP) vendor/bin/phpunit --testsuite unit --group policy

qa: cs phpstan deptrac test-policy test ## Run cs, phpstan, deptrac, test-policy and all tests

##@ Other
console: ## Run any Symfony console command (c="debug:router")
	$(CONSOLE) $(c)

cache-clear: ## Clear the Symfony cache
	$(CONSOLE) cache:clear

worker-restart: ## Restart the Messenger worker
	$(EXEC) supervisorctl restart worker

worker-logs: ## Follow the app container logs (nginx, php-fpm and the worker)
	$(DC) logs -f app

openapi: ## Export the OpenAPI spec to openapi/openapi.yaml
	$(CONSOLE) nelmio:apidoc:dump --format=yaml > openapi/openapi.yaml.tmp
	mv openapi/openapi.yaml.tmp openapi/openapi.yaml

jwt-keys: ## Generate the JWT key pair in config/jwt/ (skipped if present)
	$(EXEC) sh -c 'mkdir -p config/jwt && [ -f config/jwt/private.pem ] || { \
		openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out config/jwt/private.pem 2>/dev/null && \
		openssl pkey -in config/jwt/private.pem -pubout -out config/jwt/public.pem && \
		chmod 600 config/jwt/private.pem; }'

prod-build: ## Build the production image
	docker build --target prod -t slep-api:prod .
