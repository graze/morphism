.DEFAULT_GOAL:= help
.PHONY: test default

setup: ## Install dependencies and set up example conf file
	@docker-compose run --rm composer install
	@test -f morphism.conf || sed "s/DOCKER_HOST/$$(docker-machine ip)/" example/morphism.conf.example > morphism.conf

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

clean-docker: stop
	@docker-compose rm -f

clean: ## Remove all generated files
clean: clean-docker
	@git clean -d -X -f

dist-clean: ## Remove all non-repo files
dist-clean: clean-docker
	@git clean -d -x -f -f

.SILENT: help
help: ## Show this help message
	set -x
	echo "Usage: make [target] ..."
	echo ""
	echo "Available targets:"
	egrep '^(.+)\:\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
