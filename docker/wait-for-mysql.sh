#!/usr/bin/env bash
# This script waits for a MySQL server to become available before continuing.

set -e

until mysqladmin ping -h"$WORDPRESS_DB_HOST" -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" --silent; do
  echo "Waiting for MySQL at $WORDPRESS_DB_HOST..."
  sleep 2
done

echo "MySQL is up - continuing"
