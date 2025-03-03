<?php
/**
 * Meta keys for koban data
 * /!\ DO NOT CHANGE ONCE IN PRODUCTION /!\
 *
 * @package WooCommerceKobanSync
 */

define( 'KOBAN_THIRD_GUID_META_KEY', 'koban_third_guid' );
define( 'KOBAN_PRODUCT_GUID_META_KEY', 'koban_product_guid' );
define( 'KOBAN_INVOICE_GUID_META_KEY', 'koban_invoice_guid' );
define( 'KOBAN_PAYMENT_GUID_META_KEY', 'koban_payment_guid' );
define( 'KOBAN_INVOICE_PDF_PATH_META_KEY', 'koban_invoice_pdf_path' );
define( 'KOBAN_CATEGORY_CODE_META_KEY', 'koban_category_code' );
define( 'KOBAN_WORKFLOW_FAILED_STEP_META_KEY', '_koban_workflow_failed_step' );
define( 'KOBAN_WORKFLOW_STATUS_META_KEY', '_koban_workflow_status' );

define( 'INVOICE_PREFIX', 'INV-' );
define( 'PRODUCT_PREFIX', 'PROD-' );
define( 'PAYMENT_PREFIX', 'PAY-' );

define( 'CUSTOMER_THIRD_STATUS_CODE', 'PTC' );
define( 'PRO_CUSTOMER_THIRD_STATUS_CODE', 'PRO' );

define(
	'CUSTOMER_COUNTRY_THIRD_TYPE_CODE_MAPPER',
	array(
		'FR' => 'P',
		'BE' => 'PB',
		'CH' => 'PS',
		'LX' => 'PLX',
	)
);

define( 'DEFAULT_ASSIGNEDTO_FULLNAME', 'Florian Piedimonte' );
define( 'DEFAULT_PAYMENTMODE_CODE', 'TPE' );
