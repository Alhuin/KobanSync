.PHONY: clean tests wp-up wp-down

DOCKER_DIR=docker
CLEAN_DIRS=tests/vendor tests/phpunit.xml tests/.phpcs.xml.dist tests/bin tests/.circleci tests/.phpunit.result.cache

help:
	@echo "Available make targets:"
	@echo "  wp-up      Start the WordPress and DB containers"
	@echo "  wp-down    Stop the WordPress and DB containers"
	@echo "  clean      Clean up the test directory"
	@echo "  tests      Run the tests via Docker"

wp-up:
	@echo "Starting the WordPress and DB containers..."
	cd docker && docker compose up -d && docker compose logs -f

wp-down:
	@echo "Stopping the WordPress and DB containers..."
	cd docker && docker compose down -v

clean:
	@echo "Cleaning the test directory..."
	rm -rf $(CLEAN_DIRS)

tests:
	@echo "Running tests $(test)"
ifdef $(test)
	cd docker && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit --filter $(test)'
else
	cd docker && docker compose exec wordpress bash -c 'cd /var/www/tests && vendor/bin/phpunit'
endif

lint:
	vendor/bin/phpcbf --standard=phpcs.xml tests/ woocommerce-koban-sync
	vendor/bin/phpcs --warning-severity=0 --standard=phpcs.xml tests/ woocommerce-koban-sync