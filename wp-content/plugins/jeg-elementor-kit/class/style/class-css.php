<?php
/**
 * CSS class
 *
 * @package jeg-elementor-kit
 * @author jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Style;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Plugin;

/**
 * Class CSS.
 *
 * @package jeg-elementor-kit
 */
class CSS {

	/**
	 * Directory
	 *
	 * @var string
	 */
	private $dir = 'jeg-elementor-kit';

	/**
	 * CSS
	 *
	 * @var array
	 */
	private $css;

	/**
	 * Breakpoints
	 *
	 * @var array
	 */
	private $br;

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Cass construct
	 *
	 * @param string $settings options.
	 * @return void
	 */
	public function __construct( $settings ) {
		$this->settings = self::adjust_settings( $settings );
		if ( class_exists( '\Elementor' ) ) {
			$br_points = \Elementor\Core\Responsive\Responsive::get_breakpoints();
		} else {
			$br_points = array(
				'sm' => '480',
				'md' => '767',
				'lg' => '1024',
			);
		}
		$this->br = $br_points;

		$this->get_css_from_settings();
		$this->combine_css();
	}

	/**
	 * Combine CSS
	 *
	 * @return void
	 */
	public function combine_css() {
		foreach ( $this->css as $type => $css ) {
			$name    = 'jeg-elementor-kit-' . $type . '.css';
			$file    = $this->get_file_info( $name, 'path' );
			$content = $this->minify_css( $css );

			if ( $this->check_folder() && ! empty( $content ) ) {
				global $wp_filesystem;

				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
				}

				$wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE );
			}
		}
	}

	/**
	 * Combine CSS
	 *
	 * @param string $css css.
	 * @return string
	 */
	public function minify_css( $css ) {
		if ( $css === null ) {
			return '';
		}

		// Remove single-line comments.
		$css = preg_replace( '!//[^\r\n]*!', '', $css );

		// Remove multi-line comments.
		$css = preg_replace( '!/\*.*?\*/!s', '', $css );

		// Remove extra spaces, new lines, and tabs.
		$css = str_replace( array( "\r\n", "\r", "\n", "\t" ), ' ', $css ); // replace with a single space.
		$css = preg_replace( '!\s+!', ' ', $css ); // collapse multiple spaces into one.

		// Remove space after colons, semicolons, and around curly braces.
		$css = str_replace( ': ', ':', $css );
		$css = str_replace( '; ', ';', $css );
		$css = str_replace( array( ' {', '{ ' ), '{', $css );
		$css = str_replace( array( ' }', '} ' ), '}', $css );

		return trim( $css ); // Trim leading/trailing whitespace.
	}

	/**
	 * Get file info
	 *
	 * @param string $name      name.
	 * @param string $type      type.
	 *
	 * @return string
	 */
	public function get_file_info( $name, $type = 'path' ) {
		$upload_dir      = wp_upload_dir();
		$before_filename = '';

		switch ( $type ) {
			case 'url':
				$before_filename = $upload_dir['baseurl'];
				break;
			case 'path':
			default:
				$before_filename = $upload_dir['basedir'];
				break;
		}

		return sprintf( '%s/%s/%s', $before_filename, $this->dir, $name );
	}

	/**
	 * Check if folder exists
	 *
	 * @return boolean
	 */
	public function check_folder() {
		$wp_upload_dir = wp_upload_dir();
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->is_dir( $wp_upload_dir['basedir'] . '/' . $this->dir ) ) {
			if ( ! wp_mkdir_p( $wp_upload_dir['basedir'] . '/' . $this->dir ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get CSS from settings
	 *
	 * @return void
	 */
	public function get_css_from_settings() {
		$this->css['global'] = $this->global_css();
	}

	/**
	 * Global CSS
	 */
	public function global_css() {
		$css = null;

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor_kit     = Plugin::$instance->kits_manager->get_active_kit_for_frontend();
			$system_colors     = $elementor_kit->get_settings_for_display( 'system_colors' );
			$custom_colors     = $elementor_kit->get_settings_for_display( 'custom_colors' );
			$system_typography = $elementor_kit->get_settings_for_display( 'system_typography' );
			$custom_typography = $elementor_kit->get_settings_for_display( 'custom_typography' );
			$global_settings   = array_merge( $system_colors, $custom_colors, $system_typography, $custom_typography );

			$json_settings = $this->filter_global_style_value( $this->settings, $global_settings );
			$br_points     = $this->br;

			// Global Color.
			include JEG_ELEMENTOR_KIT_FILE . '/class/style/extra/global-color-css.php';

			// Global Font.
			// include JEG_ELEMENTOR_KIT_FILE . /'style/extra/global-font-css.php';

			// Global Buttons.
			include JEG_ELEMENTOR_KIT_FILE . '/class/style/extra/global-buttons-css.php';

			// Custom CSS.
			$css .= $json_settings['JCodeCSS'];
		}

		return $css;
	}

	/**
	 * Change Global Style value to normal value
	 *
	 * @param string $settings ID.
	 * @param array  $global_settings Global Settings.
	 */
	private function filter_global_style_value( $settings, $global_settings ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$settings[ $key ] = $this->filter_global_style_value( $value, $global_settings );
			} elseif ( strpos( $value, 'globals/' ) !== false ) {
				$query_string = substr( $value, strpos( $value, '?' ) + 1 );
				parse_str( $query_string, $params );

				if ( strpos( $value, '/colors' ) !== false ) {
					$settings[ $key ] = $this->get_global_settings_data( $params['id'], $global_settings, 'color' );
				} elseif ( strpos( $value, '/typography' ) !== false ) {
					$prepare_data = $this->get_global_settings_data( $params['id'], $global_settings );
					$prefix       = explode( '_typography', $key );

					foreach ( $prepare_data as $key => $value ) {
						$settings[ $prefix[0] . '_' . $key ] = $value;
					}
				}
			}
		}
		return $settings;
	}

	/**
	 * Get Global Settings Data
	 *
	 * @param string $id ID.
	 * @param array  $global_settings Global Settings.
	 * @param string $needle ID.
	 */
	private function get_global_settings_data( $id, $global_settings, $needle = null ) {
		foreach ( $global_settings as $data ) {
			if ( $data['_id'] === $id ) {
				if ( isset( $needle ) ) {
					return $data[ $needle ];
				}

				return $data;
			}
		}

		return false;
	}

	/**
	 * Adjust Settings
	 *
	 * @param array $settings settings.
	 *
	 * @return array
	 */
	public static function adjust_settings( $settings ) {
		// we can do some adjustment here.
		return $settings;
	}
}
