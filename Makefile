.PHONY: clean tests wp-up wp-down

DOCKER_DIR=docker
CLEAN_DIRS=tests/vendor tests/bin tests/.circleci tests/.phpunit.result.cache tests/.phpcs.xml.dist

help:
	@echo "Available make targets:"
	@echo "  wp-up      Start the WordPress and DB containers"
	@echo "  wp-down    Stop the WordPress and DB containers"
	@echo "  clean      Clean up the test directory"
	@echo "  tests      Run the tests via Docker"

wp-up:
	@echo "Starting the WordPress and DB containers..."
	cd $(DOCKER_DIR) && docker compose up -d && docker compose logs -f

wp-down:
	@echo "Stopping the WordPress and DB containers..."
	cd $(DOCKER_DIR) && docker compose down -v

clean:
	@echo "Cleaning the test directory..."
	rm -rf $(CLEAN_DIRS)

tests:
	@echo "Running tests $(test)"
ifdef $(test)
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit --filter $(test)'
else
	cd $(DOCKER_DIR) && docker compose exec bash -c 'cat /var/www/tests/phpunit.xml'
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit'
endif

lint:
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpcbf --standard=phpcs.xml'
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpcs --warning-severity=0 --standard=phpcs.xml'

wp-up-ci:
	@echo "Starting the WordPress and DB containers (CI mode)..."
	cd $(DOCKER_DIR) && docker compose up -d