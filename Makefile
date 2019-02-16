SHELL = /bin/sh

DOCKER = $(shell which docker)
PHP_VER := 7.2
IMAGE := graze/php-alpine:${PHP_VER}-test
VOLUME := /srv
DOCKER_RUN_BASE := ${DOCKER} run --rm -t -v $$(pwd):${VOLUME} -w ${VOLUME}
DOCKER_RUN := ${DOCKER_RUN_BASE} ${IMAGE}

PREFER_LOWEST ?=

.PHONY: build build-update composer-% clean help run
.PHONY: lint lint-fix
.PHONY: test test-unit test-integration test-lowest test-matrix test-coverage test-coverage-html test-coverage-clover

.SILENT: help

# Building

build: ## Install the dependencies
build: ensure-composer-file ensure-conf-file
	make 'composer-install --optimize-autoloader --prefer-dist ${PREFER_LOWEST}'

build-update: ## Update the dependencies
build-update: ensure-composer-file ensure-conf-file
	make 'composer-update --optimize-autoloader --prefer-dist ${PREFER_LOWEST}'

ensure-composer-file: # Update the composer file
	make 'composer-config platform.php ${PHP_VER}'

composer-%: ## Run a composer command, `make "composer-<command> [...]"`.
	${DOCKER} run -t --rm \
        -v $$(pwd):/app:delegated \
        -v ~/.composer:/tmp:delegated \
        -v ~/.ssh:/root/.ssh:ro \
        composer --ansi --no-interaction $* $(filter-out $@,$(MAKECMDGOALS))

ensure-conf-file: # Ensure that there is a configuration file in dev
	@test -f morphism.conf || cp example/morphism.conf.example morphism.conf

# Testing

test: ## Run the unit and integration testsuites.
test: lint test-unit

lint: ## Run phpcs against the code.
	${DOCKER_RUN} vendor/bin/phpcs -p --colors --extensions=php --warning-severity=0 --ignore=*/vendor/* src/ tests/

lint-fix: ## Run phpcsf and fix possible lint errors.
	${DOCKER_RUN} vendor/bin/phpcbf -p src/ tests/

test-unit: ## Run the unit testsuite.
	${DOCKER_RUN} vendor/bin/phpunit --testsuite tests

test-functional: example
	docker-compose run --rm morphism diff morphism.conf
	docker-compose run --rm morphism dump morphism.conf
	docker-compose run --rm db mysqldump -hdb -umorphism -pmorphism morphism-test > dump.sql
	docker-compose run --rm morphism lint dump.sql
	docker-compose run --rm morphism extract dump.sql
	@rm -f dump.sql

test-lowest: ## Test using the lowest possible versions of the dependencies
test-lowest: PREFER_LOWEST=--prefer-lowest
test-lowest: build-update test

test-matrix-lowest: ## Test all version, with the lowest version
	${MAKE} test-matrix PREFER_LOWEST=--prefer-lowest
	${MAKE} build-update

test-matrix: ## Run the unit tests against multiple targets.
	${MAKE} PHP_VER="5.6" build-update test
	${MAKE} PHP_VER="7.0" build-update test
	${MAKE} PHP_VER="7.1" build-update test
	${MAKE} PHP_VER="7.2" build-update test

test-coverage: ## Run all tests and output coverage to the console.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-text

test-coverage-html: ## Run all tests and output coverage to html.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-html=./tests/report/html

test-coverage-clover: ## Run all tests and output clover coverage to file.
	${DOCKER_RUN} phpdbg7 -qrr vendor/bin/phpunit --coverage-clover=./tests/report/coverage.clover

.PHONY: example
example: ## Set up example project and schema
example: start-db
	[ ! -f morphism.conf ] && cp example/morphism.conf.example morphism.conf || true
	rm -rf schema schema2
	mkdir -p schema/morphism-test schema2/morphism-test
	cp example/schema/product.sql example/schema/ingredient.sql schema/morphism-test
	cp example/schema/product_ingredient_map.sql schema2/morphism-test
	docker-compose run --rm morphism diff --apply-changes yes morphism.conf

# Database

start-db: ## Start up the test database
	@docker-compose up -d db

stop: ## Stop all docker containers
	@docker-compose stop

# Cleaning

clean-docker: ## Remove all docker containers
clean-docker: stop
	@docker-compose rm -f

clean: ## Remove docker containers and all generated files
clean: clean-docker
	@git clean -d -X -f

dist-clean: ## Remove docker containers and all non-repo files
dist-clean: clean-docker
	@git clean -d -x -f -f

# Help

help: ## Show this help message.
	echo "usage: make [target] ..."
	echo ""
	echo "targets:"
	egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
