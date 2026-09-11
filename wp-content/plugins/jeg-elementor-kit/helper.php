<?php
/**
 * Jeg Kit Helper
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 1.0.0
 */

if ( ! function_exists( 'jkit_get_menu_option' ) ) {
	/**
	 * Get menu list using cache
	 *
	 * @return array
	 */
	function jkit_get_menu_option() {
		$menus = wp_cache_get( 'menu', 'jeg-elementor-kit' );

		if ( ! $menus ) {
			$menus = wp_get_nav_menus();
			wp_cache_set( 'menu', $menus, 'jeg-elementor-kit' );
		}

		$menus = array_combine( wp_list_pluck( $menus, 'slug' ), wp_list_pluck( $menus, 'name' ) );

		return $menus;
	}
}

if ( ! function_exists( 'essential_is_wp_debug' ) ) {
	/**
	 * Check if wp_debug mode is enable
	 *
	 * @return boolean
	 */
	function essential_is_wp_debug() {
		return defined( 'WP_DEBUG' ) && true === WP_DEBUG;
	}
}

if ( ! function_exists( 'essential_get_license' ) ) {
	/**
	 * Get license data
	 *
	 * @return string
	 */
	function essential_get_license() {
		return get_option( essential_get_license_optionname(), array() );
	}
}

if ( ! function_exists( 'essential_get_license_optionname' ) ) {
	/**
	 * Get license option name
	 *
	 * @return string
	 */
	function essential_get_license_optionname() {
		return 'essential_license';
	}
}

if ( ! function_exists( 'essential_imported_demo_key' ) ) {
	/**
	 * Imported demo option name
	 *
	 * @return string
	 */
	function essential_imported_demo_key() {
		return ESSENTIAL_FRAMEWORK . '_option_imported_demo';
	}
}

if ( ! function_exists( 'essential_create_import_backup' ) ) {
	/**
	 * Create import backup once
	 */
	function essential_create_import_backup() {
		$args         = array();
		$option_name  = essential_import_backup_key();
		$option_value = get_option( $option_name );
		$active_kit   = get_option( 'elementor_active_kit' );

		if ( $active_kit ) {
			$args['style'] = $active_kit;
		}

		if ( ! $option_value && ! empty( $args ) ) {
			update_option( $option_name, $args );
		}
	}
}

if ( ! function_exists( 'essential_favorited_demo_key' ) ) {
	/**
	 * Favorited demo option name
	 *
	 * @return string
	 */
	function essential_favorited_demo_key() {
		return ESSENTIAL_FRAMEWORK . '_option_favorited_demo';
	}
}

if ( ! function_exists( 'essential_import_backup_key' ) ) {
	/**
	 * Backup import option name
	 *
	 * @return string
	 */
	function essential_import_backup_key() {
		return ESSENTIAL_FRAMEWORK . '_option_import_backup';
	}
}

if ( ! function_exists( 'essential_get_theme_filter_key' ) ) {
	/**
	 * Get Theme Filter Key
	 *
	 * @return string
	 */
	function essential_get_theme_filter_key() {
		$default = 'jkit';

		return apply_filters( 'essential_get_theme_filter_key', $default );
	}
}

if ( ! function_exists( 'essential_import_menu' ) ) {
	/**
	 * Import menu
	 *
	 * @param string $menu_title .
	 * @param array  $menu_items .
	 * @param string $demo .
	 *
	 * @return int
	 */
	function essential_import_menu( $menu_title, $menu_items, $demo ) {
		$menu_exists = wp_create_nav_menu( $menu_title . ' ' . ucwords( $demo ) );
		$menu_temp   = array();
		$import_data = get_option( essential_import_demo_key( $demo ), array() );

		foreach ( $menu_items as $menu_item ) {
			switch ( $menu_item['object'] ) {
				case 'page':
				case 'post':
				case 'product':
					if ( isset( $import_data['page'] ) ) {
						$id = array_search( $menu_item['slug'], $import_data['page'] );

						if ( $id ) {
							$menu_item_data = array(
								'menu-item-object-id' => $id,
								'menu-item-object'    => $menu_item['object'],
								'menu-item-type'      => $menu_item['type'],
								'menu-item-title'     => $menu_item['title'],
								'menu-item-status'    => 'publish',
								'menu-item-parent-id' => isset( $menu_item['parent'] ) && isset( $menu_temp[ $menu_item['parent'] ] ) ? $menu_temp[ $menu_item['parent'] ] : '',
							);

							$new_menu = wp_update_nav_menu_item( $menu_exists, 0, $menu_item_data );

							if ( $new_menu ) {
								$menu_temp[ $menu_item['slug'] ] = $new_menu;
							}
						}
					}
					break;

				case 'category':
					$category = get_term_by( 'slug', $menu_item['slug'], $menu_item['object'] );

					if ( $category ) {
						$menu_item_data = array(
							'menu-item-object-id' => $category->term_id,
							'menu-item-object'    => $menu_item['object'],
							'menu-item-type'      => $menu_item['type'],
							'menu-item-title'     => $menu_item['title'],
							'menu-item-status'    => 'publish',
							'menu-item-parent-id' => isset( $menu_item['parent'] ) && isset( $menu_temp[ $menu_item['parent'] ] ) ? $menu_temp[ $menu_item['parent'] ] : '',
						);

						$new_menu = wp_update_nav_menu_item( $menu_exists, 0, $menu_item_data );

						if ( $new_menu ) {
							$menu_temp[ $menu_item['slug'] ] = $new_menu;
						}
					}
					break;

				case 'custom':
					if ( strpos( $menu_item['url'], '/shop' ) !== false ) {
						$menu_item['url'] = home_url() . '/shop';
					}

					$menu_item_data = array(
						'menu-item-object-id' => '',
						'menu-item-object'    => $menu_item['object'],
						'menu-item-type'      => $menu_item['type'],
						'menu-item-title'     => $menu_item['title'],
						'menu-item-url'       => $menu_item['url'],
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => isset( $menu_item['parent'] ) && isset( $menu_temp[ $menu_item['parent'] ] ) ? $menu_temp[ $menu_item['parent'] ] : '',
					);

					$new_menu = wp_update_nav_menu_item( $menu_exists, 0, $menu_item_data );

					if ( $new_menu ) {
						$menu_temp[ $menu_item['slug'] ] = $new_menu;
					}
					break;
			}

			if ( isset( $menu_item['mega'] ) ) {
				$query = new WP_Query(
					array(
						'name'           => $menu_item['mega'],
						'post_type'      => 'elementor_library',
						'post_status'    => 'publish',
						'posts_per_page' => 1
					)
				);

				if ( $query->have_posts() ) {
					update_post_meta( $new_menu, 'menu_item_jkit_mega_menu', array( 'jkit_mega_menu' => $query->posts[0]->ID ) );
				}
			}
		}

		return $menu_exists;
	}
}

