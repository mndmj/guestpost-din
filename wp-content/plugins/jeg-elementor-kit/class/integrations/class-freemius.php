<?php
/**
 * Freemius integration class
 *
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Integrations;

/**
 * Class Freemius
 *
 * @package Jeg\Elementor_Kit\Integrations
 */
class Freemius {
	/**
	 * Instance
	 *
	 * @var Freemius|null
	 */
	private static $instance;

	/**
	 * Freemius SDK instance
	 *
	 * @var mixed
	 */
	private $fs = null;

	/**
	 * Freemius product slug
	 *
	 * @var string
	 */
	private $fs_product = 'jeg-elementor-kit';

	/**
	 * Get instance
	 *
	 * @return Freemius
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
		$this->init();
	}

	/**
	 * Initialize Freemius SDK
	 */
	public function init() {
		if ( ! defined( 'WP_FS__PRODUCT_26060_MULTISITE' ) ) {
			define( 'WP_FS__PRODUCT_26060_MULTISITE', true );
		}

		if ( function_exists( 'fs_dynamic_init' ) ) {
			$this->fs = fs_dynamic_init( array(
				'id'                  => '26060',
				'slug'                => $this->fs_product,
				'premium_slug'        => 'jeg-kit-pro',
				'type'                => 'plugin',
				'public_key'          => 'pk_af50d7e3c7029cda93a28f0dd390b',
				'is_premium'          => false,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => true,
				'enable_anonymous'    => true,
				'anonymous_mode'      => true,
				'menu'                => array(
					'slug'        => 'jkit',
					'first-path'  => 'admin.php?action=jeg-kit-onboarding-wizard',
					'account'     => false,
					'contact'     => false,
					'support'     => false,
					'affiliation' => false,
					'addons'      => false,
					'pricing'     => false,
					'upgrade'     => false,
				),
			) );

			// Skip opt-in flow agar modal permintaan data tidak muncul.
			if ( $this->fs && ! $this->fs->is_registered() ) {
				$this->fs->skip_connection( true );
			}
		}

		do_action( 'jkit_fs_loaded' );
	}

	/**
	 * Get Freemius checkout / upgrade URL.
	 * Falls back to the pricing page on jegkit.com.
	 *
	 * @return string
	 */
	public function get_upgrade_url() {
		if ( $this->fs ) {
			return $this->fs->get_upgrade_url();
		}

		return JEG_ELEMENT_SERVER_URL . 'pricing';
	}

