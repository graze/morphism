default:
	@echo >&2 "please specify one of these targets: test"

.PHONY: test default

install:
	@composer install

# Run test suite
test:
	@./vendor/bin/phpunit --testsuite tests

clean: ## Remove all generated files
clean:
	@git clean -d -X -f

dist-clean: ## Remove all non-repo files.
dist-clean:
	@git clean -d -x -f -f
