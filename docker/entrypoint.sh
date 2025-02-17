#!/usr/bin/env bash
# Custom entrypoint script to set up WordPress for testing, install WooCommerce, etc.

set -e

# Wait until MySQL is ready.
/usr/local/bin/wait-for-mysql.sh

export WP_CORE_DIR="/var/www/html"
export WP_TESTS_DIR="/tmp/wordpress-tests-lib"
PLUGIN_TESTS_DIR="/var/www/tests"

# Check if WordPress is already installed.
if ! wp --allow-root core is-installed --path="$WP_CORE_DIR" >/dev/null 2>&1; then
  echo "Téléchargement de WordPress..."
  wp --allow-root core download --force --path="$WP_CORE_DIR"

  echo "Création de la configuration..."
  wp --allow-root config create --dbname="$WORDPRESS_DB_NAME" --dbuser="$WORDPRESS_DB_USER" --dbpass="$WORDPRESS_DB_PASSWORD" --dbhost="$WORDPRESS_DB_HOST" --skip-check --path="$WP_CORE_DIR"

  echo "Installation de WordPress..."
  wp --allow-root core install --url="http://localhost:8080" --title="TestSite" --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email --path="$WP_CORE_DIR"

  echo "Installation de WooCommerce..."
  wp --allow-root plugin install woocommerce --activate

  echo "Création de la suite de tests..."
  wp --allow-root scaffold plugin-tests woocommerce-koban-sync --path="$WP_CORE_DIR"

  # Move automatically created test scaffolding to the /tests directory at project root.
  rm -rf "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/tests

  if [ ! -d "$PLUGIN_TESTS_DIR"/bin ]; then
    mv "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/bin "$PLUGIN_TESTS_DIR"
    mv "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/.circleci "$PLUGIN_TESTS_DIR"
    mv "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/.phpcs.xml.dist "$PLUGIN_TESTS_DIR"
    mv "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/phpunit.xml.dist "$PLUGIN_TESTS_DIR"/phpunit.xml
    sed -i 's|\(>\.\/\)tests/|\1|g' "$PLUGIN_TESTS_DIR"/phpunit.xml
    sed -i 's|bootstrap="tests/bootstrap.php"|bootstrap="bootstrap.php"|g' "$PLUGIN_TESTS_DIR"/phpunit.xml
  else
    rm -rf "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/.phpcs.xml.dist
    rm -rf "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/phpunit.xml.dist
    rm -rf "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/bin
    rm -rf "$WP_CORE_DIR"/wp-content/plugins/woocommerce-koban-sync/.circleci
  fi

  echo "Installation de la suite de tests..."
  # Vider complètement le répertoire de tests pour repartir sur une base propre
  rm -rf "$WP_TESTS_DIR"
  cd "$PLUGIN_TESTS_DIR"
#  sed -i "s/svn export --quiet/svn export/g" "$PLUGIN_TESTS_DIR"/bin/install-wp-tests.sh
  yes | bin/install-wp-tests.sh "$WORDPRESS_DB_NAME" "$WORDPRESS_DB_USER" "$WORDPRESS_DB_PASSWORD" "$WORDPRESS_DB_HOST" 6.6
#  cp "$PLUGIN_TESTS_DIR/wp-tests-config.php" "$WP_TESTS_DIR/wp-tests-config.php"

  echo "Installation des dépendances..."
  composer install

fi

echo "Démarrage d'Apache..."
exec apache2-foreground
