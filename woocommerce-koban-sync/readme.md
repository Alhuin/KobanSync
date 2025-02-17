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

1. Clone or download the code into `wp-content/plugins/woocommerce-koban-sync`.
2. Make sure **WooCommerce** is installed and active.
3. Activate **WooCommerce Koban Sync** in the WordPress admin.

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

4. **Shut down**:
    ```
    make wp-down
    ```