if ( ! function_exists( 'essential_import_demo_key' ) ) {
	/**
	 * Import demo option name
	 *
	 * @param array $demo .
	 * @return string
	 */
	function essential_import_demo_key( $demo ) {
		return ESSENTIAL_FRAMEWORK . '_option_import_' . $demo;
	}
}


if ( ! function_exists( 'jkit_edit_post' ) ) {
	/**
	 * Get post edit link
	 *
	 * @param  int    $post_id  Post ID.
	 * @param  string $position Link position.
	 * @return bool|string
	 */
	function jkit_edit_post( $post_id, $position = 'left' ) {
		if ( current_user_can( 'edit_posts' ) ) {
			$url = get_edit_post_link( $post_id );

			return '<a class="jkit-edit-post ' . $position . '" href="' . $url . '" target="_blank">
				<i class="fas fa-pencil-alt"></i>
				<span>' . esc_html__( 'edit post', 'jeg-elementor-kit' ) . '</span>
			</a>';
		}

		return false;
	}
}

if ( ! function_exists( 'jkit_get_post_date' ) ) {
	/**
	 * Get post date
	 *
	 * @param  string       $format Get post format.
	 * @param  int|\WP_Post $post   Optional. Post ID or post object.
	 * @param  string       $type Date type.
	 * @return false|string
	 */
	function jkit_get_post_date( $format = '', $post = null, $type = '' ) {
		if ( 'published' === $type ) {
			return get_the_date( $format, $post );
		}

		return get_the_modified_date( $format, $post );
	}
}

if ( ! function_exists( 'jkit_get_post_ago_time' ) ) {
	/**
	 * Get time in ago format
	 *
	 * @param string       $type Date type.
	 * @param int|\WP_Post $post Optional. Post ID or post object.
	 * @return string
	 */
	function jkit_get_post_ago_time( $type, $post ) {
		if ( 'published' === $type ) {
			$output = jkit_ago_time( human_time_diff( get_the_time( 'U', $post ), time() ) );
		} else {
			$output = jkit_ago_time( human_time_diff( get_the_modified_time( 'U', $post ), time() ) );
		}

		return $output;
	}
}

if ( ! function_exists( 'jkit_ago_time' ) ) {
	/**
	 * Format Time ago string.
	 *
	 * @param  string $time time ago from now.
	 * @return string
	 */
	function jkit_ago_time( $time ) {
		return esc_html(
			sprintf(
				/* translators: 1: Time from now. */
				esc_html__( '%s ago', 'jeg-elementor-kit' ),
				$time
			)
		);
	}
}

if ( ! function_exists( 'jkit_get_comments_number' ) ) {
	/**
	 * Get comment number
	 *
	 * @param  int $post_id Post ID.
	 * @return mixed
	 */
	function jkit_get_comments_number( $post_id = 0 ) {
		$comments_number = get_comments_number( $post_id );

		return apply_filters( 'jkit_get_comments_number', $comments_number, $post_id );
	}
}

if ( ! function_exists( 'jkit_get_respond_link' ) ) {
	/**
	 * Get respond link
	 *
	 * @param  null $post_id Post ID.
	 * @return string
	 */
	function jkit_get_respond_link( $post_id = null ) {
		return esc_url( get_the_permalink( $post_id ) ) . '#respond';
	}
}

/** Start custom template directory */
if ( ! function_exists( 'jkit_get_template_part' ) ) {
	/**
	 * Get custom tempate directory
	 *
	 * @param string      $slug Template slug.
	 * @param string|null $name Template name.
	 * @param bool        $dir Template directory.
	 */
	function jkit_get_template_part( $slug, $name = null, $dir = JEG_ELEMENTOR_KIT_DIR ) {
		do_action( "jkit_get_template_part_{$slug}", $slug, $name, $dir );
		$templates = array();
		if ( isset( $name ) ) {
			$templates[] = "{$slug}-{$name}.php";
		}
		$templates[] = "{$slug}.php";
		if ( ! $dir ) {
			$dir = get_template_directory();
		}

		jkit_get_template_path( $templates, true, false, $dir );
	}
}

if ( ! function_exists( 'jkit_get_template_path' ) ) {
	/**
	 * Get custom template path
	 *
	 * @param array  $template_names Templates.
	 * @param bool   $load Load template.
	 * @param bool   $require_once Require once.
	 *
	 * @param string $dir Template directory.
	 *
	 * @return mixed
	 */
	function jkit_get_template_path( $template_names, $load = false, $require_once = true, $dir = JEG_ELEMENTOR_KIT_DIR ) {
		$located = '';
		if ( $dir ) {
			foreach ( (array) $template_names as $template_name ) {
				if ( ! $template_name ) {
					continue;
				}
				/* search file within the $dir only */
				if ( file_exists( $dir . $template_name ) ) {
					$located = $dir . $template_name;
					break;
				}
			}
			if ( $load && '' !== $located ) {
				load_template( $located, $require_once );
			}
		}

		return $located;
	}
}
/** End custom template directory */

if ( ! function_exists( 'jkit_get_nonce_identifier' ) ) {
	/**
	 * Get nonce identifier
	 *
	 * @return string
	 */
	function jkit_get_nonce_identifier( $slug = '' ) {
		if ( ! is_null( $slug ) ) {
			$slug = '-' . $slug;
		}

		return 'jkit-nonce' . $slug;
	}
}

if ( ! function_exists( 'jkit_create_global_nonce' ) ) {
	/**
	 * Get nonce identifier
	 *
	 * @return string
	 */
	function jkit_create_global_nonce( $slug = '' ) {
		return wp_create_nonce( jkit_get_nonce_identifier( $slug ) );
	}
}

if ( ! function_exists( 'jkit_load_resource_limit' ) ) {
	/**
	 * Number of limit we can load resouce to prevent system crash
	 *
	 * @return int
	 */
	function jkit_load_resource_limit() {
		return apply_filters( 'jkit_load_resource_limit', 25 );
	}
}

if ( ! function_exists( 'jkit_get_taxonomies' ) ) {
	/**
	 * Retrieves a list of registered taxonomy names or objects.
	 *
	 * @return array
	 */
	function jkit_get_taxonomies( $label = true ) {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);

		if ( $label ) {
			foreach ( $taxonomies as $taxonomy ) {
				$object                  = get_taxonomy( $taxonomy );
				$taxonomies[ $taxonomy ] = $object->labels->name;
			}
		} else {
			$taxonomies = array_keys( $taxonomies );
		}

		return $taxonomies;
	}
}

