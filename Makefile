.DEFAULT_GOAL:= help
.PHONY: test default

install: ## Install dependencies
	@docker-compose run --rm composer install

test: ## Run test suite
	@test -f ./vendor/bin/phpunit || ${MAKE} install
	@docker-compose run --rm php    ./vendor/bin/phpunit --testsuite tests
	@docker-compose run --rm php-70 ./vendor/bin/phpunit --testsuite tests
	@docker-compose run --rm php-56 ./vendor/bin/phpunit --testsuite tests
	@docker-compose run --rm php-55 ./vendor/bin/phpunit --testsuite tests

start-db:
	@docker-compose up -d db

stop:
	@docker-compose stop

clean: ## Remove all generated files
	@git clean -d -X -f

dist-clean: ## Remove all non-repo files
	@git clean -d -x -f -f

.SILENT: help
help: ## Show this help message
	set -x
	echo "Usage: make [target] ..."
	echo ""
	echo "Available targets:"
	egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
