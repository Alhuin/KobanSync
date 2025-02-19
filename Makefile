.PHONY: clean fclean tests wp-up wp-up-ci wp-down lint format lint-format test-all

DOCKER_DIR=docker
CLEAN_DIRS=tests/vendor tests/bin tests/.circleci tests/.phpunit.result.cache tests/.phpcs.xml.dist

help:
	@echo "Available make targets:"
	@echo "  wp-up         Start the WordPress and DB containers"
	@echo "  wp-up-ci      Start containers (CI mode)"
	@echo "  wp-down       Stop the WordPress and DB containers"
	@echo "  tests         Run the tests via Docker"
	@echo "  format        Format code via phpcbf"
	@echo "  lint          Run lint checks via phpcs"
	@echo "  lint-format   Run both format and lint"
	@echo "  test-all      Run format, lint and tests"
	@echo "  clean         Clean up the test directory"
	@echo "  fclean        Run clean + wp-down"

wp-up:
	@echo "Starting the WordPress and DB containers..."
	cd $(DOCKER_DIR) && docker compose up -d && docker compose logs -f

wp-up-ci:
	@echo "Starting the WordPress and DB containers (CI mode)..."
	cd $(DOCKER_DIR) && docker compose up -d

wp-down:
	@echo "Stopping the WordPress and DB containers..."
	cd $(DOCKER_DIR) && docker compose down -v

tests:
	@echo "Running tests $(test)"
ifdef $(test)
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit --filter $(test)'
else
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit'
endif

format:
	@echo "Formatting code..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpcbf --standard=phpcs.xml'

lint:
	@echo "Running lint checks..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpcs --warning-severity=0 --standard=phpcs.xml'

lint-format: format lint
	@echo "Done formatting and linting."

test-all: lint-format tests

clean:
	@echo "Cleaning the test directory..."
	rm -rf $(CLEAN_DIRS)

fclean: clean wp-down
	@echo "Done cleaning artifacts and stopping containers."
