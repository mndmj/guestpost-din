<?php
/**
 * Wizard Class.
 *
 * @package jeg-elementor-kit
 * @author jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Wizard;

/**
 * Class  Wizard.
 *
 * @package jeg-elementor-kit
 */
class Wizard {

	/**
	 * Class instance
	 *
	 * @var Element
	 */
	private static $instance;

	/**
	 * Onboard Action Slug
	 *
	 * @var string
	 */
	public static $onboard = 'jeg-kit-onboarding-wizard';

	/**
	 * Upgrade_Wizard constructor.
	 */
	public function __construct() {
		add_action( 'admin_action_' . self::$onboard, array( $this, 'onboard_wizard_page' ) );
		add_action( 'init', array( $this, 'set_onboard_wizard_page' ), 99 );
	}

	/**
	 * Get class instance
	 *
	 * @return Wizard
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Ge Upgrader Option Name.
	 *
	 * @return string.
	 */
	public function get_onboard_wizard_name() {
		return 'jkit_wizard_action_complete';
	}

	/**
	 * Set Hooks
	 */
	public function set_hook() {
		add_filter( 'show_admin_bar', '__return_false' );

		// Remove all HTML related WordPress actions.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_print_styles' );
		remove_all_actions( 'wp_print_head_scripts' );
		remove_all_actions( 'wp_footer' );

		// Enqueue Script.
		add_action( 'wp_head', 'wp_enqueue_scripts', 1 );
		add_action( 'wp_head', 'wp_print_styles', 8 );
		add_action( 'wp_head', 'wp_print_head_scripts', 9 );
		add_action( 'wp_head', 'wp_site_icon' );

		// Handle `wp_footer`.
		add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
		add_action( 'wp_footer', 'wp_auth_check_html', 30 );

		// Handle `wp_enqueue_scripts`.
		remove_all_actions( 'wp_enqueue_scripts' );
		add_filter( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 999999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 999999 );
	}

	/**
	 * Enqueue Scripts
	 */
	public function enqueue_scripts() {

		wp_enqueue_script(
			'jkit-wizard',
			JEG_ELEMENTOR_KIT_URL . '/assets/js/admin/wizard.js',
			array( 'lodash', 'react', 'react-dom', 'regenerator-runtime', 'wp-api-fetch', 'wp-data', 'wp-hooks', 'wp-i18n', 'wp-notices', 'jkit-core-control' ),
			JEG_ELEMENTOR_KIT_VERSION,
			true
		);

		wp_localize_script(
			'jkit-wizard',
			'jkitWizard',
			$this->wizard_config()
		);

		do_action( 'wizard_enqueue_scripts', 'wizard' );
	}

	/**
	 * Wizard Config
	 *
	 * @return array
	 */
	public function wizard_config() {
		$config                 = array();
		$config['adminUrl']     = admin_url();
		$config['dashboard']    = admin_url( 'admin.php?page=jkit' );
		$config['ajaxurl']      = admin_url( 'admin-ajax.php' );
		$config['installNonce'] = wp_create_nonce( 'updates' );
		$config['imageUrl']     = JEG_ELEMENTOR_KIT_URL . '/assets/img/admin';
		$config['bannerData']   = jkit_get_banner_data();
		$config['bannerNonce']  = wp_create_nonce( 'jkit-banner' );
		return $config;
	}

	/**
	 * Enqueue Style
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'jkit-backend-font',
			'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&amp;family=Roboto:wght@400;500;600&amp;display=swap',
			array(),
			JEG_ELEMENTOR_KIT_VERSION
		);

		wp_enqueue_style(
			'jkit-wizard',
			JEG_ELEMENTOR_KIT_URL . '/assets/css/admin/wizard.css',
			array(),
			JEG_ELEMENTOR_KIT_VERSION
		);
	}

	/**
	 * Wizard Page.
	 *
	 * @throws \Exception Throw exception.
	 */
	public function onboard_wizard_page() {

		// if ( get_option( 'jkit_wizard_action_complete' ) || ! current_user_can( 'manage_options' ) ) {
		// wp_die( 'You are not allowed to access this page.', 'Access Denied', array( 'response' => 403 ) );
		// }
		try {
			if ( isset( $_REQUEST['nonce'] ) && wp_verify_nonce( sanitize_key( $_REQUEST['nonce'] ), self::$onboard ) ) {
				// Nanti implement.
			}

			if ( ! current_user_can( 'install_plugins' ) ) {
				throw new \Exception( 'Access denied', 403 );
			}

			header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
			$this->set_hook();
			$this->render_onboard_wizard();
			exit();
		} catch ( \Exception $e ) {
			echo wp_kses( $e->getMessage(), wp_kses_allowed_html() );
		}
	}

	/**
	 * Render Onboard Wizard.
	 */
	public function render_onboard_wizard() {
		?>
			<!DOCTYPE html>
			<html <?php language_attributes(); ?>>
			<head>
				<meta charset="utf-8"/>
				<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
				<title><?php esc_html_e( 'Welcome to Jeg Kit', 'jkit' ); ?></title>
					<?php wp_head(); ?>
			</head>
			<body>
			<div id="jkit-onboard-wizard" ></div>
					<?php
					wp_footer();
					/** This action is documented in wp-admin/admin-footer.php */
					do_action( 'admin_print_footer_scripts' );
					?>
			</body>
			</html>
		<?php
	}

	/**
	 * Check if plugin is installed.
	 *
	 * @param string $plugin_slug plugin slug.
	 *
	 * @return boolean
	 */
	public function is_installed( $plugin_slug ) {
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$plugin_dir = dirname( $plugin_file );

			if ( $plugin_dir === $plugin_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set form split option meta
	 */
	public function set_onboard_wizard_page() {
		$flag = get_option( $this->get_onboard_wizard_name() );

		if ( ! $flag && current_user_can( 'manage_options' ) ) {
			add_option( $this->get_onboard_wizard_name(), true );

			wp_safe_redirect( admin_url( 'admin.php?action=jeg-kit-onboarding-wizard' ) );
		}
	}
}
