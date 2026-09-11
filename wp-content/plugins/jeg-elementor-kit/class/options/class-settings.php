<?php
/**
 * Settings class
 *
 * @package jeg-elementor-kit
 * @author jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Options;

use Jeg\Elementor_Kit\Options\Options;
use Jeg\Elementor_Kit\Style\CSS;
use Jeg\Elementor_Kit\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class
 */
class Settings {

	/**
	 * List of Color
	 *
	 * @var array
	 */
	public static $list_color = array(
		'JColorPrimary',
		'JColorSecondary',
		'JColorText',
		'JColorAccent',
		'JColorTertiary',
		'JColorMeta',
		'JColorBorder',
	);

	/**
	 * List of Font
	 *
	 * @var array
	 */
	public static $list_font = array(
		'JFontPrimary',
		'JFontSecondary',
		'JFontText',
		'JFontAccent',
		'JFontTextMenu',
		'JFontTextButton',
		'JFontTextHero',
		'JFontTextFooter',
		'JFontBlogTitle',
		'JFontIconBoxTitle',
		'JFontPricingTitle',
		'JFontStepTitle',
	);

	/**
	 * List of Typography
	 *
	 * @var array
	 */
	public static $list_typography = array(
		'JTypographyBody',
		'JTypographyLink',
		'JTypographyH1',
		'JTypographyH2',
		'JTypographyH3',
		'JTypographyH4',
		'JTypographyH5',
		'JTypographyH6',
	);

	/**
	 * List of Typography Setting
	 *
	 * @var array
	 */
	public static $list_typography_setting = array(
		'typography_typography',
		'typography_font_family',
		'typography_font_size',
		'typography_font_weight',
		'typography_text_transform',
		'typography_font_style',
		'typography_text_decoration',
		'typography_line_height',
		'typography_letter_spacing',
		'typography_word_spacing',
	);

	/**
	 * List of Buttons
	 *
	 * @var array
	 */
	public static $list_buttons = array(
		'JButtonsTypography',
		'JButtonsTextShadow',
		'JButtonsPadding',
		'JButtonsTextColor',
		'JButtonsBackground',
		'JButtonsBoxShadow',
		'JButtonsBorderType',
	);

	/**
	 * List of Image
	 *
	 * @var array
	 */
	public static $list_images = array(
		'JImagesOpacity',
		'JImagesBoxShadow',
		'JImagesCSSFilter',
		'JImagesHoverTransition',
		'JImagesBorder',
	);

	/**
	 * List of Form
	 *
	 * @var array
	 */
	public static $list_forms = array(
		'JFormLabelTypography',
		'JFormTypography',
		'JFormTextColor',
		'JFormAccentColor',
		'JFormBackgroundColor',
		'JFormBoxShadow',
		'JFormBorderType',
		'JFormPadding',
	);

	/**
	 * List of Site Option
	 *
	 * @var array
	 */
	public static $list_sites = array(
		'JSiteName',
		'JSiteDescription',
		'JSiteLogo',
		'JSiteFavico',
	);

	/**
	 * List of layouts Option
	 *
	 * @var array
	 */
	public static $list_layouts = array(
		'JLayoutContentWidth',
		'JLayoutWidgetsSpace',
		'JLayoutStretchSection',
		'JLayoutTitleSelector',
		'JLayoutPageLayout',
		'JLayoutBreakpoints',
	);

	/**
	 * List of Background Option
	 *
	 * @var array
	 */
	public static $list_backgrounds = array(
		'JBackgroundBackground',
		'JBackgroundMobile',
	);

	/**
	 * Clear cache
	 *
	 * @param boolean $regenerate Regenerate cache.
	 */
	public static function clear_cache( $regenerate = false ) {
		Options::delete_theme_option();
		if ( $regenerate ) {
			$new = self::current_settings();
			self::update_settings( $new );
		}
	}

	/**
	 * Get current settings of Theme Option
	 *
	 * @return array
	 */
	public static function current_settings() {
		return Options::get_all_options();
	}

