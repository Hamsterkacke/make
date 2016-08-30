<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Builder_Settings
 *
 *
 *
 * @since 1.8.0.
 */
class MAKE_Builder_Settings extends MAKE_Settings_Base implements MAKE_Builder_SettingsInterface {
	/**
	 * The type of settings. Should be defined in the child class.
	 *
	 * @since 1.8.0.
	 *
	 * @var string
	 */
	protected $type = 'buildersetting';

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @var int
	 */
	private $post_id = 0;

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @var string
	 */
	private $prefix = '_make-builder-';

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function set_value( $setting_id, $value ) {
		if ( ! $this->post_id ) {
			return false;
		}

		if ( $this->setting_exists( $setting_id ) ) {
			// Sanitize the value before saving it.
			$sanitized_value = $this->sanitize_value( $value, $setting_id );

			if ( $this->undefined !== $sanitized_value ) {
				// Section
				if ( $this->is_section( $setting_id ) ) {
					$stored_value = $this->get_section_stored_value( $setting_id, $sanitized_value );

					return update_post_meta( $this->post_id, $this->prefix . $setting_id, $sanitized_value, $stored_value );
				}
				// Other setting
				else {
					return update_post_meta( $this->post_id, $this->prefix . $setting_id, $sanitized_value );
				}
			}
		}

		return false;
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 *
	 * @return bool
	 */
	public function unset_value( $setting_id ) {
		if ( ! $this->post_id ) {
			return false;
		}

		if ( $this->setting_exists( $setting_id ) ) {
			// Section
			if ( $this->is_section( $setting_id ) ) {
				// Look for an extra passed argument
				if ( func_num_args() > 1 ) {
					$value = array_slice( func_get_args(), 1, 1 );
					$stored_value = $this->get_section_stored_value( $setting_id, $value );

					// Only delete the post meta if a stored value was found. Otherwise this would
					// delete all the section data.
					if ( $stored_value ) {
						return delete_post_meta( $this->post_id, $this->prefix . $setting_id, $stored_value );
					}
				}

				return false;
			}
			// Other setting
			else {
				return delete_post_meta( $this->post_id, $this->prefix . $setting_id );
			}
		}

		return false;
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 *
	 * @return mixed|null
	 */
	public function get_raw_value( $setting_id ) {
		$value = $this->undefined;

		if ( ! $this->post_id ) {
			return $value;
		}

		if ( $this->setting_exists( $setting_id ) && metadata_exists( 'post', $this->post_id, $this->prefix . $setting_id ) ) {
			// Section
			if ( $this->is_section( $setting_id ) ) {
				$value = get_post_meta( $this->post_id, $this->prefix . $setting_id, false );
			}
			// Other setting
			else {
				$value = get_post_meta( $this->post_id, $this->prefix . $setting_id, true );
			}
		}

		return $value;
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 * @param string $context
	 *
	 * @return mixed|null
	 */
	public function get_value( $setting_id, $context = '' ) {
		$value = $this->undefined;

		if ( ! $this->post_id ) {
			return $value;
		}

		if ( $this->setting_exists( $setting_id ) ) {
			$raw_value = $this->get_raw_value( $setting_id );

			// Sanitize the raw value.
			if ( $this->undefined !== $raw_value ) {
				// Section
				if ( $this->is_section( $setting_id ) ) {
					$value = array();

					foreach ( $raw_value as $section ) {
						$value[] = $this->sanitize_value( $section, $setting_id, $context );
					}
				}
				// Other setting
				else {
					$value = $this->sanitize_value( $raw_value, $setting_id, $context );
				}
			}

			// Use the default if the value is still undefined.
			if ( $this->undefined === $value ) {
				$value = $this->get_default( $setting_id );
			}
		}

		/**
		 * Filter: Modify the current value for a particular setting.
		 *
		 * @since 1.8.0.
		 *
		 * @param mixed  $value         The current value of the setting.
		 * @param string $setting_id    The id of the setting.
		 * @param string $context       Optional. The context in which a setting needs to be sanitized.
		 */
		return apply_filters( "make_settings_{$this->type}_current_value", $value, $setting_id, $context );
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param $post_id
	 *
	 * @return void
	 */
	public function set_post_id( $post_id ) {
		$this->post_id = absint( $post_id );
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 *
	 * @return bool
	 */
	private function is_section( $setting_id ) {
		return in_array( $setting_id, array_keys( $this->get_settings( 'is_section' ), true ) );
	}

	/**
	 *
	 *
	 * @since 1.8.0.
	 *
	 * @param string $setting_id
	 * @param array  $value
	 *
	 * @return null
	 */
	private function get_section_stored_value( $setting_id, $value ) {
		$previous_value = $this->undefined;

		if ( ! $this->is_section( $setting_id ) || ! isset( $value['id'] ) ) {
			return $previous_value;
		}

		$stored_sections = $this->get_raw_value( $setting_id );
		$stored_section_ids = wp_list_pluck( $stored_sections, 'id' );
		$index = array_search( $value['id'], $stored_section_ids );

		if ( $index ) {
			$previous_value = $stored_sections[ $index ];
		}

		return $previous_value;
	}
}