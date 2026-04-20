<?php
/**
 * Plugin Name: JSON to Elementor Converter
 * Description: Instantly generate Elementor layouts from AI-generated JSON.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: json-to-elementor-converter
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'LFE_VERSION', '1.0.0' );
define( 'LFE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'LFE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Layout_For_Elementor {

	/**
	 * Instance
	 * @var Layout_For_Elementor
	 */
	private static $instance = null;

	/**
	 * Get Instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->includes();
		$this->init();
	}

	/**
	 * Include Files
	 */
	private function includes() {
		require_once LFE_PLUGIN_PATH . 'includes/class-lfe-history.php';
		require_once LFE_PLUGIN_PATH . 'includes/class-lfe-generator.php';
		require_once LFE_PLUGIN_PATH . 'includes/class-lfe-admin.php';
		require_once LFE_PLUGIN_PATH . 'includes/class-lfe-editor.php';
	}

	/**
	 * Initialize
	 */
	private function init() {
		if ( is_admin() ) {
			new LFE_Admin();
			new LFE_Editor();
		}
	}
}

// Kick it off
Layout_For_Elementor::get_instance();
