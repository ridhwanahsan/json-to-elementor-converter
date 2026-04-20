<?php
/**
 * LFE Admin Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LFE_Admin {

	/**
	 * Main class instance
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add Menu
	 */
	public function add_menu() {
		add_menu_page(
			__( 'JSON to Elementor Converter', 'json-to-elementor-converter' ),
			__( 'JSON to Elementor Converter', 'json-to-elementor-converter' ),
			'manage_options',
			'json-to-elementor-converter',
			array( $this, 'render_admin_page' ),
			'dashicons-layout',
			30
		);
	}

	/**
	 * Enqueue Assets
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_json-to-elementor-converter' !== $hook ) {
			return;
		}

		$style_path = LFE_PLUGIN_PATH . 'assets/css/admin-style.css';
		$style_ver  = file_exists( $style_path ) ? (string) filemtime( $style_path ) : LFE_VERSION;

		wp_enqueue_style( 'lfe-admin-style', LFE_PLUGIN_URL . 'assets/css/admin-style.css', array(), $style_ver );
	}

	/**
	 * Render Page
	 */
	public function render_admin_page() {
		$history = LFE_History::get_all();

		?>
		<div class="wrap lfe-admin-wrapper">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'JSON to Elementor Converter', 'json-to-elementor-converter' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( ! did_action( 'elementor/loaded' ) ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Elementor is not active! This plugin requires Elementor to function.', 'json-to-elementor-converter' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="lfe-container">
				<div class="lfe-card">
					<h2><?php esc_html_e( 'Editor-Only Import', 'json-to-elementor-converter' ); ?></h2>
					<p><?php esc_html_e( 'The dashboard JSON paste system is disabled. Import layouts directly from the Elementor editor popup.', 'json-to-elementor-converter' ); ?></p>
					<p><?php esc_html_e( 'Open any page with Elementor, click the JSON to Elementor Converter button inside the editor, then paste your JSON there.', 'json-to-elementor-converter' ); ?></p>
				</div>

				<?php if ( ! empty( $history ) ) : ?>
				<div class="lfe-history-section">
					<h2><?php esc_html_e( 'Recent Imports', 'json-to-elementor-converter' ); ?></h2>
					<div class="lfe-history-card">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Title', 'json-to-elementor-converter' ); ?></th>
									<th><?php esc_html_e( 'Date', 'json-to-elementor-converter' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $history as $item ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
										<td><?php echo esc_html( $item['date'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
