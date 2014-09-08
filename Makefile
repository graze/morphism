default:
	@echo >&2 "please specify one of these targets: test"

.PHONY: test default

# Run test suite
test:
	@./vendor/bin/phpunit --testsuite tests
