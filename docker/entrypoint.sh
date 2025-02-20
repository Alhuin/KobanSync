#!/usr/bin/env bash
# Custom entrypoint script to set up WordPress for testing, install WooCommerce, etc.

set -e

# Wait until MySQL is ready.
/usr/local/bin/wait-for-mysql.sh

export WP_CORE_DIR="/var/www/html"
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
PLUGIN_DIR="$WP_CORE_DIR/wp-content/plugins/woocommerce-koban-sync"

# Check if WordPress is already installed.
if ! wp --allow-root core is-installed --path="$WP_CORE_DIR" >/dev/null 2>&1; then
  echo "Téléchargement de WordPress..."
  wp --allow-root core download --force --path="$WP_CORE_DIR"

  echo "Création de la configuration..."
  wp --allow-root config create --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --dbhost="$WORDPRESS_DB_HOST" --skip-check --path="$WP_CORE_DIR"

  echo "Installation de WordPress..."
  wp --allow-root core install --url="http://localhost:8080" --title="TestSite" --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email --path="$WP_CORE_DIR"

  echo "Installation de WooCommerce..."
  wp --allow-root plugin install woocommerce --activate --path="$WP_CORE_DIR"

  echo "Création de la suite de tests..."
  mkdir -p /tmp/scaffold-tests
  cd /tmp/scaffold-tests

  wp --allow-root scaffold plugin-tests \
      --path="$WP_CORE_DIR" \
      --dir=/tmp/scaffold-tests

  cp -r bin/install-wp-tests.sh "$PLUGIN_DIR"/bin/install-wp-tests.sh

  cd ~
  rm -rf /tmp/scaffold-tests

  echo "Installation de la suite de tests..."
  # Vider complètement le répertoire de tests pour repartir sur une base propre
  rm -rf "$WP_TESTS_DIR"
  yes | "$PLUGIN_DIR"/bin/install-wp-tests.sh "$WORDPRESS_DB_NAME" "$WORDPRESS_DB_USER" "$WORDPRESS_DB_PASSWORD" "$WORDPRESS_DB_HOST" 6.6
  rm -rf "${PLUGIN_DIR:?}"/bin/

  cd "$PLUGIN_DIR"
  echo "Installation des dépendances..."
  composer install

fi

echo "Démarrage d'Apache..."
exec apache2-foreground