if ( ! function_exists( 'jkit_get_public_post_type' ) ) {
	/**
	 * Get public post type with label
	 *
	 * @return array
	 */
	function jkit_get_public_post_type() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);

		$exclude = \Jeg\Elementor_Kit\Dashboard\Dashboard::post_type_list();

		foreach ( $types as $type => $data ) {
			if ( in_array( $type, $exclude ) ) {
				unset( $types[ $type ] );
			} else {
				$object         = get_post_type_object( $type );
				$types[ $type ] = $object->labels->singular_name;
			}
		}

		return $types;
	}
}

if ( ! function_exists( 'jkit_get_public_post_type_array' ) ) {
	/**
	 * Get public post type
	 *
	 * @return array
	 */
	function jkit_get_public_post_type_array() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			)
		);

		/** Remove header builder post type */
		foreach ( \Jeg\Elementor_Kit\Dashboard\Dashboard::post_type_list() as $list => $data ) {
			unset( $types[ $list ] );
		}

		return array_keys( $types );
	}
}

if ( ! function_exists( 'jkit_get_element_data' ) ) {
	/**
	 * Jeg Kit Get Element Data
	 *
	 * @param $type
	 * @param $meta
	 *
	 * @return array
	 */
	function jkit_get_element_data( $type, $meta = null ) {
		return array(
			'publish' => jkit_get_element( 'publish', $type, $meta ),
			'draft'   => jkit_get_element( 'draft', $type, $meta ),
		);
	}
}

if ( ! function_exists( 'jkit_is_free_header_footer_template_limit_reached' ) ) {
	/**
	 * Check whether free users already have a Header/Footer template.
	 *
	 * @param string $type Template post type.
	 *
	 * @return bool
	 */
	function jkit_is_free_header_footer_template_limit_reached( $type ) {
		if ( defined( 'JEG_KIT_PRO' ) || ! in_array( $type, array( 'jkit-header', 'jkit-footer' ), true ) ) {
			return false;
		}

		$templates = jkit_get_element_data( $type );

		return ! empty( $templates['publish'] ) || ! empty( $templates['draft'] );
	}
}

if ( ! function_exists( 'jkit_get_element' ) ) {
	/**
	 * Jeg Kit Get Element
	 *
	 * @param $status
	 * @param $type
	 * @param $meta
	 *
	 * @return array
	 */
	function jkit_get_element( $status, $type, $meta = null ) {
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

		if ( $meta ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'jkit-template-type',
					'value' => $meta,
				),
			);
		}

		$query  = get_posts( $args );
		$result = array();

		if ( $query ) {
			foreach ( $query as $post ) {
				$result[] = array(
					'id'    => $post->ID,
					'title' => $post->post_title,
					'url'   => \Jeg\Elementor_Kit\Dashboard\Dashboard::editor_url( $post->ID ),
				);
			}
		}

		wp_reset_postdata();

		return $result;
	}
}
if ( ! function_exists( 'get_jkit_template_classes' ) ) {
	/**
	 * Get Jeg Kit additional temlpate classes
	 *
	 * @param $template
	 *
	 * @return string
	 */
	function get_jkit_template_classes( $template = 'header' ) {
		$html_classes = '';
		$classes      = array();

		if ( 'header' === $template ) {
			$classes = apply_filters( 'jkit_header_template_classes', array() );
		} elseif ( '404' === $template ) {
			$classes = apply_filters( 'jkit_404_template_classes', array() );
		} elseif ( 'single' === $template ) {
			$classes = apply_filters( 'jkit_single_template_classes', array() );
		}

		$classes = apply_filters( 'jkit_custom_template_classes', $classes, $template );

		if ( $classes && ! empty( $classes ) ) {
			foreach ( $classes as $class ) {
				$html_classes .= sanitize_html_class( $class ) . ' ';
			}
		}

		return $html_classes;
	}
}

if ( ! function_exists( 'jkit_extract_ids' ) ) {
	/**
	 * Extract ID from Query
	 *
	 * @param $items
	 *
	 * @return array
	 */
	function jkit_extract_ids( $items ) {
		$id = array();
		foreach ( $items as $item ) {
			$id[] = $item['id'];
		}

		return $id;
	}
}

if ( ! function_exists( 'jkit_remove_array' ) ) {
	/**
	 * Remove Array from List
	 *
	 * @param $key
	 * @param $array
	 *
	 * @return mixed
	 */
	function jkit_remove_array( $key, $array ) {
		if ( ( $key = array_search( $key, $array ) ) !== false ) {
			unset( $array[ $key ] );
		}

		return $array;
	}
}

if ( ! function_exists( 'jkit_get_elementor_saved_template_option' ) ) {
	/**
	 * Get elementor saved template option
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	function jkit_get_elementor_saved_template_option( $args = array() ) {
		$options = array();

		$default_args = array(
			'post_type'      => 'elementor_library',
			'posts_per_page' => -1,
		);

		$args           = array_replace( $default_args, $args );
		$page_templates = get_posts( $args );

		if ( ! empty( $page_templates ) && ! is_wp_error( $page_templates ) ) {
			foreach ( $page_templates as $post ) {
				$options[ $post->ID ] = $post->post_title;
			}
		}

		return $options;
	}
}

if ( ! function_exists( 'jkit_get_selected_elementor_template' ) ) {
	/**
	 * Get the valid Elementor Template ID
	 *
	 * @param int|string $template_id Template ID.
	 * @param array      $args Query args.
	 *
	 * @return array
	 */
	function jkit_get_selected_elementor_template( $template_id, $args = array() ) {
		$available_templates = jkit_get_elementor_saved_template_option( $args );

		if ( ! empty( $available_templates ) && array_key_exists( $template_id, $available_templates ) ) {
			return $template_id;
		}

		return false;
	}
}

if ( ! function_exists( 'jkit_get_responsive_breakpoints' ) ) {
	/**
	 * Get Elementor responsive breakpoints
	 *
	 * @return array
	 */
	function jkit_get_responsive_breakpoints() {
		$breakpoints = array();

		$elementor = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

		foreach ( $elementor as $key => $breakpoint ) {
			array_push(
				$breakpoints,
				array(
					'key'   => $key,
					'value' => $breakpoint->get_value(),
					'label' => $breakpoint->get_label(),
				)
			);
		}

		usort(
			$breakpoints,
			function ( $a, $b ) {
				return $b['value'] - $a['value'];
			}
		);

		return $breakpoints;
	}
}

