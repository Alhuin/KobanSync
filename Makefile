.PHONY: wp-up wp-up-ci wp-down wp-exec tests format lint lint-format test-all clean fclean po po2mo

DOCKER_DIR=docker
PLUGIN_DIR=/var/www/html/wp-content/plugins/woocommerce-koban-sync
TESTS_DIR=$(PLUGIN_DIR)/tests
VENDOR=$(TESTS_DIR)/vendor
CLEAN_DIRS=woocommerce-koban-sync/tests/vendor woocommerce-koban-sync/tests/.phpunit.result.cache
debug ?= 0

help:
	@echo "Available make targets:"
	@echo "  wp-up         Start the WordPress and DB containers"
	@echo "  wp-up-ci      Start containers (CI mode)"
	@echo "  wp-down       Stop the WordPress and DB containers"
	@echo "  wp-exec       SSH into WordPress container"
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

wp-exec:
	@echo "SSH into wordpress Docker container..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash

tests:
	@echo "Running tests $(test) with debug=$(debug)"
ifeq ($(test), )
	@cd $(DOCKER_DIR) && docker compose exec wordpress bash -c '\
		cd $(TESTS_DIR) && env WCKOBAN_DEBUG=$(debug) $(VENDOR)/bin/phpunit \
	'
else
	@cd $(DOCKER_DIR) && docker compose exec wordpress bash -c '\
		cd $(TESTS_DIR) && env WCKOBAN_DEBUG=$(debug) $(VENDOR)/bin/phpunit --filter $(test)\
	'
endif

format:
	@echo "Formatting code..."
	echo $(TESTS_DIR)
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd $(TESTS_DIR) && $(VENDOR)/bin/phpcbf --standard=phpcs.xml'

lint:
	@echo "Running lint checks..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c 'cd $(TESTS_DIR) && $(VENDOR)/bin/phpcs --warning-severity=0 --standard=phpcs.xml -s'

lint-format: format lint
	@echo "Done formatting and linting."

test-all: lint-format tests

clean:
	@echo "Cleaning the test directory..."
	rm -rf $(CLEAN_DIRS)

fclean: clean wp-down
	@echo "Done cleaning artifacts and stopping containers."

po:
	@echo "Generating .pot file..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c '\
	  wp --allow-root i18n make-pot $(PLUGIN_DIR)/src \
	  $(PLUGIN_DIR)/src/languages/woocommerce-koban-sync.pot --domain=woocommerce-koban-sync \
	'

	@echo "Merging .pot changes into fr_FR.po..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c '\
		msgmerge --update \
		$(PLUGIN_DIR)/src/languages/woocommerce-koban-sync-fr_FR.po \
		$(PLUGIN_DIR)/src/languages/woocommerce-koban-sync.pot \
		'

po2mo:
	@echo "Compiling translation files..."
	cd $(DOCKER_DIR) && docker compose exec wordpress bash -c '\
		msgfmt $(PLUGIN_DIR)/src/languages/woocommerce-koban-sync-fr_FR.po \
		-o $(PLUGIN_DIR)/src/languages/woocommerce-koban-sync-fr_FR.mo \
	'
