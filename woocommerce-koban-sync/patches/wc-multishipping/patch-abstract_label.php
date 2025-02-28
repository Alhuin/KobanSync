<?php
/**
 * Should be added to wc-multishipping/inc/admin/classes/abstract_classes/abstract_label.php
 *
 * This snippet saves the generated label to our protectedpdfs directory with the name `chronopost-label-{$tracking_number}`
 * It must be added to abstract_label::save_label_PDF, before the return statement of the try block
 *
 * To be able to check if the order was already successfully synced (We don't need to save the label locally anymore),
 *
 * We also need:
 * - to add $order as a parameter of the function:
 *    public static function save_label_PDF( $tracking_number, $order ) { <- add $order as parameter
 * - to call the function with the $order as parameter in  wc-multishipping/inc/admin/classes/abstract_classes/abstract_order.php
 *    $label_pdf_path = $label_class::save_label_PDF( $one_tracking_number, $order ); <- add $order as parameter
 *
 * This allows us to check if the order has already been successfully synced to avoid unnecessary label downloads.
 */
// ...
// foreach ( $files_to_merge as $one_file_to_merge ) {
// unlink( $one_file_to_merge );
// }
// ------------------------------------------------------------------------------------------------
// WCKoban: Save PDF to protected directory (will unlink after email to logistics is sent)
// ------------------------------------------------------------------------------------------------
			$upload_dir    = wp_upload_dir();
			$protected_dir = trailingslashit( $upload_dir['basedir'] ) . 'protected-pdfs';

if ( ! file_exists( $protected_dir ) ) {
	wp_mkdir_p( $protected_dir );
	file_put_contents( $protected_dir . '/.htaccess', "Order allow,deny\nDeny from all\n" );
}

			$filename = 'chronopost-label-' . $tracking_number . '.pdf';
			$filepath = trailingslashit( $protected_dir ) . $filename;

			// If the label is not already saved and the logistics email has not already been sent, save the label.
if ( ! file_exists( $filepath ) && 'success' !== $order->get_meta( 'check_sync', true ) ) {
	file_put_contents( $filepath, $label );
}
// ------------------------------------------------------------------------------------------------
// End WCKoban
// ------------------------------------------------------------------------------------------------
// return $file_to_save_name;
// } catch (\Exception $e) {
// return false;
// }
// }
//
// }