if ( ! function_exists( 'jkit_is_preview_mode' ) ) {
	/**
	 * Check if current page is on the Elementor preview mode
	 *
	 * @return boolean
	 */
	function jkit_is_preview_mode() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		return \Elementor\Plugin::instance()->preview->is_preview_mode();
	}
}

if ( ! function_exists( 'jkit_remove_form_control' ) ) {
	/**
	 * Remove Form Control.
	 * Conditions for not using the Form Control from the Jeg Kit. Usually used when there is a conflict with another version of Bootstrap.
	 *
	 * @return bool
	 */
	function jkit_remove_form_control() {
		$conditions = false;
		$page       = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		if ( $page ) {
			$lists = array( 'gf_', 'formidable', 'bookly-', 'vik', 'wpdatatables-' );

			foreach ( $lists as $list ) {
				if ( strpos( $page, $list ) !== false ) {
					$conditions = true;
					break;
				}
			}
		}

		return apply_filters( 'jkit_remove_form_control_conditions', $conditions );
	}
}

if ( ! function_exists( 'jkit_render_guteverse_banner' ) ) {
	/**
	 * Render Gutenverse Banner
	 */
	function jkit_render_guteverse_banner() {
		$fetch = wp_remote_post( 'https://gutenverse.com/wp-json/gutenverse-banner/v1/bannerdata' );
		if ( $fetch ) {
			$data = wp_remote_retrieve_body( $fetch );
			$data = json_decode( $data );
		}

		$banner_assets = JEG_ELEMENTOR_KIT_URL . '/assets/banner/gutenverse/';

		wp_enqueue_style( 'gutenverse-banner', $banner_assets . 'style.css', array(), JEG_ELEMENTOR_KIT_VERSION );
		?>
		<div class="gutenverse-banner <?php echo ( isset( $data->url ) && isset( $data->banner ) ) ? 'fetch' : ''; ?>">
			<?php if ( isset( $data->url ) && isset( $data->banner ) ) : ?>
				<a href="<?php echo esc_url( $data->url ); ?>" target="_blank">
					<img src="<?php echo esc_url( $data->banner ); ?>" width="300px" height="300px" loading="lazy" alt="Logo" />
				</a>
			<?php else : ?>
				<div class="banner-content">
					<div class="logo-wrapper"><img class="logo" src="<?php echo esc_url( $banner_assets ); ?>gutenverse-logo.svg" loading="lazy" alt="Logo" /></div>
					<div class="main-content">
						Advanced Addons for Gutenberg Or Fullsite Editing (FSE)
					</div>
					<div class="buttons">
						<div class="plugin-link">
							<a href="<?php echo esc_url( network_admin_url( 'plugin-install.php?s=gutenverse&tab=search&type=term' ) ); ?>" target="_blank" rel="noreferrer">
								Try Gutenverse
							</a>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'jkit_allowed_style_attr' ) ) {

	add_filter( 'safe_style_css', 'jkit_allowed_style_attr' );

	/**
	 * Allowed style attribute
	 *
	 * @param array $styles
	 *
	 * @return array
	 */
	function jkit_allowed_style_attr( $styles ) {
		$styles[] = 'display';

		return $styles;
	}
}

