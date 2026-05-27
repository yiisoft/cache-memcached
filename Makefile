.DEFAULT_GOAL := help

PHP_VERSION ?= 8.1
-include .env.local

export PHP_VERSION
export USER_ID := $(shell id -u)
export GROUP_ID := $(shell id -g)

COMPOSE := docker compose -f docker/compose.yaml
DOCKER_RUN := $(COMPOSE) run --rm $(if $(NO_TTY),-T,) php
RUN := $(if $(YII_INSIDE_CONTAINER),,$(DOCKER_RUN))

build: ## Build the dev image. Override the PHP version: `make build PHP_VERSION=8.3`
	$(COMPOSE) build

shell: ## Open a shell inside the container.
	$(RUN) bash

composer: ## Run a Composer command: `make composer ARGS=update`
	$(RUN) composer $(ARGS)

test: phpunit
phpunit: ## [test] Run PHPUnit tests: `make phpunit ARGS="--filter=TestName"`
	$(RUN) ./vendor/bin/phpunit $(ARGS)

mutation: infection
infection: ## [mutation] Run mutation testing with Infection.
	$(RUN) ./vendor/bin/roave-infection-static-analysis-plugin --threads=1 --ignore-msi-with-no-mutations --only-covered

psalm: ## Run Psalm static analysis: `make psalm ARGS="--show-info=true"`
	$(RUN) ./vendor/bin/psalm $(if $(ARGS),$(ARGS),--php-version=$(PHP_VERSION))

cs-fix: php-cs-fixer
php-cs-fixer: ## [cs-fix] Fix code style with PHP-CS-Fixer: `make php-cs-fixer ARGS="--dry-run"`
	$(RUN) ./vendor/bin/php-cs-fixer fix $(ARGS)

coverage: ## Generate an HTML code coverage report in runtime/coverage.
	$(RUN) ./vendor/bin/phpunit --coverage-html=runtime/coverage

down: ## Stop and remove the containers.
	$(COMPOSE) down

help: ## This help.
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)
