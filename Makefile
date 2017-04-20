.DEFAULT_GOAL:= help
.PHONY: test default

install: ## Install dependencies
	@docker-compose run --rm composer install

test: ## Run test suite
	@./vendor/bin/phpunit --testsuite tests

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
