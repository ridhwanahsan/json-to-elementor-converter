<?php
/**
 * LFE Generator Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LFE_Generator {

	/**
	 * Generate a stable-length Elementor ID.
	 *
	 * @return string
	 */
	private static function generate_element_id() {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
	}

	/**
	 * Determine whether an array uses sequential numeric keys.
	 *
	 * @param mixed $data Data to inspect.
	 * @return bool
	 */
	private static function is_sequential_array( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( array() === $data ) {
			return true;
		}

		return array_keys( $data ) === range( 0, count( $data ) - 1 );
	}

	/**
	 * Determine whether a given Elementor type is supported.
	 *
	 * @param mixed $el_type Element type.
	 * @return bool
	 */
	private static function is_supported_el_type( $el_type ) {
		return is_string( $el_type ) && in_array( strtolower( $el_type ), array( 'section', 'column', 'widget', 'container' ), true );
	}

	/**
	 * Determine whether the given data is a single Elementor element object.
	 *
	 * @param mixed $data Data to inspect.
	 * @return bool
	 */
	private static function is_element_object( $data ) {
		return is_array( $data ) && isset( $data['elType'] ) && self::is_supported_el_type( $data['elType'] ) && ! isset( $data[0] );
	}

	/**
	 * Extract the first importable collection of Elementor elements.
	 *
	 * Supports plain element arrays and common document wrappers such as
	 * Elementor exports that store data in `content`, `elements`, or `data`.
	 *
	 * @param mixed $data Raw import data.
	 * @return array
	 */
	private static function extract_element_collection( $data ) {
		if ( self::is_element_object( $data ) ) {
			return array( $data );
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( self::is_sequential_array( $data ) ) {
			return $data;
		}

		foreach ( array( 'content', 'elements', 'data' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$extracted = self::extract_element_collection( $data[ $key ] );

				if ( ! empty( $extracted ) ) {
					return $extracted;
				}
			}
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$extracted = self::extract_element_collection( $value );

				if ( ! empty( $extracted ) ) {
					return $extracted;
				}
			}
		}

		return array();
	}

	/**
	 * Normalize an element list recursively and discard unsupported nodes.
	 *
	 * @param mixed $elements Elements to normalize.
	 * @return array
	 */
	private static function sanitize_elements( $elements ) {
		if ( ! is_array( $elements ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $elements as $element ) {
			foreach ( self::sanitize_candidate( $element ) as $candidate ) {
				$normalized[] = $candidate;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a single candidate item or unwrap nested element collections.
	 *
	 * @param mixed $candidate Candidate data.
	 * @return array
	 */
	private static function sanitize_candidate( $candidate ) {
		if ( ! is_array( $candidate ) ) {
			return array();
		}

		if ( self::is_element_object( $candidate ) ) {
			$element = self::sanitize_element( $candidate );

			return $element ? array( $element ) : array();
		}

		$extracted = self::extract_element_collection( $candidate );

		if ( empty( $extracted ) ) {
			return array();
		}

		return self::sanitize_elements( $extracted );
	}

	/**
	 * Normalize a single Elementor element object.
	 *
	 * @param array $element Element data.
	 * @return array|null
	 */
	private static function sanitize_element( $element ) {
		$el_type = isset( $element['elType'] ) ? strtolower( (string) $element['elType'] ) : '';

		if ( ! self::is_supported_el_type( $el_type ) ) {
			return null;
		}

		$element['elType'] = $el_type;

		if ( empty( $element['id'] ) || ! is_string( $element['id'] ) ) {
			$element['id'] = self::generate_element_id();
		}

		if ( ! isset( $element['settings'] ) || ! is_array( $element['settings'] ) ) {
			$element['settings'] = array();
		}

		if ( 'widget' === $el_type ) {
			if ( empty( $element['widgetType'] ) || ! is_string( $element['widgetType'] ) ) {
				return null;
			}

			$element['elements'] = array();

			return $element;
		}

		$element['elements'] = isset( $element['elements'] ) && is_array( $element['elements'] )
			? self::sanitize_elements( $element['elements'] )
			: array();

		if ( 'column' === $el_type && empty( $element['settings']['_column_size'] ) ) {
			$element['settings']['_column_size'] = 100;
		}

		return $element;
	}

	/**
	 * Wrap partial root elements into a valid Elementor root structure.
	 *
	 * @param array $element Normalized element.
	 * @return array
	 */
	private static function wrap_root_element( $element ) {
		if ( ! is_array( $element ) || empty( $element['elType'] ) ) {
			return array();
		}

		if ( in_array( $element['elType'], array( 'section', 'container' ), true ) ) {
			return array( $element );
		}

		if ( 'column' === $element['elType'] ) {
			return array(
				array(
					'id'       => self::generate_element_id(),
					'elType'   => 'section',
					'settings' => array(),
					'elements' => array( $element ),
				),
			);
		}

		if ( 'widget' === $element['elType'] ) {
			return array(
				array(
					'id'       => self::generate_element_id(),
					'elType'   => 'section',
					'settings' => array(),
					'elements' => array(
						array(
							'id'       => self::generate_element_id(),
							'elType'   => 'column',
							'settings' => array( '_column_size' => 100 ),
							'elements' => array( $element ),
						),
					),
				),
			);
		}

		return array();
	}

	/**
	 * Normalize Elementor Data
	 * Ensures IDs exist and root structure is array of sections
	 */
	public static function normalize( $data ) {
		if ( empty( $data ) ) {
			return array();
		}

		if ( ! is_array( $data ) ) {
			$data = json_decode( $data, true );
			if ( ! is_array( $data ) ) {
				return array();
			}
		}

		$data = self::extract_element_collection( $data );

		if ( empty( $data ) ) {
			return array();
		}

		$final_data = array();

		foreach ( self::sanitize_elements( $data ) as $element ) {
			foreach ( self::wrap_root_element( $element ) as $root_element ) {
				$final_data[] = $root_element;
			}
		}

		return $final_data;
	}

	/**
	 * Create Page
	 */
	public static function create_page( $title, $normalized_data ) {
		$post_id = wp_insert_post( array(
			'post_title'   => $title,
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$applied = self::apply_elementor_data( $post_id, $normalized_data );

		if ( is_wp_error( $applied ) ) {
			wp_delete_post( $post_id, true );

			return $applied;
		}

		return $post_id;
	}

	/**
	 * Apply Elementor Meta
	 */
	public static function apply_elementor_data( $post_id, $data ) {
		if ( empty( $data ) ) {
			return new WP_Error( 'lfe_empty_data', __( 'No valid Elementor data found to import.', 'json-to-elementor-converter' ) );
		}

		$json_data = wp_json_encode( $data );
		
		if ( false === $json_data ) {
			return new WP_Error( 'lfe_json_encode_failed', __( 'Could not encode the Elementor data.', 'json-to-elementor-converter' ) );
		}

		update_post_meta( $post_id, '_elementor_data', wp_slash( $json_data ) );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}

		// Safely clear cache
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( \Throwable $e ) {
				// Silently fail if cache clearing fails
			}
		}

		return true;
	}
}