	/**
	 * Is not default
	 *
	 * @param mixed $default Default value.
	 * @param mixed $current Current value.
	 *
	 * @return booelan
	 */
	public static function is_not_default( $default, $current ) {
		// check if array convert to json then compare.
		if ( is_array( $default ) ) {
			$default = wp_json_encode( $default );
		}
		if ( is_array( $current ) ) {
			$current = wp_json_encode( $current );
		}
		return ( $default != $current ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
	}

	/**
	 * Update settings
	 *
	 * @param array $settings Theme option.
	 *
	 * @return array
	 */
	public static function update_settings( $settings ) {
		$new_settings = Options::update_option( $settings );

		// update elementor options and globals.
		self::elementor_update( $settings );

		// Update all styles and scripts.
		self::update_style( $new_settings );

		return array( 'status' => 'success' );
	}

	/**
	 * Update style
	 *
	 * @param array $settings Theme option.
	 */
	public static function update_style( $settings ) {
		// We need to regenerate CSS.
		new CSS( $settings );
	}

	/**
	 * Update Elementor options and globals
	 *
	 * @param array $json Elementor options.
	 */
	public static function elementor_update( $json ) {
		update_option( 'elementor_disable_color_schemes', 'yes' );
		self::sync_globals_with_jkit( $json );
	}

	/**
	 * Create default kit
	 *
	 * @return int|\WP_Error
	 */
	public static function create_default_kit() {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( $kit_id ) {
			return $kit_id;
		}

		$kit_id = wp_insert_post(
			array(
				'post_type'   => 'elementor_library',
				'post_title'  => __( 'Default Kit', 'jeg-elementor-kit' ),
				'post_status' => 'publish',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'kit',
				),
			)
		);

		if ( is_wp_error( $kit_id ) ) {
			return $kit_id;
		}

		update_option( 'elementor_active_kit', $kit_id );
		return $kit_id;
	}

