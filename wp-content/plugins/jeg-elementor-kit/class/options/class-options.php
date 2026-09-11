<?php
/**
 * Options class
 *
 * @package jeg-elementor-kit
 * @author jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Options;

use Jeg\Elementor_Kit\Options\Settings;
use Jeg\Elementor_Kit\Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Options.
 *
 * @package jeg-elementor-kit
 */
class Options {
	/**
	 * Class instance
	 *
	 * @var Options
	 */
	private static $instance;

	/**
	 * Theme option
	 *
	 * @var array
	 */
	public static $theme_option;

	/**
	 * Defaults theme option
	 *
	 * @var array
	 */
	public static $defaults_theme_option;

	/**
	 * Theme option no defaults
	 *
	 * @var array
	 */
	public static $theme_option_no_defaults;

	/**
	 * Return class instance
	 *
	 * @return Options
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
		$this->setup_hook();
	}

	/**
	 * Setup Hook
	 */
	private function setup_hook() {
		add_action( 'after_setup_theme', array( $this, 'refresh_theme_options' ) );
		add_filter( 'add_post_metadata', array( '\Jeg\Elementor_Kit\Options\Options', 'sync_globals_with_elementor' ), 20, 5 );
		add_filter( 'update_post_metadata', array( '\Jeg\Elementor_Kit\Options\Options', 'sync_globals_with_elementor' ), 20, 5 );
		Meta::instance()->set_option( 'no_sync_global', false );
	}

	/**
	 * Refresh Theme Option
	 */
	public static function refresh_theme_options() {
		self::$theme_option = wp_parse_args( self::get_raw_option(), self::defaults() );
	}

	/**
	 * Get default options of Theme Option
	 * Contain System Color for Elementor and other configuration.
	 */
	public static function defaults() {
		$setting                     = array(
			'JColorPrimary'          => '#08344E',
			'JColorSecondary'        => '#5AA794',
			'JColorText'             => '#797979',
			'JColorAccent'           => '#FFEF62',
			'JColorTertiary'         => '#E9F3F1',
			'JColorMeta'             => '#AAAAAA',
			'JColorBorder'           => '#E2E2E5',
			'JFontPrimary'           => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontSecondary'         => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontText'              => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontAccent'            => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontTextMenu'          => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontTextButton'        => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontTextHero'          => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontTextFooter'        => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontBlogTitle'         => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontIconBoxTitle'      => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontPricingTitle'      => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JFontStepTitle'         => array(
				'font-family' => 'Inter',
				'font-weight' => '400',
			),
			'JTypographyBody'        => array(),
			'JTypographyLink'        => array(),
			'JTypographyH1'          => array(),
			'JTypographyH2'          => array(),
			'JTypographyH3'          => array(),
			'JTypographyH4'          => array(),
			'JTypographyH5'          => array(),
			'JTypographyH6'          => array(),
			'JButtonsTypography'     => array(),
			'JButtonsTextShadow'     => array(),
			'JButtonsPadding'        => array(),
			'JButtonsTextColor'      => array(),
			'JButtonsBackground'     => array(),
			'JButtonsBoxShadow'      => array(),
			'JButtonsBorderType'     => array(),
			'JImagesOpacity'         => array(),
			'JImagesBoxShadow'       => array(),
			'JImagesCSSFilter'       => array(),
			'JImagesHoverTransition' => array(),
			'JImagesBorder'          => array(),
			'JFormLabelTypography'   => array(),
			'JFormTypography'        => array(),
			'JFormPadding'           => array(),
			'JFormTextColor'         => array(),
			'JFormAccentColor'       => array(),
			'JFormBackgroundColor'   => array(),
			'JFormBoxShadow'         => array(),
			'JFormBorderType'        => array(),
			'JSiteName'              => get_bloginfo( 'name' ),
			'JSiteDescription'       => get_bloginfo( 'description', 'display' ),
			'JSiteLogo'              => array(),
			'JSiteFavico'            => array(),
			'JLayoutContentWidth'    => array(),
			'JLayoutWidgetsSpace'    => array(),
			'JLayoutTitleSelector'   => '',
			'JLayoutStretchSection'  => '',
			'JLayoutPageLayout'      => array(),
			'JLayoutBreakpoints'     => array(),
			'JBackgroundBackground'  => array(),
			'JBackgroundMobile'      => array(),
			'JCodeCSS'               => ' /* CUSTOM CSS */',
			'JCodeJSHead'            => ' // Additional JS Header',
			'JCodeJSFoot'            => ' // Additional JS Footer',
			'JAdditionalCursor'      => true,
		);
		$setting                     = self::get_default_elementor( $setting );
		self::$defaults_theme_option = $setting;

