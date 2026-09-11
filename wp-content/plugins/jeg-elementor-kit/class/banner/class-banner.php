<?php
/**
 * Jeg Kit Banner Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 2.5.5
 */

namespace Jeg\Elementor_Kit\Banner;

use Jeg\Elementor_Kit\Init;

/**
 * Class Banner
 *
 * @package jeg-kit
 */
class Banner {
	/**
	 * Option Name.
	 *
	 * @var string
	 */
	private $option_name = 'jkit_banner_active_time';

	/**
	 * Option Name.
	 *
	 * @var string
	 */
	private $key_upgrade_to_pro = 'jkit_banner_upgrade_to_pro';

	/**
	 * Template slug
	 *
	 * @var string
	 */
	private $template_slug = 'templates/banner/';

	/**
	 * Class instance
	 *
	 * @var Element
	 */
	private static $instance;

	/**
	 * Init constructor.
	 */
	public function __construct() {
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'metform-menu-settings', 'metform_wpmet_plugins' ) ) ) {
			add_action( 'in_admin_header', array( $this, 'notice' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'notice' ) );
		}
		add_action( 'wp_ajax_jkit_notice_banner_close', array( $this, 'close' ) );
		add_action( 'wp_ajax_jkit_notice_banner_review', array( $this, 'review' ) );
		add_action( 'wp_ajax_jkit_notice_banner_upgrade_close', array( $this, 'close_banner_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Get class instance
	 *
	 * @return Banner
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Enqueue Script.
	 */
	public function enqueue_scripts() {
		if ( $this->can_render_notice() || $this->can_render_upgrade_to_pro_banner() ) {
			wp_enqueue_script( 'jkit-notice-banner', JEG_ELEMENTOR_KIT_URL . '/assets/js/admin/notice-banner.js', array( 'jquery' ), JEG_ELEMENTOR_KIT_VERSION, true );
			wp_enqueue_style( 'jkit-notice-banner', JEG_ELEMENTOR_KIT_URL . '/assets/css/admin/notice-banner.css', array(), JEG_ELEMENTOR_KIT_VERSION );
		}
	}

	/**
	 * Register Active Time.
	 */
	public function register_active_banner() {
		$option = get_option( $this->option_name, true );

		if ( 'review' !== $option && (bool) $option ) {
			update_option( $this->option_name, true );
		}

		update_option( $this->key_upgrade_to_pro, true );
	}

	/**
	 * Get Second by days.
	 *
	 * @param int $days Days Number.
	 *
	 * @return int
	 */
	public function get_second( $days ) {
		return $days * 24 * 60 * 60;
	}

	/**
	 * Check if we can render notice.
	 */
	public function can_render_notice() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		$option = get_option( $this->option_name );

		if ( 'review' === $option ) {
			return false;
		}

		return (bool) $option;
	}

	/**
	 * Check if we can render banner upgrade to pro.
	 */
	public function can_render_upgrade_to_pro_banner() {
		if ( ! current_user_can( 'edit_theme_options' ) || defined( 'JEG_KIT_PRO' ) ) {
			return false;
		}

		$option = get_option( $this->key_upgrade_to_pro, 'none' );

		if ( 'none' === $option ) {
			update_option( $this->key_upgrade_to_pro, true );

			return true;
		}

		if ( is_numeric( $option ) ) {
			return time() >= (int) $option;
		}

		if ( false === $option ) {
			update_option( $this->key_upgrade_to_pro, time() + $this->get_second( 14 ) );
		}

		return (bool) $option;
	}

	/**
	 * Close Button Clicked.
	 */
	public function close() {
		update_option( $this->option_name, false );
		wp_send_json_success();
	}

	/**
	 * Close Button Clicked.
	 */
	public function close_banner_upgrade() {
		update_option( $this->key_upgrade_to_pro, time() + $this->get_second( 14 ) );

		wp_send_json_success();
	}

	/**
	 * Review Button Clicked.
	 */
	public function review() {
		update_option( $this->option_name, 'review' );
		wp_send_json_success();
	}

	/**
	 * Show Notice.
	 */
	public function notice() {
		if ( $this->can_render_notice() ) {
			jkit_get_template_part( $this->template_slug . 'notice-banner' );
		}

		if ( $this->can_render_upgrade_to_pro_banner() ) {
			jkit_get_template_part( $this->template_slug . 'upgrade-to-pro' );
		}
	}
}