	/**
	 * Sync Global Option with Essential
	 *
	 * @param array|null $settings Elementor settings.
	 */
	public static function sync_globals_with_jkit( $settings = null ) {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( null === $settings ) {
			$settings = self::current_settings();
		}
		if ( ! $kit_id ) {
			$kit_id = self::create_default_kit();
		}

		if ( is_wp_error( $kit_id ) ) {
			return;
		}

		if ( isset( $settings['JColorPrimary'] ) ) {
			$new_settings         = array();
			$placeholder_settings = array(
				'system_colors'     => array(),
				'system_typography' => array(),
				'__globals__'       => array(),
			);
			$elementor_settings   = get_post_meta( $kit_id, '_elementor_page_settings', true );
			if ( is_wp_error( $elementor_settings ) ) {
				$new_settings = $placeholder_settings;
			}
			if ( is_array( $elementor_settings ) ) {
				$new_settings = $elementor_settings;
			}

			foreach ( self::$list_color as $index => $color ) {
				$new_settings['system_colors'][ $index ] = array(
					'_id'   => strtolower( str_replace( 'JColor', '', $color ) ),
					'title' => 'Jeg Kit - ' . str_replace( 'JColor', '', $color ),
					'color' => $settings[ $color ],
				);
			}

			// global font converter.
			foreach ( self::$list_font as $index => $font ) {
				$new_settings['system_typography'][ $index ] = array(
					'_id'                   => strtolower( str_replace( 'JFont', '', $font ) ),
					'title'                 => 'Jeg Kit - Font ' . implode( ' ', preg_split( '/(?=[A-Z])/', preg_replace( '/([a-z])([A-Z])/', '$1 $2', str_replace( 'JFont', '', $font ) ) ) ),
					'typography_typography' => 'custom',
				);
				$device_control                              = jkit_get_elementor_responsive_breakpoints();

				if ( count( $settings[ $font ] ) > 0 ) {
					if ( isset( $settings[ $font ]['font-family'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_font_family'] = $settings[ $font ]['font-family'];
					}
					if ( isset( $settings[ $font ]['font-size'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_font_size'] = $settings[ $font ]['font-size'];
					}
					if ( isset( $settings[ $font ]['font-line-height'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_line_height'] = $settings[ $font ]['font-line-height'];
					}
					if ( isset( $settings[ $font ]['font-letter-spacing'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_letter_spacing'] = $settings[ $font ]['font-letter-spacing'];
					}
					if ( isset( $settings[ $font ]['font-word-spacing'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_word_spacing'] = $settings[ $font ]['font-word-spacing'];
					}
					foreach ( $device_control as $control ) {
						$key = $control['key'];
						if ( isset( $settings[ $font ][ "font-size-$key" ] ) ) {
							$new_settings['system_typography'][ $index ][ "typography_font_size_$key" ] = $settings[ $font ][ "font-size-$key" ];
						}
						if ( isset( $settings[ $font ][ "font-line-height-$key" ] ) ) {
							$new_settings['system_typography'][ $index ][ "typography_line_height_$key" ] = $settings[ $font ][ "font-line-height-$key" ];
						}
						if ( isset( $settings[ $font ][ "font-letter-spacing-$key" ] ) ) {
							$new_settings['system_typography'][ $index ][ "typography_letter_spacing_$key" ] = $settings[ $font ][ "font-letter-spacing-$key" ];
						}
						if ( isset( $settings[ $font ][ "font-word-spacing-$key" ] ) ) {
							$new_settings['system_typography'][ $index ][ "typography_word_spacing_$key" ] = $settings[ $font ][ "font-word-spacing-$key" ];
						}
					}
					if ( isset( $settings[ $font ]['font-weight'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_font_weight'] = ( $settings[ $font ]['font-weight'] === 'regular' ) ? 'normal' : $settings[ $font ]['font-weight'];
					}
					if ( isset( $settings[ $font ]['font-transform'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_text_transform'] = ( $settings[ $font ]['font-transform'] === 'regular' ) ? 'normal' : $settings[ $font ]['font-transform'];
					}
					if ( isset( $settings[ $font ]['font-style'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_font_style'] = ( $settings[ $font ]['font-style'] === 'regular' ) ? 'normal' : $settings[ $font ]['font-style'];
					}
					if ( isset( $settings[ $font ]['font-decoration'] ) ) {
						$new_settings['system_typography'][ $index ]['typography_text_decoration'] = ( $settings[ $font ]['font-decoration'] === 'regular' ) ? 'normal' : $settings[ $font ]['font-decoration'];
					}
				}
			}

			foreach ( self::$list_typography as $index => $typography ) {
				$id = strtolower( str_replace( 'JTypography', '', $typography ) );
				if ( 'link' === $id ) {
					$link = array(
						'normal',
						'hover',
					);
					foreach ( $link as $lnx ) {
						if ( isset( $settings[ $typography ][ $id . '_' . $lnx . '_typography_typography' ] ) && ( strpos( $settings[ $typography ][ $id . '_' . $lnx . '_typography_typography' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_' . $lnx . '_typography_typography' ] = $settings[ $typography ][ $id . '_' . $lnx . '_typography_typography' ];
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $lnx . '_' . $stt;
								if ( isset( $new_settings[ $key ] ) ) {
									unset( $new_settings[ $key ] );
								}
							}
						} else {
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $lnx . '_' . $stt;
								if ( isset( $settings[ $typography ][ $key ] ) ) {
									$new_settings[ $key ] = $settings[ $typography ][ $key ];
								}
							}
							if ( isset( $new_settings['__globals__'][ $id . '_' . $lnx . '_typography_typography' ] ) ) {
								unset( $new_settings['__globals__'][ $id . '_' . $lnx . '_typography_typography' ] );
							}
						}
						if ( isset( $settings[ $typography ][ $id . '_' . $lnx . '_color' ] ) && ( strpos( $settings[ $typography ][ $id . '_' . $lnx . '_color' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_' . $lnx . '_color' ] = $settings[ $typography ][ $id . '_' . $lnx . '_color' ];
							if ( isset( $new_settings[ $id . '_' . $lnx . '_color' ] ) ) {
								unset( $new_settings[ $id . '_' . $lnx . '_color' ] );
							}
						} else {
							if ( isset( $settings[ $typography ][ $id . '_' . $lnx . '_color' ] ) ) {
								$new_settings[ $id . '_' . $lnx . '_color' ] = $settings[ $typography ][ $id . '_' . $lnx . '_color' ];
							}
							if ( isset( $new_settings['__globals__'][ $id . '_' . $lnx . '_color' ] ) ) {
								unset( $new_settings['__globals__'][ $id . '_' . $lnx . '_color' ] );
							}
						}
					}
				} else {
					if ( isset( $settings[ $typography ][ $id . '_typography_typography' ] ) && ( strpos( $settings[ $typography ][ $id . '_typography_typography' ], 'globals' ) !== false ) ) {
						$new_settings['__globals__'][ $id . '_typography_typography' ] = $settings[ $typography ][ $id . '_typography_typography' ];
						foreach ( self::$list_typography_setting as $index => $stt ) {
							$key = $id . '_' . $stt;
							if ( isset( $new_settings[ $key ] ) ) {
								unset( $new_settings[ $key ] );
							}
						}
					} else {
						foreach ( self::$list_typography_setting as $index => $stt ) {
							$key = $id . '_' . $stt;
							if ( isset( $settings[ $typography ][ $key ] ) ) {
								$new_settings[ $key ] = $settings[ $typography ][ $key ];
							}
						}
						if ( isset( $new_settings['__globals__'][ $id . '_typography_typography' ] ) ) {
							unset( $new_settings['__globals__'][ $id . '_typography_typography' ] );
						}
					}
					if ( isset( $settings[ $typography ][ $id . '_color' ] ) && ( strpos( $settings[ $typography ][ $id . '_color' ], 'globals' ) !== false ) ) {
						$new_settings['__globals__'][ $id . '_color' ] = $settings[ $typography ][ $id . '_color' ];
						if ( isset( $new_settings[ $id . '_color' ] ) ) {
							unset( $new_settings[ $id . '_color' ] );
						}
					} else {
						if ( isset( $settings[ $typography ][ $id . '_color' ] ) ) {
							$new_settings[ $id . '_color' ] = $settings[ $typography ][ $id . '_color' ];
						}
						if ( isset( $new_settings['__globals__'][ $id . '_color' ] ) ) {
							unset( $new_settings['__globals__'][ $id . '_color' ] );
						}
					}
				}
			}

			foreach ( self::$list_buttons as $index => $button ) {
				$id = 'button';
				switch ( $button ) {
					case 'JButtonsTypography':
						if ( isset( $settings[ $button ][ $id . '_typography_typography' ] ) && ( strpos( $settings[ $button ][ $id . '_typography_typography' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_typography_typography' ] = $settings[ $button ][ $id . '_typography_typography' ];
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $new_settings[ $key ] ) ) {
									unset( $new_settings[ $key ] );
								}
							}
						} else {
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $settings[ $button ][ $key ] ) ) {
									$new_settings[ $key ] = $settings[ $button ][ $key ];
								}
							}
							if ( isset( $new_settings['__globals__'][ $id . '_typography_typography' ] ) ) {
								unset( $new_settings['__globals__'][ $id . '_typography_typography' ] );
							}
						}
						break;
					case 'JButtonsTextShadow':
						if ( isset( $settings[ $button ][ $id . '_text_shadow_text_shadow_type' ] ) ) {
							$new_settings[ $id . '_text_shadow_text_shadow_type' ] = $settings[ $button ][ $id . '_text_shadow_text_shadow_type' ];
						}
						if ( isset( $settings[ $button ][ $id . '_text_shadow_text_shadow' ] ) ) {
							$new_settings[ $id . '_text_shadow_text_shadow_type' ] = $settings[ $button ][ $id . '_text_shadow_text_shadow' ];
						}
						break;
					case 'JButtonsTextColor':
						$hover = array(
							'',
							'_hover',
						);
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $button ][ $id . $hvr . '_text_color' ] ) && ( strpos( $settings[ $button ][ $id . $hvr . '_text_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $hvr . '_text_color' ] = $settings[ $button ][ $id . $hvr . '_text_color' ];
								if ( isset( $new_settings[ $id . $hvr . '_text_color' ] ) ) {
									unset( $new_settings[ $id . $hvr . '_text_color' ] );
								}
							} else {
								if ( isset( $settings[ $button ][ $id . $hvr . '_text_color' ] ) ) {
									$new_settings[ 'button' . $hvr . '_text_color' ] = $settings[ $button ][ $id . $hvr . '_text_color' ];
								}
								if ( isset( $new_settings['__globals__'][ $id . $hvr . '_text_color' ] ) ) {
									unset( $new_settings['__globals__'][ $id . $hvr . '_text_color' ] );
								}
							}
						}
						break;
					case 'JButtonsBackground':
						$hover = array(
							'',
							'_hover',
						);
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_color' ] ) && ( strpos( $settings[ $button ][ $id . $hvr . '_background_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $hvr . '_background_color' ] = $settings[ $button ][ $id . $hvr . '_background_color' ];
							} else {
								if ( isset( $settings[ $button ][ $id . $hvr . '_background_color' ] ) ) {
									$new_settings[ $id . $hvr . '_background_color' ] = $settings[ $button ][ $id . $hvr . '_background_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $hvr . '_background_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $hvr . '_background_color' ] );
									}
								}
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_color_b' ] ) && ( strpos( $settings[ $button ][ $id . $hvr . '_background_color_b' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $hvr . '_background_color_b' ] = $settings[ $button ][ $id . $hvr . '_background_color_b' ];
							} else {
								if ( isset( $settings[ $button ][ $id . $hvr . '_background_color_b' ] ) ) {
									$new_settings[ $id . $hvr . '_background_color_b' ] = $settings[ $button ][ $id . $hvr . '_background_color_b' ];
									if ( isset( $new_settings['__globals__'][ $id . $hvr . '_background_color_b' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $hvr . '_background_color_b' ] );
									}
								}
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_color_stop' ] ) ) {
								$new_settings[ $id . $hvr . '_background_color_stop' ] = $settings[ $button ][ $id . $hvr . '_background_color_stop' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_color_b_stop' ] ) ) {
								$new_settings[ $id . $hvr . '_background_color_b_stop' ] = $settings[ $button ][ $id . $hvr . '_background_color_b_stop' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_background' ] ) ) {
								$new_settings[ $id . $hvr . '_background_background' ] = $settings[ $button ][ $id . $hvr . '_background_background' ];
							} else {
								if ( isset( $new_settings[ $id . $hvr . '_background_background' ] ) ) {
									unset( $new_settings[ $id . $hvr . '_background_background' ] );
								}
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_gradient_type' ] ) ) {
								$new_settings[ $id . $hvr . '_background_gradient_type' ] = $settings[ $button ][ $id . $hvr . '_background_gradient_type' ];
							} else {
								if ( isset( $new_settings[ $id . $hvr . '_background_gradient_type' ] ) ) {
									unset( $new_settings[ $id . $hvr . '_background_gradient_type' ] );
								}
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_gradient_position' ] ) ) {
								$new_settings[ $id . $hvr . '_background_gradient_position' ] = $settings[ $button ][ $id . $hvr . '_background_gradient_position' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_background_gradient_angle' ] ) ) {
								$new_settings[ $id . $hvr . '_background_gradient_angle' ] = $settings[ $button ][ $id . $hvr . '_background_gradient_angle' ];
							}
						}
						break;
					case 'JButtonsBoxShadow':
						$hover = array(
							'',
							'_hover',
						);
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow' ] ) ) {
								$new_settings[ $id . $hvr . '_box_shadow_box_shadow' ] = $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow_type' ] ) ) {
								$new_settings[ $id . $hvr . '_box_shadow_box_shadow_type' ] = $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow_type' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow_position' ] ) ) {
								$new_settings[ $id . $hvr . '_box_shadow_box_shadow_position' ] = $settings[ $button ][ $id . $hvr . '_box_shadow_box_shadow_position' ];
							}
						}
						break;
					case 'JButtonsBorderType':
						$hover = array(
							'',
							'_hover',
						);
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $button ][ $id . $hvr . '_border_border' ] ) ) {
								$new_settings[ $id . $hvr . '_border_border' ] = $settings[ $button ][ $id . $hvr . '_border_border' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_border_width' ] ) ) {
								$new_settings[ $id . $hvr . '_border_width' ] = $settings[ $button ][ $id . $hvr . '_border_width' ];
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_border_color' ] ) && ( strpos( $settings[ $button ][ $id . $hvr . '_border_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $hvr . '_border_color' ] = $settings[ $button ][ $id . $hvr . '_border_color' ];
							} else {
								if ( isset( $settings[ $button ][ $id . $hvr . '_border_color' ] ) ) {
									$new_settings[ $id . $hvr . '_border_color' ] = $settings[ $button ][ $id . $hvr . '_border_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $hvr . '_border_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $hvr . '_border_color' ] );
									}
								}
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $settings[ $button ][ $id . $hvr . '_border_width_' . $key ] ) ) {
									$new_settings[ $id . $hvr . '_border_width_' . $key ] = $settings[ $button ][ $id . $hvr . '_border_width_' . $key ];
								}
							}
							if ( isset( $settings[ $button ][ $id . $hvr . '_border_radius' ] ) ) {
								$new_settings[ $id . $hvr . '_border_radius' ] = $settings[ $button ][ $id . $hvr . '_border_radius' ];
							}
						}
						break;
					case 'JButtonsPadding':
						$hover = array(
							'',
							'_hover',
						);
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $button ][ $id . $hvr . '_padding' ] ) ) {
								$new_settings[ $id . $hvr . '_padding' ] = $settings[ $button ][ $id . $hvr . '_padding' ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $settings[ $button ][ $id . $hvr . '_padding_' . $key ] ) ) {
									$new_settings[ $id . $hvr . '_padding_' . $key ] = $settings[ $button ][ $id . $hvr . '_padding_' . $key ];
								}
							}
						}
						break;
					default:
						break;
				}
			}

			foreach ( self::$list_images as $index => $image ) {
				$hover = array(
					'',
					'_hover',
				);
				$id    = 'image';
				switch ( $image ) {
					case 'JImagesBorder':
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_border_border' ] ) ) {
								$new_settings[ 'image' . $hvr . '_border_border' ] = $settings[ $image ][ 'image' . $hvr . '_border_border' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_border_width' ] ) ) {
								$new_settings[ 'image' . $hvr . '_border_width' ] = $settings[ $image ][ 'image' . $hvr . '_border_width' ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $settings[ $image ][ 'image' . $hvr . '_border_width_' . $key ] ) ) {
									$new_settings[ 'image' . $hvr . '_border_width_' . $key ] = $settings[ $image ][ 'image' . $hvr . '_border_width_' . $key ];
								}
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_border_radius' ] ) ) {
								$new_settings[ 'image' . $hvr . '_border_radius' ] = $settings[ $image ][ 'image' . $hvr . '_border_radius' ];
							}
						}
						break;
					case 'JImagesOpacity':
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_opacity' ] ) ) {
								$new_settings[ 'image' . $hvr . '_opacity' ] = $settings[ $image ][ 'image' . $hvr . '_opacity' ];
							}
						}
						break;
					case 'JImagesBoxShadow':
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_type' ] ) ) {
								$new_settings[ 'image' . $hvr . '_box_shadow_box_shadow_type' ] = $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_type' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow' ] ) ) {
								$new_settings[ 'image' . $hvr . '_box_shadow_box_shadow' ] = $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_position' ] ) ) {
								$new_settings[ 'image' . $hvr . '_box_shadow_box_shadow_position' ] = $settings[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_position' ];
							}
						}
						break;
					case 'JImagesCSSFilter':
						foreach ( $hover as $hvr ) {
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_css_filter' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_css_filter' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_css_filter' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_blur' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_blur' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_blur' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_brightness' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_brightness' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_brightness' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_contrast' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_contrast' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_contrast' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_saturate' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_saturate' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_saturate' ];
							}
							if ( isset( $settings[ $image ][ 'image' . $hvr . '_css_filters_hue' ] ) ) {
								$new_settings[ 'image' . $hvr . '_css_filters_hue' ] = $settings[ $image ][ 'image' . $hvr . '_css_filters_hue' ];
							}
						}
						break;
					case 'JImagesHoverTransition':
						if ( isset( $settings[ $image ]['image_hover_transition'] ) ) {
							$new_settings['image_hover_transition'] = $settings[ $image ]['image_hover_transition'];
						}
						break;
					default:
						break;
				}
			}

			foreach ( self::$list_forms as $index => $form ) {
				$focus = array(
					'',
					'_focus',
				);
				switch ( $form ) {
					case 'JFormLabelTypography':
						$id = 'form_label';
						if ( isset( $settings[ $form ][ $id . '_typography_typography' ] ) && ( strpos( $settings[ $form ][ $id . '_typography_typography' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_typography_typography' ] = $settings[ $form ][ $id . '_typography_typography' ];
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $new_settings[ $key ] ) ) {
									unset( $new_settings[ $key ] );
								}
							}
						} else {
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $settings[ $form ][ $key ] ) ) {
									$new_settings[ $key ] = $settings[ $form ][ $key ];
								}
							}
							if ( isset( $new_settings['__globals__'][ $id . '_typography_typography' ] ) ) {
								unset( $new_settings['__globals__'][ $id . '_typography_typography' ] );
							}
						}
						if ( isset( $settings[ $form ][ $id . '_color' ] ) && ( strpos( $settings[ $form ][ $id . '_color' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_color' ] = $settings[ $form ][ $id . '_color' ];
						} else {
							if ( isset( $settings[ $form ][ $id . '_color' ] ) ) {
								$new_settings[ $id . '_color' ] = $settings[ $form ][ $id . '_color' ];
								if ( isset( $new_settings['__globals__'][ $id . '_color' ] ) ) {
									unset( $new_settings['__globals__'][ $id . '_color' ] );
								}
							}
						}
						break;
					case 'JFormTypography':
						$id = 'form_field';
						if ( isset( $settings[ $form ][ $id . '_typography_typography' ] ) && ( strpos( $settings[ $form ][ $id . '_typography_typography' ], 'globals' ) !== false ) ) {
							$new_settings['__globals__'][ $id . '_typography_typography' ] = $settings[ $form ][ $id . '_typography_typography' ];
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $new_settings[ $key ] ) ) {
									unset( $new_settings[ $key ] );
								}
							}
						} else {
							foreach ( self::$list_typography_setting as $index => $stt ) {
								$key = $id . '_' . $stt;
								if ( isset( $settings[ $form ][ $key ] ) ) {
									$new_settings[ $key ] = $settings[ $form ][ $key ];
								}
							}
							if ( isset( $new_settings['__globals__'][ $id . '_typography_typography' ] ) ) {
								unset( $new_settings['__globals__'][ $id . '_typography_typography' ] );
							}
						}
						break;
					case 'JFormTextColor':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_text_color' ] ) && ( strpos( $settings[ $form ][ $id . $fcs . '_text_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $fcs . '_text_color' ] = $settings[ $form ][ $id . $fcs . '_text_color' ];
							} else {
								if ( isset( $settings[ $form ][ $id . $fcs . '_text_color' ] ) ) {
									$new_settings[ $id . $fcs . '_text_color' ] = $settings[ $form ][ $id . $fcs . '_text_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $fcs . '_text_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $fcs . '_text_color' ] );
									}
								}
							}
						}
						break;
					case 'JFormAccentColor':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_accent_color' ] ) && ( strpos( $settings[ $form ][ $id . $fcs . '_accent_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $fcs . '_accent_color' ] = $settings[ $form ][ $id . $fcs . '_accent_color' ];
							} else {
								if ( isset( $settings[ $form ][ $id . $fcs . '_accent_color' ] ) ) {
									$new_settings[ $id . $fcs . '_accent_color' ] = $settings[ $form ][ $id . $fcs . '_accent_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $fcs . '_accent_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $fcs . '_accent_color' ] );
									}
								}
							}
						}
						break;
					case 'JFormBackgroundColor':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_background_color' ] ) && ( strpos( $settings[ $form ][ $id . $fcs . '_background_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $fcs . '_background_color' ] = $settings[ $form ][ $id . $fcs . '_background_color' ];
							} else {
								if ( isset( $settings[ $form ][ $id . $fcs . '_background_color' ] ) ) {
									$new_settings[ $id . $fcs . '_background_color' ] = $settings[ $form ][ $id . $fcs . '_background_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $fcs . '_background_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $fcs . '_background_color' ] );
									}
								}
							}
						}
						break;
					case 'JFormBoxShadow':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_type' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow_type' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_type' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_position' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow_position' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_position' ];
							}
						}
						break;
					case 'JFormBoxShadow':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_type' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow_type' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_type' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_position' ] ) ) {
								$new_settings[ $id . $fcs . '_box_shadow_box_shadow_position' ] = $settings[ $form ][ $id . $fcs . '_box_shadow_box_shadow_position' ];
							}
						}
						break;
					case 'JFormBorderType':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_border_border' ] ) ) {
								$new_settings[ $id . $fcs . '_border_border' ] = $settings[ $form ][ $id . $fcs . '_border_border' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_border_width' ] ) ) {
								$new_settings[ $id . $fcs . '_border_width' ] = $settings[ $form ][ $id . $fcs . '_border_width' ];
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_border_color' ] ) && ( strpos( $settings[ $form ][ $id . $fcs . '_border_color' ], 'globals' ) !== false ) ) {
								$new_settings['__globals__'][ $id . $fcs . '_border_color' ] = $settings[ $form ][ $id . $fcs . '_border_color' ];
							} else {
								if ( isset( $settings[ $form ][ $id . $fcs . '_border_color' ] ) ) {
									$new_settings[ $id . $fcs . '_border_color' ] = $settings[ $form ][ $id . $fcs . '_border_color' ];
									if ( isset( $new_settings['__globals__'][ $id . $fcs . '_border_color' ] ) ) {
										unset( $new_settings['__globals__'][ $id . $fcs . '_border_color' ] );
									}
								}
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $settings[ $form ][ $id . $fcs . '_border_width_' . $key ] ) ) {
									$new_settings[ $id . $fcs . '_border_width_' . $key ] = $settings[ $form ][ $id . $fcs . '_border_width_' . $key ];
								}
							}
							if ( isset( $settings[ $form ][ $id . $fcs . '_border_radius' ] ) ) {
								$new_settings[ $id . $fcs . '_border_radius' ] = $settings[ $form ][ $id . $fcs . '_border_radius' ];
							}
						}
						break;
					case 'JFormPadding':
						$id = 'form_field';
						foreach ( $focus as $fcs ) {
							if ( isset( $settings[ $form ][ $id . $fcs . '_padding' ] ) ) {
								$new_settings[ $id . $fcs . '_padding' ] = $settings[ $form ][ $id . $fcs . '_padding' ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $settings[ $form ][ $id . $fcs . '_padding_' . $key ] ) ) {
									$new_settings[ $id . $fcs . '_padding_' . $key ] = $settings[ $form ][ $id . $fcs . '_padding_' . $key ];
								}
							}
						}
						break;
					default:
						break;
				}
			}

			foreach ( self::$list_sites as $index => $site ) {
				switch ( $site ) {
					case 'JSiteName':
						if ( isset( $settings[ $site ] ) ) {
							if ( isset( $new_settings['site_description'] ) && $settings[ $site ] !== $new_settings['site_name'] ) {
								update_option( 'blogname', $settings[ $site ] );
							}
							$new_settings['site_name'] = $settings[ $site ];
						}
						break;
					case 'JSiteDescription':
						if ( isset( $settings[ $site ] ) ) {
							if ( isset( $new_settings['site_description'] ) && $settings[ $site ] !== $new_settings['site_description'] ) {
								update_option( 'blogdescription', $settings[ $site ] );
							}
							$new_settings['site_description'] = $settings[ $site ];
						}
						break;
					case 'JSiteLogo':
						if ( isset( $settings[ $site ] ) ) {
							if ( isset( $new_settings['site_logo'] ) && $settings[ $site ] !== $new_settings['site_logo'] ) {
								if ( isset( $settings[ $site ]['id'] ) ) {
									set_theme_mod( 'custom_logo', $settings[ $site ]['id'] );
								}
							}
							$new_settings['site_logo'] = $settings[ $site ];
						}
						break;
					case 'JSiteFavico':
						if ( isset( $settings[ $site ] ) ) {
							if ( isset( $new_settings['site_favicon'] ) && $settings[ $site ] !== $new_settings['site_favicon'] ) {
								if ( isset( $settings[ $site ]['id'] ) ) {
									update_option( 'site_icon', $settings[ $site ]['id'] );
								}
							}
							$new_settings['site_favicon'] = $settings[ $site ];
						}
						break;
					default:
						break;
				}
			}

			foreach ( self::$list_layouts as $index => $layout ) {
				switch ( $layout ) {
					case 'JLayoutContentWidth':
						if ( isset( $settings[ $layout ]['container_width'] ) ) {
							$new_settings['container_width'] = $settings[ $layout ]['container_width'];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];

							if ( isset( $settings[ $layout ][ 'container_width_' . $key ] ) ) {
								$new_settings[ 'container_width_' . $key ] = $settings[ $layout ][ 'container_width_' . $key ];
							}
						}
						break;
					case 'JLayoutWidgetsSpace':
						if ( isset( $settings[ $layout ] ) ) {
							$new_settings['space_between_widgets'] = $settings[ $layout ];
						}
						break;
					case 'JLayoutStretchSection':
						if ( isset( $settings[ $layout ] ) ) {
							$new_settings['stretched_section_container'] = $settings[ $layout ];
						}
						break;
					case 'JLayoutTitleSelector':
						if ( isset( $settings[ $layout ] ) ) {
							$new_settings['page_title_selector'] = $settings[ $layout ];
						}
						break;
					case 'JLayoutPageLayout':
						if ( isset( $settings[ $layout ] ) ) {
							$new_settings['default_page_template'] = $settings[ $layout ];
						}
						break;
					case 'JLayoutBreakpoints':
						$active_breakpoint = false;
						if ( isset( $settings[ $layout ]['active_breakpoints'] ) ) {
							$new_settings['active_breakpoints'] = $settings[ $layout ]['active_breakpoints'];
							$active_breakpoint                  = $settings[ $layout ]['active_breakpoints'];
						}
						if ( $active_breakpoint ) {
							foreach ( $active_breakpoint as $breakpoint ) {
								if ( isset( $settings[ $layout ][ $breakpoint ] ) ) {
									$new_settings[ $breakpoint ] = $settings[ $layout ][ $breakpoint ];
								}
							}
						}
						break;
					default:
						break;
				}
			}

			foreach ( self::$list_backgrounds as $index => $bg ) {
				switch ( $bg ) {
					case 'JBackgroundBackground':
						if ( isset( $settings[ $bg ]['body_background_color'] ) && ( strpos( $settings[ $bg ]['body_background_color'], 'globals' ) !== false ) ) {
							$new_settings['__globals__']['body_background_color'] = $settings[ $bg ]['body_background_color'];
						} else {
							if ( isset( $settings[ $bg ]['body_background_color'] ) ) {
								$new_settings['body_background_color'] = $settings[ $bg ]['body_background_color'];
								if ( isset( $new_settings['__globals__']['body_background_color'] ) ) {
									unset( $new_settings['__globals__']['body_background_color'] );
								}
							}
						}
						if ( isset( $settings[ $bg ]['body_background_color_b'] ) && ( strpos( $settings[ $bg ]['body_background_color_b'], 'globals' ) !== false ) ) {
							$new_settings['__globals__']['body_background_color_b'] = $settings[ $bg ]['body_background_color_b'];
						} else {
							if ( isset( $settings[ $bg ]['body_background_color_b'] ) ) {
								$new_settings['body_background_color_b'] = $settings[ $bg ]['body_background_color_b'];
								if ( isset( $new_settings['__globals__']['body_background_color_b'] ) ) {
									unset( $new_settings['__globals__']['body_background_color_b'] );
								}
							}
						}
						if ( isset( $settings[ $bg ]['body_background_color_stop'] ) ) {
							$new_settings['body_background_color_stop'] = $settings[ $bg ]['body_background_color_stop'];
						}
						if ( isset( $settings[ $bg ]['body_background_color_b_stop'] ) ) {
							$new_settings['body_background_color_b_stop'] = $settings[ $bg ]['body_background_color_b_stop'];
						}
						if ( isset( $settings[ $bg ]['body_background_background'] ) ) {
							$new_settings['body_background_background'] = $settings[ $bg ]['body_background_background'];
						}
						if ( isset( $settings[ $bg ]['body_background_gradient_type'] ) ) {
							$new_settings['body_background_gradient_type'] = $settings[ $bg ]['body_background_gradient_type'];
						} else {
							if ( isset( $new_settings['body_background_gradient_type'] ) ) {
								unset( $new_settings['body_background_gradient_type'] );
							}
						}
						if ( isset( $settings[ $bg ]['body_background_gradient_position'] ) ) {
							$new_settings['body_background_gradient_position'] = $settings[ $bg ]['body_background_gradient_position'];
						}
						if ( isset( $settings[ $bg ]['body_background_gradient_angle'] ) ) {
							$new_settings['body_background_gradient_angle'] = $settings[ $bg ]['body_background_gradient_angle'];
						}
						break;
					case 'JBackgroundMobile':
						if ( isset( $settings[ $bg ]['mobile_browser_background'] ) && ( strpos( $settings[ $bg ]['mobile_browser_background'], 'globals' ) !== false ) ) {
							$new_settings['__globals__']['mobile_browser_background'] = $settings[ $bg ]['mobile_browser_background'];
						} else {
							if ( isset( $settings[ $bg ]['mobile_browser_background'] ) ) {
								$new_settings['mobile_browser_background'] = $settings[ $bg ]['mobile_browser_background'];
								if ( isset( $new_settings['__globals__']['mobile_browser_background'] ) ) {
									unset( $new_settings['__globals__']['mobile_browser_background'] );
								}
							}
						}
						break;
					default:
						break;
				}
			}

			Meta::instance()->set_option( 'no_sync_global', true );

			update_post_meta( $kit_id, '_elementor_page_settings', $new_settings );

			// update site kit css (will generate the new css with the new globals).
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$post_css_file = new \Elementor\Core\Files\CSS\Post( $kit_id );
				$post_css_file->update();
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}

			Meta::instance()->set_option( 'no_sync_global', false );
		}
	}
}
