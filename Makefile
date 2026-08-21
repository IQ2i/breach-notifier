# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) run --rm php

# Executables
PHP = $(PHP_CONT) php

# Misc
.DEFAULT_GOAL = help
.PHONY        = help build install sf check migrate test lint build-prod check-prod

## —— Help 🐳 🎵 ———————————————————————————————————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker (dev) 🐳 ——————————————————————————————————————————————————————————
build: ## Build the dev Docker image
	@$(DOCKER_COMP) build --pull --no-cache

## —— Project 🐝 ———————————————————————————————————————————————————————————————
install: ## Install PHP dependencies
	@$(PHP_CONT) composer install

sf: ## Run a Symfony console command, e.g. make sf cmd="app:breach:check -v"
	@$(PHP) bin/console $(cmd)

check: ## Run the breach-checking command
	@$(PHP) bin/console app:breach:check -v

migrate: ## Run pending Doctrine migrations
	@$(PHP) bin/console doctrine:migrations:migrate -n

test: ## Run the test suite
	@$(PHP) bin/phpunit

lint: ## Lint config and templates
	@$(PHP) bin/console lint:yaml config feeds.dist.yaml watchlist.dist.yaml notifications.dist.yaml
	@$(PHP) bin/console lint:twig templates
	@$(PHP) bin/console lint:container

## —— Docker (prod) 🐳 —————————————————————————————————————————————————————————
build-prod: ## Build the production Docker image
	@docker build --target prod -t breach-notifier:prod .

check-prod: ## Run the breach-checking command through the production image (one-shot)
	@docker compose -f compose.prod.yaml run --rm breach-notifier
