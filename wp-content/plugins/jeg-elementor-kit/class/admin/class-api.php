<?php
/**
 * Essential Framework API Class.
 *
 * @package jkit
 * @author jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Admin;

use Jeg\Elementor_Kit\Meta;
use Jeg\Elementor_Kit\Options\Settings;
use Jeg\Elementor_Kit\Integrations\Freemius;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API.
 *
 * @package jkit
 */
class Api {
	/**
	 * Class instance
	 *
	 * @var Api
	 */
	private static $instance;

	/**
	 * Endpoint Path
	 *
	 * @var string
	 */
	const ENDPOINT = 'jkit/v1';

	/**
	 * Hold demo slug when doing import
	 *
	 * @var string
	 */
	private $demo;

	/**
	 * Return class instance
	 *
	 * @return Api_Demos
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
		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		} else {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			// add_action( 'elementor/ajax/register_actions', array( $this, 'register_elementor_ajax_actions' ) );
		}
	}

	/**
	 * Register API
	 */
	public function register_routes() {

		add_filter( 'wp_doing_ajax', '__return_true' );

		register_rest_route(
			self::ENDPOINT,
			'updateOption',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_option' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'getGlobalStyle',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_global_style' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'getOption',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_option' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'pricing-modal-data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pricing_modal_data' ),
				'permission_callback' => array( $this, 'pricing_modal_permission_check' ),
			)
		);