	/**
	 * Get Freemius pricing config data to be consumed by the React pricing page.
	 * Returns null if the Freemius SDK is not yet initialized.
	 *
	 * @return array|null
	 */
	public function get_pricing_config() {
		if ( ! $this->fs ) {
			return null;
		}

		$fs        = $this->fs;
		$timestamp = time();

		$context_params = array(
			'plugin_id'         => $fs->get_id(),
			'plugin_public_key' => $fs->get_public_key(),
			'plugin_version'    => $fs->get_plugin_version(),
		);

		$bundle_id = $fs->get_bundle_id();
		if ( ! is_null( $bundle_id ) ) {
			$context_params['bundle_id'] = $bundle_id;
		}

		if ( $fs->is_registered() ) {
			$context_params = array_merge( $context_params, \FS_Security::instance()->get_context_params(
				$fs->get_site(),
				$timestamp,
				'upgrade'
			) );
		} else {
			$context_params['home_url'] = home_url();
		}

		if ( $fs->is_payments_sandbox() ) {
			// Add sandbox secure token and full sandbox context parameters.
			// Some frontend integrations expect the full `s_ctx_*` context
			// (type/id/ts/secure) alongside the `sandbox` token so the
			// hosted checkout validates sandbox mode. Ensure we include
			// them even for anonymous/non-registered flows by deriving
			// context from the plugin entity.
			$context_params['sandbox'] = \FS_Security::instance()->get_secure_token(
				$fs->get_plugin(),
				$timestamp,
				'checkout'
			);
			// Merge explicit context params (s_ctx_type, s_ctx_id, s_ctx_ts, s_ctx_secure).
			$context_params = array_merge(
				$context_params,
				\FS_Security::instance()->get_context_params( $fs->get_plugin(), $timestamp, 'checkout' )
			);
		}

		$query_params = array_merge( $context_params, array(
			'next'             => $fs->_get_sync_license_url( false, false ),
			'plugin_version'   => $fs->get_plugin_version(),
			'billing_cycle'    => WP_FS__PERIOD_ANNUALLY,
			'is_network_admin' => fs_is_network_admin() ? 'true' : 'false',
			'currency'         => $fs->apply_filters( 'default_currency', 'usd' ),
			'discounts_model'  => $fs->apply_filters( 'pricing/discounts_model', 'absolute' ),
		) );

		return array_merge( array(
			'contact_url'            => $fs->contact_url(),
			'is_production'          => defined( 'WP_FS__IS_PRODUCTION_MODE' ) ? WP_FS__IS_PRODUCTION_MODE : null,
			'menu_slug'              => $fs->get_menu_slug(),
			'mode'                   => 'dashboard',
			'fs_wp_endpoint_url'     => WP_FS__ADDRESS,
			'request_handler_url'    => admin_url(
				'admin-ajax.php?' . http_build_query( array(
					'module_id' => $fs->get_id(),
					'action'    => $fs->get_ajax_action( 'pricing_ajax_action' ),
					'security'  => $fs->get_ajax_security( 'pricing_ajax_action' ),
				) )
			),
			'unique_affix'           => $fs->get_unique_affix(),
			'show_annual_in_monthly' => $fs->apply_filters( 'pricing/show_annual_in_monthly', true ),
			'license'                => $fs->has_active_valid_license() ? $fs->_get_license() : null,
			'plugin_icon'            => $fs->get_local_icon_url(),
			'disable_single_package' => $fs->apply_filters( 'pricing/disable_single_package', false ),
			// Coupon code to pre-fill in checkout. Override via WordPress filter:
			// add_filter( 'jeg-elementor-kit/pricing/default_coupon', fn() => 'YOUR_CODE' );
			'default_coupon'         => $fs->apply_filters( 'pricing/default_coupon', '' ),
			'client_site'            => home_url(),
			'client_theme'           => wp_get_theme()->get( 'Name' ),
			'client_theme_author'    => wp_get_theme()->get( 'Author' ),
			'client_theme_author_uri' => wp_get_theme()->get( 'AuthorURI' ),
		), $query_params );
	}

	/**
	 * Get live pricing data from Freemius API.
	 * Mirrors the SDK handler `_fs_pricing_ajax_action_handler` for `fetch_pricing_data`.
	 *
	 * @return array|null
	 */
	public function get_pricing_data() {
		if ( ! $this->fs ) {
			return null;
		}

		$fs            = $this->fs;
		$pricing_cfg   = $this->get_pricing_config();
		$bundle_id     = $fs->get_bundle_id();
		$bundle_pubkey = $fs->get_bundle_public_key();

		$params = array(
			'is_enriched' => true,
			'trial'       => false,
		);

		foreach ( array( 'sandbox', 's_ctx_type', 's_ctx_id', 's_ctx_ts', 's_ctx_secure' ) as $key ) {
			if ( ! empty( $pricing_cfg[ $key ] ) ) {
				$params[ $key ] = $pricing_cfg[ $key ];
			}
		}

		$has_bundle_context = ( \FS_Plugin::is_valid_id( $bundle_id ) && ! empty( $bundle_pubkey ) );

		if ( ! $has_bundle_context ) {
			$api = $fs->get_api_plugin_scope();
		} else {
			$api = \FS_Api::instance(
				$bundle_id,
				'plugin',
				$bundle_id,
				$bundle_pubkey,
				! $fs->is_live(),
				false,
				$fs->get_sdk_version()
			);

			$params['plugin_id']         = $fs->get_id();
			$params['plugin_public_key'] = $fs->get_public_key();
		}

		$cache_ttl = $this->get_pricing_api_cache_ttl();
		$result    = $api->get( $fs->add_show_pending( 'pricing.json?' . http_build_query( $params ) ), true, $cache_ttl );

		if ( ! is_object( $result ) || isset( $result->error ) ) {
			return null;
		}

		$data = json_decode( wp_json_encode( $result ), true );

		if ( ! is_array( $data ) || empty( $data['plans'] ) || ! is_array( $data['plans'] ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Get Freemius FS_Api pricing cache TTL in seconds.
	 *
	 * @return int
	 */
	protected function get_pricing_api_cache_ttl() {
		$default_ttl = 10 * MINUTE_IN_SECONDS;

		$ttl = (int) apply_filters( 'jeg-elementor-kit/pricing/fs_api_cache_ttl', $default_ttl );

		if ( $ttl < 0 ) {
			$ttl = 0;
		}

		return $ttl;
	}

}
