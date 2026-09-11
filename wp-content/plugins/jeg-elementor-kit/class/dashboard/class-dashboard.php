<?php
/**
 * Jeg Kit Dashboard Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 2.0.0
 */

namespace Jeg\Elementor_Kit\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Jeg\Elementor_Kit\Elements\Element;
use Jeg\Elementor_Kit\Fonts;
use Jeg\Elementor_Kit\Options\Settings;

/**
 * Class Dashboard.
 *
 * @package jeg-elementor-kit
 */
class Dashboard {

	/**
	 * Slug Default Jeg Kit Dashboard
	 *
	 * @var string
	 */
	public static $slug_default;

	/**
	 * Slug for accessing Jeg Kit Dashboard
	 *
	 * @var string
	 */
	public static $dashboard = 'jkit-dashboard';

	/**
	 * Slug for accessing Jeg Kit Settings
	 *
	 * @var string
	 */
	public static $settings = 'jkit-settings';

	/**
	 * Slug for accessing Jeg Kit Templates
	 *
	 * @var string
	 */
	public static $templates = 'jkit-manage-template';

	/**
	 * Slug for accessing Jeg Kit Dashboard
	 *
	 * @var string
	 */
	public static $user_data = 'jkit-user-data';

	/**
	 * Slug for accessing Jeg Kit Header Post Type
	 *
	 * @var string
	 */
	public static $jkit_header = 'jkit-header';

	/**
	 * Slug for accessing Jeg Kit Footer Post Type
	 *
	 * @var string
	 */
	public static $jkit_footer = 'jkit-footer';

	/**
	 * Slug for accessing Jeg Kit Single Post Type
	 *
	 * @var string
	 */
	public static $jkit_post = 'jkit-single-post';

	/**
	 * Slug for accessing Jeg Kit Single Product Type
	 *
	 * @var string
	 */
	public static $jkit_product = 'jkit-single-product';

	/**
	 * Slug for accessing Jeg Kit Archive Type
	 *
	 * @var string
	 */
	public static $jkit_archive = 'jkit-archive-template';

	/**
	 * Slug for accessing Jeg Kit Popup Type
	 *
	 * @var string
	 */
	public static $jkit_popup = 'jkit-popup-template';

	/**
	 * Jeg Kit Template Post Type
	 *
	 * @var string
	 */
	public static $jkit_template = 'jkit-template';

	/**
	 * Slug for meta condition
	 *
	 * @var string
	 */
	public static $jkit_condition = 'jkit-condition';

	/**
	 * Ajax endpoint
	 *
	 * @var string
	 */
	private $endpoint = 'jkit-ajax-request';

	/**
	 * Template slug
	 *
	 * @var string
	 */
	private $template_slug = 'templates/dashboard/dashboard';

	/**
	 * Class instance
	 *
	 * @var Dashboard
	 */
	private static $instance;

	/**
	 * Menu for dashboard
	 *
	 * @var array
	 */
	public static $framework_menu = array();

	/**
	 * Return class instance
	 *
	 * @return Dashboard
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Class constructor
	 */
	private function __construct() {
		$this->load_hook();
	}

