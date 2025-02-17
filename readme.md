# WooCommerce Koban Sync (Developer Overview)

A WordPress plugin that integrates WooCommerce with [Koban CRM](https://www.koban.cloud/en/). It listens for key
WooCommerce events—orders, product updates, billing changes—and communicates these changes to Koban. This brief overview
is intended for developers contributing to, or extending, the plugin.

---

## Key Points

- **Synchronizes Users/Customers**: Upserts Koban “Third” records from WooCommerce customer data.
- **Generates Invoices**: On order payment completion, creates Koban invoices.
- **Product Upsert**: Syncs WooCommerce products (by SKU or product ID) with Koban.
- **Logging**: Captures activities in a custom database table for debugging.

---

## Requirements

- **WordPress 5.7+** and **WooCommerce 6.0+**
- **PHP 7.4+** (including PHP 8.x)
- **MySQL 5.7+** or equivalent
- **Koban CRM** instance with valid API credentials

---

## Installation (Dev Setup)

1. Clone or download this repository, and copy the `/woocommerce-koban-sync` directory into
   `wp-content/plugins/woocommerce-koban-sync`.
2. Make sure **WooCommerce** is installed and active in your WordPress instance.
3. Activate **WooCommerce Koban Sync** via the WordPress admin plugins screen.

Upon activation, the plugin automatically:
- Creates a custom logs table in the WordPress database.
- Registers its settings page under **Koban Sync** in the admin menu.

---

## Local Development & Testing

A Docker-based environment is included:

1. **Requirements**:
    - Docker + Docker Compose installed.


2. **Spin Up Containers**:
   ```bash
   make wp-up
   ```
    - Launches a WordPress (PHP 8.2) + MySQL 5.7 setup with WP-CLI and Composer pre-installed.
    - Logs the process, when apache is running you can safely exit logs and run the tests


3. **Tests**:

- Run all:
    ```bash
    make tests
    ```
- Run a specific test:
    ```bash
    # Launch test class
    make tests test=HooksTest
  
    # Launch test function
    make tests test=HooksTest::test_payment_complete_registered_user_with_meta_guid
   ```

4. **Lint / Format (PHPCS & PHPCBF):**:
    ```
    make lint
    ```
   - Runs phpcbf to auto-fix code style issues, then phpcs to report any remaining errors.
   - Applies to both tests/ and woocommerce-koban-sync directories by default.


4. **Shut Down:**:
    ```
    make wp-down
    ```
   - Stops and removes the Docker containers (WordPress and MySQL).

---

## Notes
- PHPCS (PHP_CodeSniffer) is configured via phpcs.xml for code style checks.
- Use make lint to apply style fixes (via phpcbf) and to run style checks (via phpcs).
- If you wish to ignore certain rules in tests/, adjust your PHPCS configuration accordingly.
