default:
	@echo >&2 "please specify one of these targets: test"

.PHONY: test default

install:
	@ composer install

# Run test suite
test:
	@./vendor/bin/phpunit --testsuite tests
