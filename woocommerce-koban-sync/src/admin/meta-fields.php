<?php

namespace WCKoban\Admin;

use WCKoban\Logger;

/**
 * Add a meta box for the product's Koban GUID.
 */
function wckoban_add_product_meta_box() {
	add_meta_box(
		'wckoban_product_meta',
		__( 'Koban Sync', 'woocommerce-koban-sync' ),
		__NAMESPACE__ . '\\wckoban_product_meta_box_cb',
		'product',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\wckoban_add_product_meta_box' );

/**
 * Callback function to render the product meta box fields.
 */
function wckoban_product_meta_box_cb( $post ) {
	wp_nonce_field( 'wckoban_save_product_meta', 'wckoban_product_meta_nonce' );
	$koban_product_guid = get_post_meta( $post->ID, KOBAN_PRODUCT_GUID_META_KEY, true );
	$koban_url          = get_option( 'wckoban_sync_options' )['koban_url'] ?? '';
	?>
	<table class="widefat wckoban_meta_box_data">
		<tbody id="wckoban-product-meta">
			<tr>
				<td>
					<strong><?php esc_html_e( 'Koban Product GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input type="text" name="<?php echo KOBAN_PRODUCT_GUID_META_KEY; ?>"
							id="<?php echo KOBAN_PRODUCT_GUID_META_KEY; ?>"
							value="<?php echo esc_attr( $koban_product_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_product_guid ) ? '' : 'disabled'; ?>
							onclick="this.setAttribute('disabled', '');"
					/>
				</td>
			</tr>
			<tr style="text-align:center;">
				<td>
					<?php if ( ! empty( $koban_product_guid ) && ! empty( $koban_url ) ) : ?>
						<a href="<?php echo esc_url( $koban_url . '/product/show/' . $koban_product_guid ); ?>"
							class="button button-primary" target="_blank">
							<?php esc_html_e( 'See Product', 'woocommerce-koban-sync' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
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

	if ( isset( $_POST[ KOBAN_PRODUCT_GUID_META_KEY ] ) ) {
		update_post_meta(
			$post_id,
			KOBAN_PRODUCT_GUID_META_KEY,
			sanitize_text_field( $_POST[ KOBAN_PRODUCT_GUID_META_KEY ] )
		);
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\wckoban_save_product_meta' );

/**
 * Add Category Code field to the "Add New Product Category" screen.
 */
function wckoban_product_cat_add_meta_field() {
	?>
	<div class="form-field">
		<label for="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>">
			<?php esc_html_e( 'Koban GUID', 'woocommerce-koban-sync' ); ?>
		</label>
		<input type="text" name="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>"
				id="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>" value=""
		/>
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_add_meta_field', 10, 2 );

/**
 * Add field to the "Edit Product Category" screen.
 */
function wckoban_product_cat_edit_meta_field( $term ) {
	$koban_category_code = get_term_meta( $term->term_id, KOBAN_CATEGORY_CODE_META_KEY, true );
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>">
				<?php esc_html_e( 'Koban Code', 'woocommerce-koban-sync' ); ?>
			</label>
		</th>
		<td>
			<input type="text" name="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>"
					id="<?php echo KOBAN_CATEGORY_CODE_META_KEY; ?>"
					value="<?php echo esc_attr( $koban_category_code ); ?>"
			/>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_edit_meta_field', 10, 2 );

/**
 * Save the custom field when creating or editing a product category.
 */
function wckoban_save_product_cat_meta( $term_id ) {
	if ( isset( $_POST[ KOBAN_CATEGORY_CODE_META_KEY ] ) ) {
		update_term_meta(
			$term_id,
			KOBAN_CATEGORY_CODE_META_KEY,
			sanitize_text_field( $_POST[ KOBAN_CATEGORY_CODE_META_KEY ] )
		);
	}
}
add_action( 'created_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );
add_action( 'edited_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );


/**
 * Register a “Koban Sync” meta box on the Order edit page.
 */
function wckoban_add_order_meta_box() {
	Logger::info( 'Bind' );
	add_meta_box(
		'wckoban_order_meta',
		__( 'Koban Sync', 'woocommerce-koban-sync' ),
		__NAMESPACE__ . '\\wckoban_order_meta_box_cb',
		'woocommerce_page_wc-orders',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_woocommerce_page_wc-orders', __NAMESPACE__ . '\\wckoban_add_order_meta_box' );

/**
 * Render fields in the Koban meta box for the order.
 */
function wckoban_order_meta_box_cb( $post ) {
	wp_nonce_field( 'wckoban_save_order_meta', 'wckoban_order_meta_nonce' );

	$order                  = wc_get_order( $post->ID );
	$koban_invoice_guid     = $order->get_meta( KOBAN_INVOICE_GUID_META_KEY, true );
	$koban_payment_guid     = $order->get_meta( KOBAN_PAYMENT_GUID_META_KEY, true );
	$koban_invoice_pdf_path = $order->get_meta( KOBAN_INVOICE_PDF_PATH_META_KEY, true );
	$koban_url              = get_option( 'wckoban_sync_options' )['koban_url'] ?? '';

	?>
	<table class="widefat wckoban_meta_box_options">
		<tbody id="wckoban-invoice-meta">
			<tr>
				<td>
					<strong><?php esc_html_e( 'Koban Invoice GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input type="text" name="<?php echo KOBAN_INVOICE_GUID_META_KEY; ?>"
							id="<?php echo KOBAN_INVOICE_GUID_META_KEY; ?>"
							value="<?php echo esc_attr( $koban_invoice_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_invoice_guid ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
			<tr style="text-align:center;">
				<td>
					<?php if ( ! empty( $koban_invoice_guid ) && ! empty( $koban_url ) ) : ?>
						<a href="<?php echo esc_url( $koban_url . '/invoice/show/' . $koban_invoice_guid ); ?>"
							class="button button-primary" target="_blank">
							<?php esc_html_e( 'See Invoice', 'woocommerce-koban-sync' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
		<tbody id="wckoban-payment-meta">
			<tr>
				<td>
					<strong><?php esc_html_e( 'Koban Payment GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input type="text" name="<?php echo KOBAN_PAYMENT_GUID_META_KEY; ?>"
							id="<?php echo KOBAN_PAYMENT_GUID_META_KEY; ?>"
							value="<?php echo esc_attr( $koban_payment_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_payment_guid ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
			<tr style="text-align:center;">
				<td>
					<?php if ( ! empty( $koban_payment_guid ) && ! empty( $koban_url ) ) : ?>
						<a href="<?php echo esc_url( $koban_url . '/payment/show/' . $koban_payment_guid ); ?>"
							class="button button-primary" target="_blank">
							<?php esc_html_e( 'See Payment', 'woocommerce-koban-sync' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>

		<tbody id="wckoban-invoice-pdf-meta">
			<tr>
				<td>
					<strong><?php esc_html_e( 'Koban Invoice PDF Path', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input type="text" name="<?php echo KOBAN_INVOICE_PDF_PATH_META_KEY; ?>"
							id="<?php echo KOBAN_INVOICE_PDF_PATH_META_KEY; ?>"
							value="<?php echo esc_attr( $koban_invoice_pdf_path ); ?>" style="width: 100%;"
						<?php echo empty( $koban_invoice_pdf_path ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
			<tr style="text-align: center;">
				<td>
					<?php if ( ! empty( $koban_invoice_pdf_path ) ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'wckoban_invoice_pdf' => '1',
									'order_id'            => $order->get_id(),
								),
								home_url()
							)
						);
						?>
						" class="button button-primary" target="_blank">
							<?php esc_html_e( 'See Invoice PDF', 'woocommerce-koban-sync' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}


/**
 * Save our Koban fields when the order is updated.
 */
function wckoban_save_order_meta( $post_id ) {
	if (
		! isset( $_POST['wckoban_order_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_order_meta_nonce'], 'wckoban_save_order_meta' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	) {
		return;
	}

	if ( 'shop_order' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST[ KOBAN_INVOICE_GUID_META_KEY ] ) ) {
		update_post_meta( $post_id, KOBAN_INVOICE_GUID_META_KEY, sanitize_text_field( $_POST[ KOBAN_INVOICE_GUID_META_KEY ] ) );
	}
	if ( isset( $_POST[ KOBAN_PAYMENT_GUID_META_KEY ] ) ) {
		update_post_meta( $post_id, KOBAN_PAYMENT_GUID_META_KEY, sanitize_text_field( $_POST[ KOBAN_PAYMENT_GUID_META_KEY ] ) );
	}
	if ( isset( $_POST[ KOBAN_INVOICE_PDF_PATH_META_KEY ] ) ) {
		update_post_meta( $post_id, KOBAN_INVOICE_PDF_PATH_META_KEY, sanitize_text_field( $_POST[ KOBAN_INVOICE_PDF_PATH_META_KEY ] ) );
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\wckoban_save_order_meta' );


/**
 * Display Koban GUID in the user profile.
 */
function wckoban_show_user_fields( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$koban_third_guid = get_user_meta( $user->ID, KOBAN_THIRD_GUID_META_KEY, true );
	?>
	<h2><?php esc_html_e( 'Koban Sync', 'woocommerce-koban-sync' ); ?></h2>
	<table class="form-table">
		<tr>
			<th>
				<label for="<?php echo KOBAN_THIRD_GUID_META_KEY; ?>">
					<?php esc_html_e( 'Koban Account GUID', 'woocommerce-koban-sync' ); ?>
				</label>
			</th>
			<td>
				<input type="text" name="<?php echo KOBAN_THIRD_GUID_META_KEY; ?>"
						id="<?php echo KOBAN_THIRD_GUID_META_KEY; ?>"
						value="<?php echo esc_attr( $koban_third_guid ); ?>" class="regular-text"
					<?php echo empty( $koban_third_guid ) ? '' : 'disabled'; ?>
				/>
			</td>
		</tr>
		<tr style="text-align:center;">
			<td>
				<?php if ( ! empty( $koban_third_guid ) && ! empty( $koban_url ) ) : ?>
					<a href="<?php echo esc_url( $koban_url . '/third/show/' . $koban_third_guid ); ?>"
						class="button button-primary" target="_blank">
						<?php esc_html_e( 'See Account', 'woocommerce-koban-sync' ); ?>
					</a>
				<?php endif; ?>
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
	if ( isset( $_POST[ KOBAN_THIRD_GUID_META_KEY ] ) ) {
		update_user_meta( $user_id, KOBAN_THIRD_GUID_META_KEY, sanitize_text_field( $_POST[ KOBAN_THIRD_GUID_META_KEY ] ) );
	}
}
add_action( 'personal_options_update', __NAMESPACE__ . '\\wckoban_save_user_fields' ); // Self
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\wckoban_save_user_fields' ); // Other