		// UTM proxy: forward tracker requests to pro.jegkit.com to avoid exposing external URL in the client
		register_rest_route(
			self::ENDPOINT,
			'utm-proxy',
			array(
				'methods'  => 'POST',
				'callback' => array( $this, 'utm_proxy_handler' ),
				'permission_callback' => array( $this, 'utm_permission_check' ),
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'updateMailChimp',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_mailchimp' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'themeOptions',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_theme_options' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'getTemplateLists',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_template_lists' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'createTemplate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_template' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'cloneTemplate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clone_template' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'deleteTemplate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_template' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'updateTemplate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_template' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);
		register_rest_route(
			self::ENDPOINT,
			'updatePriority',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_template_priority' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'updateTemplateStatus',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_template_status' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'searchPostsOrPages',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_posts' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'searchTaxonomies',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_taxonomies' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'searchTaxonomiesByTitleAndId',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_taxonomies_by_title_and_id' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'searchPostsByTitleAndId',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_posts_by_title_and_id' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'searchAuthors',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'search_authors' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'getTemplateConditions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_template_conditions' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		/* coppyed enpoint from proficient */

		register_rest_route(
			self::ENDPOINT,
			'demo/get',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_demos' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'demo/categories',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_demos_categories' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'demos/getConfig',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_config' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'getContent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_content' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'managePlugin',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'install_plugin' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'manageContent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manage_content' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'manageImage',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_image' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'manageDemo',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manage_demo' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'manageFavorite',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'manage_favorite' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		// register_rest_route(
		// self::ENDPOINT,
		// 'resetLicense',
		// array(
		// 'methods'             => 'GET',
		// 'callback'            => 'essential_reset_license',
		// 'permission_callback' => array( $this, 'jkit_permission_check_admin' ),
		// )
		// );

		register_rest_route(
			self::ENDPOINT,
			'newsletter-subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'newsletter_subscribe_handler' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);
		/**
		 * Backend routes.
		 */

		register_rest_route(
			self::ENDPOINT,
			'demo/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'fetch_demo_data' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);
		register_rest_route(
			self::ENDPOINT,
			'demo/like-state',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_like_state' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);
		register_rest_route(
			self::ENDPOINT,
			'demo/import-state',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'demo_import_state' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		register_rest_route(
			self::ENDPOINT,
			'close-banner',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'close_banner_handler' ),
				'permission_callback' => 'jkit_permission_check_admin',
			)
		);

		add_filter( 'wp_doing_ajax', '__return_false' );
	}

	/**
	 * Get pricing modal data.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_pricing_modal_data() {
		return $this->response_success(
			array(
				'freemius'    => array(
					'pricing' => Freemius::instance()->get_pricing_config(),
				),
				'pricingPlan' => jkit_get_pricing_plan(),
				'imgDir'      => JEG_ELEMENTOR_KIT_URL . '/assets/img/',
				'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Check pricing modal data permission.
	 *
	 * @return bool|\WP_Error
	 */
	public function pricing_modal_permission_check() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new \WP_Error( 'rest_forbidden', esc_html__( 'You are not allowed to access this endpoint.', 'jeg-elementor-kit' ), array( 'status' => 403 ) );
	}

	/**
	 * Handler for close banner.
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function close_banner_handler( $request ) {
		if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-banner', false ) ) {
			return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		}

		set_transient( 'jkit_banner_closed', true, 7 * DAY_IN_SECONDS );
		return $this->response_success( esc_html__( 'Banner closed successfully.', 'jeg-elementor-kit' ) );
	}

	/**
	 * Method to search the posts by title or ID
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function search_posts( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }
		$search    = sanitize_text_field( $request->get_param( 'search' ) );
		$type      = sanitize_text_field( $request->get_param( 'type' ) );
		$include   = sanitize_text_field( $request->get_param( 'include' ) );
		$post_type = sanitize_text_field( $request->get_param( 'post_type' ) );
		$exclude   = $request->get_param( 'exclude' );
		global $wpdb;
		$posts_data = array();
		if ( 'search' === $type ) {
			if ( ! empty( $exclude ) ) {
				$exclude_ids_placeholder = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );
				$query                   = $wpdb->prepare(
					"
					SELECT ID, post_title
					FROM $wpdb->posts
					WHERE
					(post_type = %s OR post_type = 'page')
					AND post_status = %s
					AND
					(ID = %d OR post_title LIKE %s)
					AND ID NOT IN ($exclude_ids_placeholder)
					ORDER BY 
					CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
					LIMIT %d;
					",
					array_merge(
						array( $post_type, 'publish', $search, '%' . $wpdb->esc_like( $search ) . '%' ),
						$exclude,
						array( (int) $search, 10 )
					)
				);
			} else {
				$query = $wpdb->prepare(
					"
						SELECT ID, post_title
						FROM $wpdb->posts
						WHERE
						(post_type = %s OR post_type = 'page')
						AND post_status = %s
						AND
						(ID = %d OR post_title LIKE %s)
						ORDER BY 
						CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
						LIMIT %d;
						",
					$post_type,
					'publish',
					(int) $search,
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search,
					10,
				);
			}
		} elseif ( ! empty( $include ) ) {
			$includes                = array_filter( explode( ',', $include ), 'is_numeric' );
			$include_ids_placeholder = implode( ',', array_fill( 0, count( $includes ), '%d' ) );
			$query                   = $wpdb->prepare(
				"
					SELECT ID, post_title
					FROM {$wpdb->posts}
					WHERE ID IN ($include_ids_placeholder);
					",
				$includes
			);
		}
		if ( $query ) {
			$results = $wpdb->get_results( $query );

			if ( ! empty( $results ) ) {
				foreach ( $results as $post ) {
					$posts_data[] = array(
						'value' => $post->ID,
						'label' => $post->post_title,
					);
				}
			}
		}

		return $this->response_success( $posts_data );
	}

	/**
	 * Method to search the posts by title or ID
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function get_template_conditions( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kitlementor-kit' ) );
		// }
		$id = sanitize_text_field( $request->get_param( 'id' ) );

		$meta = get_post_meta( $id, 'jkit-condition', true );

		return $this->response_success( get_post_meta( $id, 'jkit-condition', true ) );
	}

	/**
	 * Method to search the Taxonomy by Name or Id
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function search_taxonomies_by_title_and_id( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kitlementor-kitlementor-kit' ) );
		// }

		$type     = sanitize_text_field( $request->get_param( 'type' ) );
		$search   = sanitize_text_field( $request->get_param( 'search' ) );
		$include  = sanitize_text_field( $request->get_param( 'include' ) );
		$taxonomy = sanitize_text_field( $request->get_param( 'taxonomy' ) );
		$exclude  = $request->get_param( 'exclude' );

		global $wpdb;
		$categories_data = array();
		if ( 'search' === $type ) {
			if ( ! empty( $exclude ) ) {
				$exclude_ids_placeholder = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );

				$query = $wpdb->prepare(
					"
						SELECT cat.term_id, cat.name
						FROM $wpdb->terms as cat
						INNER JOIN $wpdb->term_taxonomy as tax ON cat.term_id = tax.term_id
						WHERE tax.taxonomy = %s
						AND (cat.term_id = %d OR cat.name LIKE %s)
						AND cat.term_id NOT IN ($exclude_ids_placeholder)
						ORDER BY 
						CASE WHEN cat.term_id = %d THEN 0 ELSE 1 END, cat.term_id ASC
						LIMIT %d;
						",
					array_merge(
						array( $taxonomy, (int) $search, '%' . $wpdb->esc_like( $search ) . '%' ),
						$exclude,
						array( (int) $search, 10 ) // Prioritas ID dan batas jumlah hasil
					)
				);
			} else {
				$query = $wpdb->prepare(
					"
						SELECT cat.term_id, cat.name
						FROM {$wpdb->terms} AS cat
						INNER JOIN {$wpdb->term_taxonomy} AS tax ON cat.term_id = tax.term_id
						WHERE tax.taxonomy = %s
						AND (cat.term_id = %d OR cat.name LIKE %s)
						ORDER BY 
						CASE WHEN cat.term_id = %d THEN 0 ELSE 1 END, cat.term_id ASC
						LIMIT %d;
						",
					$taxonomy,
					(int) $search,
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search,
					10
				);
			}
		} elseif ( ! empty( $include ) ) {
			$includes                = array_filter( explode( ',', $include ), 'is_numeric' );
			$include_ids_placeholder = implode( ',', array_fill( 0, count( $includes ), '%d' ) );
			$query                   = $wpdb->prepare(
				"
				SELECT term_id, name
				FROM {$wpdb->terms}
				WHERE term_id IN ($include_ids_placeholder);
				",
				$includes
			);
		}

		if ( $query ) {
			$results = $wpdb->get_results( $query );

			if ( ! empty( $results ) ) {
				foreach ( $results as $term ) {
					$categories_data[] = array(
						'value' => $term->term_id,
						'label' => $term->name,
					);
				}
			}
		}

		return $this->response_success( $categories_data );
	}

	/**
	 * Method to search the posts by title or ID
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function search_posts_by_title_and_id( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }
		$search    = sanitize_text_field( $request->get_param( 'search' ) );
		$type      = sanitize_text_field( $request->get_param( 'type' ) );
		$include   = sanitize_text_field( $request->get_param( 'include' ) );
		$post_type = sanitize_text_field( $request->get_param( 'post_type' ) );
		$exclude   = $request->get_param( 'exclude' );
		global $wpdb;
		$posts_data = array();
		if ( 'search' === $type ) {
			if ( ! empty( $exclude ) ) {
				$exclude_ids_placeholder = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );
				$query                   = $wpdb->prepare(
					"
					SELECT ID, post_title
					FROM $wpdb->posts
					WHERE
					post_type = %s
					AND post_status = %s
					AND
					(ID = %d OR post_title LIKE %s)
					AND ID NOT IN ($exclude_ids_placeholder)
					ORDER BY 
					CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
					LIMIT %d;
					",
					array_merge(
						array( $post_type, 'publish', $search, '%' . $wpdb->esc_like( $search ) . '%' ),
						$exclude,
						array( (int) $search, 10 )
					)
				);
			} else {
				$query = $wpdb->prepare(
					"
						SELECT ID, post_title
						FROM $wpdb->posts
						WHERE
						post_type = %s
						AND post_status = %s
						AND
						(ID = %d OR post_title LIKE %s)
						ORDER BY 
						CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
						LIMIT %d;
						",
					$post_type,
					'publish',
					(int) $search,
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search,
					10,
				);
			}
		} elseif ( ! empty( $include ) ) {
			$includes                = array_filter( explode( ',', $include ), 'is_numeric' );
			$include_ids_placeholder = implode( ',', array_fill( 0, count( $includes ), '%d' ) );
			$query                   = $wpdb->prepare(
				"
					SELECT ID, post_title
					FROM {$wpdb->posts}
					WHERE ID IN ($include_ids_placeholder);
					",
				$includes
			);
		}
		if ( $query ) {
			$results = $wpdb->get_results( $query );

			if ( ! empty( $results ) ) {
				foreach ( $results as $post ) {
					$posts_data[] = array(
						'value' => $post->ID,
						'label' => $post->post_title,
					);
				}
			}
		}

		return $this->response_success( $posts_data );
	}




	/**
	 * Method to search the Taxonomy by Name or Id
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function search_taxonomies( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$type    = sanitize_text_field( $request->get_param( 'type' ) );
		$search  = sanitize_text_field( $request->get_param( 'search' ) );
		$include = sanitize_text_field( $request->get_param( 'include' ) );
		$exclude = $request->get_param( 'exclude' );

		global $wpdb;
		$categories_data = array();
		if ( 'search' === $type ) {
			if ( ! empty( $exclude ) ) {
				$exclude_ids_placeholder = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );

				$query = $wpdb->prepare(
					"
					SELECT cat.term_id, cat.name
					FROM $wpdb->terms as cat
					INNER JOIN $wpdb->term_taxonomy as tax ON cat.term_id = tax.term_id
					WHERE (cat.term_id = %d OR cat.name LIKE %s)
					AND cat.term_id NOT IN ($exclude_ids_placeholder)
					ORDER BY 
					CASE WHEN cat.term_id = %d THEN 0 ELSE 1 END, cat.term_id ASC
					LIMIT %d;
					",
					array_merge(
						array( (int) $search, '%' . $wpdb->esc_like( $search ) . '%' ),
						$exclude,
						array( (int) $search, 10 ) // Prioritas ID dan batas jumlah hasil
					)
				);
			} else {
				$query = $wpdb->prepare(
					"
					SELECT cat.term_id, cat.name
					FROM {$wpdb->terms} AS cat
					INNER JOIN {$wpdb->term_taxonomy} AS tax ON cat.term_id = tax.term_id
					WHERE (cat.term_id = %d OR cat.name LIKE %s)
					ORDER BY 
					CASE WHEN cat.term_id = %d THEN 0 ELSE 1 END, cat.term_id ASC
					LIMIT %d;
					",
					(int) $search,
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search,
					10
				);
			}
		} elseif ( ! empty( $include ) ) {
			$includes                = array_filter( explode( ',', $include ), 'is_numeric' );
			$include_ids_placeholder = implode( ',', array_fill( 0, count( $includes ), '%d' ) );
			$query                   = $wpdb->prepare(
				"
				SELECT term_id, name
				FROM {$wpdb->terms}
				WHERE term_id IN ($include_ids_placeholder);
				",
				$includes
			);
		}

		if ( $query ) {
			$results = $wpdb->get_results( $query );
			if ( ! empty( $results ) ) {
				foreach ( $results as $term ) {
					$categories_data[] = array(
						'value' => $term->term_id,
						'label' => $term->name,
					);
				}
			}
		}

		return $this->response_success( $categories_data );
	}

	/**
	 * Method to search the Users by display_name or ID
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function search_authors( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'wp_rest', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$search  = sanitize_text_field( $request->get_param( 'search' ) );
		$type    = sanitize_text_field( $request->get_param( 'type' ) );
		$include = sanitize_text_field( $request->get_param( 'include' ) );
		$exclude = $request->get_param( 'exclude' );
		global $wpdb;
		$users_data = array();
		if ( 'search' === $type ) {
			if ( ! empty( $exclude ) ) {
				$exclude_ids_placeholder = implode( ',', array_fill( 0, count( $exclude ), '%d' ) );
				$query                   = $wpdb->prepare(
					"
				SELECT ID, display_name
				FROM $wpdb->users
				WHERE ID = %d 
				OR display_name LIKE %s
				AND ID NOT IN ($exclude_ids_placeholder)
				ORDER BY 
				CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
				LIMIT %d;
				",
					array_merge(
						array( (int) $search, '%' . $wpdb->esc_like( $search ) . '%' ),
						$exclude,
						array( (int) $search, 10 )
					)
				);
			} else {
				$query = $wpdb->prepare(
					"
					SELECT ID, display_name
					FROM $wpdb->users
					WHERE ID = %d 
					OR display_name LIKE %s
					ORDER BY 
					CASE WHEN ID = %d THEN 0 ELSE 1 END, ID DESC
					LIMIT %d;
					",
					(int) $search,
					'%' . $wpdb->esc_like( $search ) . '%',
					(int) $search,
					10,
				);
			}
		} elseif ( ! empty( $include ) ) {
			$includes                = array_filter( explode( ',', $include ), 'is_numeric' );
			$include_ids_placeholder = implode( ',', array_fill( 0, count( $includes ), '%d' ) );
			$query                   = $wpdb->prepare(
				"
				SELECT ID, display_name
				FROM {$wpdb->users}
				WHERE ID IN ($include_ids_placeholder);
				",
				$includes
			);
		}
		if ( $query ) {
			$results = $wpdb->get_results( $query );
			if ( ! empty( $results ) ) {
				foreach ( $results as $author ) {
					$users_data[] = array(
						'value' => $author->ID,
						'label' => $author->display_name,
					);
				}
			}
		}

		return $this->response_success( $users_data );
	}




	/**
	 * Create Element
	 */
	public function create_template( $request ) {
		$data      = $request->get_param( 'data' );
		$post_type = $request->get_param( 'type' );

		$title = isset( $data['title'] ) ? $data['title'] : '';

		if ( ! empty( $title ) ) {
			if ( jkit_is_free_header_footer_template_limit_reached( $post_type ) ) {
				return new \WP_REST_Response(
					array(
						'code'         => 'jkit_template_limit_reached',
						'show_pricing' => true,
						'message'      => esc_html__( 'Upgrade to Pro to create more header or footer templates.', 'jeg-elementor-kit' ),
					),
					403
				);
			}

			$condition = isset( $data['condition'] ) ? $data['condition'] : array();
			$published = jkit_get_element_data( $post_type )['publish'];
			$keys      = jkit_extract_ids( $published );
			$post_args = array(
				'post_title'  => $title,
				'post_type'   => $post_type,
				'post_status' => 'publish',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'page',
					'_elementor_data'          => json_encode( array() ),
					'_wp_page_template'        => 'elementor_canvas',
				),
			);
			$meta      = null;

			if ( 'jkit-template' === $post_type ) {
				$page = $data['type'];
				$post_args['meta_input']['_wp_page_template']  = 'elementor_header_footer';
				$post_args['meta_input']['jkit-template-type'] = $page;
				$meta = $page;
			}

			$post_id = wp_insert_post( $post_args );

			update_post_meta( $post_id, 'jkit-condition', $condition );
			array_unshift( $keys, $post_id );
			$this->update_post_sequence( $keys );

			$element  = jkit_get_element_data( $post_type, $meta );
			$response = array(
				'status'  => true,
				'message' => esc_html__( 'Success create new template', 'jeg-elementor-kit' ),
				'data'    => $element,
			);

			return wp_send_json( $response, 200 );
		}

		return wp_send_json(
			array(
				'status'  => false,
				'message' => esc_html__( 'Error when create new template.', 'jeg-elementor-kit' ),
			),
			400
		);
	}
	/**
	 * Get active template lists only.
	 *
	 * @param string $type Template type.
	 * @return array
	 */
	private function get_active_template( $type ) {
		if ( 'jkit-header' === $type || 'jkit-footer' === $type ) {
			return jkit_get_element( 'publish', $type, null );
		} else {
			return jkit_get_element( 'publish', 'jkit-template', $type );
		}
	}

	/**
	 * Clone Template
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function clone_template( $request ) {
		$data      = $request->get_param( 'data' );
		$post_type = $data['type'];
		$title     = isset( $data['title'] ) ? $data['title'] : '';
		$source_id = isset( $data['id'] ) ? $data['id'] : false;
		if ( $source_id ) {
			if ( jkit_is_free_header_footer_template_limit_reached( $post_type ) ) {
				return new \WP_REST_Response(
					array(
						'code'         => 'jkit_template_limit_reached',
						'show_pricing' => true,
						'message'      => esc_html__( 'Upgrade to Pro to create more header or footer templates.', 'jeg-elementor-kit' ),
					),
					403
				);
			}

			$template_type = ( 'jkit-header' === $post_type || 'jkit-footer' === $post_type ) ? $post_type : 'jkit-template';
			$meta          = ( 'jkit-header' === $post_type || 'jkit-footer' === $post_type ) ? null : $post_type;

			$post_id   = $this->duplicate_element( $source_id, $title, $template_type );
			$published = $this->get_active_template( $post_type );

			$keys = jkit_extract_ids( $published );
			$keys = jkit_remove_array( $post_id, $keys );
			array_unshift( $keys, $post_id );
			$this->update_post_sequence( $keys );

			$element = apply_filters( 'jkit_element_data_clone', jkit_get_element_data( $template_type, $meta ), $template_type, $post_type );

			$response = array(
				'status'  => true,
				'message' => esc_html__( 'Success colne the template', 'jeg-elementor-kit' ),
				'data'    => $element,
			);

			return wp_send_json( $response, 200 );
		}

		return wp_send_json(
			array(
				'status'  => false,
				'message' => esc_html__( 'Error when create new template.', 'jeg-elementor-kit' ),
			),
			400
		);
	}


	/**
	 * Delete Element
	 *
	 * @param \WP_REST_Request $request request.
	 *
	 * @return \WP_REST_Response|array
	 */
	public function delete_template( $request ) {
		$post_id = $request->get_param( 'id' );

		$sucess = wp_delete_post( $post_id, true );
		if ( $sucess ) {
			return $this->response_success(
				array(
					'status'  => true,
					'message' => esc_html__( 'Success delete template', 'jeg-elementor-kit' ),
				)
			);
		}
		return $this->response_error( esc_html__( 'Faild delete template', 'jeg-elementor-kit' ) );
	}

	/**
	 * Duplicate Element
	 *
	 * @param $post_id
	 *
	 * @return int|\WP_Error
	 */
	public function duplicate_element( $post_id, $title, $type ) {
		$post        = array(
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_type'   => $type,
			'post_author' => 1,
		);
		$new_post_id = wp_insert_post( $post );

		$data = get_post_custom( $post_id );
		foreach ( $data as $key => $values ) {
			$value = get_post_meta( $post_id, $key, true );
			add_post_meta( $new_post_id, $key, $value );
		}

		return $new_post_id;
	}

	public function update_template( $request ) {
		try {
			$data      = $request->get_param( 'data' );
			$title     = isset( $data['title'] ) ? $data['title'] : '';
			$condition = isset( $data['condition'] ) ? $data['condition'] : array();
			$post_id   = isset( $data['id'] ) ? $data['id'] : array();

			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $title,
				)
			);
			update_post_meta( $post_id, sanitize_key( 'jkit-condition' ), $condition );
		} catch ( \Exception $e ) {
			return $this->response_error( $e->getMessage() );
		}
		return $this->response_success(
			array(
				'message' => esc_html__( 'Success update the template', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Jeg Kit Update Sequence
	 *
	 * @param $ids
	 */
	public function update_post_sequence( $ids ) {
		foreach ( $ids as $sequence => $id ) {
			wp_update_post(
				array(
					'ID'         => $id,
					'menu_order' => $sequence,
				)
			);
		}
	}


	/**
	 * Update Sequence
	 */
	public function update_template_status( $request ) {

		try {
			$draft   = $request->get_param( 'draft' );
			$publish = $request->get_param( 'publish' );
			if ( isset( $publish ) && count( $publish ) ) {
				foreach ( $publish as $key => $post ) {
					wp_update_post(
						array(
							'ID'          => $post['id'],
							'menu_order'  => $key,
							'post_status' => 'publish',
						)
					);
				}
			}
			if ( isset( $draft ) && count( $draft ) ) {
				foreach ( $draft as $key => $post ) {
					wp_update_post(
						array(
							'ID'          => $post['id'],
							'menu_order'  => $key,
							'post_status' => 'draft',
						)
					);
				}
			}
		} catch ( \Exception $e ) {
			return $this->response_error( $e->getMessage() );
		}
		return $this->response_success(
			array(
				'message' => esc_html__( 'Success update the template priority', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Update Sequence
	 */
	public function update_template_priority( $request ) {

		try {
			$data = $request->get_param( 'data' );
			$data = $request->get_param( 'lists' );
			foreach ( $data as $key => $id ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'menu_order'  => $key,
						'post_status' => 'publish',
					)
				);
			}
		} catch ( \Exception $e ) {
			return $this->response_error( $e->getMessage() );
		}
		return $this->response_success(
			array(
				'message' => esc_html__( 'Success update the template priority', 'jeg-elementor-kit' ),
			)
		);
	}
	/**
	 * Update wp_option API.
	 *
	 * @param \WP_REST_Request $request The Request.
	 */
	public function update_option( $request ) {
		$name     = $request->get_param( 'option_name' );
		$option   = $request->get_param( 'options' );
		$autoload = $request->get_param( 'auto_load' );
		$updated  = update_option( $name, $option, $autoload );
		if ( $updated ) {
			return $this->response_success(
				array(
					'message' => esc_html__( 'Success update option', 'jeg-elementor-kit' ),
				)
			);
		}
		return $this->response_error( esc_html__( 'Failed update option', 'jeg-elementor-kit' ) );
	}

	public function get_template_lists( $request ) {
		$type = $request->get_param( 'type' );
		if ( 'jkit-header' === $type || 'jkit-footer' === $type ) {
			return jkit_get_element_data( $type );
		} else {
			return jkit_get_element_data( 'jkit-template', $type );
		}
	}

	private function get_template_data( $status, $type, $meta = null ) {
		$args = array(
			'post_type'   => $type,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'post_status' => $status,
			'numberposts' => '-1',
		);

		if ( jkit_is_multilanguage() ) {
			$args['lang'] = '';
		}

		$query  = get_posts( $args );
		$result = array();

		if ( $query ) {
			foreach ( $query as $post ) {
				$result[] = array(
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'url'       => \Jeg\Elementor_Kit\Dashboard\Dashboard::editor_url( $post->ID ),
					'condition' => get_post_meta( $post->ID, 'jkit-condition', true ),
				);
			}
		}

		wp_reset_postdata();

		return $result;
	}

	public function get_option( $request ) {
		$name = $request->get_param( 'name' );
		return get_option( $name );
	}
	public function get_global_style() {

		return array(
			'choices'  => jkit_get_elementor_saved_template_option(),
			'selected' => get_option( 'elementor_active_kit' ),
		);
	}

	public function update_mailchimp( $request ) {
		$api_key = $request->get_param( 'mailchimp_api_key' );
		if ( isset( $api_key ) ) {
			$save     = array(
				'mailchimp' => array(
					'api_key' => $api_key,
				),
			);
			$response = array(
				'status'  => false,
				'message' => esc_html__( 'API Key is Invalid.', 'jeg-elementor-kit' ),
			);
			$split    = explode( '-', $api_key );

			if ( isset( $split[1] ) ) {
				$dc = $split[1];

				$request = wp_remote_request(
					'https://' . $dc . '.api.mailchimp.com/3.0/?fields=account_id',
					array(
						'method'  => 'GET',
						'headers' =>
							array(
								'Authorization' => sprintf( 'Basic %s', base64_encode( 'mc4wp:' . $api_key ) ),
							),
						'timeout' => 30,
					)
				);

				$mc_response = json_decode( wp_remote_retrieve_body( $request ) );
			}

			if ( '' === $api_key || is_object( $mc_response ) && property_exists( $mc_response, 'account_id' ) ) {
				update_option( 'jkit_user_data', $save );
				$response = array(
					'status'  => true,
					'message' => esc_html__( 'Success update API Key.', 'jeg-elementor-kit' ),
				);
			}
		}

		return wp_send_json( $response );
	}


	/**
	 * Newsletter Subscribre Handler
	 *
	 * @param \WP_REST_Request $request The Request.
	 */
	public function newsletter_subscribe_handler( $request ) {
		$email    = $request->get_param( 'email' );
		$site     = $request->get_param( 'site' );
		$response = array(
			'status'  => false,
			'message' => esc_html__( 'Bad Request', 'jeg-elementor-kit' ),
		);

		if ( isset( $email ) && isset( $site ) ) {
			add_filter( 'http_request_host_is_external', '__return_true' );
			if ( is_email( $email ) && wp_http_validate_url( $site ) ) {
				$save_request = wp_remote_request(
					JEG_ELEMENT_SERVER_URL . '/wp-json/jeg-kit-server/v1/subscribe',
					array(
						'method'  => 'POST',
						'timeout' => 10,
						'body'    => array(
							'email'  => sanitize_email( $email ),
							'domain' => esc_url_raw( $site ),
						),
					)
				);

				if ( is_wp_error( $response ) ) {
					$response = array(
						'status'  => true,
						'message' => esc_html__( 'Faild to subscribe the newsletter.', 'jeg-elementor-kit' ),
					);
				}

				$save_response = json_decode( wp_remote_retrieve_body( $save_request ), true );

				if ( 200 === wp_remote_retrieve_response_code( $save_request ) ) {
					$response = array(
						'status'  => true,
						'message' => esc_html__( 'Thank you for subscribing.', 'jeg-elementor-kit' ),
					);
				} else {
					$response = array(
						'status'  => true,
						'message' => isset( $save_response['message'] ) ? $save_response['message'] : esc_html__( 'Faild to subscribe the newsletter.', 'jeg-elementor-kit' ),
					);
				}
			}
			add_filter( 'http_request_host_is_external', '__return_false' );
		}

		return wp_send_json( $response );
	}

	/**
	 * Modify Settings
	 *
	 * @param object $request .
	 */
	public function update_theme_options( $request ) {

		$data = $request->get_param( 'setting' );
		// use this to debug
		// delete_option( JEG_ELEMENTOR_KIT_OPTIONS );
		$status = Settings::update_settings( $data );
		Settings::clear_cache( true );

		return $status;
	}

	/**
	 * Modify Settings
	 *
	 * @param object $request .
	 */
	public function demo_like_state( $request ) {
		$slug  = $request->get_param( 'slug' );
		$state = $request->get_param( 'state' );
		$liked = Meta::instance()->get_option( 'liked_demo' );
		$liked = ! empty( $liked ) ? $liked : array();
		if ( $state ) {
			if ( ! in_array( $slug, $liked, true ) ) {
				$liked[] = $slug;
			}
		} elseif ( in_array( $slug, $liked, true ) ) {
			$liked = array_diff( $liked, array( $slug ) );
		}
		return Meta::instance()->set_option( 'liked_demo', $liked );
	}

	/**
	 * Modify Settings
	 *
	 * @param object $request .
	 */
	public function demo_import_state( $request ) {
		$slug     = $request->get_param( 'slug' );
		$state    = $request->get_param( 'state' );
		$imported = Meta::instance()->get_option( 'imported_demo' );
		$imported = ! empty( $imported ) ? $imported : array();
		if ( $state ) {
			if ( ! in_array( $slug, $imported, true ) ) {
				$imported[] = $slug;
			}
		} elseif ( in_array( $slug, $imported, true ) ) {
			$imported = array_diff( $imported, array( $slug ) );
		}

		return Meta::instance()->set_option( 'imported_demo', $imported );
	}

	/**
	 * Fetch Data
	 *
	 * @return WP_Rest
	 */
	public function fetch_demo_data() {
		$demo_time = Meta::instance()->get_option( 'fetch_demo_time' );
		$now       = time();

		$demo_time = null;
		if ( null === $demo_time || $demo_time < $now || apply_filters( 'fetch_demo_data', false ) ) {
			if ( $this->update_demo_data() ) {
				$next_fetch = $now + ( 24 * 60 * 60 );
				Meta::instance()->set_option( 'fetch_demo_time', $next_fetch );
			}
		}

		return $this->demo_data();
	}

	/**
	 * Update Data.
	 */
	public function update_demo_data() {
		$api_url  = JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/demo/list';
		$args     = array(
			'sslverify' => false,
			'body'      => array(
				'debug' => essential_is_wp_debug(),
			),
		);
		$response = wp_remote_post( $api_url, $args );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$data = wp_remote_retrieve_body( $response );
		$data = $this->filter_demo_data( $data );

		if ( ! $data || ! isset( $data['demos'] ) || empty( $data['demos'] ) ) {
			return false;
		}

		Meta::instance()->set_option( 'demo_data', $data );

		return true;
	}

	/**
	 * Demo Data.
	 *
	 * @return array
	 */
	public function demo_data() {
		$demo_data = Meta::instance()->get_option( 'demo_data', array() );
		$demo_data = $this->demo_like_filter( $demo_data );
		$demo_data = $this->demo_imported_filter( $demo_data );

		return $demo_data;
	}

	/**
	 * Demo Data.
	 *
	 * @param array $demo_data demo data.
	 * @return array
	 */
	public function demo_like_filter( $demo_data ) {
		$liked         = Meta::instance()->get_option( 'liked_demo' );
		$liked         = ! empty( $liked ) ? $liked : array();
		$filtered_demo = array();
		$id            = 0;

		if ( isset( $demo_data['demos'] ) ) {
			foreach ( $demo_data['demos'] as $demo ) {
				$filtered_demo[] = array(
					'id'   => $id,
					'name' => $demo['name'],
					'data' => $demo['data'],
					'like' => in_array( $demo['data']['slug'], $liked, true ),
				);
				++$id;
			}
		}

		$demo_data['demos'] = $filtered_demo;

		return $demo_data;
	}

	/**
	 * Filter Demo Data.
	 *
	 * @param array $demo_data demo data.
	 * @return array
	 */
	public function demo_imported_filter( $demo_data ) {
		$imported      = Meta::instance()->get_option( 'imported_demo' );
		$imported      = ! empty( $imported ) ? $imported : array();
		$filtered_demo = array();
		$id            = 0;

		if ( isset( $demo_data['demos'] ) ) {
			foreach ( $demo_data['demos'] as $demo ) {
				$filtered_demo[] = array(
					'id'       => $id,
					'name'     => $demo['name'],
					'data'     => $demo['data'],
					'like'     => $demo['like'],
					'imported' => in_array( $demo['data']['slug'], $imported, true ),
				);
				++$id;
			}
		}

		$demo_data['demos'] = $filtered_demo;

		return $demo_data;
	}

	/**
	 * Update Data.
	 *
	 * @param array $data demo data.
	 * @return array filtered data.
	 */
	public function filter_demo_data( $data ) {
		$data           = json_decode( $data, true );
		$demos          = array();
		$demos['demos'] = array();
		if ( isset( $data ) ) {
			foreach ( $data as $demo ) {
				$demos['demos'][] = array(
					'name' => $demo['title'],
					'data' => array(
						'title'            => $demo['title'],
						'slug'             => $demo['slug'],
						'cover'            => array(
							$demo['cover'],
						),
						'demo'             => $demo['url'],
						'newFlag'          => $demo['newFlag'],
						'id'               => $demo['demo_id'],
						'requirements'     => $demo['requirements'],
						'pro'              => $demo['pro'],
						'categories'       => $demo['categories'],
						'wordpress_req'    => $demo['wordpress_req'],
						'file'             => $demo['file'],
						'demo_description' => $demo['demo_description'],
						'order'            => $demo['order'],
					),
					'like' => false,
				);
			}
		} else {
			return false;
		}

		return $demos;
	}

	/**
	 * Manage content
	 *
	 * @param object $request data.
	 *
	 * @return WP_Error|array
	 */
	public function manage_content( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$demo   = sanitize_text_field( $request->get_param( 'demo' ) );
		$action = sanitize_text_field( $request->get_param( 'action' ) );

		if ( 'import' === $action || 'reinstall' === $action && ! empty( $request->get_param( 'data' ) ) ) {
			return $this->import_content( $demo, $request );
		} elseif ( 'uninstall' === $action || 'reinstall' === $action ) {
			$type = sanitize_text_field( $request->get_param( 'type' ) );
			return $this->uninstall_content( $demo, $type );
		}
	}

	/**
	 * Uninstall content
	 *
	 * @param object $demo slug.
	 * @param object $type install/uninstall.
	 *
	 * @return WP_Error|array
	 */
	public function uninstall_content( $demo, $type ) {
		$option_name  = essential_import_demo_key( $demo );
		$option_value = get_option( $option_name, array() );

		if ( 'uninstall-style' === $type ) {
			unset( $option_value['style'] );
		}

		if ( 'uninstall-image' === $type ) {
			$images = isset( $option_value['image'] ) ? $option_value['image'] : '';

			if ( $images && is_array( $images ) ) {
				foreach ( $images as $image ) {
					wp_delete_post( $image, true );
				}
				unset( $option_value['image'] );
			}
		}

		if ( 'uninstall-content' === $type ) {
			$pages = isset( $option_value['page'] ) ? $option_value['page'] : '';

			if ( $pages && is_array( $pages ) ) {
				$pages = array_flip( $pages );

				foreach ( $pages as $page ) {
					wp_delete_post( $page, true );
				}
				unset( $option_value['page'] );
			}

			$menus = isset( $option_value['menu'] ) ? $option_value['menu'] : '';

			if ( $menus && is_array( $menus ) ) {
				$menus = array_flip( $menus );

				foreach ( $menus as $menu ) {
					wp_delete_nav_menu( $menu, true );
				}
				unset( $option_value['menu'] );
			}

			$mega_menus = isset( $option_value['mega-menu'] ) ? $option_value['mega-menu'] : '';

			if ( $mega_menus && is_array( $mega_menus ) ) {
				$mega_menus = array_flip( $mega_menus );

				foreach ( $mega_menus as $mega_menu ) {
					wp_delete_post( $mega_menu, true );
				}
				unset( $option_value['mega-menu'] );
			}

			if ( isset( $option_value['term'] ) ) {
				wp_delete_term( $option_value['term'], 'product_cat' );
			}
		}

		update_option( $option_name, $option_value );

		return $this->response_success(
			array(
				'message' => esc_html__( 'Completed', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Import content
	 *
	 * @param object $demo demo data.
	 * @param object $request demo data.
	 *
	 * @return WP_Error|array
	 */
	public function import_content( $demo, $request ) {
		$this->demo        = $demo;
		$option_name       = essential_import_demo_key( $demo );
		$import_data       = get_option( $option_name, array() );
		$data              = $request->get_param( 'data' );
		$elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0';
		$args              = array(
			'post_title'   => sanitize_text_field( $data['title'] ),
			'post_type'    => sanitize_text_field( $data['type'] ),
			'post_excerpt' => isset( $data['excerpt'] ) ? sanitize_text_field( $data['excerpt'] ) : '',
			'post_status'  => 'publish',
			'meta_input'   => array(
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => $data['metadata']['template_type'],
				'_wp_page_template'        => $data['metadata']['wp_page_template'],
				'_elementor_page_settings' => $data['page_settings'],
				'_elementor_data'          => isset( $data['content'] ) ? $data['content'] : '',
				'_elementor_version'       => $elementor_version,
			),
		);

		if ( 'global-styles' === $data['metadata']['template_type'] ) {
			$args['post_title'] = sanitize_text_field( $data['title'] ) . ': ' . sanitize_text_field( $request->get_param( 'title' ) );
		}

		$args = $this->import_content_setup_custom_template( $data, $args );

		$args = $this->import_content_setup_metform( $data, $args );

		$args = $this->import_content_setup_tabs( $data, $args );

		if ( 'product' === sanitize_text_field( $data['type'] ) && $data['content'] ) {
			$args['post_content'] = $data['content'];
		}

		if ( 'post' === sanitize_text_field( $data['type'] ) && empty( $data['metadata']['wp_page_template'] ) && $data['content'] ) {
			$args['post_content'] = $data['content'];
			unset( $args['meta_input']['_elementor_edit_mode'] );
		}

		if ( isset( $data['metadata']['elementor_document'] ) && ! $data['metadata']['elementor_document'] ) {
			unset( $args['meta_input']['_elementor_edit_mode'] );
		}

		if ( 'jkit-mega-menu' === sanitize_text_field( $data['type'] ) ) {
			$args['post_type'] = 'elementor_library';
			$args['post_name'] = $data['slug'];
		}

		if ( 'nav_menu' === sanitize_text_field( $data['type'] ) ) {
			$post_id = essential_import_menu( $data['title'], $data['nav_menu_item_setting'], $demo );
		} else {
			if ( is_array( $args['meta_input']['_elementor_data'] ) ) {
				$args['meta_input']['_elementor_data'] = json_encode( $args['meta_input']['_elementor_data'] );
			}

			$post_id = wp_insert_post( wp_slash( $args ) );
		}

		if ( ! is_wp_error( $post_id ) ) {
			if ( 'global-styles' === $data['metadata']['template_type'] ) {
				$this->import_content_setup_global_styles( $post_id );

				$import_data['style'] = $post_id;
			} elseif ( 'nav_menu' === sanitize_text_field( $data['type'] ) ) {
				$import_data['menu'][ $post_id ] = isset( $data['slug'] ) ? $data['slug'] : $post_id;
			} elseif ( 'jkit-mega-menu' === sanitize_text_field( $data['type'] ) ) {
				$type = 'mega-menu';
				update_post_meta( $post_id, \Elementor\Core\Base\Document::TYPE_META_KEY, $type );
				wp_set_object_terms( $post_id, $type, \Elementor\TemplateLibrary\Source_Local::TAXONOMY_TYPE_SLUG );
				$import_data[ $type ][ $post_id ] = isset( $data['slug'] ) ? $data['slug'] : $post_id;
			} else {
				$import_data['page'][ $post_id ] = isset( $data['slug'] ) ? $data['slug'] : $post_id;

				$this->import_content_setup_404_template( $data, $post_id );
			}

			$import_data = $this->import_content_setup_featured_image( $data, $post_id, $import_data );
			$import_data = $this->import_content_setup_product_term( $data, $post_id, $import_data );

			$this->import_content_setup_pages( $data, $post_id );
			$this->import_content_setup_product_meta( $data, $post_id );
			$this->import_content_setup_header_footer_order( $data, $post_id );
		}

		update_option( $option_name, $import_data );

		return $this->response_success(
			array(
				'id'      => $post_id,
				'url'     => get_permalink( $post_id ),
				'message' => esc_html__( 'Completed', 'jeg-elementor-kit' ),
				'status'  => esc_html__( 'Importing contents', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Setup product term
	 *
	 * @param array $data data.
	 * @param int   $product_id id.
	 * @param array $import_data imported.
	 *
	 * @return array $import_data imported.
	 */
	private function import_content_setup_product_term( $data, $product_id, $import_data ) {
		if ( 'product' === sanitize_text_field( $data['type'] ) ) {
			$term = term_exists( $this->demo, 'product_cat' );

			if ( ! $term ) {
				$term = wp_insert_term(
					ucfirst( $this->demo ),
					'product_cat',
					array(
						'description' => esc_html__( 'This is a dummy product category from the import demo.', 'jeg-elementor-kit' ),
						'slug'        => $this->demo,
					)
				);

				update_term_meta(
					$term['term_id'],
					'thumbnail_id',
					$data['thumbnail']
				);

				$import_data['term'] = $term['term_id'];
			}

			wp_set_post_terms( $product_id, $term['term_id'], 'product_cat' );
		}

		return $import_data;
	}

	/**
	 * Setup product meta
	 *
	 * @param array $data content.
	 * @param array $post_id post id.
	 *
	 * @return void
	 */
	private function import_content_setup_product_meta( $data, $post_id ) {
		if ( 'product' === sanitize_text_field( $data['type'] ) ) {
			if ( is_array( $data['product_meta'] ) ) {
				foreach ( $data['product_meta'] as $meta => $value ) {
					update_post_meta( $post_id, $meta, $value );
				}
			}

			if ( isset( $data['gallery'] ) ) {
				update_post_meta( $post_id, '_product_image_gallery', implode( ',', $data['gallery'] ) );
			}
		}
	}

	/**
	 * Setup condition for header and footer template order when import content
	 *
	 * @param array $data content.
	 * @param array $post_id post id.
	 *
	 * @return void
	 */
	private function import_content_setup_header_footer_order( $data, $post_id ) {
		if ( in_array( sanitize_text_field( $data['type'] ), array( 'jkit-header', 'jkit-footer' ) ) ) {
			$query = get_posts(
				array(
					'post_type'   => $data['type'],
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
					'post_status' => 'publish',
				)
			);

			if ( $query ) {
				$index = 1;

				foreach ( $query as $post ) {
					wp_update_post(
						array(
							'ID'         => $post->ID,
							'menu_order' => $index,
						)
					);

					++$index;
				}
			}

			wp_update_post(
				array(
					'ID'         => $post_id,
					'menu_order' => 0,
				)
			);

			wp_reset_postdata();
		}
	}

	/**
	 * Setup condition for custom template when import content
	 *
	 * @param array $data data.
	 * @param array $args args.
	 *
	 * @return array
	 */
	private function import_content_setup_custom_template( $data, $args ) {
		if ( in_array( sanitize_text_field( $data['type'] ), array( 'jkit-header', 'jkit-footer', 'jkit-template' ) ) ) {
			$condition = array();

			if ( $data['jkit-condition'] ) {
				$condition = json_encode( $data['jkit-condition'], true );
				$condition = json_decode( $this->text_filter( $condition ), true );
			}

			$args['meta_input']['jkit-condition'] = $condition;

			if ( 'jkit-header' === $data['type'] ) {
				$elementor_data = json_encode( $args['meta_input']['_elementor_data'] );
				$elementor_data = $this->text_filter( $elementor_data );
				$elementor_data = $this->text_filter_menu( $elementor_data );

				$args['meta_input']['_elementor_data'] = json_decode( $elementor_data, true );
			}

			if ( 'jkit-template' === $data['type'] ) {
				$args['meta_input']['jkit-template-type'] = $data['metadata']['jkit-template-type'];
			}
		}

		return $args;
	}

	/**
	 * Setup metform when import content
	 *
	 * @param array $data data.
	 * @param array $args args.
	 *
	 * @return array
	 */
	private function import_content_setup_metform( $data, $args ) {
		if ( 'metform-form' === sanitize_text_field( $data['type'] ) ) {
			$args['meta_input']['metform_form__form_setting'] = $data['metform_form__form_setting'] ? $data['metform_form__form_setting'] : '';
			$args['meta_input']['_elementor_data']            = json_encode( $args['meta_input']['_elementor_data'] );
		}

		if ( 'page' === sanitize_text_field( $data['type'] ) || 'jkit-template' === sanitize_text_field( $data['type'] ) ) {
			$elementor_data = json_encode( $args['meta_input']['_elementor_data'] );

			if ( strpos( $elementor_data, 'mf_form_id' ) !== false ) {
				$args['meta_input']['_elementor_data'] = $this->text_filter( $elementor_data );
			}
		}

		return $args;
	}

	/**
	 * Setup tabs when import content
	 *
	 * @param array $data data.
	 * @param array $args args.
	 *
	 * @return array
	 */
	private function import_content_setup_tabs( $data, $args ) {
		if ( 'page' === sanitize_text_field( $data['type'] ) ) {
			$elementor_data = json_encode( $args['meta_input']['_elementor_data'] );

			if ( strpos( $elementor_data, 'jkit_tabs' ) !== false ) {
				$args['meta_input']['_elementor_data'] = json_decode( $this->text_filter( $elementor_data ), true );
			}
		}

		return $args;
	}

	/**
	 * Setup global styles template when import content
	 *
	 * @param int $post_id post id.
	 *
	 * @return void
	 */
	private function import_content_setup_global_styles( $post_id ) {
		update_option( 'elementor_active_kit', $post_id );
		update_post_meta( $post_id, '_elementor_template_type', 'kit' );
	}

	/**
	 * Setup 404 page template when import content
	 *
	 * @param array $data content.
	 * @param array $post_id post id.
	 *
	 * @return void
	 */
	private function import_content_setup_404_template( $data, $post_id ) {
		if ( strpos( $data['title'], '404' ) !== false ) {
			update_option( 'jkit_notfound_template', $post_id );
		}
	}

	/**
	 * Setup post featured image when import content
	 *
	 * @param array $data content.
	 * @param array $post_id post id.
	 * @param array $import_data import data.
	 *
	 * @return array
	 */
	private function import_content_setup_featured_image( $data, $post_id, $import_data ) {
		if ( in_array( sanitize_text_field( $data['type'] ), array( 'post', 'product' ) ) && isset( $data['thumbnail'] ) ) {
			$image_id = $this->manage_featured_image( $post_id, $data['thumbnail'] );

			if ( $image_id ) {
				$import_data['image'][ $image_id ] = $image_id;
			}
		}

		return $import_data;
	}

	/**
	 * Setup pages when import content
	 *
	 * @param array $data content.
	 * @param array $post_id post id.
	 *
	 * @return void
	 */
	private function import_content_setup_pages( $data, $post_id ) {
		if ( 'page' !== $data['type'] ) {
			return;
		}

		if ( isset( $data['homepage'] ) && $data['homepage'] ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $post_id );
		}

		if ( isset( $data['slug'] ) && 'cart' === $data['slug'] ) {
			update_option( 'woocommerce_cart_page_id', $post_id );
		}

		if ( isset( $data['slug'] ) && 'checkout' === $data['slug'] ) {
			update_option( 'woocommerce_checkout_page_id', $post_id );
		}

		if ( isset( $data['slug'] ) && 'my-account' === $data['slug'] ) {
			update_option( 'woocommerce_myaccount_page_id', $post_id );
		}

		if ( isset( $data['slug'] ) && strpos( $data['slug'], 'shop' ) !== false ) {
			update_option( 'woocommerce_shop_page_id', $post_id );
		}
	}

	/**
	 * Filter given text
	 *
	 * @param string $text text.
	 *
	 * @return string
	 */
	private function text_filter( $text ) {
		return preg_replace_callback( '/(\{{' . essential_get_theme_filter_key() . '.*?\}})/', array( $this, 'trim_convert_tag' ), $text );
	}

	/**
	 * Filter given text especially for menu
	 *
	 * @param string $text text.
	 *
	 * @return string
	 */
	private function text_filter_menu( $text ) {
		return preg_replace_callback( '/"sg_menu_choose":"(.*?)"/', array( $this, 'trim_menu_tag' ), $text );
	}

	/**
	 * Parse menu item slug
	 *
	 * @param string $matches text.
	 *
	 * @return string
	 */
	public function trim_menu_tag( $matches ) {
		$import_data = get_option( essential_import_demo_key( $this->demo ), array() );
		$menu_id     = array_search( $matches[1], $import_data['menu'] );

		if ( $menu_id ) {
			$menu_data = wp_get_nav_menu_object( $menu_id );

			return '"sg_menu_choose":"' . $menu_data->slug . '"';
		}

		return $matches[0];
	}

	/**
	 * Remove unused curly brace function
	 *
	 * @param string $content content.
	 *
	 * @return string
	 */
	public function trim_convert_tag( $content ) {
		return $this->convert_tag( trim( $content[1], '{}' ) );
	}

	/**
	 * Convert tag
	 *
	 * @param string $string slug.
	 *
	 * @return string
	 */
	private function convert_tag( $string ) {
		$tag = explode( ':', $string );

		if ( sizeof( $tag ) > 1 ) {
			switch ( $tag[1] ) {
				case 'metform':
					$result = $this->metform_tag( $tag );
					break;

				case 'offcanvas':
					$result = $this->offcanvas_tag( $tag );
					break;

				case 'condition':
					$result = $this->condition_tag( $tag );
					break;

				case 'tabs':
					$result = $this->tabs_tag( $tag );
					break;

				default:
					$result = $string;
					break;
			}
		} else {
			$result = $string;
		}

		return apply_filters( 'essential_convert_tag', $result, $tag );
	}

	/**
	 * Parse tabs tag
	 *
	 * Ex: get ID: essential:tabs:slug:id
	 *
	 * @param array $tag tag.
	 *
	 * @return int
	 */
	private function tabs_tag( $tag ) {
		$slug = $tag[2];
		$to   = $tag[3];

		if ( 'id' === $to ) {
			$import_data = get_option( essential_import_demo_key( $this->demo ), array() );

			if ( isset( $import_data['page'] ) ) {
				$page_id = array_search( $slug, $import_data['page'] );

				if ( $page_id ) {
					return $page_id;
				}
			}
		}

		return '';
	}

	/**
	 * Parse offcanvas tag
	 *
	 * Ex: get ID: essential:offcanvas:slug:id
	 *
	 * @param array $tag tag.
	 *
	 * @return int
	 */
	private function offcanvas_tag( $tag ) {
		$slug = $tag[2];
		$to   = $tag[3];

		if ( 'id' === $to ) {
			$import_data = get_option( essential_import_demo_key( $this->demo ), array() );

			if ( isset( $import_data['page'] ) ) {
				$offcanvas_id = array_search( $slug, $import_data['page'] );

				if ( $offcanvas_id ) {
					return $offcanvas_id;
				}
			}
		}

		return '';
	}

	/**
	 * Parse condition tag
	 *
	 * Ex: get ID: essential:condition:slug:id
	 *
	 * @param array $tag tag.
	 *
	 * @return int
	 */
	private function condition_tag( $tag ) {
		$slugs = $tag[2];
		$to    = $tag[3];

		if ( 'id' === $to ) {
			$import_data = get_option( essential_import_demo_key( $this->demo ), array() );
			$slugs       = explode( ',', $slugs );
			$ids         = array();

			if ( $slugs && is_array( $slugs ) ) {
				foreach ( $slugs as $slug ) {
					$id = array_search( $slug, $import_data['page'] );

					if ( $id ) {
						$ids[] = $id;
					}
				}
			}

			if ( $ids ) {
				return implode( ',', $ids );
			}
		}

		return '';
	}

	/**
	 * Parse metform tag
	 *
	 * Ex: get ID: essential:metform:slug:id
	 *
	 * @param array $tag tag.
	 *
	 * @return int
	 */
	private function metform_tag( $tag ) {
		$slug = $tag[2];
		$to   = $tag[3];

		if ( 'id' === $to ) {
			$import_data = get_option( essential_import_demo_key( $this->demo ), array() );

			if ( isset( $import_data['page'] ) ) {
				$metform_id = array_search( $slug, $import_data['page'] );

				if ( $metform_id ) {
					return $metform_id;
				}
			}
		}

		return '';
	}

	/**
	 * Manage post featured image
	 *
	 * @param int    $post_id post id.
	 * @param string $thumbnail thumbnail.
	 *
	 * @return int|bool
	 */
	private function manage_featured_image( $post_id, $thumbnail ) {
		if ( is_int( $thumbnail ) ) {
			$data = array( 'id' => $thumbnail );
		} else {
			$data = $this->image_exist( esc_url( $thumbnail ) );
		}

		if ( ! $data ) {
			$data = $this->add_image( $thumbnail );
		}

		if ( ! empty( $data['id'] ) ) {
			set_post_thumbnail( $post_id, $data['id'] );

			return $data['id'];
		}

		return false;
	}

	/**
	 * Manage demo favorite status
	 *
	 * @param object $request object.
	 *
	 * @return WP_Error|array
	 */
	public function manage_favorite( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$demo           = sanitize_text_field( $request->get_param( 'demo' ) );
		$action         = sanitize_text_field( $request->get_param( 'action' ) );
		$option_name    = essential_favorited_demo_key();
		$favorited_demo = get_option( $option_name, array() );

		if ( 'unset' === $action ) {
			unset( $favorited_demo[ $demo ] );
		} else {
			$favorited_demo[ $demo ] = $demo;
		}

		update_option( $option_name, $favorited_demo );

		return $this->response_success(
			array(
				'message' => esc_html__( 'Completed', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Manage demo status if they have been imported or not
	 *
	 * @param object $request object.
	 *
	 * @return WP_Error|array
	 */
	public function manage_demo( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$demo          = sanitize_text_field( $request->get_param( 'demo' ) );
		$action        = sanitize_text_field( $request->get_param( 'action' ) );
		$option_name   = essential_imported_demo_key();
		$imported_demo = get_option( $option_name, array() );

		if ( 'uninstall' === $action ) {
			unset( $imported_demo[ $demo ] );
		} else {
			$imported_demo[ $demo ] = $demo;
		}

		update_option( $option_name, $imported_demo );

		// We need to create an import backup if not yet.
		essential_create_import_backup();

		return $this->response_success(
			array(
				'message' => esc_html__( 'Completed', 'jeg-elementor-kit' ),
			)
		);
	}

	/**
	 * Import image
	 *
	 * @param object $request object.
	 *
	 * @return WP_Error|array
	 */
	public function import_image( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$image        = esc_url( $request->get_param( 'image' ) );
		$demo         = sanitize_text_field( $request->get_param( 'demo' ) );
		$data         = $this->image_exist( $image );
		$option_name  = essential_import_demo_key( $demo );
		$option_value = get_option( $option_name, array() );

		if ( ! $data ) {
			$data          = $this->add_image( $image );
			$data['count'] = true;
		}

		if ( $data && isset( $data['id'] ) ) {
			$option_value['image'][ $data['id'] ] = $data['id'];

			update_option( $option_name, $option_value );
		}

		$data['status'] = esc_html__( 'Downloading images', 'jeg-elementor-kit' );

		return $this->response_success( $data );
	}

	/**
	 * Handle import image, and return file ID when process complete
	 *
	 * @param string $image image.
	 *
	 * @return int|null
	 */
	private function add_image( $image ) {
		$file_name = basename( $image );
		$upload    = wp_upload_bits( $file_name, null, '' );

		if ( isset( $upload['file'] ) ) {
			$this->fetch_file( $image, $upload['file'] );
		}

		if ( isset( $upload['file'] ) && $upload['file'] ) {
			$file_loc  = $upload['file'];
			$file_name = basename( $upload['file'] );
			$file_type = wp_check_filetype( $file_name );

			$attachment = array(
				'post_mime_type' => $file_type['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_name ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			include_once ABSPATH . 'wp-admin/includes/image.php';
			$attach_id             = wp_insert_attachment( $attachment, $file_loc );
			$attach_data           = wp_generate_attachment_metadata( $attach_id, $file_loc );
			$attach_data['source'] = $image;

			wp_update_attachment_metadata( $attach_id, $attach_data );

			return array(
				'id'  => $attach_id,
				'url' => $upload['url'],
			);
		} else {
			return null;
		}
	}

	/**
	 * Download file and save to file system
	 *
	 * @param string $url url.
	 * @param string $file_path file path.
	 *
	 * @return array|bool
	 */
	public function fetch_file( $url, $file_path ) {
		$http     = new \WP_Http();
		$response = $http->get( $url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$headers             = wp_remote_retrieve_headers( $response );
		$headers['response'] = wp_remote_retrieve_response_code( $response );

		if ( false == $file_path ) {
			return $headers;
		}

		// GET request - write it to the supplied filename.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $file_path, wp_remote_retrieve_body( $response ), FS_CHMOD_FILE );

		return $headers;
	}

	/**
	 * Check whetever image exists
	 *
	 * @param string $url url.
	 *
	 * @return array|boolean
	 */
	private function image_exist( $url ) {
		$file        = pathinfo( $url );
		$attachments = new \WP_Query(
			array(
				'post_type' => 'attachment',
				'name'      => $file['filename'],
			)
		);

		foreach ( $attachments->posts as $post ) {
			$metadata = wp_get_attachment_metadata( $post->ID );

			if ( isset( $metadata['source'] ) && $url === $metadata['source'] ) {
				$attachment_url = wp_get_attachment_url( $post->ID );
				return array(
					'id'  => $post->ID,
					'url' => $attachment_url,
				);
			}

			return false;
		}

		return false;
	}

	/**
	 * Set Demos
	 *
	 * @param object $request args.
	 *
	 * @return WP_Error|array
	 */
	public function get_demos( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$remote_request = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/demo/list',
			array(
				'method'  => 'POST',
				'timeout' => 10,
				'body'    => array(
					'debug' => $request->get_param( 'debug' ),
				),
			)
		);

		$response = wp_remote_retrieve_body( $remote_request );
		return $this->response_success( json_decode( $response ) );
	}

	/**
	 * Set Demos
	 *
	 * @param object $request args.
	 *
	 * @return WP_Error|array
	 */
	public function get_demos_categories() {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$remote_request = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/demo/categories',
			array(
				'method'  => 'POST',
				'timeout' => 10,
				'body'    => array(
					'slug' => 'category',
				),
			)
		);

		$response = wp_remote_retrieve_body( $remote_request );
		return $this->response_success( json_decode( $response ) );
	}

	/**
	 * Get Config of Demo
	 *
	 * @param object $request args.
	 *
	 * @return WP_Error|array
	 */
	public function get_config( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$remote_request = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/getConfig',
			array(
				'method'  => 'POST',
				'timeout' => 10,
				'body'    => array(
					'demo'   => $request->get_param( 'demo' ),
					'domain' => $request->get_param( 'content' ),
					'code'   => $request->get_param( 'code' ),
				),
			)
		);

		$response = wp_remote_retrieve_body( $remote_request );

		return $this->response_success( json_decode( $response ) );
	}

	/**
	 * Get Content of Demo
	 *
	 * @param object $request args.
	 *
	 * @return WP_Error|array
	 */
	public function get_content( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		$remote_request = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/getContent',
			array(
				'method'  => 'POST',
				'timeout' => 10,
				'body'    => array(
					'demo'    => $request->get_param( 'demo' ),
					'content' => $request->get_param( 'content' ),
				),
			)
		);

		$response = wp_remote_retrieve_body( $remote_request );

		return $this->response_success( json_decode( $response ) );
	}

	/**
	 * Installing give plugin
	 *
	 * @param object $request args.
	 *
	 * @return WP_Error|array
	 */
	public function install_plugin( $request ) {
		// if ( ! wp_verify_nonce( sanitize_text_field( $request->get_param( 'nonce' ) ), 'jkit-dashboard', false ) ) {
		// return $this->response_error( esc_html__( 'You are not allowed to perform this action.', 'jeg-elementor-kit' ) );
		// }

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$plugin_details              = array_map( 'sanitize_text_field', $request->get_param( 'plugin' ) );
		$all_plugins                 = get_plugins();
		$is_plugin_already_installed = $is_plugin_need_updated = false;

		foreach ( $all_plugins as $key => $item ) {
			if ( strpos( $key, $plugin_details['file'] ) !== false ) {
				if ( isset( $plugin_details['version'] ) && version_compare( $item['Version'], $plugin_details['version'], '<' ) ) {
					$is_plugin_need_updated = true;
				} else {
					$is_plugin_already_installed = true;
				}
			}
		}

		if ( $is_plugin_already_installed ) {
			$activate_status = $this->activate_plugin( $plugin_details['file'] );

			$status = array(
				'success' => true,
				'plugin'  => sanitize_key( wp_unslash( $plugin_details['slug'] ) ),
				'status'  => esc_html__( 'Installing plugins', 'jeg-elementor-kit' ),
			);
		} else {
			$status = array(
				'success' => false,
			);

			if ( isset( $plugin_details['type'] ) && 'server' === $plugin_details['type'] ) {
				$domain = home_url();
				$code   = sanitize_text_field( $request->get_param( 'code' ) );
				$args   = array(
					'method'    => 'POST',
					'sslverify' => false,
					'body'      => build_query(
						array(
							'code'   => $code,
							'domain' => $domain,
						)
					),
				);

				$status['plugin']         = $plugin_details['slug'];
				$license_data             = essential_get_license();
				$plugin_details['source'] = add_query_arg(
					array(
						'domain' => $domain,
						'code'   => isset( $license_data['purchase_code'] ) ? $license_data['purchase_code'] : '',
						'plugin' => $plugin_details['slug'],
					),
					JEG_ELEMENT_SERVER_URL . 'wp-json/jkit-export/v1/installPlugin'
				);
				$plugin_source            = $plugin_details['source'];

			} elseif ( isset( $plugin_details['type'] ) && 'bundle' === $plugin_details['type'] ) {
				// $status['plugin']         = $plugin_details['slug'];
				// $plugin_details['source'] = ESSENTIAL_DIR_PLUGIN . $plugin_details['source'];
				// $plugin_source            = $plugin_details['source'];
			} else {
				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => sanitize_key( wp_unslash( $plugin_details['slug'] ) ),
						'fields' => array(
							'sections' => false,
						),
					)
				);

				if ( is_wp_error( $api ) && essential_is_wp_debug() ) {
					return $this->response_error( $api->get_error_message() );
				}

				$status['plugin'] = $api->name;
				$plugin_source    = $api->download_link;
			}

			$skin     = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );

			add_filter( 'http_request_args', array( $this, 'turn_off_reject_unsafe_urls' ) );

			if ( $is_plugin_need_updated ) {
				if ( isset( $plugin_details['type'] ) && 'bundle' === $plugin_details['type'] ) {
					$this->register_update_plugin( $plugin_details );
				}

				$result = $upgrader->upgrade( $plugin_details['file'] );
			} else {
				$result = $upgrader->install( $plugin_source );
			}

			// need to remove the core filter
			// remove_filter( 'http_request_args', array( $this, 'turn_off_reject_unsafe_urls' ) );

			if ( is_wp_error( $result ) && essential_is_wp_debug() ) {
				return $this->response_error( $result->get_error_message() );
			} elseif ( is_wp_error( $skin->result ) && essential_is_wp_debug() ) {
				return $this->response_error( $skin->result->get_error_message() );
			} elseif ( $skin->get_errors()->has_errors() && essential_is_wp_debug() ) {
				return $this->response_error( $skin->get_error_messages() );
			} elseif ( is_null( $result ) ) {
				global $wp_filesystem;

				$message = esc_html__( 'Unable to connect to the wp_filesystem.', 'jeg-elementor-kit' );

				if ( $wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					$message = esc_html( $wp_filesystem->errors->get_error_message() );
				}

				if ( essential_is_wp_debug() ) {
					return $this->response_error( $message );
				}
			}

			$activate_status = $this->activate_plugin( $plugin_details['file'] );
		}

		if ( $activate_status && ! is_wp_error( $activate_status ) ) {
			$status['success'] = true;
			$status['status']  = esc_html__( 'Installing plugins', 'jeg-elementor-kit' );
		}

		return $this->response_success( $status );
	}

	/**
	 * Turn off reject unsafe urls
	 *
	 * @param array $args Arguments used for the HTTP request.
	 *
	 * @return array
	 */
	public function turn_off_reject_unsafe_urls( $args ) {
		$args['reject_unsafe_urls'] = false;

		return $args;
	}

	/**
	 * Activate given plugin
	 *
	 * @param string $file file.
	 *
	 * @return WP_Error|boolean
	 */
	private function activate_plugin( $file ) {
		if ( current_user_can( 'activate_plugin', $file ) && is_plugin_inactive( $file ) ) {
			$result = activate_plugin( $file, false, false );

			if ( is_wp_error( $result ) ) {
				return $result;
			} else {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register data for plugin update
	 *
	 * @param array $plugin plugin.
	 */
	private function register_update_plugin( $plugin ) {
		extract( $plugin );

		$repo_updates = get_site_transient( 'update_plugins' );

		if ( ! is_object( $repo_updates ) ) {
			$repo_updates = new \stdClass();
		}

		$file_path = $file;

		if ( empty( $repo_updates->response[ $file_path ] ) ) {
			$repo_updates->response[ $file_path ] = new \stdClass();
		}

		$repo_updates->response[ $file_path ]->slug        = $slug;
		$repo_updates->response[ $file_path ]->plugin      = $file_path;
		$repo_updates->response[ $file_path ]->new_version = $version;
		$repo_updates->response[ $file_path ]->package     = $source;
		$repo_updates->response[ $file_path ]->url         = '';

		set_site_transient( 'update_plugins', $repo_updates );
	}

	/**
	 * Template Library Data Handler
	 *
	 * @param array $data Parameter Requests.
	 *
	 * @throws \Exception Exception in case the WP_CLI::add_command fails.
	 */
	public function essential_template_library_data_handler( $data ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'Access Denied' );
		}

		if ( ! empty( $data['editor_post_id'] ) ) {
			$editor_post_id = absint( $data['editor_post_id'] );

			if ( ! get_post( $editor_post_id ) ) {
				throw new \Exception( __( 'Post not found', 'jeg-elementor-kit' ) );
			}

			\Elementor\Plugin::instance()->db->switch_to_post( $editor_post_id );
		}

		if ( empty( $data['template_id'] ) ) {
			throw new \Exception( __( 'Template id missing', 'jeg-elementor-kit' ) );
		}

		$template_name = $data['template_id'];
		$template_file = $data['file'];

		$data     = Meta::instance()->get_option( 'template_library_data' );
		$response = wp_remote_get( $data->paths->{$template_name} . '/' . $template_file );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$json_data    = wp_remote_retrieve_body( $response );
			$decoded_data = json_decode( $json_data );

			if ( null !== $decoded_data ) {
				return $json_data;
			} else {
				throw new \Exception( __( 'Failed to decode JSON data.', 'jeg-elementor-kit' ) );
			}
		} else {
			throw new \Exception( __( 'HTTP request error: ', 'jeg-elementor-kit' ) . $response->get_error_message() );
		}
	}


	/**
	 * Template Library Favorite Handler
	 *
	 * @param array $data Parameter Requests.
	 *
	 * @throws \Exception Exception in case the WP_CLI::add_command fails.
	 */
	public function essential_template_favorite_handler( $data ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'Access Denied' );
		}

		if ( ! empty( $data['editor_post_id'] ) ) {
			$editor_post_id = absint( $data['editor_post_id'] );

			if ( ! get_post( $editor_post_id ) ) {
				throw new \Exception( __( 'Post not found', 'jeg-elementor-kit' ) );
			}

			\Elementor\Plugin::instance()->db->switch_to_post( $editor_post_id );
		}

		$key       = 'essential_template_favorite';
		$user_id   = get_current_user_id();
		$user_data = get_user_meta( $user_id, $key, true );

		if ( 'remove' === $data['action'] ) {
			unset( $user_data[ $data['template'] ][ $data['file'] ] );
		} elseif ( 'add' === $data['action'] ) {
			if ( ! is_array( $user_data ) ) {
				$user_data = array();
			}

			$user_data[ $data['template'] ][ $data['file'] ] = $data['file'];
		}

		update_user_meta( $user_id, $key, $user_data );

		return $user_data;
	}


	/**
	 * Template Library Image Handler
	 *
	 * @param array $data Parameter Requests.
	 *
	 * @throws \Exception Exception in case the WP_CLI::add_command fails.
	 */
	public function essential_template_library_manage_image_handler( $data ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( 'Access Denied' );
		}

		if ( ! empty( $data['editor_post_id'] ) ) {
			$editor_post_id = absint( $data['editor_post_id'] );

			if ( ! get_post( $editor_post_id ) ) {
				throw new \Exception( __( 'Post not found', 'jeg-elementor-kit' ) );
			}

			\Elementor\Plugin::instance()->db->switch_to_post( $editor_post_id );
		}

		$image        = esc_url( $data['image']['url'] );
		$demo         = sanitize_text_field( $data['template_id'] );
		$data         = $this->image_exist( $image );
		$option_name  = essential_import_demo_key( $demo );
		$option_value = get_option( $option_name );

		if ( ! $data ) {
			$data          = $this->add_image( $image );
			$data['count'] = true;
		}

		if ( $data && isset( $data['id'] ) ) {
			$option_value['image'][ $data['id'] ] = $data['id'];

			update_option( $option_name, $option_value );
		}

		$data['status'] = esc_html__( 'Downloading images', 'jeg-elementor-kit' );

		return $this->response_success( $data );
	}

	/**
	 * Load file content
	 *
	 * @param string $path path.
	 *
	 * @return string
	 */
	private function load_file_content( $path ) {
		ob_start();
		include $path;
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * Return error response
	 *
	 * @param array|string $message args.
	 *
	 * @return \WP_REST_Response
	 */
	private function response_error( $message, $code = 400 ) {
		return new \WP_REST_Response(
			array(
				'message' => $message,
			),
			$code
		);
	}

	/**
	 * Return success response
	 *
	 * @param array $args args.
	 *
	 * @return \WP_REST_Response
	 */
	private function response_success( $args ) {
		return new \WP_REST_Response( $args, 200 );
	}

	/**
	 * Proxy handler that forwards UTM tracker requests to pro.jegkit.com
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function utm_proxy_handler( $request ) {
		// nonce validated in permission callback (utm_permission_check) — no duplicate check here

		$body = $request->get_body();
		$headers = array(
			'Content-Type' => $request->get_header( 'content-type' ) ?: 'application/json',
		);

		$remote = wp_remote_post(
			'https://pro.jegkit.com/wp-json/jeg-kit-license/v1/utm-tracker/',
			array(
				'body'      => $body,
				'headers'   => $headers,
				'timeout'   => 10,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $remote ) ) {
			return $this->response_error( $remote->get_error_message(), 502 );
		}

		$code = wp_remote_retrieve_response_code( $remote );
		$resp_body = wp_remote_retrieve_body( $remote );

		// try decode JSON, otherwise return raw body
		$decoded = json_decode( $resp_body, true );

		return new \WP_REST_Response( $decoded ?: $resp_body, $code );
	}

	/**
	 * Permission check for UTM proxy endpoint.
	 * Accepts a localized nonce (`jkit-dashboard`) or falls back to admin permission.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function utm_permission_check( $request ) {
		// Check WP REST nonce first (sent as X-WP-Nonce header)
		$header_nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_header( 'x_wp_nonce' );
		if ( $header_nonce && wp_verify_nonce( $header_nonce, 'wp_rest' ) ) {
			return true;
		}

		// Fallback to existing admin permission check
		if ( function_exists( 'jkit_permission_check_admin' ) ) {
			return (bool) call_user_func( 'jkit_permission_check_admin' );
		}

		return new \WP_Error( 'rest_forbidden', esc_html__( 'You are not allowed to access this endpoint.', 'jeg-elementor-kit' ), array( 'status' => 403 ) );
	}
}
