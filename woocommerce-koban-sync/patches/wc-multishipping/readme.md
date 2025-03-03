# Patches for wc-multishipping

This folder contains patches that modify the **wc-multishipping** plugin to enable certain functionality required by **WooCommerce Koban Sync**.

> **Important**  
> These patches override or inject code in wc-multishipping plugin files. Each time wc-multishipping updates, you should re-check and reapply these patches if needed.

---

## Table of Contents

- [Overview](#overview)
- [Patch Files](#patch-files)
- [How to Apply the Patches](#how-to-apply-the-patches)
- [Maintenance Notes](#maintenance-notes)

---

## Overview

**wc-multishipping** typically restricts certain hooks and label generation to the admin pages. We need them on the front-end to automatically generate the label when an order is completed from the shop. Additionally, we override some email templates and label-saving logic so that:

1. **Chronopost shipping labels** are saved to be emailed to the logistics team, then deleted.
2. The standard WooCommerce email header and footer are reinstated in Chronopost’s tracking emails.

---

## Patch Files

1. **`patch-wms_front_init.php`**
    - **Location**: Add this code at the top of the `__construct()` method in `wc-multishipping/inc/front/wms_front_init.php`.
    - **Purpose**:
        - Registers Chronopost’s `woocommerce_order_status_changed` hooks on the front-end, allowing label generation when an order status update occurs outside the admin interface.

2. **`override-wms_chronopost_tracking.php`**
    - **Location**: Overwrites the template in `wc-multishipping/inc/resources/email/templates/wms_chronopost_tracking.php`.
    - **Purpose**:
        - Replaces the Chronopost tracking email content:
            - Adds the standard WooCommerce header and footer
            - Customizes the text content

3. **`patch-abstract_label.php`**
    - **Location**: Inserted into `wc-multishipping/inc/admin/classes/abstract_classes/abstract_label.php`, within the `save_label_PDF()` method (before the final `return` in the `try` block).
    - **Purpose**:
        - Saves the generated shipping label as a PDF in a protected folder (`protected-pdfs`) if the order has not already been successfully synced.
    - **Additional Info**:
        - Requires commenting the capabilities check at the top of the function
        - Requires changing the function signature of `abstract_label::save_label_PDF()` to accept `$order`.
        - The method should then be called with `$order` in `abstract_order.php`, for example:
          ```php
          $label_pdf_path = $label_class::save_label_PDF( $one_tracking_number, $order );
          ```

---

## How to Apply the Patches

1. **Backup the original wc-multishipping files** before any changes.
2. **Open** the specified file in a text editor:
    - For example, if you’re applying `patch-wms_front_init.php`, open `wc-multishipping/inc/front/wms_front_init.php`.
3. **Locate** the section indicated by the inline comments in the patch file.
4. **Copy & Paste** the snippet from your patch file into the corresponding spot.
5. **Save** the changes and confirm there are no syntax errors.

---

## Maintenance Notes

- **Version Compatibility**  
  Each patch depends on specific lines or methods in wc-multishipping. Whenever wc-multishipping updates, those lines may move or change. Always re-check or reapply as needed.

- **Upstream Changes**  
  If wc-multishipping adds hooks or features addressing these needs, you might no longer need these patches.

- **Security**  
  These patches allow front-end hooking of shipping label generation. Ensure this workflow is appropriate for your site.
