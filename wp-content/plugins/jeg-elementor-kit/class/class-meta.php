<?php
/**
 * Jeg Kit Meta Class.
 *
 * @author jegtheme
 * @since 1.0.0
 * @package jeg-elementor-kit
 */

namespace Jeg\Elementor_Kit;

/**
 * Class Plugin Meta.
 *
 * @package jeg-elementor-kit
 */
class Meta {
	/**
	 * Option Name.
	 *
	 * @var string
	 */
	private $option_name = 'jkit-meta-option';

	/**
	 * Instance of Meta.
	 *
	 * @var Meta_Option
	 */
	private static $instance;

	/**
	 * Singleton page for Meta_Option Class
	 *
	 * @return Meta_Option
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_meta_option();
	}

	/**
	 * Upgrade Plugin Hook.
	 *
	 * @param string $old_version Old Version.
	 * @param string $new_version New Version.
	 * @param string $plugin_name Plugin Name.
	 */
	public function upgrade_plugin( $old_version, $new_version, $plugin_name ) {
		$tracker        = $this->get_option( 'tracker', array() );
		$plugin_tracker = $tracker[ $plugin_name ];
		$versions       = $plugin_tracker['version_history'];
		$versions[]     = $old_version;

		$plugin_tracker['current_version'] = $new_version;
		$plugin_tracker['upgrade_time']    = time();
		$plugin_tracker['version_history'] = $versions;

		$tracker[ $plugin_name ] = $plugin_tracker;

		$this->set_option( 'tracker', $tracker );
	}

	/**
	 * Initial Option.
	 */
	public function initial_option() {
		$options = apply_filters(
			'essential_initial_meta_option',
			array(
				'tracker'          => array(),
				'liked_layout'     => get_option( 'jkit-liked-layout', array() ),
				'liked_section'    => get_option( 'jkit-liked-section', array() ),
				'subscribed'       => get_option( 'jkit-subscribed', false ),
				'subscribed_email' => get_option( 'jkit-subscribed-email', '' ),
			)
		);

		$this->set_options( $options );
	}

	/**
	 * Upgrade Process.
	 */
	public function init_meta_option() {
		$option = $this->get_option();

		if ( false === $option ) {
			$this->initial_option();
		}

		do_action( 'essential_check_update', $this );
	}

	/**
	 * Load Meta Data.
	 *
	 * @param string|null $name Name of setting.
	 * @param \mixed      $default Default Option Value.
	 *
	 * @return \mixed
	 */
	public function get_option( $name = null, $default = null ) {
		$options = get_option( $this->option_name );

		if ( $name ) {
			if ( isset( $options[ $name ] ) ) {
				return $options[ $name ];
			} else {
				return $default;
			}
		}

		return $options;
	}

	/**
	 * Set Option
	 *
	 * @param object $value Value of settings.
	 */
	public function set_options( $value ) {
		$option = $this->get_option();

		if ( $option ) {
			return update_option( $this->option_name, $value );
		} else {
			return add_option( $this->option_name, $value );
		}
	}

	/**
	 * Set Option Name.
	 *
	 * @param string $name Name of setting.
	 * @param mixed  $value Value of settings.
	 */
	public function set_option( $name, $value ) {
		$option          = $this->get_option();
		$option[ $name ] = $value;

		return $this->set_options( $option );
	}

	/**
	 * Delete Option.
	 *
	 * @param string $name Name of setting.
	 */
	public function delete_option( $name ) {
		$option = $this->get_option();
		unset( $option[ $name ] );

		return $this->set_options( $option );
	}
}
