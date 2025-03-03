# WooCommerce Koban Sync

A WordPress plugin that integrates WooCommerce with [Koban CRM](https://www.koban.cloud/en/).  
It listens for key WooCommerce events—orders, product updates, billing changes—and communicates these changes to Koban.

---

## Synchronization
**How it works**:  
- Koban IDs (GUIDs) or References (“Codes”) are stored in WordPress/WooCommerce metadata:
  - **WC_Customer** → Koban Third GUID
  - **WC_Product** → Koban Product GUID
  - **WP_Term (categories)** → Koban Category Code
  - **WC_Order** → Koban Invoice GUID, Koban Payment GUID, plus local path of the Invoice PDF

**When does it sync?**
- Various WooCommerce hooks trigger background tasks (via ActionScheduler) to sync data with Koban.
- Each background task is allowed to retry up to 2 times if necessary before definitive failure.

### WooCommerce Payment Complete Flow 
Trigger: `woocommerce_payment_complete`  
1. **Find the Koban Third GUID**  
   - If user meta doesn’t have it, check Koban by email.  
   - If not found, create a new Koban Third from billing info, then store the GUID locally.  
2. **Create a Koban Invoice**  
   - Attach it to the found/created Third, store its GUID in the WC_Order meta.  
3. **Create a Koban Payment**  
   - Attach it to that Invoice, store its GUID in the order meta.  
4. **Download Invoice PDF**  
   - Store the local PDF path in the order meta.
5. **Send Logistics Email**
   - Requires to apply the patches contained in woocommerce-koban-sync/patches to enable wc-multishipping integration
   - Send an email to the logistics department with the Invoice & Chronopost label PDFs.

### WooCommerce Update/Create Product Flow
Triggers: `woocommerce_new_product` and `woocommerce_update_product`  
- Check if WC_Product has a Koban Product GUID.  
  - If found, update it in Koban.  
  - If not found, create it in Koban, then store the new GUID locally.

### WooCommerce Update Customer Flow
Trigger: `woocommerce_customer_save_address`  
- If `address_type` ≠ `billing`, do nothing.  
- If there’s no Koban Third GUID in user meta, do nothing.  
- Otherwise, update the Koban Third with the new billing details.

---

## Koban Links
**In the WordPress Admin**:  
- **User profile**: link to the corresponding Koban Third.  
- **Product editor**: link to the Koban Product.  
- **Order editor**: 
  - Link to the Koban Invoice.  
  - Link to the locally stored Invoice PDF.

**In “My Account”** (WooCommerce front-end):  
- Each order includes a link to view the stored Invoice PDF.

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
