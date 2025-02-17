#!/usr/bin/env bash
# This hook Runs PHPCBF && PHPCS on staged PHP files found in /woocommerce-koban-sync and /tests.

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

echo "=== Pre-commit hook: running code checks ==="

STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACMR | grep -E '^(woocommerce-koban-sync|tests)/.*\.php$' || true)

if [ -z "$STAGED_PHP_FILES" ]; then
  echo "No staged PHP files in /woocommerce-koban-sync or /tests."
else
  echo "Running PHPCBF on the following staged PHP files:"
  echo "$STAGED_PHP_FILES"

  vendor/bin/phpcbf --standard=phpcs.xml $STAGED_PHP_FILES || true
  vendor/bin/phpcs --standard=phpcs.xml

  PHPCS_EXIT_CODE=$?

  if [ $PHPCS_EXIT_CODE -ne 0 ]; then
    echo -e "${RED}PHPCS validation failed. Please fix any remaining errors before committing.${NC}"
    exit 1
  fi

  echo -e "${GREEN}PHPCBF/PHPCS checks passed for staged PHP files.${NC}"
fi

echo -e "${GREEN}All checks passed. Proceeding with commit.${NC}"
exit 0
