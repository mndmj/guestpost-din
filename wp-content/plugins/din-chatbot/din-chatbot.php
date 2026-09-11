<?php
/**
 * Plugin Name: Din Chatbot
 * Description: Rule-based WooCommerce font advisor with operator handoff.
 * Version: 1.0.0
 * Author: Din Studio
 * Text Domain: din-chatbot
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'DIN_CHATBOT_FILE', __FILE__ );
define( 'DIN_CHATBOT_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register( static function ( string $class ): void {
    $prefix = 'DinStudio\\DinChatbot\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }

    $relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
    $file     = DIN_CHATBOT_DIR . 'src/' . $relative . '.php';
    if ( is_readable( $file ) ) {
        require $file;
    }
} );

register_activation_hook( DIN_CHATBOT_FILE, [ DinStudio\DinChatbot\Installer::class, 'activate' ] );

DinStudio\DinChatbot\Plugin::boot();
