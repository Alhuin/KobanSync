# WooCommerce Koban Sync

A WordPress plugin that integrates WooCommerce with [Koban CRM](https://www.koban.cloud/en/).  
It listens for key WooCommerce events—orders, product updates, billing changes—and communicates these changes to Koban.

---

## Key Points

- **Synchronizes Users/Customers**: Upserts Koban “Third” records from WooCommerce customer data.
- **Generates Invoices**: On order payment completion, creates Koban invoices in Koban.
- **Creates Invoice Payments**: After the invoice creation, creates the relevant payment in Koban.
- **Retrieves Invoice PDF Document**: After the payment creation, download the invoice PDF from Koban and store it securely.
- **Product Upsert**: Syncs WooCommerce products (by SKU or product ID) with Koban.
- **Logging**: Captures activities in a custom database table for debugging and auditing.

> When deployed, this plugin **won’t do anything** unless you configure the Koban credentials in **Koban Sync → Settings**.
> 
> In a dev setup, these credentials are faked in ```tests/bootstrap.php```.

---

## Requirements

- **WordPress 5.7+** and **WooCommerce 6.0+**
- **PHP 7.4+** (PHP 8.x is compatible but this Docker image uses PHP 7.4 to match the prod environment)
- **MariaDB / MySQL 5.7+** or equivalent
- **Koban CRM** instance with valid API credentials

---

## Dev Setup

1. **Clone or download** this repository
2. Run ```make wp-up```

Upon activation, the plugin automatically:
- Creates a custom logs table in the WordPress database.
- Registers its settings page under **Koban Sync** in the admin menu.

---

## Local Development & Testing

A Docker-based environment is included for local testing and development.

### Requirements

- **Docker** & **Docker Compose** installed on your system.

### 1. Spin Up Containers
   ```bash
   make wp-up
   ```
    - Launches a WordPress (PHP 7.4) + MariaDB 10.11.8 setup with WP-CLI and Composer pre-installed.
    - Logs the process, when apache is running you can safely exit logs and run the tests

### 2. Interact with Containers
- **Stop containers**
   ```bash
  make wp-down
   ```
  Stops and removes the WordPress + DB containers.


- **Shell into WordPress container**
   ```bash
  make wp-exec
   ```
  Provides a shell inside the running WordPress container.

### 3. Run Tests:

- **All tests**:
    ```bash
    make tests
    ```
- **Specific tests**:
    ```bash
   # By test class name
   make tests test=HooksTest
   
   # By specific test method
   make tests test=HooksTest::test_payment_complete_registered_user_with_meta_guid
   ```
- **Debug Logging**
  ```bash
  make tests debug=1
  ```
  - This sets the environment variable ```WCKOBAN_DEBUG=1``` inside the container, causing debug messages to be written to ```wp-content/uploads/koban-debug.log```.
  - By default (```make tests```), ```WCKOBAN_DEBUG=0``` (quiet mode).

### 4. Lint / Format (PHPCS & PHPCBF):
- **Lint**:
    ```bash
    make lint
    ```
   - Runs phpcs to check code style against phpcs.xml.


- **Format**:
    ```bash
    make format
    ```
   - Runs phpcbf to auto-fix code style issues where possible.


- **Lint + Format**:
    ```bash
    make lint-format
    ```

### 5. Shut Down:
 ```
 make wp-down
 ```
Stops and removes the Docker containers (WordPress and MySQL).

---
## CI and Deployment
**Github actions**: A workflow (```.github/workflows/test-and-deploy.yml```):
  - runs lint checks,
  - runs tests,
  - bundles the ```woocommerce-koban-sync/src``` folder into an artifact ```woocommerce-koban-sync.zip``` for manual installation,
  - deploys via SFTP if relevant files changed on ```main```.

> To use the Github action for SFTP deployment, setup your Github secrets for the remote server:
>- SFTP_USER
>- SFTP_HOST
>- SFTP_KEY
>- SFTP_REMOTE_PATH

---
## Notes
- **PHPCS** (PHP_CodeSniffer) is configured via ```phpcs.xml``` for code style checks.
- If you wish to ignore certain rules in ```tests/```, adjust your ```phpcs.xml``` configuration accordingly.
- The plugin logs high-level events to a custom DB table and logs debug details to a file (```koban-debug.log```).
- You can view plugin settings in the WordPress Admin under **Koban Sync → Settings**.
- **Protected PDFs**: For invoice PDFs, the plugin places them in ```/uploads/protected-pdfs``` with a ```.htaccess``` that denies direct access. On Nginx or alternate servers, ensure you replicate this restriction.
