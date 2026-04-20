<?php
/**
 * LFE History Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LFE_History {

	/**
	 * History Option Name
	 */
	const OPTION_NAME = 'lfe_history';

	/**
	 * Add to history
	 *
	 * @param string $title
	 * @param string $json
	 */
	public static function add( $title, $json ) {
		$history = self::get_all();
		$entry   = array(
			'title' => sanitize_text_field( $title ),
			'date'  => current_time( 'mysql' ),
			'json'  => is_string( $json ) ? $json : wp_json_encode( $json ),
		);

		array_unshift( $history, $entry );

		// Keep only last 10 entries
		$history = array_slice( $history, 0, 10 );

		// History blobs can be large, so keep them out of autoloaded options.
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $history, '', false );
			return;
		}

		update_option( self::OPTION_NAME, $history, false );
	}

	/**
	 * Get history
	 *
	 * @return array
	 */
	public static function get_all() {
		$history = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $history ) ) {
			return array();
		}

		$normalized_history = array();

		foreach ( $history as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$normalized_history[] = array(
				'title' => isset( $item['title'] ) ? (string) $item['title'] : '',
				'date'  => isset( $item['date'] ) ? (string) $item['date'] : '',
				'json'  => isset( $item['json'] ) ? (string) $item['json'] : '',
			);
		}

		return $normalized_history;
	}
}
