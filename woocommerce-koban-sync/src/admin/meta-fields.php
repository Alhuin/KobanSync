<?php
/**
 * This file contains functions to manage the meta boxes for Koban sync (product, order, and user information)
 * within the WooCommerce admin interface. These meta boxes handle the Koban GUIDs, the payment, invoice, and
 * user-specific information by displaying them on the product, category, order, and user profile pages.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Admin;

use WC_Order;
use WP_Post;
use WP_User;
use WP_Term;
use WC_Product;
use WCKoban\Utils\MetaUtils;

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
 *
 * @param WP_Post $post The WP_Post object.
 */
function wckoban_product_meta_box_cb( WP_Post $post ) {
	wp_nonce_field( 'wckoban_save_product_meta', 'wckoban_product_meta_nonce' );
	$product            = wc_get_product( $post->ID );
	$koban_product_guid = MetaUtils::get_koban_product_guid( $product );
	$koban_url          = get_option( 'wckoban_sync_options' )['koban_url'] ?? '';
	?>
	<table class="widefat wckoban_meta_box_data">
		<tbody id="wckoban-product-meta">
			<tr>
				<td>
					<strong><?php echo esc_html__( 'Koban Product GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input
							type="text"
							name="<?php echo esc_attr( KOBAN_PRODUCT_GUID_META_KEY ); ?>"
							id="<?php echo esc_attr( KOBAN_PRODUCT_GUID_META_KEY ); ?>"
							value="<?php echo esc_attr( $koban_product_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_product_guid ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
			<tr style="text-align:center;">
				<td>
					<?php if ( ! empty( $koban_product_guid ) && ! empty( $koban_url ) ) : ?>
						<a
								href="<?php echo esc_url( $koban_url . '/product/show/' . $koban_product_guid ); ?>"
								class="button button-primary" target="_blank">
							<?php echo esc_html__( 'View Product', 'woocommerce-koban-sync' ); ?>
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
 *
 * @param int $post_id The WP_Post ID representing the WC_Product.
 */
function wckoban_save_product_meta( int $post_id ) {
	if (
		! isset( $_POST['wckoban_product_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_product_meta_nonce'], 'wckoban_save_product_meta' )   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	) {
		return;
	}

	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST[ KOBAN_PRODUCT_GUID_META_KEY ] ) ) {
		MetaUtils::set_koban_product_guid_for_product_id(
			$post_id,
			sanitize_text_field( wp_unslash( $_POST[ KOBAN_PRODUCT_GUID_META_KEY ] ) )
		);
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\wckoban_save_product_meta' );

/**
 * Add Category Guid field to the "Add New Product Category" screen.
 */
function wckoban_product_cat_add_meta_field() {
	wp_nonce_field( 'wckoban_save_category_meta', 'wckoban_category_meta_nonce' );
	?>
	<div class="form-field">
		<label for="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>">
			<?php echo esc_html__( 'Koban Code', 'woocommerce-koban-sync' ); ?>
		</label>
		<input
				type="text"
				name="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>"
				id="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>"
				value=""
		/>
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_add_meta_field', 10, 2 );

/**
 * Add field to the "Edit Product Category" screen.
 *
 * @param WP_Term $term The WP Category.
 */
function wckoban_product_cat_edit_meta_field( WP_Term $term ) {
	wp_nonce_field( 'wckoban_save_category_meta', 'wckoban_category_meta_nonce' );
	$koban_category_code = MetaUtils::get_koban_category_code( $term->term_id );
	?>
	<tr class="form-field">
		<th scope="row" valign="top">
			<label for="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>">
				<?php echo esc_html__( 'Koban Code', 'woocommerce-koban-sync' ); ?>
			</label>
		</th>
		<td>
			<input
					type="text" name="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>"
					id="<?php echo esc_attr( KOBAN_CATEGORY_CODE_META_KEY ); ?>"
					value="<?php echo esc_attr( $koban_category_code ); ?>"
				<?php echo empty( $koban_category_code ) ? '' : 'disabled'; ?>
			/>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', __NAMESPACE__ . '\\wckoban_product_cat_edit_meta_field', 10, 2 );

/**
 * Save the custom field when creating or editing a product category.
 *
 * @param int $term_id The Category ID.
 */
function wckoban_save_product_cat_meta( int $term_id ) {
	if (
		! isset( $_POST['wckoban_category_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_category_meta_nonce'], 'wckoban_save_category_meta' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	) {
		return;
	}

	if ( isset( $_POST[ KOBAN_CATEGORY_CODE_META_KEY ] ) ) {
		MetaUtils::set_koban_category_code(
			$term_id,
			sanitize_text_field( wp_unslash( $_POST[ KOBAN_CATEGORY_CODE_META_KEY ] ) )
		);
	}
}
add_action( 'created_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );
add_action( 'edited_product_cat', __NAMESPACE__ . '\\wckoban_save_product_cat_meta', 10, 2 );


/**
 * Register a “Koban Sync” meta box on the Order edit page.
 */
function wckoban_add_order_meta_box() {
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
 *
 * @param WC_Order $post the WordPress Post object representing the WC_Order.
 */
function wckoban_order_meta_box_cb( WC_Order $post ) {
	wp_nonce_field( 'wckoban_save_order_meta', 'wckoban_order_meta_nonce' );

	$order                  = wc_get_order( $post->ID );
	$koban_invoice_guid     = MetaUtils::get_koban_invoice_guid( $order );
	$koban_payment_guid     = MetaUtils::get_koban_payment_guid( $order );
	$koban_invoice_pdf_path = MetaUtils::get_koban_invoice_pdf_path( $order );
	$koban_url              = get_option( 'wckoban_sync_options' )['koban_url'] ?? '';

	?>
	<table class="widefat wckoban_meta_box_options">
		<tbody id="wckoban-invoice-meta">
			<tr>
				<td>
					<strong><?php echo esc_html__( 'Koban Invoice GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input
							type="text" name="<?php echo esc_attr( KOBAN_INVOICE_GUID_META_KEY ); ?>"
							id="<?php echo esc_attr( KOBAN_INVOICE_GUID_META_KEY ); ?>"
							value="<?php echo esc_attr( $koban_invoice_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_invoice_guid ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
			<tr style="text-align:center;">
				<td>
					<?php if ( ! empty( $koban_invoice_guid ) && ! empty( $koban_url ) ) : ?>
						<a
								href="<?php echo esc_url( $koban_url . '/invoice/show/' . $koban_invoice_guid ); ?>"
								class="button button-primary" target="_blank">
							<?php echo esc_html__( 'View Invoice', 'woocommerce-koban-sync' ); ?>
						</a>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
		<tbody id="wckoban-payment-meta">
			<tr>
				<td>
					<strong><?php echo esc_html__( 'Koban Payment GUID', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input
							type="text" name="<?php echo esc_attr( KOBAN_PAYMENT_GUID_META_KEY ); ?>"
							id="<?php echo esc_attr( KOBAN_PAYMENT_GUID_META_KEY ); ?>"
							value="<?php echo esc_attr( $koban_payment_guid ); ?>" style="width: 100%;"
						<?php echo empty( $koban_payment_guid ) ? '' : 'disabled'; ?>
					/>
				</td>
			</tr>
		</tbody>

		<tbody id="wckoban-invoice-pdf-meta">
			<tr>
				<td>
					<strong><?php echo esc_html__( 'Koban Invoice PDF Path', 'woocommerce-koban-sync' ); ?></strong>
				</td>
			</tr>
			<tr>
				<td>
					<input
							type="text" name="<?php echo esc_attr( KOBAN_INVOICE_PDF_PATH_META_KEY ); ?>"
							id="<?php echo esc_attr( KOBAN_INVOICE_PDF_PATH_META_KEY ); ?>"
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
						"
							class="button button-primary" target="_blank">
							<?php echo esc_html__( 'View Invoice PDF', 'woocommerce-koban-sync' ); ?>
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
 *
 * @param int $post_id The WP_Post ID representing the WC_Order.
 */
function wckoban_save_order_meta( int $post_id ) {
	if (
		! isset( $_POST['wckoban_order_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_order_meta_nonce'], 'wckoban_save_order_meta' )   // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
	) {
		return;
	}

	if ( 'shop_order' !== get_post_type( $post_id ) ) {
		return;
	}

	if ( isset( $_POST[ KOBAN_INVOICE_GUID_META_KEY ] ) ) {
		MetaUtils::set_koban_invoice_guid_for_order_id(
			$post_id,
			sanitize_text_field(
				wp_unslash( $_POST[ KOBAN_INVOICE_GUID_META_KEY ] )
			)
		);
	}
	if ( isset( $_POST[ KOBAN_PAYMENT_GUID_META_KEY ] ) ) {
		MetaUtils::set_koban_payment_guid_for_order_id(
			$post_id,
			sanitize_text_field(
				wp_unslash( $_POST[ KOBAN_PAYMENT_GUID_META_KEY ] )
			)
		);
	}
	if ( isset( $_POST[ KOBAN_INVOICE_PDF_PATH_META_KEY ] ) ) {
		MetaUtils::set_koban_invoice_pdf_path_for_order_id(
			$post_id,
			sanitize_text_field(
				wp_unslash( $_POST[ KOBAN_INVOICE_PDF_PATH_META_KEY ] )
			)
		);
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\wckoban_save_order_meta' );


/**
 * Display Koban GUID in the user profile.
 *
 * @param WP_User $user The user.
 */
function wckoban_show_user_fields( WP_User $user ) {
	wp_nonce_field( 'wckoban_save_user_meta', 'wckoban_user_meta_nonce' );

	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}
	$koban_third_guid = MetaUtils::get_koban_third_guid( $user->ID );
	$koban_url        = get_option( 'wckoban_sync_options' )['koban_url'] ?? '';
	?>
	<h2><?php echo esc_html__( 'Koban Sync', 'woocommerce-koban-sync' ); ?></h2>
	<table class="form-table">
		<tr>
			<th>
				<label for="<?php echo esc_attr( KOBAN_THIRD_GUID_META_KEY ); ?>">
					<?php echo esc_html__( 'Koban Account GUID', 'woocommerce-koban-sync' ); ?>
				</label>
			</th>
			<td>
				<input
						type="text" name="<?php echo esc_attr( KOBAN_THIRD_GUID_META_KEY ); ?>"
						id="<?php echo esc_attr( KOBAN_THIRD_GUID_META_KEY ); ?>"
						value="<?php echo esc_attr( $koban_third_guid ); ?>" class="regular-text"
					<?php echo empty( $koban_third_guid ) ? '' : 'disabled'; ?>
				/>
			</td>
		</tr>
		<tr style="text-align:center;">
			<td>
				<?php if ( ! empty( $koban_third_guid ) && ! empty( $koban_url ) ) : ?>
					<a
							href="<?php echo esc_url( $koban_url . '/third/show/' . $koban_third_guid ); ?>"
							class="button button-primary" target="_blank">
						<?php echo esc_html__( 'View Account', 'woocommerce-koban-sync' ); ?>
					</a>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', __NAMESPACE__ . '\\wckoban_show_user_fields' );
add_action( 'edit_user_profile', __NAMESPACE__ . '\\wckoban_show_user_fields' );

/**
 * Save the Koban GUID when the user profile is updated.
 *
 * @param int $user_id  The user ID.
 */
function wckoban_save_user_fields( int $user_id ) {
	if (
		! isset( $_POST['wckoban_user_meta_nonce'] )
		|| ! wp_verify_nonce( $_POST['wckoban_user_meta_nonce'], 'wckoban_save_user_meta' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	) {
		return;
	}

	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST[ KOBAN_THIRD_GUID_META_KEY ] ) ) {
		MetaUtils::set_koban_third_guid(
			$user_id,
			sanitize_text_field(
				wp_unslash( $_POST[ KOBAN_THIRD_GUID_META_KEY ] )
			)
		);
	}
}
add_action( 'personal_options_update', __NAMESPACE__ . '\\wckoban_save_user_fields' );
add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\wckoban_save_user_fields' );