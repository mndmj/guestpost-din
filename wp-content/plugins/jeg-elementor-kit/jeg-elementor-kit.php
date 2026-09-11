<?php
/**
 * Plugin Name: Jeg Kit for Elementor
 * Plugin URI: https://jegkit.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * Description: Jeg Kit for Elementor (formerly Jeg Elementor Kit) extends Elementor with powerful, customizable widgets and templates — helping you build modern, responsive WordPress websites faster.
 * Requires Plugins: elementor
 * Version: 3.2.13
 * Author: Jegtheme
 * Author URI: https://jegkit.com/?utm_source=wp-plugins&utm_campaign=plugin-uri&utm_medium=wp-dash
 * License: GPLv3
 * Text Domain: jeg-elementor-kit
 *
 * Elementor tested up to: 4.2
 * Elementor Pro tested up to: 4.2
 *
 * @author Jegtheme
 * @since 1.0.0
 * @package jeg-kit
 */

defined( 'JEG_ELEMENTOR_KIT' ) || define( 'JEG_ELEMENTOR_KIT', 'jeg-elementor-kit' );
defined( 'JEG_ELEMENTOR_KIT_NAME' ) || define( 'JEG_ELEMENTOR_KIT_NAME', 'Jeg Kit' );
defined( 'JEG_ELEMENTOR_KIT_VERSION' ) || define( 'JEG_ELEMENTOR_KIT_VERSION', '3.2.13' );
defined( 'JEG_ELEMENTOR_KIT_URL' ) || define( 'JEG_ELEMENTOR_KIT_URL', plugins_url( JEG_ELEMENTOR_KIT ) );
defined( 'JEG_ELEMENTOR_KIT_FILE' ) || define( 'JEG_ELEMENTOR_KIT_FILE', __FILE__ );
defined( 'JEG_ELEMENTOR_KIT_BASE' ) || define( 'JEG_ELEMENTOR_KIT_BASE', plugin_basename( __FILE__ ) );
defined( 'JEG_ELEMENTOR_KIT_DIR' ) || define( 'JEG_ELEMENTOR_KIT_DIR', plugin_dir_path( __FILE__ ) );
defined( 'JEG_ELEMENTOR_KIT_ID' ) || define( 'JEG_ELEMENTOR_KIT_ID', 0 );
defined( 'JEG_ELEMENTOR_KIT_OPTIONS' ) || define( 'JEG_ELEMENTOR_KIT_OPTIONS', 'jeg_elementor_kit_options' );

defined( 'JEG_THEME_URL' ) || define( 'JEG_THEME_URL', JEG_ELEMENTOR_KIT_URL );
defined( 'JEG_ELEMENT_THEME_URL' ) || define( 'JEG_ELEMENT_THEME_URL', JEG_ELEMENTOR_KIT_URL . '/lib/jeg-element' );
defined( 'JEG_ELEMENT_SERVER_URL' ) || define( 'JEG_ELEMENT_SERVER_URL', 'https://jegkit.com/' );
defined( 'ESSENTIAL_FRAMEWORK' ) || define( 'ESSENTIAL_FRAMEWORK', 'essential-framework' );

if ( ! defined( 'JEG_VERSION' ) ) {
	require_once JEG_ELEMENTOR_KIT_DIR . 'lib/jeg-framework/bootstrap.php';
}

if ( ! defined( 'JEG_ELEMENT_VERSION' ) ) {
	require_once JEG_ELEMENTOR_KIT_DIR . 'lib/jeg-element/bootstrap.php';
}

require_once JEG_ELEMENTOR_KIT_DIR . 'lib/vendor/autoload.php';

require_once JEG_ELEMENTOR_KIT_DIR . 'autoload.php';
require_once JEG_ELEMENTOR_KIT_DIR . 'helper.php';

/**
 * Initialize Plugin
 */
add_action(
	'plugins_loaded',
	function () {
		Jeg\Elementor_Kit\Init::instance();
	},
	99
);


/**
 * Fires when the upgrader process is complete.
 *
 * @since 2.5.11
 *
 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a
 *                                Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
 * @param array       $hook_extra {
 *     Array of bulk item update data.
 *
 *     @type string $action       Type of action. Default 'update'.
 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
 *     @type array  $plugins      Array of the basename paths of the plugins' main files.
 *     @type array  $themes       The theme slugs.
 *     @type array  $translations {
 *         Array of translations update data.
 *
 *         @type string $language The locale the translation is for.
 *         @type string $type     Type of translation. Accepts 'plugin', 'theme', or 'core'.
 *         @type string $slug     Text domain the translation is for. The slug of a theme/plugin or
 *                                'default' for core translations.
 *         @type string $version  The version of a theme, plugin, or core.
 *     }
 * }
 */
function jkit_update_complete( $upgrader, $hook_extra ) {
	if ( isset( $hook_extra['plugins'] ) && isset( $hook_extra['plugins'][0] ) && plugin_basename( __FILE__ ) === $hook_extra['plugins'][0] ) {
		\Jeg\Elementor_Kit\Banner\Banner::instance()->register_active_banner();
	}
}
add_action( 'upgrader_process_complete', 'jkit_update_complete', 10, 2 );

/**
 * Fires when the upgrader has successfully overwritten a currently installed
 * plugin or theme with an uploaded zip package.
 *
 * @since 2.5.11
 *
 * @param string $package      The package file.
 * @param array  $data         The new plugin or theme data.
 * @param string $package_type The package type ('plugin' or 'theme').
 */
function jkit_overide_complete( $package, $data, $package_type ) {
	if ( 'plugin' === $package_type && isset( $data['Name'] ) && 'Jeg Kit for Elementor' === $data['Name'] ) {
		\Jeg\Elementor_Kit\Banner\Banner::instance()->register_active_banner();
	}
}
add_action( 'upgrader_overwrote_package', 'jkit_overide_complete', 10, 3 );

/**
 * Disable plugin auto-update.
 */
function jkit_disable_auto_update() {
	$auto_update_plugins = (array) get_site_option( 'auto_update_plugins', array() );
	$plugin_base         = plugin_basename( __FILE__ );

	$updated_auto_update_plugins = array_values( array_diff( $auto_update_plugins, array( $plugin_base ) ) );

	if ( $auto_update_plugins !== $updated_auto_update_plugins ) {
		update_site_option( 'auto_update_plugins', $updated_auto_update_plugins );
	}
}
register_activation_hook( __FILE__, 'jkit_disable_auto_update' );
register_deactivation_hook( __FILE__, 'jkit_disable_auto_update' );
