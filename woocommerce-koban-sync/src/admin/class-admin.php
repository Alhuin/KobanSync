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
	 * Constructor that sets up the admin menu and registers settings.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_footer', array( $this, 'render_custom_admin_footer' ) );
	}

	/**
	 * Adds a new top-level menu page for Koban Sync settings.
	 */
	public function add_settings_page(): void {
		add_menu_page(
			'Koban Sync Settings',  // Page title.
			'Koban Sync',           // Menu label.
			'manage_options',       // Capability.
			'wckoban-sync-settings', // Slug.
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
			'Koban API Settings',
			array( $this, 'render_settings_section' ),
			'wckoban-sync-settings-page'
		);

		add_settings_field(
			'koban_api_key',
			'API Key',
			array( $this, 'render_api_key_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);

		add_settings_field(
			'koban_user_key',
			'User Key',
			array( $this, 'render_user_key_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);

		add_settings_field(
			'koban_url',
			'Koban API URL',
			array( $this, 'render_api_url_field' ),
			'wckoban-sync-settings-page',
			'wckoban_sync_section'
		);
	}

	/**
	 * Renders the introductory text for the main settings section.
	 */
	public function render_settings_section(): void {
		echo '<p>Enter your Koban API credentials below.</p>';
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
		$api_url = isset( $options['koban_url'] ) ? $options['koban_url'] : '';
		echo '<input type="url" name="wckoban_sync_options[koban_url]" value="' . esc_attr( $api_url ) . '" style="width:300px;" />';
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
					Réglages
				</a>
				<a href="?page=wckoban-sync-settings&tab=logs"
					class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					Logs
				</a>
			</h2>
		<?php
		if ( 'settings' === $active_tab ) {
			?>
				<form method="post" action="options.php">
			<?php
			settings_fields( 'wckoban_sync_settings_group' );
			do_settings_sections( 'wckoban-sync-settings-page' );
			submit_button( 'Save Changes' );
			?>
				</form>
			<?php
		} else {
			$this->display_logs_table();
		}
		?>
		</div>
		<?php
	}

	/**
	 * Queries and displays the most recent logs from the custom Koban Sync logs table.
	 *
	 * TODO: handle pagination
	 */
	public function display_logs_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'koban_sync_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC LIMIT 50" );

		echo '<h2>Synchronization Logs</h2>';
		echo '<table class="widefat fixed" cellspacing="0">';
		echo '<thead><tr>';
		echo '<th>ID</th><th>Date/Time</th><th>Level</th><th>Message</th><th>Context</th>';
		echo '</tr></thead>';

		echo '<tbody>';
		if ( $logs ) {
			foreach ( $logs as $log ) {
				echo '<tr>';
				echo '<td>' . esc_html( $log->id ) . '</td>';
				echo '<td>' . esc_html( $log->time ) . '</td>';
				echo '<td>' . esc_html( $log->level ) . '</td>';
				echo '<td>' . esc_html( $log->message ) . '</td>';

				$context_decoded = json_decode( $log->context, true );
				$context_json    = wp_json_encode( $context_decoded );

				echo '<td>';
				echo '<button type="button" class="open-context-btn" data-context="' . esc_attr( $context_json ) . '">View Context</button>';
				echo '</td>';
				echo '<tr style="display:none;"><td colspan="5"><pre class="context-content"></pre></td></tr>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="5">No logs available.</td></tr>';
		}
		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Renders a custom admin footer script via the "admin_footer" hook.
	 */
	public function render_custom_admin_footer(): void {
		?>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.open-context-btn').forEach(button => {
					button.addEventListener('click', function () {
						const contextRow = this.closest('tr').nextElementSibling;
						const context = this.getAttribute('data-context');
						const contextObj = JSON.parse(context);

						contextRow.querySelector('.context-content').textContent = JSON.stringify(contextObj, null, 2);

						if (contextRow.style.display === 'table-row') {
							contextRow.style.display = 'none';
							this.textContent = 'View Context';
						} else {
							contextRow.style.display = 'table-row';
							this.textContent = 'Hide Context';
						}
					});
				});
			});
		</script>
		<style>
			.context-content {
				background-color: #f9f9f9;
				padding: 10px;
				word-wrap: break-word;
			}

			.context-conten pre {
				white-space: pre-wrap;
				word-wrap: break-word;
				margin: 0;
			}
		</style>
		<?php
	}
}
