<?php

namespace WCKoban\Admin;

/**
 * Add a meta box for the product's Koban GUID.
 */
function wckoban_add_product_meta_box() {
	add_meta_box(
		'wckoban_product_meta',
		__( 'Koban Product Data', 'woocommerce-koban-sync' ),
		__NAMESPACE__ . '\\wckoban_product_meta_box_cb',
		'product',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\wckoban_add_product_meta_box' );

/**
 * Callback function to render the product meta box fields.
 */
function wckoban_product_meta_box_cb( $post ) {
	wp_nonce_field( 'wckoban_save_product_meta', 'wckoban_product_meta_nonce' );
	$koban_guid = get_post_meta( $post->ID, 'koban_guid', true );
	?>
	<p>
		<label for="koban_guid"><strong><?php esc_html_e( 'Koban GUID', 'woocommerce-koban-sync' ); ?></strong></label><br>
		<input type="text" name="koban_guid" id="koban_guid"
				value="<?php echo esc_attr( $koban_guid ); ?>" style="width: 100%;">
	</p>
	<?php
}

/**
 * Save the product meta data when the product is saved.
 */
function wckoban_save_product_meta( $post_id ) {
	// Check nonce & autosave
	if (
		! isset( $_POST['wckoban_product_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_product_meta_nonce'], 'wckoban_save_product_meta' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	) {
		return;
	}

	// Only save if this is a 'product' post.
	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST['koban_guid'] ) ) {
		update_post_meta(
			$post_id,
			'koban_guid',
			sanitize_text_field( $_POST['koban_guid'] )
		);
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\wckoban_save_product_meta' );

/**
 * Add field to the "Add New Product Category" screen.
 */
function wckoban_product_cat_add_meta_field() {
	?>
	<div class="form-field">
		<label for="koban_code"><?php esc_html_e( 'Koban Code', 'woocommerce-koban-sync' ); ?></label>
		<input type="text" name="koban_code" id="koban_code" value="" />
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_add_meta_field', 10, 2 );

/**
 * Add field to the "Edit Product Category" screen.
 */
function wckoban_product_cat_edit_meta_field( $term ) {
	$koban_code = get_term_meta( $term->term_id, 'koban_code', true );
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="koban_code"><?php esc_html_e( 'Koban Code', 'woocommerce-koban-sync' ); ?></label>
		</th>
		<td>
			<input type="text" name="koban_code" id="koban_code" value="<?php echo esc_attr( $koban_code ); ?>" />
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_edit_meta_field', 10, 2 );

/**
 * Save the custom field when creating or editing a product category.
 */
function wckoban_save_product_cat_meta( $term_id ) {
	if ( isset( $_POST['koban_code'] ) ) {
		update_term_meta(
			$term_id,
			'koban_code',
			sanitize_text_field( $_POST['koban_code'] )
		);
	}
}
add_action( 'created_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );
add_action( 'edited_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );

/**
 * Add a meta box to the Order edit page.
 */
function wckoban_add_order_meta_box() {
	add_meta_box(
		'wckoban_order_meta',
		__( 'Koban Order Data', 'woocommerce-koban-sync' ),
		__NAMESPACE__ . '\\wckoban_order_meta_box_cb',
		'shop_order',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_shop_order', __NAMESPACE__ . '\\wckoban_add_order_meta_box' );

/**
 * Render fields in the Order meta box.
 */
function wckoban_order_meta_box_cb( $post ) {
	wp_nonce_field( 'wckoban_save_order_meta', 'wckoban_order_meta_nonce' );

	$koban_invoice_guid     = get_post_meta( $post->ID, 'koban_invoice_guid', true );
	$koban_payment_guid     = get_post_meta( $post->ID, 'koban_payment_guid', true );
	$koban_invoice_pdf_path = get_post_meta( $post->ID, 'koban_invoice_pdf_path', true );
	?>
	<p>
		<label for="koban_invoice_guid"><strong><?php esc_html_e( 'Koban Invoice GUID', 'woocommerce-koban-sync' ); ?></strong></label><br>
		<input type="text" name="koban_invoice_guid" id="koban_invoice_guid" value="<?php echo esc_attr( $koban_invoice_guid ); ?>" style="width: 100%;">
	</p>

	<p>
		<label for="koban_payment_guid"><strong><?php esc_html_e( 'Koban Payment GUID', 'woocommerce-koban-sync' ); ?></strong></label><br>
		<input type="text" name="koban_payment_guid" id="koban_payment_guid" value="<?php echo esc_attr( $koban_payment_guid ); ?>" style="width: 100%;">
	</p>

	<p>
		<label for="koban_invoice_pdf_path"><strong><?php esc_html_e( 'Koban Invoice PDF Path', 'woocommerce-koban-sync' ); ?></strong></label><br>
		<input type="text" name="koban_invoice_pdf_path" id="koban_invoice_pdf_path" value="<?php echo esc_attr( $koban_invoice_pdf_path ); ?>" style="width: 100%;">
	</p>
	<?php
}

/**
 * Save the order meta data when order is updated.
 */
function wckoban_save_order_meta( $post_id ) {
	// Check nonce & autosave
	if (
		! isset( $_POST['wckoban_order_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_order_meta_nonce'], 'wckoban_save_order_meta' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	) {
		return;
	}

	// Only proceed if this post is actually an order
	if ( 'shop_order' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST['koban_invoice_guid'] ) ) {
		update_post_meta( $post_id, 'koban_invoice_guid', sanitize_text_field( $_POST['koban_invoice_guid'] ) );
	}
	if ( isset( $_POST['koban_payment_guid'] ) ) {
		update_post_meta( $post_id, 'koban_payment_guid', sanitize_text_field( $_POST['koban_payment_guid'] ) );
	}
	if ( isset( $_POST['koban_invoice_pdf_path'] ) ) {
		update_post_meta( $post_id, 'koban_invoice_pdf_path', sanitize_text_field( $_POST['koban_invoice_pdf_path'] ) );
	}
}
add_action( 'save_post_shop_order', __NAMESPACE__ . '\\wckoban_save_order_meta' );


/**
 * Display Koban GUID in the user profile.
 */
function wckoban_show_user_fields( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$koban_guid = get_user_meta( $user->ID, 'koban_guid', true );
	?>
	<h2><?php esc_html_e( 'Koban Data', 'woocommerce-koban-sync' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="koban_guid"><?php esc_html_e( 'Koban GUID', 'woocommerce-koban-sync' ); ?></label></th>
			<td>
				<input type="text" name="koban_guid" id="koban_guid"
						value="<?php echo esc_attr( $koban_guid ); ?>" class="regular-text" />
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', __NAMESPACE__ . '\\wckoban_show_user_fields' );  // For your own profile
add_action( 'edit_user_profile', __NAMESPACE__ . '\\wckoban_show_user_fields' );   // For other users' profiles

/**
 * Save the Koban GUID when the user profile is updated.
 */
function wckoban_save_user_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['koban_guid'] ) ) {
		update_user_meta( $user_id, 'koban_guid', sanitize_text_field( $_POST['koban_guid'] ) );
	}
}
add_action( 'personal_options_update', __NAMESPACE__ . '\\wckoban_save_user_fields' ); // Self
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\wckoban_save_user_fields' ); // Other
