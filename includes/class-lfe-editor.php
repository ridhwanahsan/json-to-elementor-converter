<?php
/**
 * LFE Editor Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LFE_Editor {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_repair_current_post' ) );
		add_action( 'wp_ajax_lfe_import_to_post', array( $this, 'ajax_import_to_post' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
	}

	/**
	 * Repair malformed Elementor data before the editor boots.
	 *
	 * Older imports may have saved wrapper arrays that cause Elementor to fatally
	 * error while opening the editor. Normalize that payload once so the editor
	 * can load again.
	 */
	public function maybe_repair_current_post() {
		$action  = '';
		$post_id = 0;

		if ( isset( $_GET['action'] ) && is_scalar( $_GET['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor editor URLs do not include a nonce; this repair path is gated by editor context and capability checks.
			$action = sanitize_key( wp_unslash( (string) $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor editor URLs do not include a nonce; this repair path is gated by editor context and capability checks.
		}

		if ( isset( $_GET['post'] ) && is_scalar( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor editor URLs do not include a nonce; this repair path is gated by editor context and capability checks.
			$post_id = absint( wp_unslash( (string) $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor editor URLs do not include a nonce; this repair path is gated by editor context and capability checks.
		}

		if ( 'elementor' !== $action || ! $post_id ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$stored_data = get_post_meta( $post_id, '_elementor_data', true );

		if ( empty( $stored_data ) || ! is_string( $stored_data ) ) {
			return;
		}

		$decoded_data = json_decode( $stored_data, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded_data ) ) {
			return;
		}

		$normalized_data = LFE_Generator::normalize( $decoded_data );

		if ( empty( $normalized_data ) ) {
			return;
		}

		$current_json    = wp_json_encode( $decoded_data );
		$normalized_json = wp_json_encode( $normalized_data );

		if ( false === $current_json || false === $normalized_json || $current_json === $normalized_json ) {
			return;
		}

		LFE_Generator::apply_elementor_data( $post_id, $normalized_data );
	}

	/**
	 * Enqueue Editor Scripts
	 */
	public function enqueue_editor_scripts() {
		$script_path = LFE_PLUGIN_PATH . 'assets/js/editor-script.js';
		$script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : LFE_VERSION;

		wp_enqueue_script( 'lfe-editor-script', LFE_PLUGIN_URL . 'assets/js/editor-script.js', array( 'jquery' ), $script_ver, true );
		
		wp_localize_script( 'lfe-editor-script', 'lfe_editor_vars', array(
			'nonce'    => wp_create_nonce( 'lfe_editor_nonce' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		) );

		// Inline style for the editor button, premium modal and notifications
		?>
		<style>
			/* Icon Styling */
			.lfe-editor-btn-wrapper {
				display: inline-flex !important;
				align-items: center;
				justify-content: center;
				width: 40px !important;
				height: 40px !important;
				background-color: #f2295b !important;
				border-radius: 50% !important;
				cursor: pointer !important;
				margin-left: 8px !important;
				transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
				border: none !important;
				position: relative !important;
				z-index: 100 !important;
			}
			.lfe-editor-btn-wrapper:hover {
				background-color: #d81b4d !important;
				transform: translateY(-2px) scale(1.05) !important;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
			}
			.lfe-editor-btn-wrapper svg {
				width: 20px !important;
				height: 20px !important;
				fill: #fff !important;
			}

			/* Modal Styling */
			#lfe-editor-modal-root {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				z-index: 999999;
				display: none;
				align-items: center;
				justify-content: center;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}
			.lfe-modal-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(15, 23, 42, 0.6);
				backdrop-filter: blur(4px);
			}
			.lfe-modal-content {
				position: relative;
				background: #fff;
				width: 100%;
				max-width: 800px;
				max-height: 90vh;
				border-radius: 20px;
				box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
				overflow: hidden;
				display: flex;
				flex-direction: column;
				animation: lfe-fade-in 0.3s ease-out;
			}
			@keyframes lfe-fade-in {
				from { opacity: 0; transform: scale(0.95); }
				to { opacity: 1; transform: scale(1); }
			}
			.lfe-modal-header {
				padding: 24px 32px;
				background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
				border-bottom: 1px solid #e2e8f0;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}
			.lfe-modal-header h2 {
				margin: 0;
				font-size: 20px;
				font-weight: 700;
				color: #1e293b;
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.lfe-modal-header .lfe-close {
				cursor: pointer;
				color: #64748b;
				transition: color 0.2s;
			}
			.lfe-modal-header .lfe-close:hover {
				color: #1e293b;
			}
			.lfe-modal-body {
				padding: 32px;
				overflow-y: auto;
				flex-grow: 1;
			}
			.lfe-modal-field {
				margin-bottom: 24px;
			}
			.lfe-modal-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 8px;
				color: #334155;
			}
			.lfe-modal-field textarea {
				width: 100%;
				min-height: 300px;
				border-radius: 12px;
				border: 1px solid #e2e8f0;
				padding: 16px;
				font-family: 'Fira Code', 'Cascadia Code', monospace;
				font-size: 14px;
				background: #f8fafc;
				resize: vertical;
				transition: all 0.2s;
			}
			.lfe-modal-field textarea:focus {
				border-color: #2563eb;
				background: #fff;
				box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
				outline: none;
			}
			.lfe-modal-actions {
				display: flex;
				gap: 12px;
				padding: 24px 32px;
				background: #f8fafc;
				border-top: 1px solid #e2e8f0;
			}
			.lfe-modal-btn {
				padding: 12px 24px;
				border-radius: 10px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
				display: flex;
				align-items: center;
				gap: 8px;
				border: 1px solid transparent;
				font-size: 14px;
			}
			.lfe-modal-btn-primary {
				background: #2563eb;
				color: #fff;
			}
			.lfe-modal-btn-primary:hover {
				background: #1d4ed8;
				transform: translateY(-1px);
			}
			.lfe-modal-btn-secondary {
				background: #fff;
				border-color: #e2e8f0;
				color: #475569;
			}
			.lfe-modal-btn-secondary:hover {
				background: #f1f5f9;
				color: #1e293b;
			}
			.lfe-modal-btn-danger {
				background: #ef4444;
				color: #fff;
			}
			.lfe-modal-btn-danger:hover {
				background: #dc2626;
			}

			/* Toast Styling */
			#lfe-toast-container {
				position: fixed;
				top: 32px;
				right: 32px;
				z-index: 1000000;
				display: flex;
				flex-direction: column;
				gap: 12px;
			}
			.lfe-toast {
				min-width: 300px;
				padding: 16px 20px;
				border-radius: 12px;
				background: #fff;
				box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
				display: flex;
				align-items: center;
				gap: 12px;
				animation: lfe-toast-in 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
				color: #1e293b;
				font-weight: 500;
				font-size: 14px;
			}
			@keyframes lfe-toast-in {
				from { transform: translateX(100%); opacity: 0; }
				to { transform: translateX(0); opacity: 1; }
			}
			.lfe-toast-success i { color: #10b981; }
			.lfe-toast-error i { color: #ef4444; }

			/* Confirm Dialog Styling */
			.lfe-confirm-overlay {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(255,255,255,0.9);
				display: none;
				align-items: center;
				justify-content: center;
				z-index: 10;
				text-align: center;
				padding: 40px;
			}
			.lfe-confirm-box h3 { margin-bottom: 12px; color: #1e293b; }
			.lfe-confirm-box p { margin-bottom: 24px; color: #64748b; font-size: 14px; }
			.lfe-confirm-btns { display: flex; gap: 12px; justify-content: center; }

			.lfe-history-mini {
				margin-top: 24px;
				border-top: 1px solid #e2e8f0;
				padding-top: 20px;
			}
			.lfe-history-mini h3 {
				font-size: 16px;
				margin-bottom: 12px;
				color: #475569;
			}
			.lfe-history-list {
				display: flex;
				flex-direction: column;
				gap: 8px;
			}
			.lfe-history-item {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 10px 16px;
				background: #f8fafc;
				border-radius: 8px;
				font-size: 13px;
				border: 1px solid #f1f5f9;
				transition: all 0.2s;
			}
			.lfe-history-item:hover {
				border-color: #cbd5e1;
				background: #fff;
			}
			.lfe-history-item-title {
				font-weight: 600;
				color: #334155;
			}
			.lfe-history-item-apply {
				color: #2563eb;
				cursor: pointer;
				font-weight: 700;
			}
		</style>

		<div id="lfe-toast-container"></div>

		<div id="lfe-editor-modal-root">
			<div class="lfe-modal-overlay"></div>
			<div class="lfe-modal-content">
				<div class="lfe-confirm-overlay" id="lfe-confirm-overlay">
					<div class="lfe-confirm-box">
						<h3 id="lfe-confirm-title"><?php esc_html_e( 'Are you sure?', 'json-to-elementor-converter' ); ?></h3>
						<p id="lfe-confirm-desc"><?php esc_html_e( 'This will replace the current page content.', 'json-to-elementor-converter' ); ?></p>
						<div class="lfe-confirm-btns">
							<button class="lfe-modal-btn lfe-modal-btn-danger" id="lfe-confirm-yes"><?php esc_html_e( 'Yes, Replace', 'json-to-elementor-converter' ); ?></button>
							<button class="lfe-modal-btn lfe-modal-btn-secondary" id="lfe-confirm-no"><?php esc_html_e( 'Cancel', 'json-to-elementor-converter' ); ?></button>
						</div>
					</div>
				</div>
				<div class="lfe-modal-header">
					<h2>
						<span class="dashicons dashicons-products"></span>
						<?php esc_html_e( 'JSON to Elementor Converter', 'json-to-elementor-converter' ); ?>
					</h2>
					<span class="lfe-close dashicons dashicons-no-alt"></span>
				</div>
				<div class="lfe-modal-body">
					<div class="lfe-modal-field">
						<label><?php esc_html_e( 'Paste JSON Layout', 'json-to-elementor-converter' ); ?></label>
						<textarea id="lfe-modal-json" placeholder='[{"elType":"section", ...}]'></textarea>
					</div>

					<div class="lfe-history-mini">
						<h3><?php esc_html_e( 'Recent Activity', 'json-to-elementor-converter' ); ?></h3>
						<div class="lfe-history-list" id="lfe-modal-history-list">
							<?php
							$history = LFE_History::get_all();
							if ( empty( $history ) ) :
								echo '<p style="font-size:12px; color:#94a3b8;">' . esc_html__( 'No recent layouts.', 'json-to-elementor-converter' ) . '</p>';
							else :
								foreach ( $history as $item ) : ?>
									<div class="lfe-history-item" data-json='<?php echo esc_attr( $item['json'] ); ?>'>
										<span class="lfe-history-item-title"><?php echo esc_html( $item['title'] ); ?></span>
										<span class="lfe-history-item-apply"><?php esc_html_e( 'Use This', 'json-to-elementor-converter' ); ?></span>
									</div>
								<?php
								endforeach;
							endif; ?>
						</div>
					</div>
				</div>
				<div class="lfe-modal-actions">
					<button class="lfe-modal-btn lfe-modal-btn-primary" id="lfe-modal-generate">
						<span class="dashicons dashicons-migrate"></span> <?php esc_html_e( 'Insert Layout', 'json-to-elementor-converter' ); ?>
					</button>
					<button class="lfe-modal-btn lfe-modal-btn-secondary" id="lfe-modal-fix">
						<span class="dashicons dashicons-hammer"></span> <?php esc_html_e( 'Fix JSON', 'json-to-elementor-converter' ); ?>
					</button>
					<button class="lfe-modal-btn lfe-modal-btn-secondary" id="lfe-modal-prettify">
						<span class="dashicons dashicons-editor-code"></span> <?php esc_html_e( 'Prettify', 'json-to-elementor-converter' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Import to current post
	 */
	public function ajax_import_to_post() {
		try {
			if ( ! check_ajax_referer( 'lfe_editor_nonce', 'nonce', false ) ) {
				throw new Exception( __( 'Security check failed.', 'json-to-elementor-converter' ) );
			}

			$post_id_raw = isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '';
			$post_id     = absint( $post_id_raw );
			$json_value  = isset( $_POST['json_content'] ) ? wp_unslash( $_POST['json_content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw JSON must remain intact and is validated with json_decode() before use.
			$json_raw    = is_string( $json_value ) ? trim( $json_value ) : '';

			if ( ! $post_id || empty( $json_raw ) ) {
				throw new Exception( __( 'Missing data.', 'json-to-elementor-converter' ) );
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				throw new Exception( __( 'Permission denied.', 'json-to-elementor-converter' ) );
			}

			$data = json_decode( $json_raw, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				/* translators: %s: JSON decoding error message. */
				throw new Exception( sprintf( __( 'Invalid JSON format: %s', 'json-to-elementor-converter' ), json_last_error_msg() ) );
			}

			$normalized_data = LFE_Generator::normalize( $data );
			if ( empty( $normalized_data ) ) {
				throw new Exception( __( 'No importable Elementor elements were found in the provided JSON.', 'json-to-elementor-converter' ) );
			}

			// Keep a history entry, but let the editor insert the layout live so it
			// behaves like Elementor's own template insertion flow.
			LFE_History::add( get_the_title( $post_id ) . ' (Insert)', $json_raw );

			wp_send_json_success( array(
				'normalized' => $normalized_data
			) );

		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
