<?php
/**
 * Jeg Kit Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit;

use Jeg\Elementor_Kit\Admin\Api;
use Jeg\Elementor_Kit\Ajax\Ajax;
use Jeg\Elementor_Kit\Dashboard\Dashboard;
use Jeg\Elementor_Kit\Assets\Asset;
use Jeg\Elementor_Kit\Elements\Element;
use Jeg\Elementor_Kit\Templates\Template;
use Jeg\Elementor_Kit\Banner\Banner;
use Jeg\Elementor_Kit\Wizard\Wizard;
use Jeg\Elementor_Kit\Integrations\Freemius;
use Jeg\Elementor_Kit\Integrations\WPML;

/**
 * Class Init
 *
 * @package Jeg\Elementor_Kit
 */
class Init {
	/**
	 * Class Instance
	 *
	 * @var Init
	 */
	private static $instance;

	/**
	 * Init constructor.
	 */
	private function __construct() {
		$this->setup_init();
		$this->setup_hook();
	}

	/**
	 * Setup Classes
	 */
	private function setup_init() {
		Element::instance();
		Asset::instance();
		Ajax::instance();
		Api::instance();
		Dashboard::instance();
		Template::instance();
		Banner::instance();
		Wizard::instance();
		Freemius::instance();
		WPML::instance();
	}

	/**
	 * Setup Hooks
	 */
	private function setup_hook() {
		add_action( 'load-plugins.php', array( $this, 'replace_plugin_update_row' ), 99 );
		add_action( 'current_screen', array( $this, 'maybe_replace_plugin_update_row' ), 20 );
		add_filter( 'plugin_action_links_' . JEG_ELEMENTOR_KIT_BASE, array( $this, 'add_upgrade_to_pro_action_link' ), 10, 4 );
		add_filter( 'body_class', array( $this, 'load_body_class' ) );
		add_filter( 'plugin_auto_update_setting_html', array( $this, 'disable_auto_update_setting_html' ), 10, 3 );
		add_action( 'after_setup_theme', array( $this, 'elementor_data_upgrader' ) );
	}

	/**
	 * Get class instance
	 *
	 * @return Init
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Replace default plugin update row.
	 */
	public function replace_plugin_update_row() {
		remove_action( 'after_plugin_row_' . JEG_ELEMENTOR_KIT_BASE, 'wp_plugin_update_row' );
		remove_action( 'after_plugin_row_' . JEG_ELEMENTOR_KIT_BASE, 'jkit_plugin_update_row', 10 );
		add_action( 'after_plugin_row_' . JEG_ELEMENTOR_KIT_BASE, 'jkit_plugin_update_row', 10, 2 );
	}

	/**
	 * Ensure custom plugin update row is used on the Plugins screen, including search results.
	 *
	 * @param \WP_Screen $screen Current screen instance.
	 * @return void
	 */
	public function maybe_replace_plugin_update_row( $screen ) {
		if ( ! isset( $screen->id ) || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}

		$this->replace_plugin_update_row();
	}

	/**
	 * Add body class
	 *
	 * @param array $classes Body classes.
	 */
	public function load_body_class( $classes ) {
		$classes[] = 'jkit-color-scheme';
		return apply_filters( 'jkit_body_classes', $classes );
	}

	/**
	 * Filters the HTML of the auto-updates setting for Jeg Kit for Elementor plugin in the Plugins list table.
	 *
	 * @since 3.1.3
	 *
	 * @param string $html        The HTML of the plugin's auto-update column content,
	 *                            including toggle auto-update action links and
	 *                            time to next update.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data. See get_plugin_data()
	 *                            and the {@see 'plugin_row_meta'} filter for the list
	 *                            of possible values.
	 *
	 * @return string
	 */
	public function disable_auto_update_setting_html( $html, $plugin_file, $plugin_data ) {
		if ( isset( $plugin_data['plugin'] ) && JEG_ELEMENTOR_KIT_BASE === $plugin_data['plugin'] ) {
			$html = '';
		}

		return $html;
	}

	/**
	 * Add upgrade to pro action link.
	 *
	 * @param array  $actions     An array of plugin action links.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data An array of plugin data.
	 * @param string $context     The plugin context.
	 *
	 * @return array
	 */
	public function add_upgrade_to_pro_action_link( $actions, $plugin_file, $plugin_data, $context ) {
		$actions['upgrade-to-jeg-kit-pro'] = sprintf(
			'<b><a class="jkit-meta-upgrade-to-pro" target="_blank" href="%1$s">%2$s</a></b>',
			esc_url(
				add_query_arg(
					array(
						'page'       => 'jkit',
						'utm_source' => 'plugin-row-meta',
						'utm_medium' => 'plugin-row-link',
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Get PRO', 'jeg-elementor-kit' )
		);

		return $actions;
	}

	/**
	 * Custom Plugin Notice Update
	 *
	 * @since 2.4.3
	 *
	 * @param array  $plugin_data An array of plugin metadata. See get_plugin_data()
	 *                            and the {@see 'plugin_row_meta'} filter for the list
	 *                            of possible values.
	 * @param object $response {
	 *     An object of metadata about the available plugin update.
	 *
	 *     @type string   $id           Plugin ID, e.g. `w.org/plugins/[plugin-name]`.
	 *     @type string   $slug         Plugin slug.
	 *     @type string   $plugin       Plugin basename.
	 *     @type string   $new_version  New plugin version.
	 *     @type string   $url          Plugin URL.
	 *     @type string   $package      Plugin update package URL.
	 *     @type string[] $icons        An array of plugin icon URLs.
	 *     @type string[] $banners      An array of plugin banner URLs.
	 *     @type string[] $banners_rtl  An array of plugin RTL banner URLs.
	 *     @type string   $requires     The version of WordPress which the plugin requires.
	 *     @type string   $tested       The version of WordPress the plugin is tested against.
	 *     @type string   $requires_php The version of PHP which the plugin requires.
	 */
	public function plugin_update_message( $plugin_data, $response ) {
		echo '<br><b style="margin-left: 26px;">' . esc_html__( 'It is recommended that you backup your site before updating the plugin so rollback is possible whenever needed.', 'jeg-elementor-kit' ) . '<b>';
	}

	/**
	 * Upgrader Elementor Data from Jeg Kit due to conflict with Metform plugin
	 *
	 * @since 2.5.11
	 */
	public function elementor_data_upgrader() {
		$post_ids = $this->get_header_footer_template_issue_id();

		foreach ( $post_ids as $id ) {
			$meta = get_post_meta( $id, '_elementor_data', true );

			if ( ! is_string( $meta ) ) {
				$meta_encode = json_encode( $meta );

				update_post_meta( $id, '_elementor_data', wp_slash( $meta_encode ) );
			}
		}
	}

	/**
	 * Get Header Footer Template Issue ID
	 *
	 * @since 2.5.11
	 *
	 * @return WP_Post|int
	 */
	private function get_header_footer_template_issue_id() {
		$args     = array(
			'post_type' => array( \Jeg\Elementor_Kit\Dashboard\Dashboard::$jkit_header, \Jeg\Elementor_Kit\Dashboard\Dashboard::$jkit_footer ),
			'fields'    => 'ids',
		);
		$post_ids = array();

		$query = get_posts( $args );

		foreach ( $query as $id ) {
			$meta = get_post_meta( $id, '_elementor_data', true );

			if ( ! is_string( $meta ) ) {
				array_push( $post_ids, $id );
			}
		}

		return $post_ids;
	}
}
