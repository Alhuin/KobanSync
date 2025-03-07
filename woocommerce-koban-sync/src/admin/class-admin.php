<?php
/**
 * WCKoban_Admin class file.
 *
 * Handles the admin interface for Koban Sync, including settings and logs display.
 *
 * @package WooCommerceKobanSync
 */

namespace WCKoban\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for managing Koban Sync plugin settings and displaying logs.
 */
class Admin {

	/**
	 * Constructor. Registers all admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_footer', array( $this, 'render_custom_admin_footer' ) );
		add_action( 'wp_ajax_wckoban_view_debug_logs', array( $this, 'handle_ajax_debug_logs' ) );
	}

	/**
	 * Adds a top-level menu page for Koban Sync settings and logs.
	 */
	public function add_settings_page(): void {
		add_menu_page(
			__( 'Koban Sync Settings', 'woocommerce-koban-sync' ),
			__( 'Koban Sync', 'woocommerce-koban-sync' ),
			'manage_options',
			'wckoban-sync-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic'
		);
	}

	/**
	 * Registers plugin settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'wckoban_sync_settings_group',
			'wckoban_sync_options',
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);

		add_settings_section(
			'wckoban_sync_section',
			__( 'Koban Settings', 'woocommerce-koban-sync' ),
			array( $this, 'render_settings_section' ),
			'wckoban-sync-settings-page'
		);

		add_settings_field(
			'koban_api_key',
			__( 'API Key', 'woocommerce-koban-sync' ),
			array( $this, 'render_api_key_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);

		add_settings_field(
			'koban_user_key',
			__( 'User Key', 'woocommerce-koban-sync' ),
			array( $this, 'render_user_key_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);

		add_settings_field(
			'koban_api_url',
			__( 'Koban API URL', 'woocommerce-koban-sync' ),
			array( $this, 'render_api_url_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);

		add_settings_field(
			'koban_url',
			__( 'Koban URL', 'woocommerce-koban-sync' ),
			array( $this, 'render_url_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);
	}

	/**
	 * Renders the introductory text for the main settings section.
	 */
	public function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Enter your Koban settings below.', 'woocommerce-koban-sync' ) . '</p>';
	}

	/**
	 * Renders the API Key field for the settings form.
	 */
	public function render_api_key_field(): void {
		$options = get_option( 'wckoban_sync_options' );
		$api_key = isset( $options['koban_api_key'] ) ? $options['koban_api_key'] : '';
		echo '<input type="password" name="wckoban_sync_options[koban_api_key]" value="' . esc_attr( $api_key ) . '" style="width:300px;" />';
	}

	/**
	 * Renders the User Key field for the settings form.
	 */
	public function render_user_key_field(): void {
		$options  = get_option( 'wckoban_sync_options' );
		$user_key = isset( $options['koban_user_key'] ) ? $options['koban_user_key'] : '';
		echo '<input type="password" name="wckoban_sync_options[koban_user_key]" value="' . esc_attr( $user_key ) . '" style="width:300px;" />';
	}

	/**
	 * Renders the Koban API URL field for the settings form.
	 */
	public function render_api_url_field(): void {
		$options = get_option( 'wckoban_sync_options' );
		$api_url = isset( $options['koban_api_url'] ) ? $options['koban_api_url'] : '';
		echo '<input type="url" name="wckoban_sync_options[koban_api_url]" value="' . esc_attr( $api_url ) . '" style="width:300px;" />';
	}

	/**
	 * Renders the Koban URL field for the settings form.
	 */
	public function render_url_field(): void {
		$options = get_option( 'wckoban_sync_options' );
		$url     = isset( $options['koban_url'] ) ? $options['koban_url'] : '';
		echo '<input type="url" name="wckoban_sync_options[koban_url]" value="' . esc_attr( $url ) . '" style="width:300px;" />';
	}

	/**
	 * Sanitizes the plugin settings values.
	 *
	 * @param array $input The raw input array of settings.
	 *
	 * @return array The sanitized settings array.
	 */
	public function sanitize_options( array $input ): array {
		$clean = array();

		$clean['koban_api_key'] = isset( $input['koban_api_key'] )
		? sanitize_text_field( $input['koban_api_key'] )
		: '';

		$clean['koban_user_key'] = isset( $input['koban_user_key'] )
		? sanitize_text_field( $input['koban_user_key'] )
		: '';

		$clean['koban_api_url'] = isset( $input['koban_api_url'] )
		? esc_url_raw( $input['koban_api_url'] )
		: '';

		$clean['koban_url'] = isset( $input['koban_url'] )
		? esc_url_raw( $input['koban_url'] )
		: '';

		return $clean;
	}

	/**
	 * Renders the main settings page, including a tab for logs.
	 */
	public function render_settings_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		?>
		<div class="wrap">
			<h1>Koban Sync</h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wckoban-sync-settings&tab=settings"
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html__( 'Settings', 'woocommerce-koban-sync' ); ?>
				</a>
				<a href="?page=wckoban-sync-settings&tab=logs"
					class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html__( 'Logs', 'woocommerce-koban-sync' ); ?>
				</a>
			</h2>

			<?php if ( 'settings' === $active_tab ) : ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wckoban_sync_settings_group' );
					do_settings_sections( 'wckoban-sync-settings-page' );
					submit_button( __( 'Save Changes', 'woocommerce-koban-sync' ) );
					?>
				</form>
			<?php else : ?>
				<?php
				$this->render_workflow_logs();
				$this->render_modal_markup();
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Queries and displays the most recent logs from the custom Koban Sync logs table, with collapsible rows for details.
	 *
	 * TODO: handle pagination
	 */
	public function render_workflow_logs(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'koban_sync_logs';
		$results = $wpdb->get_results(
		    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY time DESC LIMIT 100" )
		);

		echo '<table class="mdc-data-table" style="width:100%;border-collapse:collapse;">';
		echo '  <thead class="mdc-data-table__header-row">';
		echo '    <tr>';
		echo '      <th>' . esc_html__( 'Action Type', 'woocommerce-koban-sync' ) . '</th>';
		echo '      <th>' . esc_html__( 'Workflow ID', 'woocommerce-koban-sync' ) . '</th>';
		echo '      <th>' . esc_html__( 'Status', 'woocommerce-koban-sync' ) . '</th>';
		echo '      <th>' . esc_html__( 'Time', 'woocommerce-koban-sync' ) . '</th>';
		echo '    </tr>';
		echo '  </thead>';
		echo '  <tbody class="mdc-data-table__content">';

		if ( $results ) {
			foreach ( $results as $row ) {
				$workflow_id = $row->workflow_id;
				$action_type = $row->action_type;
				$status      = $row->status;
				$log_time    = $row->time;
				$color       = $this->color_for_status( $status );
				$payload_arr = ! empty( $row->payload ) ? json_decode( $row->payload, true ) : array();
				?>
				<!-- Parent row (clickable) -->
				<tr class="workflow-row"
					data-workflow-id="<?php echo esc_attr( $workflow_id ); ?>"
					style="cursor:pointer;">
					<td><?php echo esc_html( $action_type ); ?></td>
					<td><?php echo esc_html( $workflow_id ); ?></td>
					<td style="color:<?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( $status ); ?>
					</td>
					<td><?php echo esc_html( $log_time ); ?></td>
				</tr>

				<!-- Child row: scheduling message -->
				<?php if ( ! empty( $row->scheduling_message ) ) : ?>
					<tr class="detail-row"
						data-parent="<?php echo esc_attr( $workflow_id ); ?>"
						style="display:none;">
						<td colspan="4" class="workflow-detail">
							<strong><?php esc_html_e( 'Initial Scheduling', 'woocommerce-koban-sync' ); ?>:</strong>
							<?php echo esc_html( $row->scheduling_message ); ?>
						</td>
					</tr>
				<?php endif; ?>

				<!-- Child rows for each step in payload -->
				<?php
				foreach ( $payload_arr as $step_name => $step_data ) {
					if ( 'status' === $step_name ) {
						continue;
					}
					$step_status  = $step_data['status'] ?? '(unknown)';
					$step_message = $step_data['message'] ?? '';
					$step_color   = $this->color_for_status( $step_status );
					?>
					<tr class="detail-row"
						data-parent="<?php echo esc_attr( $workflow_id ); ?>"
						style="display:none;">
						<td colspan="4" class="workflow-detail">
							<strong><?php echo esc_html( $step_name ); ?></strong>:
							<span style="color:<?php echo esc_attr( $step_color ); ?>;">
								<?php echo esc_html( $step_status ); ?>
							</span>
							<?php if ( $step_message ) : ?>
								<br><em><?php echo esc_html( $step_message ); ?></em>
							<?php endif; ?>
						</td>
					</tr>
					<?php
				}
				?>
				<!-- Child row: debug logs button -->
				<tr class="detail-row"
					data-parent="<?php echo esc_attr( $workflow_id ); ?>"
					style="display:none;">
					<td colspan="4" class="workflow-detail">
						<button class="view-debug-btn button button-secondary"
								data-workflow-id="<?php echo esc_attr( $workflow_id ); ?>">
							<?php esc_html_e( 'View Debug Logs', 'woocommerce-koban-sync' ); ?>
						</button>
					</td>
				</tr>
				<?php
			}
		} else {
			echo '<tr><td colspan="4">' . esc_html__( 'No workflow logs found.', 'woocommerce-koban-sync' ) . '</td></tr>';
		}

		echo '  </tbody>';
		echo '</table>';
	}

	/**
	 * Basic color mapping for workflow statuses.
	 *
	 * @param string $status The status string.
	 * @return string Hex or color name.
	 */
	private function color_for_status( string $status ): string {
		switch ( $status ) {
			case 'success':
				return 'green';
			case 'failed':
				return 'red';
			case 'scheduled':
			case 'processing':
				return 'orange';
			default:
				return 'gray';
		}
	}

	/**
	 * Outputs hidden modal markup for viewing debug logs.
	 */
	private function render_modal_markup(): void {
		?>
		<div id="debug-modal"
			style="
				display:none;
				position:fixed;
				z-index:9999;
				left:0;
				top:0;
				width:100%;
				height:100%;
				background-color:rgba(0,0,0,0.5);
				overflow-y:auto;">
			<div id="debug-modal-box"
				style="
					background-color:#fff;
					margin:5% auto;
					padding:20px;
					border-radius:4px;
					width:80%;
					max-width:800px;
					position:relative;">
				<button id="debug-modal-close"
						class="dashicons dashicons-no-alt"
						style="
							font-size:24px;
							position:absolute;
							top:10px;
							right:20px;
							cursor:pointer;
							background:none;
							border:none;">
				</button>
				<div id="debug-modal-content"
					style="
						white-space:pre-wrap;
						word-wrap:break-word;
						max-height:500px;
						overflow-y:auto;">
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a custom admin footer script via the "admin_footer" hook.
	 */
	public function render_custom_admin_footer(): void {
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Toggle child rows on parent row click
				const workflowRows = document.querySelectorAll('.workflow-row');
				workflowRows.forEach(function(row) {
					row.addEventListener('click', function() {
						const workflowId = this.getAttribute('data-workflow-id');
						const detailRows = document.querySelectorAll('.detail-row[data-parent="'+ workflowId +'"]');
						detailRows.forEach(function(dr) {
							dr.style.display = (dr.style.display === 'none' || dr.style.display === '')
								? 'table-row'
								: 'none';
						});
					});
				});

				// Debug log modal
				const debugModal       = document.getElementById('debug-modal');
				const debugModalClose  = document.getElementById('debug-modal-close');
				const debugModalContent= document.getElementById('debug-modal-content');

				function openModal() {
					debugModal.style.display = 'block';
					document.body.style.overflow = 'hidden'; // prevent background scroll
				}

				function closeModal() {
					debugModal.style.display = 'none';
					document.body.style.overflow = ''; // restore scroll
				}

				debugModalClose.addEventListener('click', function() {
					closeModal();
				});
				debugModal.addEventListener('click', function(e) {
					if (e.target === debugModal) {
						closeModal();
					}
				});

				// "View Debug Logs" button logic
				const debugButtons = document.querySelectorAll('.view-debug-btn');
				debugButtons.forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						// Don't toggle child row expansion
						e.stopPropagation();

						const workflowId = this.getAttribute('data-workflow-id');
						if (!workflowId) return;

						debugModalContent.textContent = 'Loading debug logs...';
						openModal();

						const nonce = '<?php echo esc_html( wp_create_nonce( 'wckoban_debug_nonce' ) ); ?>';
						fetch(ajaxurl, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								action: 'wckoban_view_debug_logs',
								workflow_id: workflowId,
								nonce: nonce
							})
						})
							.then(res => res.json())
							.then(data => {
								if (!data.success) {
									debugModalContent.textContent = 'Error: ' + (data.data || 'Unknown error');
									return;
								}
								const lines = data.data.lines || [];
								if (!lines.length) {
									debugModalContent.textContent = 'No debug logs found for this workflow.';
									return;
								}
								debugModalContent.innerHTML = '<pre style="white-space: pre-wrap;">' + lines.join('\n') + '</pre>';
							})
							.catch(err => {
								debugModalContent.textContent = 'Fetch error: ' + err;
							});
					});
				});
			});
		</script>

		<style>
			.workflow-row {
				cursor: pointer;
			}
			.workflow-row:hover {
				background-color: #fafafa;
			}
			.detail-row {
				background-color: #fcfcfc;
				border-left: 4px solid #dcdcdc;
			}
			.mdc-data-table__header-row > th {
				border-bottom: 2px solid #ddd;
			}
			.mdc-data-table__content td {
				border-bottom: 1px solid #eee;
			}
			.mdc-data-table th,
			.mdc-data-table td {
				padding: 1rem;
				text-align: left;
			}
		</style>
		<?php
	}

	/**
	 * AJAX callback: reads the debug file for lines matching a workflow ID.
	 */
	public function handle_ajax_debug_logs(): void {
		check_ajax_referer( 'wckoban_debug_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed', 403 );
		}

		$workflow_id = sanitize_text_field( wp_unslash( $_POST['workflow_id'] ?? '' ) );
		if ( empty( $workflow_id ) ) {
			wp_send_json_error( 'Missing workflow_id', 400 );
		}

		$log_file = WP_CONTENT_DIR . '/uploads/koban-debug.log';
		if ( ! file_exists( $log_file ) ) {
			wp_send_json_error( 'No debug log file found', 404 );
		}

		$matching_lines = array();
		$handle         = fopen( $log_file, 'r' );
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				if ( strpos( $line, $workflow_id ) !== false ) {
					$matching_lines[] = rtrim( $line . '<br />' );
				}
			}
			fclose( $handle );
		}

		if ( empty( $matching_lines ) ) {
			wp_send_json_success(
				array(
					'lines' => array( 'No debug logs found for this workflow.' ),
				)
			);
		} else {
			wp_send_json_success( array( 'lines' => $matching_lines ) );
		}
	}
}
