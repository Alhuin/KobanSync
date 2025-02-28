<?php
/**
 * This short snippet should go at the top of the __construct() function of wms_front_init class,
 * in wc-multishipping/inc/front/wms_front_init.php
 *
 * The devs have decided to restrict the label auto-generation on order_status change to only admin pages,
 * because plugins on some sites would abuse order_status modifications on the front_end, which results in
 * a lot of label generation.
 *
 * For our use-case, registering the woocommerce_order_status_changed hook in the front-end is quite safe,
 * so we can register the providers hooks (Chronopost for now) at the top of wms_front_init's constructor.
 */
// ...
// class wms_front_init {
// public function __construct() {
// ------------------------------------------------------------------------------------------------
// WCKoban: Register Chronopost order_status_change hooks on the front-end
// ------------------------------------------------------------------------------------------------
			chronopost_pickup_widget::register_hooks();
// ------------------------------------------------------------------------------------------------
// End WCKoban
// ------------------------------------------------------------------------------------------------
// mondial_relay_pickup_widget::register_hooks();
// ...