		return apply_filters( 'jkit_theme_options_defaults', self::$defaults_theme_option );
	}

	/**
	 * Get Default Elementor
	 *
	 * @param string $setting default setting.
	 * @return mixed
	 */
	public static function get_default_elementor( $setting ) {
		$device_control = jkit_get_elementor_responsive_breakpoints();
		$ele_kit_id     = get_option( 'elementor_active_kit', false );
		if ( false !== $ele_kit_id ) {
			$ele_global_data = get_post_meta( $ele_kit_id, '_elementor_page_settings', true );
			if ( $ele_global_data ) {
				$system_color = $ele_global_data['system_colors'];
				foreach ( Settings::$list_color as $index => $color ) {
					foreach ( $system_color as $sindex => $scolor ) {
						if ( ( $sindex === $index ) && isset( $scolor['color'] ) ) {
							$setting[ $color ] = $scolor['color'];
						}
					}
				}

				$system_font = $ele_global_data['system_typography'];
				foreach ( Settings::$list_font as $index => $font ) {
					foreach ( $system_font as $sindex => $sfont ) {
						if ( $sindex === $index ) {
							$setting[ $font ] = array(
								'font-family'         => isset( $sfont['typography_font_family'] ) ? $sfont['typography_font_family'] : null,
								'font-weight'         => isset( $sfont['typography_font_weight'] ) ? $sfont['typography_font_weight'] : null,
								'font-size'           => isset( $sfont['typography_font_size'] ) ? $sfont['typography_font_size'] : null,
								'font-transform'      => isset( $sfont['typography_text_transform'] ) ? $sfont['typography_text_transform'] : null,
								'font-style'          => isset( $sfont['typography_font_style'] ) ? $sfont['typography_font_style'] : null,
								'font-decoration'     => isset( $sfont['typography_text_decoration'] ) ? $sfont['typography_text_decoration'] : null,
								'font-line-height'    => isset( $sfont['typography_line_height'] ) ? $sfont['typography_line_height'] : null,
								'font-letter-spacing' => isset( $sfont['typography_letter_spacing'] ) ? $sfont['typography_letter_spacing'] : null,
								'font-word-spacing'   => isset( $sfont['typography_word_spacing'] ) ? $sfont['typography_word_spacing'] : null,
							);
							foreach ( $device_control as $control ) {
								$key                                  = $control['key'];
								$setting[ $font ][ "font-size-$key" ] = isset( $sfont[ "typography_font_size_$key" ] ) ? $sfont[ "typography_font_size_$key" ] : null;
								$setting[ $font ][ "font-line-height-$key" ]    = isset( $sfont[ "typography_line_height_$key" ] ) ? $sfont[ "typography_line_height_$key" ] : null;
								$setting[ $font ][ "font-letter-spacing-$key" ] = isset( $sfont[ "typography_letter_spacing_$key" ] ) ? $sfont[ "typography_letter_spacing_$key" ] : null;
								$setting[ $font ][ "font-word-spacing-$key" ]   = isset( $sfont[ "typography_word_spacing_$key" ] ) ? $sfont[ "typography_word_spacing_$key" ] : null;
							}
							$setting[ $font ] = array_filter(
								$setting[ $font ],
								function ( $value ) {
									return null !== $value;
								}
							);
						}
					}
				}

				$setting = self::sync_rest( $setting, $ele_global_data );
			}
		}

		return $setting;
	}

	/**
	 * Sync the rest of elementor option
	 *
	 * @param array  $new_setting new.
	 * @param string $setting saved.
	 *
	 * @return array
	 */
	public static function sync_rest( $new_setting, $setting ) {
		$device_control = jkit_get_elementor_responsive_breakpoints();
		// Typography.
		foreach ( Settings::$list_typography as $index => $typography ) {
			$tkey = strtolower( str_replace( 'JTypography', '', $typography ) );
			if ( 'link' === $tkey ) {
				$link = array(
					'normal',
					'hover',
				);
				foreach ( $link as $lnx ) {
					if ( isset( $setting['__globals__'][ $tkey . '_' . $lnx . '_typography_typography' ] ) && $setting['__globals__'][ $tkey . '_' . $lnx . '_typography_typography' ] ) {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting ] ) ) {
								unset( $new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting ] );
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting . '_' . $key ] ) ) {
									unset( $new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting . '_' . $key ] );
								}
							}
						}
						$new_setting[ $typography ][ $tkey . '_' . $lnx . '_typography_typography' ] = $setting['__globals__'][ $tkey . '_' . $lnx . '_typography_typography' ];
					} else {
						// typography.
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $setting[ $tkey . '_' . $lnx . '_' . $tsetting ] ) ) {
								$new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting ] = $setting[ $tkey . '_' . $lnx . '_' . $tsetting ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $setting[ $tkey . '_' . $lnx . '_' . $tsetting . '_' . $key ] ) ) {
									$new_setting[ $typography ][ $tkey . '_' . $lnx . '_' . $tsetting . '_' . $key ] = $setting[ $tkey . '_' . $lnx . '_' . $tsetting . '_' . $key ];
								}
							}
						}
					}

					// color.
					if ( isset( $setting[ $tkey . '_' . $lnx . '_color' ] ) ) {
						$new_setting[ $typography ][ $tkey . '_' . $lnx . '_color' ] = $setting[ $tkey . '_' . $lnx . '_color' ];
					}
				}
			} else {
				if ( isset( $setting['__globals__'][ $tkey . '_typography_typography' ] ) && $setting['__globals__'][ $tkey . '_typography_typography' ] ) {
					foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
						if ( isset( $new_setting[ $typography ][ $tkey . '_' . $tsetting ] ) ) {
							unset( $new_setting[ $typography ][ $tkey . '_' . $tsetting ] );
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $new_setting[ $typography ][ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
								unset( $new_setting[ $typography ][ $tkey . '_' . $tsetting . '_' . $key ] );
							}
						}
					}
					$new_setting[ $typography ][ $tkey . '_typography_typography' ] = $setting['__globals__'][ $tkey . '_typography_typography' ];
				} else {
					// typography.
					foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
						if ( isset( $setting[ $tkey . '_' . $tsetting ] ) ) {
							$new_setting[ $typography ][ $tkey . '_' . $tsetting ] = $setting[ $tkey . '_' . $tsetting ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
								$new_setting[ $typography ][ $tkey . '_' . $tsetting . '_' . $key ] = $setting[ $tkey . '_' . $tsetting . '_' . $key ];
							}
						}
					}
				}

				// color.
				if ( isset( $setting['__globals__'][ $tkey . '_color' ] ) && $setting['__globals__'][ $tkey . '_color' ] ) {
					$new_setting[ $typography ][ $tkey . '_color' ] = $setting['__globals__'][ $tkey . '_color' ];
				} elseif ( isset( $setting[ $tkey . '_color' ] ) ) {
						$new_setting[ $typography ][ $tkey . '_color' ] = $setting[ $tkey . '_color' ];
				}
			}
		}

		// Buttons.
		foreach ( Settings::$list_buttons as $index => $button ) {
			$tkey = 'button';
			switch ( $button ) {
				case 'JButtonsTypography':
					if ( isset( $setting['__globals__'][ $tkey . '_typography_typography' ] ) && $setting['__globals__'][ $tkey . '_typography_typography' ] ) {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $new_setting[ $button ][ $tkey . '_' . $tsetting ] ) ) {
								unset( $new_setting[ $button ][ $tkey . '_' . $tsetting ] );
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $new_setting[ $button ][ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									unset( $new_setting[ $button ][ $tkey . '_' . $tsetting . '_' . $key ] );
								}
							}
						}
						$new_setting[ $button ][ $tkey . '_typography_typography' ] = $setting['__globals__'][ $tkey . '_typography_typography' ];
					} else {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $setting[ $tkey . '_' . $tsetting ] ) ) {
								$new_setting[ $button ][ $tkey . '_' . $tsetting ] = $setting[ $tkey . '_' . $tsetting ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $setting[ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									$new_setting[ $button ][ $tkey . '_' . $tsetting . '_' . $key ] = $setting[ $tkey . '_' . $tsetting . '_' . $key ];
								}
							}
						}
					}
					break;
				case 'JButtonsTextShadow':
					if ( isset( $setting['button_text_shadow_text_shadow_type'] ) ) {
						$new_setting[ $button ]['button_text_shadow_text_shadow_type'] = $setting['button_text_shadow_text_shadow_type'];
					}
					if ( isset( $setting['button_text_shadow_text_shadow'] ) ) {
						$new_setting[ $button ]['button_text_shadow_text_shadow_type'] = $setting['button_text_shadow_text_shadow'];
					}
					break;
				case 'JButtonsTextColor':
					$hover = array(
						'',
						'_hover',
					);
					foreach ( $hover as $hvr ) {
						if ( isset( $setting['__globals__'][ 'button' . $hvr . '_text_color' ] ) && $setting['__globals__'][ 'button' . $hvr . '_text_color' ] ) {
							$new_setting[ $button ][ 'button' . $hvr . '_text_color' ] = $setting['__globals__'][ 'button' . $hvr . '_text_color' ];
						} elseif ( isset( $setting[ 'button' . $hvr . '_text_color' ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_text_color' ] = $setting[ 'button' . $hvr . '_text_color' ];
						}
					}
					break;
				case 'JButtonsBackground':
					$hover = array(
						'',
						'_hover',
					);
					foreach ( $hover as $hvr ) {
						if ( isset( $setting['__globals__'][ 'button' . $hvr . '_background_color' ] ) && $setting['__globals__'][ 'button' . $hvr . '_background_color' ] ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_color' ] = $setting['__globals__'][ 'button' . $hvr . '_background_color' ];
						} elseif ( isset( $setting[ 'button' . $hvr . '_background_color' ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_background_color' ] = $setting[ 'button' . $hvr . '_background_color' ];
						}
						if ( isset( $setting['__globals__'][ 'button' . $hvr . '_background_color_b' ] ) && $setting['__globals__'][ 'button' . $hvr . '_background_color_b' ] ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_color_b' ] = $setting['__globals__'][ 'button' . $hvr . '_background_color_b' ];
						} elseif ( isset( $setting[ 'button' . $hvr . '_background_color_b' ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_background_color_b' ] = $setting[ 'button' . $hvr . '_background_color_b' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_color_stop' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_color_stop' ] = $setting[ 'button' . $hvr . '_background_color_stop' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_color_b_stop' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_color_b_stop' ] = $setting[ 'button' . $hvr . '_background_color_b_stop' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_background' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_background' ] = $setting[ 'button' . $hvr . '_background_background' ];
						} elseif ( isset( $new_setting[ $button ][ 'button' . $hvr . '_background_background' ] ) ) {
								unset( $new_setting[ $button ][ 'button' . $hvr . '_background_background' ] );
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_gradient_type' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_gradient_type' ] = $setting[ 'button' . $hvr . '_background_gradient_type' ];
						} elseif ( isset( $new_setting[ $button ][ 'button' . $hvr . '_background_gradient_type' ] ) ) {
								unset( $new_setting[ $button ][ 'button' . $hvr . '_background_gradient_type' ] );
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_gradient_position' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_gradient_position' ] = $setting[ 'button' . $hvr . '_background_gradient_position' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_background_gradient_angle' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_background_gradient_angle' ] = $setting[ 'button' . $hvr . '_background_gradient_angle' ];
						}
					}
					break;
				case 'JButtonsBoxShadow':
					$hover = array(
						'',
						'_hover',
					);
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'button' . $hvr . '_box_shadow_box_shadow_type' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_box_shadow_box_shadow_type' ] = $setting[ 'button' . $hvr . '_box_shadow_box_shadow_type' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_box_shadow_box_shadow' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_box_shadow_box_shadow' ] = $setting[ 'button' . $hvr . '_box_shadow_box_shadow' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_box_shadow_box_shadow_position' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_box_shadow_box_shadow_position' ] = $setting[ 'button' . $hvr . '_box_shadow_box_shadow_position' ];
						}
					}
					break;
				case 'JButtonsBorderType':
					$hover = array(
						'',
						'_hover',
					);
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'button' . $hvr . '_border_border' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_border_border' ] = $setting[ 'button' . $hvr . '_border_border' ];
						}
						if ( isset( $setting[ 'button' . $hvr . '_border_width' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_border_width' ] = $setting[ 'button' . $hvr . '_border_width' ];
						}
						if ( isset( $setting['__globals__'][ 'button' . $hvr . '_border_color' ] ) && $setting['__globals__'][ 'button' . $hvr . '_border_color' ] ) {
							$new_setting[ $button ][ 'button' . $hvr . '_border_color' ] = $setting['__globals__'][ 'button' . $hvr . '_border_color' ];
						} elseif ( isset( $setting[ 'button' . $hvr . '_border_color' ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_border_color' ] = $setting[ 'button' . $hvr . '_border_color' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'button' . $hvr . '_border_width_' . $key ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_border_width_' . $key ] = $setting[ 'button' . $hvr . '_border_width_' . $key ];
							}
						}
						if ( isset( $setting[ 'button' . $hvr . '_border_radius' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_border_radius' ] = $setting[ 'button' . $hvr . '_border_radius' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'button' . $hvr . '_border_radius_' . $key ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_border_radius_' . $key ] = $setting[ 'button' . $hvr . '_border_radius_' . $key ];
							}
						}
					}
					break;
				case 'JButtonsPadding':
					$hover = array(
						'',
						'_hover',
					);
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'button' . $hvr . '_padding' ] ) ) {
							$new_setting[ $button ][ 'button' . $hvr . '_padding' ] = $setting[ 'button' . $hvr . '_padding' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'button' . $hvr . '_padding_' . $key ] ) ) {
								$new_setting[ $button ][ 'button' . $hvr . '_padding_' . $key ] = $setting[ 'button' . $hvr . '_padding_' . $key ];
							}
						}
					}
					break;
				default:
					break;
			}
		}

		foreach ( Settings::$list_images as $index => $image ) {
			$hover = array(
				'',
				'_hover',
			);
			switch ( $image ) {
				case 'JImagesBorder':
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'image' . $hvr . '_border_border' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_border_border' ] = $setting[ 'image' . $hvr . '_border_border' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_border_width' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_border_width' ] = $setting[ 'image' . $hvr . '_border_width' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'image' . $hvr . '_border_width_' . $key ] ) ) {
								$new_setting[ $image ][ 'image' . $hvr . '_border_width_' . $key ] = $setting[ 'image' . $hvr . '_border_width_' . $key ];
							}
						}
						if ( isset( $setting[ 'image' . $hvr . '_border_radius' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_border_radius' ] = $setting[ 'image' . $hvr . '_border_radius' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'image' . $hvr . '_border_radius_' . $key ] ) ) {
								$new_setting[ $image ][ 'image' . $hvr . '_border_radius_' . $key ] = $setting[ 'image' . $hvr . '_border_radius_' . $key ];
							}
						}
						if ( isset( $setting['__globals__'][ 'image' . $hvr . '_border_color' ] ) && $setting['__globals__'][ 'image' . $hvr . '_border_color' ] ) {
							$new_setting[ $image ][ 'image' . $hvr . '_border_color' ] = $setting['__globals__'][ 'image' . $hvr . '_border_color' ];
						} elseif ( isset( $setting[ 'image' . $hvr . '_border_color' ] ) ) {
								$new_setting[ $image ][ 'image' . $hvr . '_border_color' ] = $setting[ 'image' . $hvr . '_border_color' ];
						}
					}
					break;
				case 'JImagesOpacity':
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'image' . $hvr . '_opacity' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_opacity' ] = $setting[ 'image' . $hvr . '_opacity' ];
						}
					}
					break;
				case 'JImagesBoxShadow':
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'image' . $hvr . '_box_shadow_box_shadow_type' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_type' ] = $setting[ 'image' . $hvr . '_box_shadow_box_shadow_type' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_box_shadow_box_shadow' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow' ] = $setting[ 'image' . $hvr . '_box_shadow_box_shadow' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_box_shadow_box_shadow_position' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_box_shadow_box_shadow_position' ] = $setting[ 'image' . $hvr . '_box_shadow_box_shadow_position' ];
						}
					}
					break;
				case 'JImagesCSSFilter':
					foreach ( $hover as $hvr ) {
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_css_filter' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_css_filter' ] = $setting[ 'image' . $hvr . '_css_filters_css_filter' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_blur' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_blur' ] = $setting[ 'image' . $hvr . '_css_filters_blur' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_brightness' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_brightness' ] = $setting[ 'image' . $hvr . '_css_filters_brightness' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_contrast' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_contrast' ] = $setting[ 'image' . $hvr . '_css_filters_contrast' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_saturate' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_saturate' ] = $setting[ 'image' . $hvr . '_css_filters_saturate' ];
						}
						if ( isset( $setting[ 'image' . $hvr . '_css_filters_hue' ] ) ) {
							$new_setting[ $image ][ 'image' . $hvr . '_css_filters_hue' ] = $setting[ 'image' . $hvr . '_css_filters_hue' ];
						}
					}
					break;
				case 'JImagesHoverTransition':
					if ( isset( $setting['image_hover_transition'] ) ) {
						$new_setting[ $image ]['image_hover_transition'] = $setting['image_hover_transition'];
					}
					break;
				default:
					break;
			}
		}

		foreach ( Settings::$list_forms as $index => $form ) {
			$focus = array(
				'',
				'_focus',
			);
			switch ( $form ) {
				case 'JFormLabelTypography':
					$tkey = 'form_label';
					if ( isset( $setting['__globals__'][ $tkey . '_typography_typography' ] ) && $setting['__globals__'][ $tkey . '_typography_typography' ] ) {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $new_setting[ $form ][ $tkey . '_' . $tsetting ] ) ) {
								unset( $new_setting[ $form ][ $tkey . '_' . $tsetting ] );
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									unset( $new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] );
								}
							}
						}
						$new_setting[ $form ][ $tkey . '_typography_typography' ] = $setting['__globals__'][ $tkey . '_typography_typography' ];
					} else {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $setting[ $tkey . '_' . $tsetting ] ) ) {
								$new_setting[ $form ][ $tkey . '_' . $tsetting ] = $setting[ $tkey . '_' . $tsetting ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $setting[ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									$new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] = $setting[ $tkey . '_' . $tsetting . '_' . $key ];
								}
							}
						}
					}
					if ( isset( $setting['__globals__'][ $tkey . '_color' ] ) && $setting['__globals__'][ $tkey . '_color' ] ) {
						$new_setting[ $form ][ $tkey . '_color' ] = $setting['__globals__'][ $tkey . '_color' ];
					} elseif ( isset( $setting[ $tkey . '_color' ] ) ) {
							$new_setting[ $form ][ $tkey . '_color' ] = $setting[ $tkey . '_color' ];
					}
					break;
				case 'JFormTypography':
					$tkey = 'form_field';
					if ( isset( $setting['__globals__'][ $tkey . '_typography_typography' ] ) && $setting['__globals__'][ $tkey . '_typography_typography' ] ) {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $new_setting[ $form ][ $tkey . '_' . $tsetting ] ) ) {
								unset( $new_setting[ $form ][ $tkey . '_' . $tsetting ] );
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									unset( $new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] );
								}
							}
						}
						$new_setting[ $form ][ $tkey . '_typography_typography' ] = $setting['__globals__'][ $tkey . '_typography_typography' ];
					} else {
						foreach ( Settings::$list_typography_setting as $index => $tsetting ) {
							if ( isset( $setting[ $tkey . '_' . $tsetting ] ) ) {
								$new_setting[ $form ][ $tkey . '_' . $tsetting ] = $setting[ $tkey . '_' . $tsetting ];
							}
							foreach ( $device_control as $control ) {
								$key = $control['key'];
								if ( isset( $setting[ $tkey . '_' . $tsetting . '_' . $key ] ) ) {
									$new_setting[ $form ][ $tkey . '_' . $tsetting . '_' . $key ] = $setting[ $tkey . '_' . $tsetting . '_' . $key ];
								}
							}
						}
					}
					break;
				case 'JFormTextColor':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting['__globals__'][ 'form_field' . $fcs . '_text_color' ] ) && $setting['__globals__'][ 'form_field' . $fcs . '_text_color' ] ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_text_color' ] = $setting['__globals__'][ 'form_field' . $fcs . '_text_color' ];
						} elseif ( isset( $setting[ 'form_field' . $fcs . '_text_color' ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_text_color' ] = $setting[ 'form_field' . $fcs . '_text_color' ];
						}
					}
					break;
				case 'JFormAccentColor':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting['__globals__'][ 'form_field' . $fcs . '_accent_color' ] ) && $setting['__globals__'][ 'form_field' . $fcs . '_accent_color' ] ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_accent_color' ] = $setting['__globals__'][ 'form_field' . $fcs . '_accent_color' ];
						} elseif ( isset( $setting[ 'form_field' . $fcs . '_accent_color' ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_accent_color' ] = $setting[ 'form_field' . $fcs . '_accent_color' ];
						}
					}
					break;
				case 'JFormBackgroundColor':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting['__globals__'][ 'form_field' . $fcs . '_background_color' ] ) && $setting['__globals__'][ 'form_field' . $fcs . '_background_color' ] ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_background_color' ] = $setting['__globals__'][ 'form_field' . $fcs . '_background_color' ];
						} elseif ( isset( $setting[ 'form_field' . $fcs . '_background_color' ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_background_color' ] = $setting[ 'form_field' . $fcs . '_background_color' ];
						}
					}
					break;
				case 'JFormBoxShadow':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow_type' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_box_shadow_box_shadow_type' ] = $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow_type' ];
						}
						if ( isset( $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_box_shadow_box_shadow' ] = $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow' ];
						}
						if ( isset( $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow_position' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_box_shadow_box_shadow_position' ] = $setting[ 'form_field' . $fcs . '_box_shadow_box_shadow_position' ];
						}
					}
					break;
				case 'JFormBorderType':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting[ 'form_field' . $fcs . '_border_border' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_border_border' ] = $setting[ 'form_field' . $fcs . '_border_border' ];
						}
						if ( isset( $setting[ 'form_field' . $fcs . '_border_width' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_border_width' ] = $setting[ 'form_field' . $fcs . '_border_width' ];
						}
						if ( isset( $setting['__globals__'][ 'form_field' . $fcs . '_border_color' ] ) && $setting['__globals__'][ 'form_field' . $fcs . '_border_color' ] ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_border_color' ] = $setting['__globals__'][ 'form_field' . $fcs . '_border_color' ];
						} elseif ( isset( $setting[ 'form_field' . $fcs . '_border_color' ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_border_color' ] = $setting[ 'form_field' . $fcs . '_border_color' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'form_field' . $fcs . '_border_width_' . $key ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_border_width_' . $key ] = $setting[ 'form_field' . $fcs . '_border_width_' . $key ];
							}
						}
						if ( isset( $setting[ 'form_field' . $fcs . '_border_radius' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_border_radius' ] = $setting[ 'form_field' . $fcs . '_border_radius' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'form_field' . $fcs . '_border_radius_' . $key ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_border_radius_' . $key ] = $setting[ 'form_field' . $fcs . '_border_radius_' . $key ];
							}
						}
					}
					break;
				case 'JFormPadding':
					foreach ( $focus as $fcs ) {
						if ( isset( $setting[ 'form_field' . $fcs . '_padding' ] ) ) {
							$new_setting[ $form ][ 'form_field' . $fcs . '_padding' ] = $setting[ 'form_field' . $fcs . '_padding' ];
						}
						foreach ( $device_control as $control ) {
							$key = $control['key'];
							if ( isset( $setting[ 'form_field' . $fcs . '_padding_' . $key ] ) ) {
								$new_setting[ $form ][ 'form_field' . $fcs . '_padding_' . $key ] = $setting[ 'form_field' . $fcs . '_padding_' . $key ];
							}
						}
					}
					break;
				default:
					break;
			}
		}

		foreach ( Settings::$list_sites as $index => $site ) {
			switch ( $site ) {
				case 'JSiteName':
					if ( isset( $setting['site_name'] ) ) {
						$new_setting[ $site ] = $setting['site_name'];
					}
					break;
				case 'JSiteDescription':
					if ( isset( $setting['site_description'] ) ) {
						$new_setting[ $site ] = $setting['site_description'];
					}
					break;
				case 'JSiteLogo':
					if ( isset( $setting['site_logo'] ) ) {
						$new_setting[ $site ] = $setting['site_logo'];
					}
					break;
				case 'JSiteFavico':
					if ( isset( $setting['site_favicon'] ) ) {
						$new_setting[ $site ] = $setting['site_favicon'];
					}
					break;
				default:
					break;
			}
		}

		foreach ( Settings::$list_layouts as $index => $layout ) {
			switch ( $layout ) {
				case 'JLayoutContentWidth':
					if ( isset( $setting['container_width'] ) ) {
						$new_setting[ $layout ]['container_width'] = $setting['container_width'];
					}
					foreach ( $device_control as $control ) {
						$key = $control['key'];
						if ( isset( $setting[ 'container_width_' . $key ] ) ) {
							$new_setting[ $layout ][ 'container_width_' . $key ] = $setting[ 'container_width_' . $key ];
						}
					}
					break;
				case 'JLayoutWidgetsSpace':
					if ( isset( $setting['space_between_widgets'] ) ) {
						$new_setting[ $layout ] = $setting['space_between_widgets'];
					}
					break;
				case 'JLayoutStretchSection':
					if ( isset( $setting['stretched_section_container'] ) ) {
						$new_setting[ $layout ] = $setting['stretched_section_container'];
					}
					break;
				case 'JLayoutTitleSelector':
					if ( isset( $setting['page_title_selector'] ) ) {
						$new_setting[ $layout ] = $setting['page_title_selector'];
					}
					break;
				case 'JLayoutPageLayout':
					if ( isset( $setting['default_page_template'] ) ) {
						$new_setting[ $layout ] = $setting['default_page_template'];
					} else {
						$new_setting[ $layout ] = false;
					}
					break;
				case 'JLayoutBreakpoints':
					$active_breakpoint = false;
					if ( isset( $setting['active_breakpoints'] ) ) {
						$new_setting[ $layout ]['active_breakpoints'] = $setting['active_breakpoints'];
						$active_breakpoint                            = $setting['active_breakpoints'];
					}
					if ( $active_breakpoint ) {
						foreach ( $active_breakpoint as $breakpoint ) {
							if ( isset( $setting[ $breakpoint ] ) ) {
								$new_setting[ $layout ][ $breakpoint ] = $setting[ $breakpoint ];
							}
						}
					}
					break;
				default:
					break;
			}
		}

		foreach ( Settings::$list_backgrounds as $index => $bg ) {
			switch ( $bg ) {
				case 'JBackgroundBackground':
					if ( isset( $setting['__globals__']['body_background_color'] ) && $setting['__globals__']['body_background_color'] ) {
						$new_setting[ $bg ]['body_background_color'] = $setting['__globals__']['body_background_color'];
					} elseif ( isset( $setting['body_background_color'] ) ) {
							$new_setting[ $bg ]['body_background_color'] = $setting['body_background_color'];
					}
					if ( isset( $setting['__globals__']['body_background_color_b'] ) && $setting['__globals__']['body_background_color_b'] ) {
						$new_setting[ $bg ]['body_background_color_b'] = $setting['__globals__']['body_background_color_b'];
					} elseif ( isset( $setting['body_background_color_b'] ) ) {
							$new_setting[ $bg ]['body_background_color_b'] = $setting['body_background_color_b'];
					}
					if ( isset( $setting['body_background_color_stop'] ) ) {
						$new_setting[ $bg ]['body_background_color_stop'] = $setting['body_background_color_stop'];
					}
					if ( isset( $setting['body_background_color_b_stop'] ) ) {
						$new_setting[ $bg ]['body_background_color_b_stop'] = $setting['body_background_color_b_stop'];
					}
					if ( isset( $setting['body_background_background'] ) ) {
						$new_setting[ $bg ]['body_background_background'] = $setting['body_background_background'];
					}
					if ( isset( $setting['body_background_gradient_type'] ) ) {
						$new_setting[ $bg ]['body_background_gradient_type'] = $setting['body_background_gradient_type'];
					} elseif ( isset( $new_setting[ $bg ]['body_background_gradient_type'] ) ) {
							unset( $new_setting[ $bg ]['body_background_gradient_type'] );
					}
					if ( isset( $setting['body_background_gradient_position'] ) ) {
						$new_setting[ $bg ]['body_background_gradient_position'] = $setting['body_background_gradient_position'];
					}
					if ( isset( $setting['body_background_gradient_angle'] ) ) {
						$new_setting[ $bg ]['body_background_gradient_angle'] = $setting['body_background_gradient_angle'];
					}
					break;
				case 'JBackgroundMobile':
					if ( isset( $setting['__globals__']['mobile_browser_background'] ) && $setting['__globals__']['mobile_browser_background'] ) {
						$new_setting[ $bg ]['mobile_browser_background'] = $setting['__globals__']['mobile_browser_background'];
					} elseif ( isset( $setting['mobile_browser_background'] ) ) {
							$new_setting[ $bg ]['mobile_browser_background'] = $setting['mobile_browser_background'];
					}
					break;
				default:
					break;
			}
		}
		return $new_setting;
	}

	/**
	 * Update option
	 *
	 * @param array  $options setting.
	 * @param string $version version.
	 *
	 * @return array
	 */
	public static function update_option( $options, $version = 'random' ) {
		if ( ! is_array( self::$theme_option_no_defaults ) ) {
			self::$theme_option_no_defaults = array();
		}

		foreach ( self::get_theme_option() as $key => $value ) {
			if ( isset( $options[ $key ] ) && Settings::is_not_default( $value, $options[ $key ] ) ) {
				self::$theme_option_no_defaults[ $key ] = $options[ $key ];
			}
		}

		if ( 'random' === $version ) {
			$version = wp_rand( 100000, 999999 );
		}

		self::$theme_option_no_defaults['version'] = $version;

		update_option( JEG_ELEMENTOR_KIT_OPTIONS, self::$theme_option_no_defaults, true );

		return wp_parse_args(
			wp_parse_args(
				self::$theme_option_no_defaults,
				self::defaults()
			)
		);
	}

	/**
	 * Get raw option
	 * Contain System Color for Elementor and other configuration.
	 *
	 * @return array
	 */
	public static function get_raw_option() {
		self::$theme_option_no_defaults = get_option( JEG_ELEMENTOR_KIT_OPTIONS, array() );
		return self::$theme_option_no_defaults;
	}

	/**
	 * Get theme option
	 *
	 * @return array
	 */
	public static function get_theme_option() {
		if ( ! self::$theme_option ) {
			self::refresh_theme_options();
		}
		return self::$theme_option;
	}

	/**
	 * Delete theme option
	 */
	public static function delete_theme_option() {
		self::$theme_option = null;
	}

	/**
	 * Get all options
	 *
	 * @return array
	 */
	public static function get_all_options() {
		return wp_parse_args(
			self::get_theme_option(),
			self::defaults()
		);
	}

	/**
	 * Get option
	 *
	 * @param string $regenerate regen new css.
	 * @return void
	 */
	public static function clear_options( $regenerate = false ) {
		if ( $regenerate ) {
			$new = Settings::current_settings();
			Settings::update_settings( $new );
		}
	}

	/**
	 * We will sync Elementor global option with our theme_option.
	 *
	 * Short-circuits updating metadata of a specific type.
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  ID of the object metadata is for.
	 * @param string    $meta_key   Metadata key.
	 * @param mixed     $value Metadata value. Must be serializable if non-scalar.
	 * @param mixed     $prev_value Optional. Previous value to check before updating.
	 *                              If specified, only update existing metadata entries with
	 *                              this value. Otherwise, update all entries.
	 *
	 * @return null|bool
	 */
	public static function sync_globals_with_elementor( $check, $object_id, $meta_key, $value, $prev_value ) {
		$elementor_template_type = get_post_meta( $object_id, '_elementor_template_type', true );
		if ( empty( $elementor_template_type ) || 'kit' !== $elementor_template_type ) {
			return $check;
		}
		if ( Meta::instance()->get_option( 'no_sync_global' ) ) {
			return $check;
		}
		$kit_id         = (int) get_option( 'elementor_active_kit' );
		$device_control = jkit_get_elementor_responsive_breakpoints();
		if ( $object_id === $kit_id && '_elementor_page_settings' === $meta_key ) {
			$current_settings = Settings::current_settings();
			$is_jkit          = apply_filters( 'essential_doing_save_theme_option', false );
			$the_filter       = current_filter();
			// Global colors.
			$global_colors = array();
			foreach ( Settings::$list_color as $index => $color ) {
				$global_colors[] = array(
					'option' => $color,
					'id'     => 'essential_' . strtolower( str_replace( 'JColor', '', $color ) ),
					'Name'   => 'Essential - ' . str_replace( 'JColor', '', $color ),
				);
			}
			// Global fonts.
			$global_fonts = array();
			foreach ( Settings::$list_font as $index => $font ) {
				$global_fonts[] = array(
					'option' => $font,
					'id'     => 'essential_' . strtolower( str_replace( 'JFont', '', $font ) ),
					'Name'   => 'Essential - ' . str_replace( 'JFont', '', $font ),
				);
			}

			// Register global color to system color elementor.
			foreach ( $global_colors as $id => $color ) {
				if ( ! $is_jkit ) {
					$to_set                               = $value['system_colors'][ $id ]['color'];
					$current_settings[ $color['option'] ] = $to_set;
				} else {
					$value['system_colors'][ $id ]['color'] = $current_settings[ $color['option'] ];
					$value['system_colors'][ $id ]['_id']   = $color['id'];
					$value['system_colors'][ $id ]['name']  = $color['name'];
				}
			}

			// Register global text to system text elementor.
			foreach ( $global_fonts as $id => $font ) {
				if ( ! $is_jkit ) {
					$to_set                              = $value['system_typography'][ $id ];
					$current_settings[ $font['option'] ] = array(
						'font-family'         => isset( $to_set['typography_font_family'] ) ? $to_set['typography_font_family'] : null,
						'font-weight'         => isset( $to_set['typography_font_weight'] ) ? $to_set['typography_font_weight'] : null,
						'font-size'           => isset( $to_set['typography_font_size'] ) ? $to_set['typography_font_size'] : null,
						'font-transform'      => isset( $to_set['typography_text_transform'] ) ? $to_set['typography_text_transform'] : null,
						'font-style'          => isset( $to_set['typography_font_style'] ) ? $to_set['typography_font_style'] : null,
						'font-decoration'     => isset( $to_set['typography_text_decoration'] ) ? $to_set['typography_text_decoration'] : null,
						'font-line-height'    => isset( $to_set['typography_line_height'] ) ? $to_set['typography_line_height'] : null,
						'font-letter-spacing' => isset( $to_set['typography_letter_spacing'] ) ? $to_set['typography_letter_spacing'] : null,
						'font-word-spacing'   => isset( $to_set['typography_word_spacing'] ) ? $to_set['typography_word_spacing'] : null,
					);
					foreach ( $device_control as $control ) {
						$key = $control['key'];
						$current_settings[ $font['option'] ][ "font-size-$key" ]           = isset( $to_set[ "typography_font_size_$key" ] ) ? $to_set[ "typography_font_size_$key" ] : null;
						$current_settings[ $font['option'] ][ "font-line-height-$key" ]    = isset( $to_set[ "typography_line_height_$key" ] ) ? $to_set[ "typography_line_height_$key" ] : null;
						$current_settings[ $font['option'] ][ "font-letter-spacing-$key" ] = isset( $to_set[ "typography_letter_spacing_$key" ] ) ? $to_set[ "typography_letter_spacing_$key" ] : null;
						$current_settings[ $font['option'] ][ "font-word-spacing-$key" ]   = isset( $to_set[ "typography_word_spacing_$key" ] ) ? $to_set[ "typography_word_spacing_$key" ] : null;
					}
					$current_settings[ $font['option'] ] = array_filter(
						$current_settings[ $font['option'] ],
						function ( $value ) {
							return null !== $value;
						}
					);

				} else {
					$value['system_typography'][ $id ]         = $current_settings[ $font['option'] ];
					$value['system_typography'][ $id ]['_id']  = $font['id'];
					$value['system_typography'][ $id ]['name'] = $font['name'];
				}
			}

			$current_settings = self::sync_rest( $current_settings, $value );

			if ( ! $is_jkit ) {
				self::direct_save_meta( $the_filter, $object_id, $meta_key, $value, $prev_value );
				$check        = $value;
				$new_settings = self::update_option( $current_settings, 0 );

				Settings::clear_cache( true );
			}
		} elseif ( $object_id === $kit_id && '_elementor_css' === $meta_key ) {
			$elementor_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

			if ( ! $elementor_settings ) {
				Settings::sync_globals_with_jkit();
			}
		}
		return $check;
	}

	/**
	 * Save to meta without get fitered by hook.
	 *
	 * @param string $filter The save meta filter.
	 * @param int    $object_id  ID of the object metadata is for.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
	 * @param mixed  $unique_or_prev_value Previous value to check before updating or Whether the specified metadata key should be unique for the object.
	 */
	public static function direct_save_meta( $filter, $object_id, $meta_key, $meta_value, $unique_or_prev_value ) {
		$prev_value = $unique_or_prev_value;
		$unique     = $unique_or_prev_value;
		Meta::instance()->set_option( 'no_sync_global', true );

		if ( 'add_post_metadata' === $filter ) {
			add_metadata( 'post', $object_id, $meta_key, $meta_value, $unique );
		} elseif ( 'update_post_metadata' === $filter ) {
			update_metadata( 'post', $object_id, $meta_key, $meta_value, $prev_value );
		}
	}
}