	/**
	 * Load Hook
	 */
	private function load_hook() {
		add_action( 'init', array( $this, 'post_type' ), 9 );

		add_action( 'admin_menu', array( $this, 'setup_parent_page' ) );
		add_action( 'admin_menu', array( $this, 'setup_child_page' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar' ), 99 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 12 );
		add_action( 'wizard_enqueue_scripts', array( $this, 'enqueue_scripts' ), 12 );
		add_action( 'current_screen', array( $this, 'suppress_dashboard_notices' ) );
	}

	/**
	 * Suppress WordPress admin notices on the Jeg Kit dashboard.
	 *
	 * @param \WP_Screen $screen Current admin screen.
	 */
	public function suppress_dashboard_notices( $screen ) {
		if ( ! $screen || 'toplevel_page_jkit' !== $screen->id ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
		remove_all_actions( 'user_admin_notices' );

		add_action( 'admin_head', array( $this, 'hide_dashboard_notices' ) );
	}

	/**
	 * Hide notices printed outside the standard notice hooks.
	 */
	public function hide_dashboard_notices() {
		?>
		<style>
			#wpbody-content > .notice,
			#wpbody-content > .error,
			#wpbody-content > .updated,
			#wpbody-content > .update-nag {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Initialize Menu
	 */
	public function define_menu() {
		self::$framework_menu['dashboard'] = array(
			'name'     => esc_html__( 'Dashboard', 'jeg-elementor-kit' ),
			'priority' => 49,
			'type'     => 'menu',
		);

		self::$framework_menu['demos'] = array(
			'name'     => esc_html__( 'Demos', 'jeg-elementor-kit' ),
			'priority' => 50,
			'type'     => 'menu',
		);

		self::$framework_menu['elements'] = array(
			'name'     => esc_html__( 'Elements', 'jeg-elementor-kit' ),
			'priority' => 51,
			'type'     => 'menu',
		);

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			self::$framework_menu['theme-builder'] = array(
				'name'     => esc_html__( 'Theme Builder', 'jeg-elementor-kit' ),
				'priority' => 53,
				'type'     => 'menu',
			);
		}

		self::$framework_menu['system-status'] = array(
			'name'     => esc_html__( 'System Status', 'jeg-elementor-kit' ),
			'priority' => 70,
			'type'     => 'menu',
		);

		self::$framework_menu = apply_filters( 'jkit_dashboard_navigation_item', self::$framework_menu );
	}

	/**
	 * Get Template List
	 */
	public function get_template_list() {
		if ( class_exists( '\Jeg\Elementor_Kit\Dashboard\Dashboard' ) ) {

			self::$framework_menu['theme-builder'] = array(
				'name'     => esc_html__( 'Theme Builder', 'jeg-elementor-kit' ),
				'priority' => 60,
				'type'     => 'url',
				'url'      => get_home_url() . '/wp-admin/admin.php?page=jkit-header',
			);

			$menus = self::instance()->get_admin_menu();

			foreach ( $menus as $key => $menu ) {
				if ( isset( $menu['parent'] ) && 'jkit-manage-template' === $menu['parent'] ) {
					self::$framework_menu[ $menu['slug'] ] = array(
						'name'     => $menu['title'],
						'priority' => $menu['priority'],
						'type'     => 'url',
						'url'      => get_home_url() . '/wp-admin/admin.php?page=' . $menu['slug'],
						'parent'   => 'theme-builder',
					);
				}
			}
		}
	}

	/**
	 * Setup parent page menu
	 */
	public function setup_parent_page() {
		$this->define_menu();

		add_menu_page(
			esc_html__( 'Jeg Kit', 'jeg-elementor-kit' ),
			esc_html__( 'Jeg Kit', 'jeg-elementor-kit' ),
			'edit_theme_options',
			'jkit',
			array( $this, 'dashboard_page' ),
			JEG_ELEMENTOR_KIT_URL . '/assets/svg/jkit-dashboard-menu-logo.svg',
			30
		);
	}

	/**
	 * Get child pages
	 *
	 * @return void
	 */
	public function setup_child_page() {
		$path    = admin_url( 'admin.php?page=jkit&path=' );
		$subpath = '&subpath=';
		$pages   = array();

		foreach ( self::$framework_menu as $key => $menu ) {
			if ( $menu ) {
				$pages[] = array(
					'title'    => $menu['name'],
					'menu'     => $menu['name'],
					'slug'     => 'url' === $menu['type'] ? $menu['url'] : $path . $key,
					'position' => $menu['priority'],
				);
				if ( 1 === count( $pages ) ) {
					$pages[ count( $pages ) - 1 ]['slug']     = 'jkit';
					$pages[ count( $pages ) - 1 ]['callback'] = array( $this, 'dashboard_page' );
				}
				if ( isset( $menu['parent'] ) ) {
					$pages[ count( $pages ) - 1 ]['class'] = 'jeg-elementor-kit-child-menu';
					$pages[ count( $pages ) - 1 ]['slug']  = 'url' === $menu['type'] ? $menu['url'] : $path . $menu['parent'] . $subpath . $key;
				}
			}
		}

		/** Sorting Page menus by Positions */
		usort(
			$pages,
			function ( $a, $b ) {
				$menu_a = floatval( $a['position'] );
				$menu_b = floatval( $b['position'] );

				if ( $menu_a < $menu_b ) {
					return -1;
				} elseif ( $menu_a > $menu_b ) {
					return 1;
				} else {
					return 0;
				}
			}
		);

		foreach ( $pages as $key => $page ) {
			add_submenu_page(
				'jkit',
				$page['title'],
				$page['menu'],
				'edit_theme_options',
				$page['slug'],
				isset( $page['callback'] ) ? $page['callback'] : ''
			);
			$this->add_child_menu_class( $key, $page );
		}

		if ( ! defined( 'JEG_KIT_PRO' ) ) {
			$crown_markup = '<img src="' . JEG_ELEMENTOR_KIT_URL . '/assets/img/crown.svg" alt="Jeg Kit Pro"/>';

			$pricing_url = add_query_arg(
				array(
					'utm_source'       => 'jeg-elementor-kit',
					'utm_medium'       => 'adminsidebar',
					'utm_client_site'  => get_home_url(),
					'utm_client_theme' => wp_get_theme()->get_stylesheet(),
				),
				JEG_ELEMENT_SERVER_URL . 'pricing'
			);

			add_submenu_page(
				'jkit',
				esc_html__( 'Upgrade to Pro', 'jeg-elementor-kit' ),
				esc_html__( 'Upgrade to Pro', 'jeg-elementor-kit' ) . ' ' . $crown_markup,
				'edit_theme_options',
				$path . '&utm_source=jeg-elementor-kit&utm_medium=nav-menu'
			);

			global $submenu;

			if ( isset( $submenu['jkit'] ) ) {
				$last_key                        = array_key_last( $submenu['jkit'] );
				$submenu['jkit'][ $last_key ][4] = ( $submenu['jkit'][ $last_key ][4] ?? '' ) . ' jkit-upgrade-to-pro-menu'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Add Class Selector to Child Menu
	 *
	 * @param int   $key Menu offset.
	 * @param array $menu List of menu.
	 */
	private function add_child_menu_class( $key, $menu ) {
		global $submenu;

		if ( isset( $menu['class'] ) ) {
			// @codingStandardsIgnoreStart
			$submenu['jkit'][ $key ][4] = $menu['class'];
			// @codingStandardsIgnoreEnd
		}
	}

	/**
	 * Summary of add_toolbar
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar
	 *
	 * @return void
	 */
	public function add_toolbar( $admin_bar ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$logo_svg = $this->get_svg_inline( 'assets/svg/jkit-dashboard-menu-logo.svg' );
		$logo     = $logo_svg ? $logo_svg : '<img src="' . JEG_ELEMENTOR_KIT_URL . '/assets/svg/jkit-dashboard-menu-logo.svg' . '" alt="' . esc_html__( 'Jeg Kit Logo', 'jeg-elementor-kit' ) . '"/>';

		$admin_bar->add_menu(
			array(
				'id'    => 'jeg-kit',
				'title' => '<span class="jeg-kit-pro">' . $logo . esc_html__( 'Jeg Kit', 'jeg-elementor-kit' ) . '</span>',
				'href'  => esc_url( get_home_url() . '/wp-admin/admin.php?page=jkit' ),
			)
		);

		$admin_bar->add_menu(
			array(
				'id'     => 'jeg-kit-admin',
				'title'  => '<span class="jeg-kit-pro">' . esc_html__( 'Jeg Kit Admin', 'jeg-elementor-kit' ) . '</span>',
				'href'   => esc_url( get_home_url() . '/wp-admin/admin.php?page=jkit' ),
				'parent' => 'jeg-kit',
			)
		);

		$this->define_menu();

		$path        = admin_url( 'admin.php?page=jkit&path=' );
		$menu_sorted = self::$framework_menu;

		uasort( $menu_sorted, function ( $a, $b ) {
			return ( ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 ) );
		} );

		foreach ( $menu_sorted as $key => $menu ) {
			if ( $menu ) {
				if ( $key === 'dashboard' ) {
					$admin_bar->add_menu(
						array(
							'id'     => 'jeg-kit-' . $key,
							'title'  => '<span class="jeg-kit-menu">' . esc_html( $menu['name'] ) . '</span>',
							'href'   => esc_url( admin_url( 'admin.php?page=jkit' ) ),
							'parent' => 'jeg-kit-admin',
						)
					);
				} else if ( $key === 'theme-builder' ) {
					$admin_bar->add_menu(
						array(
							'id'     => 'jeg-kit-' . $key,
							'title'  => '<span class="jeg-kit-menu">' . esc_html( $menu['name'] ) . '</span>',
							'href'   => 'url' === $menu['type'] ? esc_url( $menu['url'] ) : esc_url( $path . $key ),
							'parent' => 'jeg-kit',
						)
					);
				} else {
					$admin_bar->add_menu(
						array(
							'id'     => 'jeg-kit-' . $key,
							'title'  => '<span class="jeg-kit-menu">' . esc_html( $menu['name'] ) . '</span>',
							'href'   => 'url' === $menu['type'] ? esc_url( $menu['url'] ) : esc_url( $path . $key ),
							'parent' => 'jeg-kit-admin',
						)
					);
				}
			}
		}

		$admin_bar->add_menu(
			array(
				'id'     => 'jeg-kit-menu-border',
				'title'  => '',
				'parent' => 'jeg-kit',
			)
		);

		$svg_icon  = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.67679 3.52128L6.67898 3.06505L5.33889 0.220085C5.30228 0.142191 5.24207 0.0791345 5.16768 0.0408059C4.98113 -0.0556336 4.75444 0.0247327 4.66116 0.220085L3.32106 3.06505L0.323258 3.52128C0.240609 3.53365 0.165044 3.57445 0.107189 3.63627C0.0372466 3.71155 -0.00129512 3.81283 3.3232e-05 3.91785C0.00136159 4.02287 0.0424513 4.12305 0.114274 4.19636L2.28323 6.41076L1.7708 9.53763C1.75878 9.61037 1.76647 9.68517 1.79299 9.75357C1.81951 9.82197 1.86379 9.88121 1.92083 9.92459C1.97787 9.96797 2.04537 9.99375 2.11568 9.999C2.186 10.0042 2.25631 9.98876 2.31865 9.9543L5.00002 8.47803L7.6814 9.9543C7.7546 9.9951 7.83962 10.0087 7.92108 9.99386C8.12653 9.95677 8.26467 9.75276 8.22925 9.53763L7.71682 6.41076L9.88577 4.19636C9.94481 4.13578 9.98377 4.05665 9.99558 3.9701C10.0275 3.75373 9.88341 3.55343 9.67679 3.52128Z" fill="url(#paint0_linear_1841_8010)"/><defs><linearGradient id="paint0_linear_1841_8010" x1="5.2381" y1="12.7273" x2="5.23809" y2="-7.27273" gradientUnits="userSpaceOnUse"><stop stop-color="#FFD978"/><stop offset="1" stop-color="#FFAA00"/></linearGradient></defs></svg>';
		$star_icon = '<span class="star-icon">' . $svg_icon . $svg_icon . $svg_icon . $svg_icon . $svg_icon . '</span>';

		$utm_common = array(
			'utm_source'       => 'jeg-elementor-kit',
			'utm_medium'       => 'admintopbar',
			'utm_client_site'  => get_home_url(),
			'utm_client_theme' => wp_get_theme()->get_stylesheet(),
		);

		$reviews_url  = add_query_arg( $utm_common, 'https://wordpress.org/support/plugin/jeg-elementor-kit/reviews/' );
		$reviews_url .= '#new-post';

		$support_url  = add_query_arg( $utm_common, 'https://wordpress.org/support/plugin/jeg-elementor-kit/' );
		$support_url .= '#new-topic-0';

		$documentation_url = add_query_arg( $utm_common, JEG_ELEMENT_SERVER_URL . 'documentation' );

		$pricing_url_adminbar = add_query_arg( $utm_common, JEG_ELEMENT_SERVER_URL . 'pricing' );

		$admin_bar->add_menu(
			array(
				'id'     => 'jeg-kit-rate-us',
				'title'  => '<span class="jeg-kit-pro">' . esc_html__( 'Rate Us ', 'jeg-elementor-kit' ) . $star_icon . '</span>',
				'href'   => esc_url( $reviews_url ),
				'meta'   => array(
					'title'  => esc_html__( 'Leave a review for Jeg Kit', 'jeg-elementor-kit' ),
					'target' => '_blank',
				),
				'parent' => 'jeg-kit',
			)
		);

		$admin_bar->add_menu(
			array(
				'id'     => 'jeg-kit-get-support',
				'title'  => '<span class="jeg-kit-pro">' . esc_html__( 'Get a Support', 'jeg-elementor-kit' ) . '</span>',
				'href'   => esc_url( $support_url ),
				'meta'   => array(
					'title'  => esc_html__( 'Get Jeg Kit Support', 'jeg-elementor-kit' ),
					'target' => '_blank',
				),
				'parent' => 'jeg-kit',
			)
		);

		$admin_bar->add_menu(
			array(
				'id'     => 'jeg-kit-documentation',
				'title'  => '<span class="jeg-kit-pro">' . esc_html__( 'Documentation', 'jeg-elementor-kit' ) . '</span>',
				'href'   => esc_url( $documentation_url ),
				'meta'   => array(
					'title'  => esc_html__( 'Open Jeg Kit Documentation', 'jeg-elementor-kit' ),
					'target' => '_blank',
				),
				'parent' => 'jeg-kit',
			)
		);

		if ( ! defined( 'JEG_KIT_PRO' ) ) {
			$crown_markup = '<img src="' . JEG_ELEMENTOR_KIT_URL . '/assets/img/crown.svg" alt="' . esc_html__( 'Jeg Kit Pro', 'jeg-elementor-kit' ) . '"/>';

			$admin_bar->add_menu(
				array(
					'id'     => 'jeg-kit-pro',
					'title'  => '<span class="jeg-kit-pro">' . esc_html__( 'Upgrade to Pro ', 'jeg-elementor-kit' ) . $crown_markup . '</span>',
					'href'   => esc_url( admin_url( 'admin.php?page=jkit&utm_source=jeg-elementor-kit&utm_medium=admintopbar' ) ),
					'meta'   => array(
						'title'  => esc_html__( 'Get Jeg Kit Pro', 'jeg-elementor-kit' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue scripts
	 *
	 * @param string $hook .
	 */
	public function enqueue_scripts( $hook ) {
		$register_location = array(
			'toplevel_page_' . 'jkit',
			'wizard',
		);

		if ( in_array( $hook, $register_location, true ) ) {
			wp_enqueue_style( 'jkit-dashboard', JEG_ELEMENTOR_KIT_URL . '/assets/css/admin/dashboard.css', null, JEG_ELEMENTOR_KIT_VERSION );

			wp_register_script( 'jkit-dashboard', JEG_ELEMENTOR_KIT_URL . '/assets/js/admin/dashboard.js', array( 'lodash', 'react', 'react-dom', 'regenerator-runtime', 'wp-api-fetch', 'wp-data', 'wp-hooks', 'wp-i18n', 'wp-notices' ), JEG_ELEMENTOR_KIT_VERSION, true );

			wp_enqueue_script( 'jkit-core-control', JEG_ELEMENTOR_KIT_URL . '/assets/js/admin/core.js', array( 'lodash', 'react', 'react-dom', 'regenerator-runtime', 'wp-api-fetch', 'wp-data', 'wp-hooks', 'wp-i18n', 'wp-notices' ), JEG_ELEMENTOR_KIT_VERSION, true );

			$name_translation_object = $this->get_object_name( 'jkit-dashboard-text', '-' );
			$name_option_object      = $this->get_object_name( 'jkit-dashboard-option', '-' );
			$option_object           = wp_json_encode( $this->get_all_option() );

			wp_enqueue_media();

			wp_localize_script(
				'jkit-dashboard',
				$name_translation_object,
				$this->get_all_text()
			);

			wp_add_inline_script( 'jkit-dashboard', "var {$name_option_object} = {$option_object};" );
			// wp_localize_script( 'jkit-dashboard', 'JkitDashboardOption', wp_json_encode( $this->get_all_option() ) );
			wp_enqueue_script( 'jkit-dashboard' );
		}
	}

	/**
	 * Post Type
	 */
	public function post_type() {
		foreach ( self::post_type_list() as $post_type => $data ) {
			register_post_type(
				$post_type,
				array(
					'label'             => $data['label'],
					'public'            => true,
					'show_ui'           => false,
					'capability_type'   => 'post',
					'hierarchical'      => false,
					'show_in_nav_menus' => false,
					'supports'          => array( 'title', 'revisions', 'page-attributes', 'elementor', 'custom-fields' ),
					'map_meta_cap'      => true,
					'rewrite'           => array(
						'slug'       => $post_type,
						'with_front' => false,
					),
				)
			);
		}
	}

	/**
	 * Admin Menu
	 *
	 * @return array
	 */
	public function get_admin_menu() {
		$menu[] = array(
			'title'    => esc_html__( 'Settings', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Settings', 'jeg-elementor-kit' ),
			'slug'     => self::$settings,
			'action'   => array(&$this, 'settings' ),
			'priority' => 56,
			'icon'     => 'fa-cogs',
		);

		$menu[] = array(
			'title'    => esc_html__( 'User Data', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'User Data', 'jeg-elementor-kit' ),
			'slug'     => self::$user_data,
			'action'   => array(&$this, 'user_data' ),
			'priority' => 57,
			'icon'     => 'fa-regular fa-circle-user',
		);

		$menu[] = array(
			'title'    => esc_html__( 'Elements', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Elements', 'jeg-elementor-kit' ),
			'slug'     => 'jkit-elements',
			'action'   => array(&$this, 'elements' ),
			'priority' => 58,
			'icon'     => 'fa-solid fa-bars-progress',
		);

		$menu[] = array(
			'title'    => esc_html__( 'Templates', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Templates', 'jeg-elementor-kit' ),
			'slug'     => self::$templates,
			'action'   => array(&$this, 'manage_template' ),
			'priority' => 59,
			'icon'     => 'fa-regular fa-file-lines',
			'class'    => 'have-jkit-child-menu',
		);

		$menu[] = array(
			'title'    => esc_html__( 'Header', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Header', 'jeg-elementor-kit' ),
			'slug'     => self::$jkit_header,
			'action'   => array( $this, 'header_template' ),
			'parent'   => self::$templates,
			'priority' => 60,
			'class'    => 'jkit-child-menu first',
		);

		$menu[] = array(
			'title'    => esc_html__( 'Footer', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Footer', 'jeg-elementor-kit' ),
			'slug'     => self::$jkit_footer,
			'action'   => array( $this, 'footer_template' ),
			'parent'   => self::$templates,
			'priority' => 61,
			'class'    => 'jkit-child-menu',
		);

		$menu[] = array(
			'title'    => esc_html__( 'Not Found 404', 'jeg-elementor-kit' ),
			'menu'     => esc_html__( 'Not Found 404', 'jeg-elementor-kit' ),
			'slug'     => 'jkit-404',
			'action'   => array( $this, 'not_found_template' ),
			'parent'   => self::$templates,
			'priority' => 62,
			'class'    => 'jkit-child-menu last',
		);

		$menu[] = array(
			'title'         => esc_html__( 'Need Help?', 'jeg-elementor-kit' ),
			'menu'          => esc_html__( 'Need Help?', 'jeg-elementor-kit' ),
			'slug'          => 'support-forum',
			'priority'      => 63,
			'icon'          => 'fa-solid fa-life-ring',
			'external_link' => 'https://wordpress.org/support/plugin/jeg-elementor-kit/#new-topic-0',
			'class'         => 'jkit-support-menu',
		);

		return apply_filters( 'jkit_admin_menu', $menu );
	}

	/**
	 * Generate Object Name
	 *
	 * @param string $name Name that will convert to object name.
	 * @param string $separator Separator use in name.
	 *
	 * @return string
	 */
	private function get_object_name( $name, $separator ) {
		$object_name = str_replace( ' ', '', ucwords( str_replace( $separator, ' ', $name ) ) );
		return $object_name;
	}

	/**
	 * Get inline SVG markup from plugin assets.
	 *
	 * @param string $relative_path Path relative to plugin dir (no leading slash).
	 * @return string|false SVG markup on success, false on failure.
	 */
	private function get_svg_inline( $relative_path ) {
		$path = JEG_ELEMENTOR_KIT_DIR . ltrim( $relative_path, '/' );
		if ( file_exists( $path ) ) {
			$svg = file_get_contents( $path );
			if ( $svg !== false ) {
				return $svg;
			}
		}
		return false;
	}

	/**
	 * Get all option
	 *
	 * @return array
	 */
	private function get_all_option() {
		$pricing_config = \Jeg\Elementor_Kit\Integrations\Freemius::instance()->get_pricing_config();

		$options = array(
			'homeSlug'          => 'jkit',
			'menus'             => self::$framework_menu,
			'globalOptions'     => Settings::current_settings(),
			'imgDir'            => JEG_ELEMENTOR_KIT_URL . '/assets/img/',
			'plugins'           => self::list_plugin(),
			'system'            => self::system_status(),
			'optionInfo'        => self::get_option_info(),
			'fontList'          => Fonts::get_formated_font_list(),
			'activeBreakpoint'  => jkit_get_elementor_responsive_breakpoints(),
			'activeTemplate'    => wp_get_theme()->get_page_templates(),
			'themeMenu'         => self::get_theme_option_menu(),
			'homeUrl'           => get_home_url(),
			'apiUrl'            => array(),
			'wooCommerceActice' => class_exists( 'WooCommerce' ),
			'root'              => 'jkit',
			'proActive'         => defined( 'JEG_KIT_PRO' ),
			'elementLists'      => Element::instance()->list_elements(),
			'activeElements'    => get_option( 'jkit_elements_enable', array() ),
			'elementCategories' => $this->jkit_element_categories(),
			'themeVersion'      => JEG_ELEMENTOR_KIT_VERSION,
			'ratingUrl'         => 'https://wordpress.org/plugins/jeg-elementor-kit/',
			'upgradeURL'        => esc_url( JEG_ELEMENT_SERVER_URL ), // TODO
			'getLicense'        => esc_url( JEG_ELEMENT_SERVER_URL ), // TODO
			'forumUrl'          => 'https://wordpress.org/support/plugin/jeg-elementor-kit/',
			'demoUrl'           => menu_page_url( 'demos' ),
			'documentationUrl'  => esc_url( JEG_ELEMENT_SERVER_URL . '/documentation/' ),
			'globalStyle'       => array(
				'choices'  => jkit_get_elementor_saved_template_option(),
				'selected' => get_option( 'elementor_active_kit' ),
			),
			'notFoundTemplate'  => get_option( 'jkit_notfound_template', false ),
			'userData'          => get_option( 'jkit_user_data', array() ),
			'themeBuilderMenu'  => $this->get_theme_builder_menus(),
			'themeBuilderDesc'  => $this->get_theme_builder_desc(),
			'conditionFields'   => $this->condition_fields(),
			'bannerData'        => jkit_get_banner_data(),
			'bannerNonce'       => wp_create_nonce( 'jkit-banner' ),
			'wpRestNonce'       => wp_create_nonce( 'wp_rest' ),
			'pricingPlan'       => jkit_get_pricing_plan(),
			'pricingData'       => $this->get_pricing_data( $pricing_config ),
			'serverUrl'         => esc_url( JEG_ELEMENT_SERVER_URL ),
			'freemius'          => array(
				'pricing' => $pricing_config,
			),
		);

		return apply_filters( 'jkit_dashboard_options', $options );
	}

	/**
	 * Fetch and cache Freemius pricing data for dashboard rendering.
	 *
	 * @param array|null $pricing_config Freemius pricing config.
	 *
	 * @return array|null
	 */
	private function get_pricing_data( $pricing_config ) {
		if ( empty( $pricing_config ) ) {
			return null;
		}

		$transient_key = 'jkit_dashboard_pricing_data_' . md5( wp_json_encode( array(
			isset( $pricing_config['plugin_id'] ) ? $pricing_config['plugin_id'] : '',
			isset( $pricing_config['bundle_id'] ) ? $pricing_config['bundle_id'] : '',
			isset( $pricing_config['sandbox'] ) ? $pricing_config['sandbox'] : '',
			isset( $pricing_config['s_ctx_type'] ) ? $pricing_config['s_ctx_type'] : '',
			isset( $pricing_config['s_ctx_id'] ) ? $pricing_config['s_ctx_id'] : '',
			isset( $pricing_config['s_ctx_secure'] ) ? $pricing_config['s_ctx_secure'] : '',
		) ) );

		$cached = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$data = \Jeg\Elementor_Kit\Integrations\Freemius::instance()->get_pricing_data();
		if ( empty( $data['plans'] ) || ! is_array( $data['plans'] ) ) {
			return null;
		}

		set_transient( $transient_key, $data, 10 * MINUTE_IN_SECONDS );

		return $data;
	}



	/**
	 * Condition
	 *
	 * @param array $value Option to retrieve.
	 *
	 * @return array
	 */
	public function condition_fields() {
		$fields = array();

		$fields['default']['location'] = array(
			'type'        => 'select',
			'title'       => esc_html__( 'Location', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Set where should this template will be shown.', 'jeg-elementor-kit' ),
			'options'     => array(
				'all_site' => esc_html__( 'All Site', 'jeg-elementor-kit' ),
				'singular' => esc_html__( 'Singular Page', 'jeg-elementor-kit' ),
				'archives' => esc_html__( 'Archive Page', 'jeg-elementor-kit' ),
			),
			'default'     => 'all_site',
		);

		$fields['default']['enclose'] = array(
			'type'        => 'select',
			'title'       => esc_html__( 'Enclose Status', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Choose the enclosed status.', 'jeg-elementor-kit' ),
			'options'     => array(
				'include' => esc_html__( 'Include', 'jeg-elementor-kit' ),
				'exclude' => esc_html__( 'Exclude', 'jeg-elementor-kit' ),
			),
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => 'not in',
					'value'    => array( '', 'all_site' ),
				),
			),
			'default'     => 'include',
		);

		/**
		 * Archive
		 */
		$fields['default']['archives'] = array(
			'type'        => 'select',
			'title'       => esc_html__( 'Archive Type', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Choose the archive page type.', 'jeg-elementor-kit' ),
			'options'     => array(
				'all_archive' => esc_html__( 'All Archives', 'jeg-elementor-kit' ),
				'author'      => esc_html__( 'Author', 'jeg-elementor-kit' ),
				'date'        => esc_html__( 'Date', 'jeg-elementor-kit' ),
				'search'      => esc_html__( 'Search', 'jeg-elementor-kit' ),
			),
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'archives',
				),
			),
			'default'     => 'all_archive',
		);

		$fields['default']['archives']['options'] = array_merge( $fields['default']['archives']['options'], jkit_get_taxonomies() );

		$fields['default']['archives_author'] = array(
			'type'        => 'select-search',
			'multiple'    => 100,
			'title'       => esc_html__( 'Archive Author', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Write the author name to search.', 'jeg-elementor-kit' ),
			'onSearch'    => 'searchAuthors',
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'archives',
				),
				array(
					'field'    => 'archives',
					'operator' => '===',
					'value'    => 'author',
				),
			),
			'default'     => '',
		);

		$fields['default']['archive_taxonomy'] = array(
			'type'        => 'select-search',
			'multiple'    => 100,
			'title'       => esc_html__( 'Archive Taxonomy', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Write the terms name to search. Leave empty to select all terms.', 'jeg-elementor-kit' ),
			'onSearch'    => 'searchTaxonomies',
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'archives',
				),
				array(
					'field'    => 'archives',
					'operator' => 'in',
					'value'    => jkit_get_taxonomies( false ),
				),
			),
			'default'     => '',
		);

		/**
		 * Singular
		 */
		$fields['default']['singular'] = array(
			'type'        => 'select',
			'title'       => esc_html__( 'Singular Type', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Choose singular type.', 'jeg-elementor-kit' ),
			'options'     => array(
				'singular' => esc_html__( 'Singular', 'jeg-elementor-kit' ),
				'front'    => esc_html__( 'Front Page', 'jeg-elementor-kit' ),
				'notfound' => esc_html__( '404 Page', 'jeg-elementor-kit' ),
			),
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'singular',
				),
			),
			'default'     => 'singular',
		);

		$fields['default']['posttype'] = array(
			'type'        => 'select',
			'title'       => esc_html__( 'Post Type Filter', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Choose post type as filter.', 'jeg-elementor-kit' ),
			'options'     => jkit_get_public_post_type(),
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'singular',
				),
				array(
					'field'    => 'singular',
					'operator' => 'in',
					'value'    => array( '', 'singular' ),
				),
			),
			'default'     => 'post',
		);

		$fields['default']['singular_post'] = array(
			'type'        => 'select-search',
			'multiple'    => 100,
			'title'       => esc_html__( 'Include Post / Page', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Write post or page name to search.', 'jeg-elementor-kit' ),
			'onSearch'    => 'searchPostsOrPage',
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'singular',
				),
				array(
					'field'    => 'singular',
					'operator' => 'in',
					'value'    => array( '', 'singular' ),
				),
			),
			'default'     => '',
		);

		$fields['default']['singular_taxonomy'] = array(
			'type'        => 'select-search',
			'multiple'    => 100,
			'title'       => esc_html__( 'Terms Name', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Write terms name (Ex: category name, tag name, etc) to search.', 'jeg-elementor-kit' ),
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'singular',
				),
				array(
					'field'    => 'singular',
					'operator' => 'in',
					'value'    => array( '', 'singular' ),
				),
			),
			'onSearch'    => 'searchTaxonomies',
			'default'     => '',
		);

		$fields['default']['singular_author'] = array(
			'type'        => 'select-search',
			'multiple'    => 100,
			'title'       => esc_html__( 'Author Name', 'jeg-elementor-kit' ),
			'description' => esc_html__( 'Write the author name to search.', 'jeg-elementor-kit' ),
			'nonce'       => jkit_create_global_nonce( 'dashboard' ),
			'onSearch'    => 'searchAuthors',
			'dependency'  => array(
				array(
					'field'    => 'location',
					'operator' => '===',
					'value'    => 'singular',
				),
				array(
					'field'    => 'singular',
					'operator' => 'in',
					'value'    => array( '', 'singular' ),
				),
			),
			'default'     => '',
		);

		if ( jkit_is_multilanguage() ) {
			$fields['default']['language'] = array(
				'type'        => 'select',
				'title'       => esc_html__( 'Language', 'jeg-elementor-kit' ),
				'description' => esc_html__( 'Select the language for the template.', 'jeg-elementor-kit' ),
				'options'     => call_user_func(
					function () {
						$languages = jkit_get_languages();
						$options   = array( '' => esc_html__( 'All Language', 'jeg-elementor-kit' ) );

						foreach ( $languages as $locale => $language ) {
							$options[ $locale ] = isset( $language['name'] ) ? $language['name'] : $language['native_name'];
						}

						return $options;
					}
				),
				'default'     => '',
			);
		}

		return apply_filters( 'jkit_template_condition_fields', $fields );
	}

	/**
	 *  Get Theme builder descriptions.
	 *
	 * @return array
	 */
	public function get_theme_builder_desc() {
		$header_publish_desc = defined( 'JEG_KIT_PRO' )
			? __( 'These are your active Header Templates. You can create multiple header and drag them to reorder.', 'jeg-elementor-kit' )
			: __( 'These are your active Header Templates. You can create multiple header and drag them to reorder. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );
		$header_empty_desc   = defined( 'JEG_KIT_PRO' )
			? __( 'Add Header Templates to use them across your website. You can create multiple header and select where to use them.', 'jeg-elementor-kit' )
			: __( 'Add Header Templates to use them across your website. You can create multiple header and select where to use them. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );
		$footer_publish_desc = defined( 'JEG_KIT_PRO' )
			? __( 'These are your active Footer Templates. You can create multiple footer and drag them to reorder.', 'jeg-elementor-kit' )
			: __( 'These are your active Footer Templates. You can create multiple footer and drag them to reorder. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );
		$footer_empty_desc   = defined( 'JEG_KIT_PRO' )
			? __( 'Add Footer Templates to use them across your website. You can create multiple footer and select where to use them.', 'jeg-elementor-kit' )
			: __( 'Add Footer Templates to use them across your website. You can create multiple footer and select where to use them. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );

		return array(
			self::$jkit_header  => array(
				'publish' => $header_publish_desc,
				'draft'   => __( 'These are your unused Header Templates. You can deletes or activate header template from this list', 'jeg-elementor-kit' ),
				'empty'   => $header_empty_desc,
			),
			self::$jkit_footer  => array(
				'publish' => $footer_publish_desc,
				'draft'   => __( 'These are your unused Footer Templates. You can deletes or activate footer template from this list', 'jeg-elementor-kit' ),
				'empty'   => $footer_empty_desc,

			),
			self::$jkit_post    => array(
				'publish' => __( 'These are your active Single Post Templates. You can create multiple activate and drag them to reorder.', 'jeg-elementor-kit' ),
				'draft'   => __( 'These are your unused Single Post Templates. You can deletes or activate Single Post Templates from this list', 'jeg-elementor-kit' ),
				'empty'   => __( 'Add single post template to use them across your website. You can create multiple Single Post Templates and select where to use them.', 'jeg-elementor-kit' ),

			),
			self::$jkit_product => array(
				'publish' => __( 'These are your active Single Product Templates. You can create multiple single product template and drag them to reorder.', 'jeg-elementor-kit' ),
				'draft'   => __( 'These are your unused Single Product Templates. You can deletes or activate single product template from this list', 'jeg-elementor-kit' ),
				'empty'   => __( 'Add single Product Templates to use them across your website. You can create multiple single product template and select where to use them.', 'jeg-elementor-kit' ),

			),
			self::$jkit_archive => array(
				'publish' => __( 'These are your active Archive Templates. You can create multiple archive template and drag them to reorder.', 'jeg-elementor-kit' ),
				'draft'   => __( 'These are your unused Archive Templates. You can deletes or activate archive template from this list', 'jeg-elementor-kit' ),
				'empty'   => __( 'Add Archive Templates to use them across your website. You can create multiple archive template and select where to use them.', 'jeg-elementor-kit' ),

			),
			self::$jkit_popup   => array(
				'publish' => __( 'These are your active Popup Templates. You can create multiple popup and drag them to reorder.', 'jeg-elementor-kit' ),
				'draft'   => __( 'These are your unused Popup Templates. You can deletes or activate popup template from this list', 'jeg-elementor-kit' ),
				'empty'   => __( 'Add Popup Templates to use them across your website. You can create multiple popup template and select where to use them.', 'jeg-elementor-kit' ),
			),
		);
	}

	/**
	 * Get Formated Font List
	 */
	public function get_theme_builder_menus() {
		$menu = array(
			array(
				'menu'    => 'settings',
				'submenu' => array(
					array(
						'title' => 'Global Style',
						'slug'  => 'global-style',
					),
					array(
						'title' => 'User Data',
						'slug'  => 'user-data',
					),
				),
			),
			array(
				'menu'    => 'templates',
				'submenu' => array(
					array(
						'title' => 'Header',
						'slug'  => self::$jkit_header,
					),
					array(
						'title' => 'Footer',
						'slug'  => self::$jkit_footer,
					),
					array(
						'title' => 'Not Found 404',
						'slug'  => 'jkit-404',
					),
					array(
						'title' => 'Single Post',
						'slug'  => self::$jkit_post,
					),
					array(
						'title' => 'Archive',
						'slug'  => self::$jkit_archive,
					),
					array(
						'title' => 'Popup',
						'slug'  => self::$jkit_popup,
					),
				),
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$menu[1] = array(
				'menu'    => 'templates',
				'submenu' => array(
					array(
						'title' => 'Header',
						'slug'  => self::$jkit_header,
					),
					array(
						'title' => 'Footer',
						'slug'  => self::$jkit_footer,
					),
					array(
						'title' => 'Not Found 404',
						'slug'  => 'jkit-404',
					),
					array(
						'title' => 'Single Post',
						'slug'  => self::$jkit_post,
					),
					array(
						'title' => 'Single Product',
						'slug'  => self::$jkit_product,
					),
					array(
						'title' => 'Archive',
						'slug'  => self::$jkit_archive,
					),
					array(
						'title' => 'Popup',
						'slug'  => self::$jkit_popup,
					),

				),
			);
		}

		return apply_filters( 'jkit_theme_builder_menus', $menu );
	}


	/**
	 * Gutenverse categories
	 *
	 * @return array
	 */
	public function jkit_element_categories() {
		$categories = apply_filters(
			'jkit_element_categories',
			array(
				'jkit-elements' => esc_html__( 'Jeg Kit Elements', 'jeg-elementor-kit' ),
				'jkit-post'     => esc_html__( 'Jeg Kit Post', 'jeg-elementor-kit' ),
			)
		);
		if ( class_exists( 'WooCommerce' ) ) {
			$categories['jkit-woo'] = esc_html__( 'Jeg Kit WooCommerce', 'jeg-elementor-kit' );
		}
		$categories = array_map(
			function ( $slug, $title ) {
				return array(
					'slug'  => $slug,
					'title' => $title,
				);
			},
			array_keys( $categories ),
			$categories
		);

		return $categories;
	}

	/**
	 * Type List
	 *
	 * @return array
	 */
	public static function post_type_list() {
		return array(
			self::$jkit_header   => array(
				'label' => esc_html__( 'Jeg Kit - Header ', 'jeg-elementor-kit' ),
			),
			self::$jkit_footer   => array(
				'label' => esc_html__( 'Jeg Kit - Footer ', 'jeg-elementor-kit' ),
			),
			self::$jkit_template => array(
				'label' => esc_html__( 'Jeg Kit - Template ', 'jeg-elementor-kit' ),
			),
		);
	}

	/**
	 * Get option info
	 *
	 * @return array
	 */
	private function get_option_info() {
		$option_info = array(
			'JColorPrimary'          => array(
				'title'       => 'Primary Color',
				'description' => 'This dominant color is used throughout most of website.',
			),
			'JColorSecondary'        => array(
				'title'       => 'Secondary Color',
				'description' => 'This color is used for hover effects or secondary design on website.',
			),
			'JColorText'             => array(
				'title'       => 'Text Color',
				'description' => 'This color used for all text elements on the website.',
			),
			'JColorAccent'           => array(
				'title'       => 'Accent Color',
				'description' => 'This color is used to highlight important elements on the website.',
			),
			'JColorTertiary'         => array(
				'title'       => 'Tertiary Color',
				'description' => 'Create nuances and variations to make the appearance more interesting.',
			),
			'JColorMeta'             => array(
				'title'       => 'Meta Color',
				'description' => 'Basically this color is used in meta (date, author, etc.).',
			),
			'JColorBorder'           => array(
				'title'       => 'Border Color',
				'description' => 'Create a more beautiful border and make your elements clearer.',
			),
			'JFontPrimary'           => array(
				'title'       => 'Primary',
				'description' => 'This font will be used for the title or headline on your web page.',
			),
			'JFontSecondary'         => array(
				'title'       => 'Secondary',
				'description' => 'This font is used to provide emphasis such as keywords on a web page.',
			),
			'JFontText'              => array(
				'title'       => 'Body',
				'description' => 'This is the font used for paragraph and main content on websites.',
			),
			'JFontAccent'            => array(
				'title'       => 'Accent',
				'description' => 'This font provides interesting design variations and contrasts with body.',
			),
			'JFontTextMenu'          => array(
				'title'       => 'Text Menu',
				'description' => 'This font is used specifically for menu elements on website.',
			),
			'JFontTextButton'        => array(
				'title'       => 'Text Button',
				'description' => 'This font is used specifically for all button elements on the website.',
			),
			'JFontTextHero'          => array(
				'title'       => 'Text Hero',
				'description' => 'Generally, this font is used in the hero description section.',
			),
			'JFontTextFooter'        => array(
				'title'       => 'Text Footer',
				'description' => 'The font used in footers that contain additional information, important links, or copyright.',
			),
			'JFontBlogTitle'         => array(
				'title'       => 'Blog Title',
				'description' => 'This font is used to create a special visual identity for your blog.',
			),
			'JFontIconBoxTitle'      => array(
				'title'       => 'Icon Box Title',
				'description' => 'Refers to the font used for titles or labels that usually accompany icons.',
			),
			'JFontPricingTitle'      => array(
				'title'       => 'Pricing Title',
				'description' => 'Used to display price information, packages, or cost details on a website.',
			),
			'JFontStepTitle'         => array(
				'title'       => 'Step Title',
				'description' => 'This font is used for stages, tutorials or processes explained on a website.',
			),
			'JTypographyBody'        => array(
				'title'       => 'Body',
				'description' => 'The aim is to create an easy-to-read text display on the website.',
			),
			'JTypographyLink'        => array(
				'title'       => 'Link',
				'description' => 'Used for links that have not been or have been clicked.',
			),
			'JTypographyH1'          => array(
				'title'       => 'H1',
				'description' => 'Used to determine the main title of a web page.',
			),
			'JTypographyH2'          => array(
				'title'       => 'H2',
				'description' => 'Used to determine a title that is hierarchically lower than H1.',
			),
			'JTypographyH3'          => array(
				'title'       => 'H3',
				'description' => 'Used to determine the heading that is below H2 in the hierarchy.',
			),
			'JTypographyH4'          => array(
				'title'       => 'H4',
				'description' => 'Used to determine the heading that is below H3 in the hierarchy.',
			),
			'JTypographyH5'          => array(
				'title'       => 'H5',
				'description' => 'Used to determine the heading that is below H4 in the hierarchy.',
			),
			'JTypographyH6'          => array(
				'title'       => 'H6',
				'description' => 'Used for the most specific subheadings or title elements.',
			),
			'JButtonsTypography'     => array(
				'title'       => 'Typography',
				'description' => 'Set the typography style for your button font.',
			),
			'JButtonsTextShadow'     => array(
				'title'       => 'Text Shadow',
				'description' => 'Increase the variety and aesthetics of the text on your button.',
			),
			'JButtonsPadding'        => array(
				'title'       => 'Padding',
				'description' => 'Set additional spaces to create a better, more balanced appearance of the button.',
			),
			'JButtonsTextColor'      => array(
				'title'       => 'Text Color',
				'description' => 'Set the color to use for the text inside the button.',
			),
			'JButtonsBackground'     => array(
				'title'       => 'Background',
				'description' => 'Set the visual effect by determining the color of the background.',
			),
			'JButtonsBoxShadow'      => array(
				'title'       => 'Box Shadow',
				'description' => 'Creates a shadow or penumbra around the button.',
			),
			'JButtonsBorderType'     => array(
				'title'       => 'Border',
				'description' => 'Create prettier borders and make your button clearer.',
			),
			'JImagesOpacity'         => array(
				'title'       => 'Opacity',
				'description' => 'Set the transparency effect to a value of 0 or 1 on the image.',
			),
			'JImagesBoxShadow'       => array(
				'title'       => 'Box Shadow',
				'description' => 'Creates a shadow or penumbra around the image.',
			),
			'JImagesCSSFilter'       => array(
				'title'       => 'CSS Filter',
				'description' => 'It is used to apply visual effects, such as grayscale, blur and saturation.',
			),
			'JImagesHoverTransition' => array(
				'title'       => 'Hover Transition',
				'description' => 'Hover transition time.',
			),
			'JImagesBorder'          => array(
				'title'       => 'Border',
				'description' => 'Create prettier borders and make your image clearer.',
			),
			'JFormLabelTypography'   => array(
				'title'       => 'Label Typography',
				'description' => 'Set typography and color on your form label.',
			),
			'JFormTypography'        => array(
				'title'       => 'Text Field',
				'description' => 'Set typography to enter certain information into a form.',
			),
			'JFormTextColor'         => array(
				'title'       => 'Text Color',
				'description' => 'Increase the variety and aesthetics of the text on your form.',
			),
			'JFormAccentColor'       => array(
				'title'       => 'Accent Color',
				'description' => 'This color is used to highlight important elements on the form.',
			),
			'JFormBackgroundColor'   => array(
				'title'       => 'Background',
				'description' => 'Set the background color of the form to make it more attractive.',
			),
			'JFormBoxShadow'         => array(
				'title'       => 'Box Shadow',
				'description' => 'Creates a shadow or penumbra around the form.',
			),
			'JFormBorderType'        => array(
				'title'       => 'Border Type',
				'description' => 'Create prettier borders and make your form clearer.',
			),
			'JFormPadding'           => array(
				'title'       => 'Padding',
				'description' => 'Set additional spaces to create a better, more balanced appearance of the form.',
			),
			'JSiteName'              => array(
				'title'       => 'Site Name',
				'description' => 'Start branding by giving your website a unique name.',
			),
			'JSiteDescription'       => array(
				'title'       => 'Site Description',
				'description' => 'This description can help the website appear in relevant search results.',
			),
			'JSiteLogo'              => array(
				'title'       => 'Logo Site',
				'description' => 'Set the default logo.',
			),
			'JSiteFavico'            => array(
				'title'       => 'Favicon Site',
				'description' => 'Set icons for browser tabs. Recommended size: 80 pixels x 80 pixels.',
			),
			'JLayoutContentWidth'    => array(
				'title'       => 'Content Width Layout',
				'description' => 'Arrange the layout to limit the width of the content to maintain readability.',
			),
			'JLayoutWidgetsSpace'    => array(
				'title'       => 'Content Space Layout',
				'description' => 'Arrange this layout to create an open, clean look.',
			),
			'JLayoutTitleSelector'   => array(
				'title'       => 'Title Selector Layout',
				'description' => 'Create a visual hierarchy with the title as the main focus.',
			),
			'JLayoutStretchSection'  => array(
				'title'       => 'Stretch Section Layout',
				'description' => 'This is to fill the entire width of the screen with specific content.',
			),
			'JLayoutPageLayout'      => array(
				'title'       => 'Page Layout',
				'description' => 'Set the layout to arrange the elements more organized.',
			),
			'JLayoutBreakpoints'     => array(
				'title'       => 'Active Breakpoints',
				'description' => 'Arrange and adjust the display on various screen sizes.',
			),
			'JBackgroundBackground'  => array(
				'title'       => 'Background',
				'description' => 'Choice of website background color to make it more attractive.',
			),
			'JBackgroundMobile'      => array(
				'title'       => 'Mobile Browser Background',
				'description' => 'Set the mobile background color on your website.',
			),
			'JCodeCSS'               => array(
				'title'       => 'Custom CSS',
				'description' => 'Take full control Customize your website design in greater detail with custom CSS.',
			),
			'JCodeJSHead'            => array(
				'title'       => 'Custom JS Header',
				'description' => 'Add functionality to your web header with custom JavaScript.',
			),
			'JCodeJSFoot'            => array(
				'title'       => 'Custom JS Footer',
				'description' => 'Add functionality to your web footer with custom JavaScript.',
			),
			'JAdditionalCursor'      => array(
				'title'       => 'Custom Cursor',
				'description' => 'Enable the Custom Cursor feature. To turn it off, simply disable this option.',
			),
		);

		return $option_info;
	}

	/**
	 * Get Formated Font List
	 */
	public function get_theme_option_menu() {
		$menu = array(
			array(
				'menu'    => 'DESIGN SYSTEM',
				'submenu' => array(
					array(
						'title' => 'Global Colors',
						'slug'  => 'global-colors',
					),
					array(
						'title' => 'Global Fonts',
						'slug'  => 'global-fonts',
					),
				),
			),
			array(
				'menu'    => 'THEME STYLE',
				'submenu' => array(
					array(
						'title' => 'Typography',
						'slug'  => 'typography',
					),
					array(
						'title' => 'Buttons',
						'slug'  => 'buttons',
					),
					array(
						'title' => 'Images',
						'slug'  => 'images',
					),
					array(
						'title' => 'Forms',
						'slug'  => 'forms',
					),
				),
			),
			array(
				'menu'    => 'SETTINGS',
				'submenu' => array(
					array(
						'title' => 'Site Identity',
						'slug'  => 'site-identity',
					),
					array(
						'title' => 'Layout',
						'slug'  => 'layout',
					),
					array(
						'title' => 'Background',
						'slug'  => 'background',
					),
					array(
						'title' => 'Custom Code',
						'slug'  => 'custom-code',
					),
					array(
						'title' => 'Additional Settings',
						'slug'  => 'additional-setting',
					),
				),
			),
		);

		return apply_filters( 'essential_get_theme_option_menu', $menu );
	}

	/**
	 * System Status.
	 *
	 * @return array
	 */
	public function system_status() {
		$status = array();

		/** Themes */
		$theme                    = wp_get_theme();
		$parent                   = wp_get_theme( get_template() );
		$status['theme_name']     = $theme->get( 'Name' );
		$status['theme_version']  = $theme->get( 'Version' );
		$status['is_child_theme'] = is_child_theme();
		$status['parent_theme']   = $parent->get( 'Name' );
		$status['parent_version'] = $parent->get( 'Version' );

		/** WordPress Environment */
		$wp_upload_dir              = wp_upload_dir();
		$status['home_url']         = home_url( '/' );
		$status['site_url']         = site_url();
		$status['login_url']        = wp_login_url();
		$status['wp_version']       = get_bloginfo( 'version', 'display' );
		$status['is_multisite']     = is_multisite();
		$status['wp_debug']         = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$status['memory_limit']     = ini_get( 'memory_limit' );
		$status['wp_memory_limit']  = WP_MEMORY_LIMIT;
		$status['wp_language']      = get_locale();
		$status['writeable_upload'] = wp_is_writable( $wp_upload_dir['basedir'] );
		$status['count_category']   = wp_count_terms( 'category' );
		$status['count_tag']        = wp_count_terms( 'post_tag' );

		/** Server Environment */
		$remote     = wp_remote_get( esc_url( rest_url() ) );
		$gd_support = array();

		if ( function_exists( 'gd_info' ) ) {
			foreach ( gd_info() as $key => $value ) {
				$gd_support[ $key ] = $value;
			}
		}

		$status['server_info']        = 'test';
		$status['php_version']        = PHP_VERSION;
		$status['post_max_size']      = ini_get( 'post_max_size' );
		$status['max_input_vars']     = ini_get( 'max_input_vars' );
		$status['max_execution_time'] = ini_get( 'max_execution_time' );
		$status['suhosin']            = extension_loaded( 'suhosin' );
		$status['imagick']            = extension_loaded( 'imagick' );
		$status['gd']                 = extension_loaded( 'gd' ) && function_exists( 'gd_info' );
		$status['gd_webp']            = extension_loaded( 'gd' ) && $gd_support['WebP Support'];
		$status['fileinfo']           = extension_loaded( 'fileinfo' ) && ( function_exists( 'finfo_open' ) || function_exists( 'mime_content_type' ) );
		$status['curl']               = extension_loaded( 'curl' ) && function_exists( 'curl_version' );
		$status['wp_remote_get']      = ! is_wp_error( $remote ) && $remote['response']['code'] >= 200 && $remote['response']['code'] < 300;

		/** Plugins */
		$status['plugins'] = $this->data_active_plugin();

		return $status;
	}

	/**
	 * Data active plugin
	 *
	 * @return array
	 */
	public function data_active_plugin() {
		$active_plugin = array();

		$plugins = array_merge(
			array_flip( (array) get_option( 'active_plugins', array() ) ),
			(array) get_site_option( 'active_sitewide_plugins', array() )
		);

		$plugins = array_intersect_key( get_plugins(), $plugins );

		if ( count( $plugins ) > 0 ) {
			foreach ( $plugins as $plugin ) {
				$item                = array();
				$item['uri']         = isset( $plugin['PluginURI'] ) ? esc_url( $plugin['PluginURI'] ) : '#';
				$item['name']        = isset( $plugin['Name'] ) ? $plugin['Name'] : esc_html__( 'unknown', 'jeg-elementor-kit' );
				$item['author_uri']  = isset( $plugin['AuthorURI'] ) ? esc_url( $plugin['AuthorURI'] ) : '#';
				$item['author_name'] = isset( $plugin['Author'] ) ? $plugin['Author'] : esc_html__( 'unknown', 'jeg-elementor-kit' );
				$item['version']     = isset( $plugin['Version'] ) ? $plugin['Version'] : esc_html__( 'unknown', 'jeg-elementor-kit' );

				$content = esc_html__( 'by', 'jeg-elementor-kit' );

				$active_plugin[] = array(
					'type'            => 'status',
					'title'           => $item['name'],
					'content'         => $content,
					'link'            => $item['author_uri'],
					'link_text'       => $item['author_name'],
					'additional_text' => $item['version'],
				);
			}
		}

		return $active_plugin;
	}

	/**
	 * Get List Of Installed Plugin.
	 *
	 * @return array
	 */
	public static function list_plugin() {
		$plugins = array();
		$active  = array();

		foreach ( get_option( 'active_plugins' ) as $plugin ) {
			$active[] = explode( '/', $plugin )[0];
		}

		foreach ( get_plugins() as $key => $plugin ) {
			$slug             = explode( '/', $key )[0];
			$data             = array();
			$data['active']   = in_array( $slug, $active, true );
			$data['version']  = $plugin['Version'];
			$data['name']     = $plugin['Name'];
			$data['path']     = str_replace( '.php', '', $key );
			$plugins[ $slug ] = $data;
		}

		return $plugins;
	}

	/**
	 * Get all text
	 *
	 * @return array
	 */
	private function get_all_text() {
		return array(
			'general' => array(
				'get_started' => __( 'Get Started', 'jeg-elementor-kit' ),
			),
		);
	}

	/**
	 * Load dashboard page
	 */
	public function dashboard_page() {
		?>
		<div id="jkit-admin-dashboard">
		</div>
		<div id="jkit-dashboard-import"></div>
		<?php
	}

	/**
	 * Get URL to Elementor Builder
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string
	 */
	public static function editor_url( $post_id ) {
		$the_id = ( strlen( $post_id ) > 0 ? $post_id : get_the_ID() );

		$parameter = array(
			'post'   => $the_id,
			'action' => 'elementor',
		);

		return admin_url( 'post.php' ) . '?' . build_query( $parameter );
	}
}
