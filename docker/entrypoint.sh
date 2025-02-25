#!/usr/bin/env bash
# Custom entrypoint script to set up WordPress for testing, install WooCommerce, etc.

set -e

/usr/local/bin/wait-for-mysql.sh

export WP_CORE_DIR="/var/www/html"
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
PLUGIN_DIR="$WP_CORE_DIR/wp-content/plugins/woocommerce-koban-sync"

if ! wp --allow-root core is-installed --path="$WP_CORE_DIR" >/dev/null 2>&1; then
  echo "Downloading WordPress..."
  wp --allow-root core download --force --path="$WP_CORE_DIR"

  echo "Creating configuration files..."
  wp --allow-root config create --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --dbhost="$WORDPRESS_DB_HOST" --skip-check --path="$WP_CORE_DIR"

  echo "Installing WordPress..."
  wp --allow-root core install --url="http://localhost:8080" --title="TestSite" --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email --path="$WP_CORE_DIR"

  echo "Installing WooCommerce..."
  wp --allow-root plugin install woocommerce --activate --path="$WP_CORE_DIR"

  echo "Creating the test suite..."
  mkdir -p /tmp/scaffold-tests
  cd /tmp/scaffold-tests

  wp --allow-root scaffold plugin-tests \
      --path="$WP_CORE_DIR" \
      --dir=/tmp/scaffold-tests

  mkdir -p "$PLUGIN_DIR"/bin
  cp -r bin/install-wp-tests.sh "$PLUGIN_DIR"/bin/install-wp-tests.sh

  cd ~
  rm -rf /tmp/scaffold-tests

  echo "Installing the test suite..."
  rm -rf "$WP_TESTS_DIR"
  yes | "$PLUGIN_DIR"/bin/install-wp-tests.sh "$WORDPRESS_DB_NAME" "$WORDPRESS_DB_USER" "$WORDPRESS_DB_PASSWORD" "$WORDPRESS_DB_HOST" 6.7
  rm -rf "${PLUGIN_DIR:?}"/bin/

  cd "$PLUGIN_DIR"/tests
  echo "Installing dependencies..."
  composer install

fi

echo "Running Apache..."
exec apache2-foreground