if ( ! function_exists( 'jkit_allowed_html' ) ) {

	add_filter( 'wp_kses_allowed_html', 'jkit_allowed_html', 99 );

	/**
	 * Allowed HTML List by Jeg Kit
	 */
	function jkit_allowed_html( $allowedtags = array() ) {
		$allowedtags['img'] = array_merge(
			isset( $allowedtags['img'] ) ? $allowedtags['img'] : array(),
			array(
				'loading'  => true,
				'id'       => true,
				'decoding' => true,
				'sizes'    => true,
			)
		);

		$allowedtags['a'] = array_merge(
			isset( $allowedtags['a'] ) ? $allowedtags['a'] : array(),
			array(
				'aria-label'    => true,
				'rel'           => true,
				'data-*'        => true,
				'aria-expanded' => true,
				'aria-controls' => true,
			)
		);

		$allowedtags['i'] = array_merge(
			isset( $allowedtags['i'] ) ? $allowedtags['i'] : array(),
			array(
				'aria-hidden' => true,
				'class'       => true,
			)
		);

		$allowedtags['link'] = array_merge(
			isset( $allowedtags['link'] ) ? $allowedtags['link'] : array(),
			array(
				'rel'  => true,
				'href' => true,
			)
		);

		$allowedtags['legend'] = array_merge(
			isset( $allowedtags['legend'] ) ? $allowedtags['legend'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['form'] = array_merge(
			isset( $allowedtags['form'] ) ? $allowedtags['form'] : array(),
			array(
				'method'       => true,
				'id'           => true,
				'class'        => true,
				'role'         => true,
				'action'       => true,
				'data-*'       => true,
				'autocomplete' => true,
			)
		);

		$allowedtags['fieldset'] = array_merge(
			isset( $allowedtags['fieldset'] ) ? $allowedtags['fieldset'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['input'] = array_merge(
			isset( $allowedtags['input'] ) ? $allowedtags['input'] : array(),
			array(
				'type'         => true,
				'name'         => true,
				'id'           => true,
				'class'        => true,
				'placeholder'  => true,
				'required'     => true,
				'value'        => true,
				'step'         => true,
				'min'          => true,
				'max'          => true,
				'title'        => true,
				'size'         => true,
				'inputmode'    => true,
				'autocomplete' => true,
			)
		);

		$allowedtags['label'] = array_merge(
			isset( $allowedtags['label'] ) ? $allowedtags['label'] : array(),
			array(
				'id'    => true,
				'class' => true,
				'for'   => true,
			)
		);

		$allowedtags['canvas'] = array_merge(
			isset( $allowedtags['canvas'] ) ? $allowedtags['canvas'] : array(),
			array(
				'height' => true,
				'width'  => true,
				'id'     => true,
				'class'  => true,
			)
		);

		$allowedtags['div'] = array_merge(
			isset( $allowedtags['div'] ) ? $allowedtags['div'] : array(),
			array(
				'style'    => true,
				'data-*'   => true,
				'tabindex' => true,
			)
		);

		$allowedtags['linearGradient'] = array_merge(
			isset( $allowedtags['linearGradient'] ) ? $allowedtags['linearGradient'] : array(),
			array(
				'gradientUnits'     => true,
				'gradientTransform' => true,
				'href'              => true,
				'spreadMethod'      => true,
				'x1'                => true,
				'x2'                => true,
				'y1'                => true,
				'y2'                => true,
				'id'                => true,
				'class'             => true,
				'style'             => true,
			)
		);

		$allowedtags['stop'] = array_merge(
			isset( $allowedtags['stop'] ) ? $allowedtags['stop'] : array(),
			array(
				'offset' => true,
			)
		);

		$allowedtags['svg'] = array_merge(
			isset( $allowedtags['svg'] ) ? $allowedtags['svg'] : array(),
			array(
				'id'                  => true,
				'xmlns'               => true,
				'viewbox'             => true,
				'preserveaspectratio' => true,
			)
		);

		$allowedtags['path'] = array_merge(
			isset( $allowedtags['path'] ) ? $allowedtags['path'] : array(),
			array(
				'd'                   => true,
				'pathLength'          => true,
				'id'                  => true,
				'tabindex'            => true,
				'class'               => true,
				'style'               => true,
				'requiredExtensions'  => true,
				'systemLanguage'      => true,
				'clip-path'           => true,
				'clip-rule'           => true,
				'color'               => true,
				'color-interpolation' => true,
				'color-rendering'     => true,
				'cursor'              => true,
				'display'             => true,
				'fill'                => true,
				'fill-opacity'        => true,
				'fill-rule'           => true,
				'filter'              => true,
				'mask'                => true,
				'opacity'             => true,
				'pointer-events'      => true,
				' shape-rendering'    => true,
				'stroke'              => true,
				'stroke-dasharray'    => true,
				'stroke-dashoffset'   => true,
				'stroke-linecap'      => true,
				'stroke-linejoin'     => true,
				'stroke-miterlimit'   => true,
				'stroke-opacity'      => true,
				'stroke-width'        => true,
				'transform'           => true,
				'vector-effect'       => true,
				'visibility'          => true,
			)
		);

		$allowedtags['select'] = array_merge(
			isset( $allowedtags['select'] ) ? $allowedtags['select'] : array(),
			array(
				'id'     => true,
				'class'  => true,
				'name'   => true,
				'value'  => true,
				'data-*' => true,
			)
		);

		$allowedtags['option'] = array_merge(
			isset( $allowedtags['option'] ) ? $allowedtags['option'] : array(),
			array(
				'id'    => true,
				'class' => true,
				'value' => true,
			)
		);

		$allowedtags['template'] = array_merge(
			isset( $allowedtags['template'] ) ? $allowedtags['template'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['p'] = array_merge(
			isset( $allowedtags['p'] ) ? $allowedtags['p'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['table'] = array_merge(
			isset( $allowedtags['table'] ) ? $allowedtags['table'] : array(),
			array(
				'id'          => true,
				'class'       => true,
				'cellspacing' => true,
				'data-*'      => true,
			)
		);

		$allowedtags['thead'] = array_merge(
			isset( $allowedtags['thead'] ) ? $allowedtags['thead'] : array(),
			array(
				'id'     => true,
				'class'  => true,
				'data-*' => true,
			)
		);

		$allowedtags['th'] = array_merge(
			isset( $allowedtags['th'] ) ? $allowedtags['th'] : array(),
			array(
				'id'      => true,
				'class'   => true,
				'data-*'  => true,
				'colspan' => true,
			)
		);

		$allowedtags['tbody'] = array_merge(
			isset( $allowedtags['tbody'] ) ? $allowedtags['tbody'] : array(),
			array(
				'id'     => true,
				'class'  => true,
				'data-*' => true,
			)
		);

		$allowedtags['tr'] = array_merge(
			isset( $allowedtags['tr'] ) ? $allowedtags['tr'] : array(),
			array(
				'id'     => true,
				'class'  => true,
				'data-*' => true,
			)
		);

		$allowedtags['td'] = array_merge(
			isset( $allowedtags['td'] ) ? $allowedtags['td'] : array(),
			array(
				'id'      => true,
				'class'   => true,
				'data-*'  => true,
				'colspan' => true,
			)
		);

		$allowedtags['button'] = array_merge(
			isset( $allowedtags['button'] ) ? $allowedtags['button'] : array(),
			array(
				'id'    => true,
				'class' => true,
				'type'  => true,
				'name'  => true,
				'value' => true,
			)
		);

		$allowedtags['header'] = array_merge(
			isset( $allowedtags['header'] ) ? $allowedtags['header'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['address'] = array_merge(
			isset( $allowedtags['address'] ) ? $allowedtags['address'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['nav'] = array_merge(
			isset( $allowedtags['nav'] ) ? $allowedtags['nav'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['ul'] = array_merge(
			isset( $allowedtags['ul'] ) ? $allowedtags['ul'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['li'] = array_merge(
			isset( $allowedtags['li'] ) ? $allowedtags['li'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h1'] = array_merge(
			isset( $allowedtags['h1'] ) ? $allowedtags['h1'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h2'] = array_merge(
			isset( $allowedtags['h2'] ) ? $allowedtags['h2'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h3'] = array_merge(
			isset( $allowedtags['h3'] ) ? $allowedtags['h3'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h4'] = array_merge(
			isset( $allowedtags['h4'] ) ? $allowedtags['h4'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h5'] = array_merge(
			isset( $allowedtags['h5'] ) ? $allowedtags['h5'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['h6'] = array_merge(
			isset( $allowedtags['h6'] ) ? $allowedtags['h6'] : array(),
			array(
				'id'    => true,
				'class' => true,
			)
		);

		$allowedtags['del'] = array_merge(
			isset( $allowedtags['del'] ) ? $allowedtags['del'] : array(),
			array(
				'aria-hidden' => true,
			)
		);

		$allowedtags['ins']   = array_merge( isset( $allowedtags['ins'] ) ? $allowedtags['ins'] : array(), array() );
		$allowedtags['style'] = array_merge( isset( $allowedtags['style'] ) ? $allowedtags['style'] : array(), array() );
		$allowedtags['bdi']   = array_merge( isset( $allowedtags['bdi'] ) ? $allowedtags['bdi'] : array(), array() );

		return $allowedtags;
	}
}

if ( ! function_exists( 'jkit_sanitize_array' ) ) {
	/**
	 * Sanitizing Array recursively
	 *
	 * @param array $data The data to be sanitized.
	 *
	 * @return array sanitized array
	 */
	function jkit_sanitize_array( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = jkit_sanitize_array( $value );
			}
			return $data;
		}
		return sanitize_text_field( $data );
	}
}

if ( ! function_exists( 'jkit_plugin_row_meta' ) ) {

	add_filter( 'plugin_row_meta', 'jkit_plugin_row_meta', 10, 2 );

	/**
	 * Filters the array of row meta and adds some custom links
	 *
	 * @param array  $plugin_meta
	 * @param string $plugin_file
	 *
	 * @return array
	 */
	function jkit_plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( $plugin_file === JEG_ELEMENTOR_KIT_BASE ) {
			$links = array(
				'<a class="jkit-meta-support" target="_blank" href="https://wordpress.org/support/plugin/jeg-elementor-kit/"><i class="fa fa-solid fa-life-ring"></i>' . esc_html__( 'Need Help?', 'jeg-elementor-kit' ) . '</a>',
				'<a class="jkit-meta-review" target="_blank" href="https://wordpress.org/support/plugin/jeg-elementor-kit/reviews/#new-post">' . esc_html__( 'Rate Us', 'jeg-elementor-kit' ) . '<span>★★★★★</span></a>',
			);

			$plugin_meta = array_merge( $plugin_meta, $links );
		}

		return $plugin_meta;
	}
}

if ( ! function_exists( 'jkit_get_languages' ) ) {
	/**
	 * Get The Available Languages
	 *
	 *  @return array
	 */
	function jkit_get_languages() {
		if ( function_exists( 'pll_the_languages' ) ) {
			return pll_the_languages( array( 'raw' => 1 ) );
		}

		if ( class_exists( 'SitePress' ) ) {
			/**
			 * Get The Active Languages by `wpml_active_languages` hook filters
			 */
			return apply_filters( 'wpml_active_languages', null, '' );
		}

		return array();
	}
}

if ( ! function_exists( 'jkit_get_the_language' ) ) {
	/**
	 * Get The Current Language
	 *
	 *  @return bool|string
	 */
	function jkit_get_current_language() {
		if ( function_exists( 'pll_current_language' ) ) {
			return pll_current_language();
		}

		if ( class_exists( 'SitePress' ) ) {
			/**
			 * Get The Current Language by `wpml_current_language` hook filters
			 */
			return apply_filters( 'wpml_current_language', null );
		}

		return false;
	}
}

if ( ! function_exists( 'jkit_is_multilanguage' ) ) {
	/**
	 * Check If Site Is Multilanguage
	 *
	 * @return bool
	 */
	function jkit_is_multilanguage() {
		if ( function_exists( 'PLL' ) || class_exists( 'SitePress' ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'jkit_get_multilanguage_post_id' ) ) {
	/**
	 * Get the Multilanguage Post ID
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int
	 */
	function jkit_get_multilanguage_post_id( $post_id = null ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$current_language = jkit_get_current_language();

		if ( function_exists( 'PLL' ) ) {
			return PLL()->model->post->get_translation( $post_id, $current_language );
		}

		if ( class_exists( 'SitePress' ) ) {
			$type = apply_filters( 'wpml_element_type', get_post_type( $post_id ) );
			$trid = apply_filters( 'wpml_element_trid', false, $post_id, $type );

			$translations = apply_filters( 'wpml_get_element_translations', array(), $trid, $type );

			if ( isset( $translations[ $current_language ] ) ) {
				$post_id = $translations[ $current_language ]->element_id;
			}
		}

		return $post_id;
	}
}

if ( ! function_exists( 'jkit_optimized_markup_class' ) ) {
	/**
	 * Get Optimized Markup Class
	 *
	 * Note: Remove After Feature is Deleted
	 *
	 * @return string
	 */
	function jkit_optimized_markup_class() {
		$optimized_markup = \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );

		if ( $optimized_markup ) {
			return '';
		}

		return ' > .elementor-widget-container';
	}
}

if ( ! function_exists( 'jkit_render_faq_schema_seo' ) ) {
	/**
	 * Render FAQ Schema for SEO
	 *
	 * @since 2.6.14
	 */
	function jkit_render_faq_schema_seo() {
		$data = apply_filters( 'jkit_faq_schema_seo_data', array() );

		if ( ! empty( $data ) ) {
			$schema = array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => array(),
			);

			foreach ( $data as $item ) {
				$schema['mainEntity'][] = array(
					'@type'          => 'Question',
					'name'           => $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['answer'],
					),
				);
			}

			echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';
		}
	}

	add_action( 'wp_footer', 'jkit_render_faq_schema_seo' );
}

if ( ! function_exists( 'jkit_permission_check_admin' ) ) {
	/**
	 * Check admin permissions.
	 *
	 * @return bool|WP_Error
	 */
	function jkit_permission_check_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'forbidden_permission',
				esc_html__( 'Forbidden Access', 'jeg-elementor-kit' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}


if ( ! function_exists( 'jkit_get_elementor_responsive_breakpoints' ) ) {
	/**
	 * Get Elementor responsive breakpoints
	 *
	 * @return array
	 */
	function jkit_get_elementor_responsive_breakpoints() {
		$breakpoints = array();

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			if ( version_compare( ELEMENTOR_VERSION, '3.2.0', '>=' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
				$elementor = \Elementor\Plugin::$instance->breakpoints->get_active_breakpoints();

				foreach ( $elementor as $key => $breakpoint ) {
					array_push(
						$breakpoints,
						array(
							'key'   => $key,
							'value' => $breakpoint->get_value(),
						)
					);
				}
			} elseif ( version_compare( ELEMENTOR_VERSION, '3.2.0', '<=' ) ) {
				$elementor = \Elementor\Core\Responsive\Responsive::get_editable_breakpoints();

				array_push(
					$breakpoints,
					array(
						'key'   => 'tablet',
						'value' => isset( $elementor['lg'] ) ? strval( $elementor['lg'] - 1 ) : 1024,
					)
				);

				array_push(
					$breakpoints,
					array(
						'key'   => 'mobile',
						'value' => isset( $elementor['md'] ) ? strval( $elementor['md'] - 1 ) : 767,
					)
				);
			}
		}

		usort(
			$breakpoints,
			function ( $a, $b ) {
				return $b['value'] - $a['value'];
			}
		);

		$breakpoints = apply_filters( 'jkit_get_elementor_responsive_breakpoints', $breakpoints );

		return $breakpoints;
	}
}


if ( ! function_exists( 'pro_banner_template' ) ) {
	/**
	 * Pro Banner Template
	 */
	function pro_banner_template() {
		ob_start();
		?>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Sora:wght@100..800&display=swap" rel="stylesheet">
		<style>
			.pro-banner {
				position: relative;
				width: 278px;
				max-width: 100%;
				padding: 32px 14px;
				background: #000E25;
				border-radius: 7.86px;
				text-align: center;
				overflow: hidden;
			}

			.pro-banner * {
				position: relative;
			}

			.pro-banner .image .features {
				width: 232px;
				height: auto;
			}

			.pro-banner .title,
			.pro-banner .description {
				margin: unset;
			}

			.pro-banner .title {
				margin-top: 9px;
				font-family: 'Sora', sans-serif;
				font-size: 23px;
				font-weight: bold;
				text-align: center;
				line-height: 120%;
				background: linear-gradient(to right, #FFFFFF, #F1DEFF);
				-webkit-background-clip: text;
				-webkit-text-fill-color: transparent;
			}

			.pro-banner .title .gradient {
				background: linear-gradient(to right, #D15DF7, #3B98FF);
				-webkit-background-clip: text;
				-webkit-text-fill-color: transparent;
			}

			.pro-banner .description {
				margin-top: 8px;
				font-family: 'Sora', sans-serif;
				font-size: 12px;
				text-align: center;
				color: #999999;
				line-height: 150%;
			}

			.pro-banner .upgrade {
				margin-top: 15px;
				padding: 14px 20px;
				display: inline-flex;
				gap: 12px;
				align-items: center;
				background: radial-gradient(circle at center -70px, #BA70FF 50%, #2F47FF);
				border-radius: 10px;
				font-family: 'Sora', sans-serif;
				font-weight: 500;
				color: #FFFFFF;
				text-decoration: none;
				transition-duration: 0.4s;
			}

			.pro-banner .upgrade:hover {
				color: white;
				transform: scale(1.1);
			}

			.pro-banner .upgrade svg {
				width: 15px;
			}

			.pro-banner .background,
			.pro-banner .background>div {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				pointer-events: none;
			}

			.pro-banner .background .gradient {
				width: 380px;
				height: 380px;
				background: linear-gradient(#3C008B, #D8AAFF 38%, #9F2FFF 61%, #3C008B);
				transform: translate(calc(-25%/2), -40%)rotate(135deg);
				filter: opacity(0.80) blur(100px);
			}

			.pro-banner .background .pattern img {
				height: 150%;
				top: -40%;
			}
		</style>
		<div class="pro-banner">
			<div class="background">
				<div class="gradient"></div>
				<div class="pattern">
					<img src="<?php echo esc_url( JEG_ELEMENTOR_KIT_URL . '/assets/banner/jkit-pro/background-pattern.svg' ) ?>" width="100%" height="100%" />
				</div>
			</div>
			<div class="image">
				<img src="<?php echo esc_url( JEG_ELEMENTOR_KIT_URL . '/assets/banner/jkit-pro/features.png' ) ?>" class="features" />
			</div>
			<h2 class="title">Unlock More <br />With <span class="gradient">JEG KIT PRO</span></h2>
			<p class="description">Elevate your website with our <br />all-in-one widget collection.</p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jkit&utm_source=jeg-elementor-kit&utm_medium=element-panel' ) ); ?>" target="_blank" class="upgrade"><?php echo esc_html__( 'Upgrade To PRO', 'jeg-elementor-kit' ) ?> <svg width="15" height="13" viewBox="0 0 15 13" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M2.34752 9.50065L0.874512 1.3991L4.92529 5.08163L7.50305 0.662598L10.0808 5.08163L14.1316 1.3991L12.6586 9.50065H2.34752ZM12.6586 11.7102C12.6586 12.1521 12.364 12.4467 11.9221 12.4467H3.08403C2.64212 12.4467 2.34752 12.1521 2.34752 11.7102V10.9737H12.6586V11.7102Z" fill="white" />
				</svg>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'pro_banner_popup_template' ) ) {
	/**
	 * Pro Banner Popup Template
	 */
	function pro_banner_popup_template() {
		ob_start();
		?>
		<div class="jkit-pro-banner">
			<div class="header">
				<h3></h3>
				<i class="eicon-close close"></i>
			</div>
			<div class="content">
				<?php echo wp_kses_post( pro_banner_template() ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

if ( ! function_exists( 'jkit_get_pricing_plan_event_expired_timestamp' ) ) {
	/**
	 * Get event pricing plan expired timestamp.
	 *
	 * @param array $data Pricing plan data.
	 *
	 * @return int|false
	 */
	function jkit_get_pricing_plan_event_expired_timestamp( $data ) {
		if ( empty( $data['is_event_sales'] ) || empty( $data['event_expired'] ) ) {
			return false;
		}

		if ( ! empty( $data['event_expired_timestamp'] ) ) {
			return (int) $data['event_expired_timestamp'];
		}

		$expired  = trim( $data['event_expired'] );
		$format   = 'Y-m-d H:i:s';
		$date     = false;
		$timezone = ! empty( $data['event_timezone'] ) ? $data['event_timezone'] : '';

		if ( $timezone ) {
			try {
				$date = DateTimeImmutable::createFromFormat( $format, $expired . ' 23:59:59', new DateTimeZone( $timezone ) );
			} catch (Exception $e) {
				$date = false;
			}
		}

		if ( ! $date && function_exists( 'wp_timezone' ) ) {
			$date = DateTimeImmutable::createFromFormat( $format, $expired . ' 23:59:59', wp_timezone() );
		}

		if ( $date instanceof DateTimeImmutable ) {
			return $date->getTimestamp();
		}

		return strtotime( $expired . ' 23:59:59' );
	}
}

if ( ! function_exists( 'jkit_get_pricing_plan' ) ) {
	/**
	 * Get Pricing Plan Data
	 *
	 * @return mixed
	 */
	function jkit_get_pricing_plan() {
		$data = get_transient( 'jkit_pricing_plan_cache' );

		if ( $data ) {
			$event_expired = jkit_get_pricing_plan_event_expired_timestamp( $data );

			if ( false !== $event_expired && time() > $event_expired ) {
				delete_transient( 'jkit_pricing_plan_cache' );
			} else {
				return $data;
			}
		}

		$response = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jeg-kit/v1/client/pricing-plan',
			array(
				'method'  => 'GET',
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			return null;
		}

		$cache_expiration = 3 * HOUR_IN_SECONDS;
		$event_expired    = jkit_get_pricing_plan_event_expired_timestamp( $data );

		if ( false !== $event_expired ) {
			$cache_expiration = max( 1, min( $cache_expiration, $event_expired - time() ) );
		}

		set_transient( 'jkit_pricing_plan_cache', $data, $cache_expiration );
		return $data;
	}
}

if ( ! function_exists( 'jkit_get_banner_data' ) ) {
	/**
	 * Get Event Banner
	 *
	 * @return mixed
	 */
	function jkit_get_banner_data() {
		$banner_closed = get_transient( 'jkit_banner_closed' );
		if ( $banner_closed ) {
			return null;
		}
		$data = get_transient( 'jkit_banner_cache' );
		if ( $data ) {
			if ( ! $data->banner || ! $data->url || ! $data->expired ) {
				return null;
			}
			return $data;
		}

		$response = wp_remote_request(
			JEG_ELEMENT_SERVER_URL . 'wp-json/jeg-kit/v1/client/banner-data',
			array(
				'method'  => 'GET',
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
			return null;
		}
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( ! $data->banner || ! $data->url || ! $data->expired ) {
			return null;
		}
		set_transient( 'jkit_banner_cache', $data, 3 * HOUR_IN_SECONDS );
		return $data;
	}
}

/**
 * Displays custom update information for a Jeg Kit plugin.
 * 
 * Adapted from WordPress core's wp_plugin_update_row() function.
 * 
 * Source:
 * wp-admin/includes/update.php
 * 
 * This implementation is customized to replace the default plugin update
 * notice shown on the Plugins screen.
 *
 * @since 3.1.3
 * 
 * @see wp_plugin_update_row()
 * @link https://developer.wordpress.org/reference/functions/wp_plugin_update_row/
 *
 * @param string $file        Plugin basename.
 * @param array  $plugin_data Plugin information.
 * @return void|false
 */
function jkit_plugin_update_row( $file, $plugin_data ) {
	$current = get_site_transient( 'update_plugins' );

	if ( ! isset( $current->response[ $file ] ) ) {
		return false;
	}

	$response = $current->response[ $file ];

	$plugins_allowedtags = array(
		'a'       => array(
			'href'  => array(),
			'title' => array(),
		),
		'abbr'    => array( 'title' => array() ),
		'acronym' => array( 'title' => array() ),
		'code'    => array(),
		'em'      => array(),
		'strong'  => array(),
	);

	$plugin_name = wp_kses( $plugin_data['Name'], $plugins_allowedtags );
	$plugin_slug = isset( $response->slug ) ? $response->slug : $response->id;

	if ( isset( $response->slug ) ) {
		$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin_slug . '&section=changelog' );
	} elseif ( isset( $response->url ) ) {
		$details_url = $response->url;
	} else {
		$details_url = $plugin_data['PluginURI'];
	}

	$details_url = add_query_arg(
		array(
			'TB_iframe' => 'true',
			'width'     => 600,
			'height'    => 800,
		),
		$details_url
	);

	/** @var WP_Plugins_List_Table $wp_list_table */
	$wp_list_table = _get_list_table(
		'WP_Plugins_List_Table',
		array(
			'screen' => get_current_screen(),
		)
	);

	if ( is_network_admin() || ! is_multisite() ) {
		if ( is_network_admin() ) {
			$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
		} else {
			$active_class = is_plugin_active( $file ) ? ' active' : '';
		}

		$requires_php   = isset( $response->requires_php ) ? $response->requires_php : null;
		$compatible_php = is_php_version_compatible( $requires_php );
		$notice_type    = $compatible_php ? 'notice-warning' : 'notice-error';
		$free_update_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ),
			'upgrade-plugin_' . $file
		);

		printf(
			'<tr class="plugin-update-tr%s" id="%s" data-slug="%s" data-plugin="%s">' .
			'<td colspan="%s" class="plugin-update colspanchange">' .
			'<div class="update-message notice inline %s notice-alt"><p>',
			$active_class,
			esc_attr( $plugin_slug . '-update' ),
			esc_attr( $plugin_slug ),
			esc_attr( $file ),
			esc_attr( $wp_list_table->get_column_count() ),
			$notice_type
		);

		if ( ! current_user_can( 'update_plugins' ) ) {
			printf(
				/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.' ),
				$plugin_name,
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Plugin name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
				),
				esc_attr( $response->new_version )
			);
		} elseif ( empty( $response->package ) ) {
			printf(
				/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number. */
				__( 'There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>. <em>Automatic update is unavailable for this plugin.</em>' ),
				$plugin_name,
				esc_url( $details_url ),
				sprintf(
					'class="thickbox open-plugin-details-modal" aria-label="%s"',
					/* translators: 1: Plugin name, 2: Version number. */
					esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
				),
				esc_attr( $response->new_version )
			);
		} else {
			if ( $compatible_php ) {
				printf(
					/* translators: 1: Plugin name, 2: Upgrade URL, 3: Additional upgrade link attributes, 4: Pricing URL, 5: Free update URL, 6: Free update label. */
					__( 'A new version of %1$s is available. <strong><a href="%2$s" target="_blank" rel="noopener noreferrer" %3$s>Upgrade to Pro</a></strong> to unlock automatic updates, premium features, widgets, templates, and priority support, or <strong><a href="%4$s" target="_blank" rel="noopener noreferrer" class="jkit-update-now-pricing" data-continue-url="%5$s" data-continue-label="%6$s">update now</a></strong>.', 'jeg-elementor-kit' ),
					esc_html( $plugin_name ),
					esc_url(
						add_query_arg(
							array(
								'page'       => 'jkit',
								'utm_source' => 'plugin-update-notice',
								'utm_medium' => 'plugin-update-link',
							),
							admin_url( 'admin.php' )
						)
					),
					sprintf(
						'class="upgrade-to-pro jeg-kit-pro" aria-label="%s"',
						/* translators: %s: Plugin name. */
						esc_attr( sprintf( _x( 'Upgrade %s now', 'plugin' ), $plugin_name ) )
					),
					esc_url(
						add_query_arg(
							array(
								'page'       => 'jkit',
								'utm_source' => 'plugin-update-notice',
								'utm_medium' => 'plugin-update-now-link',
							),
							admin_url( 'admin.php' )
						)
					),
					esc_url( $free_update_url ),
					esc_attr__( 'Continue Free Update', 'jeg-elementor-kit' )
				);
			} else {
				printf(
					/* translators: 1: Plugin name, 2: Details URL, 3: Additional link attributes, 4: Version number 5: URL to Update PHP page. */
					__( 'There is a new version of %1$s available, but it does not work with your version of PHP. <a href="%2$s" %3$s>View version %4$s details</a> or <a href="%5$s">learn more about updating PHP</a>.' ),
					$plugin_name,
					esc_url( $details_url ),
					sprintf(
						'class="thickbox open-plugin-details-modal" aria-label="%s"',
						/* translators: 1: Plugin name, 2: Version number. */
						esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin_name, $response->new_version ) )
					),
					esc_attr( $response->new_version ),
					esc_url( wp_get_update_php_url() )
				);
				wp_update_php_annotation( '<br><em>', '</em>' );
			}
		}

		/**
		 * Fires at the end of the update message container in each
		 * row of the plugins list table.
		 *
		 * The dynamic portion of the hook name, `$file`, refers to the path
		 * of the plugin's primary file relative to the plugins directory.
		 *
		 * @since 2.8.0
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
		 * }
		 */
		do_action( "in_plugin_update_message-{$file}", $plugin_data, $response ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		echo '</p></div></td></tr>';
	}
}
