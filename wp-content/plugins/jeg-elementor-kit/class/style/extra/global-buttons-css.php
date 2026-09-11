<?php
defined( 'ABSPATH' ) || exit();
// INCLUDED IN CLASS CSS.

$settings = '';
$prefix   = '--jkit-button-';

foreach ( $json_settings as $key_settings => $settings_value ) {
	switch ( $key_settings ) {
		case 'JButtonsTypography':
			foreach ( $settings_value as $key => $value ) {
				$setting = explode( 'button_typography_', $key );

				if ( ! isset( $setting[1] ) ) {
					continue;
				}

				$suffix = str_replace( '_', '-', $setting[1] );

				if ( is_array( $value ) ) {
					if ( isset( $value['size'] ) && ! empty( $value['size'] ) ) {
						$size = $value['size'];
						$unit = ( isset( $value['unit'] ) && ! empty( $value['unit'] ) ) ? $value['unit'] : 'px';

						$settings .= $prefix . $suffix . ': ' . $size . $unit . ';';
					}
				} elseif ( ! empty( $value ) ) {
					$settings .= $prefix . $suffix . ': ' . $value . ';';
				}
			}
			break;
		case 'JButtonsTextShadow':
			if ( isset( $settings_value['button_text_shadow_text_shadow_type'] ) ) {
				$text_shadow = $settings_value['button_text_shadow_text_shadow_type'];

				$color      = $text_shadow['color'];
				$horizontal = ( ! empty( $text_shadow['horizontal'] ) ) ? $text_shadow['horizontal'] . 'px ' : '0 ';
				$vertical   = ( ! empty( $text_shadow['vertical'] ) ) ? $text_shadow['vertical'] . 'px ' : '0 ';
				$blur       = ( ! empty( $text_shadow['blur'] ) ) ? $text_shadow['blur'] . 'px ' : '0 ';

				if ( ! empty( $color ) && ( ! empty( $horizontal ) || ! empty( $vertical ) || ! empty( $blur ) ) ) {
					$settings .= $prefix . 'text-shadow: ' . $horizontal . $vertical . $blur . $color . ';';
				}
			}
			break;
		case 'JButtonsPadding':
			foreach ( $settings_value as $key => $value ) {
				$setting = explode( 'button_', $key );
				$suffix  = str_replace( '_', '-', $setting[1] );

				$unit   = ( ! empty( $value['unit'] ) ) ? $value['unit'] . ' ' : 'px ';
				$top    = ( ! empty( $value['top'] ) ) ? $value['top'] . $unit : '';
				$right  = ( ! empty( $value['right'] ) ) ? $value['right'] . $unit : '';
				$bottom = ( ! empty( $value['bottom'] ) ) ? $value['bottom'] . $unit : '';
				$left   = ( ! empty( $value['left'] ) ) ? $value['left'] . $unit : '';

				if ( ! empty( $top ) || ! empty( $right ) || ! empty( $bottom ) || ! empty( $left ) ) {
					$settings .= $prefix . $suffix . ': ' . $top . $right . $bottom . $left . ';';
				}
			}
			break;
		case 'JButtonsTextColor':
			foreach ( $settings_value as $key => $value ) {
				if ( ! empty( $value ) ) {
					$setting = explode( 'button_', $key );
					$suffix  = str_replace( '_', '-', $setting[1] );

					$settings .= $prefix . $suffix . ': ' . $value . ';';
				}
			}
			break;
		case 'JButtonsBackground':
			$states = array( 'button_', 'button_hover_' );

			foreach ( $states as $state ) {
				$prefix_states = '--jkit-' . str_replace( '_', '-', $state );

				if ( isset( $settings_value[ $state . 'background_background' ] ) && 'gradient' === $settings_value[ $state . 'background_background' ] ) {
					$gradient_type = isset( $settings_value[ $state . 'background_gradient_type' ] ) ? $settings_value[ $state . 'background_gradient_type' ] : 'linear';
					$color_a       = isset( $settings_value[ $state . 'background_color' ] ) ? $settings_value[ $state . 'background_color' ] . ' ' : '';
					$color_a_stop  = $settings_value[ $state . 'background_color_stop' ]['size'] . $settings_value[ $state . 'background_color_stop' ]['unit'] . ', ';
					$color_b       = $settings_value[ $state . 'background_color_b' ] . ' ';
					$color_b_stop  = $settings_value[ $state . 'background_color_b_stop' ]['size'] . $settings_value[ $state . 'background_color_b_stop' ]['unit'];

					if ( 'radial' === $gradient_type ) {
						// position.
						$tilted = 'at ' . $settings_value[ $state . 'background_gradient_position' ] . ', ';
					} else {
						$gradient_type = 'linear';
						// angle.
						$tilted = $settings_value[ $state . 'background_gradient_angle' ]['size'] . $settings_value[ $state . 'background_gradient_angle' ]['unit'] . ', ';
					}

					$gradient = $gradient_type . '-gradient(' . $tilted . $color_a . $color_a_stop . $color_b . $color_b_stop . ')';

					$settings .= $prefix_states . 'background-image: ' . $gradient . ';';
					$settings .= $prefix_states . 'background-color: transparent;';
				} elseif ( isset( $settings_value[ $state . 'background_color' ] ) && ! empty( $settings_value[ $state . 'background_color' ] ) ) {
					$settings .= $prefix_states . 'background-color: ' . $settings_value[ $state . 'background_color' ] . ';';
				}
			}
			break;
		case 'JButtonsBoxShadow':
			$states = array( 'button_', 'button_hover_' );

			foreach ( $states as $state ) {
				if ( isset( $settings_value[ $state . 'box_shadow_box_shadow' ] ) ) {
					$prefix_states = '--jkit-' . str_replace( '_', '-', $state );
					$box_shadow    = $settings_value[ $state . 'box_shadow_box_shadow' ];

					if ( isset( $box_shadow['color'] ) && ! empty( $box_shadow['color'] ) ) {
						$color      = $box_shadow['color'];
						$horizontal = ( ! empty( $box_shadow['horizontal'] ) ) ? $box_shadow['horizontal'] . 'px ' : '0 ';
						$vertical   = ( ! empty( $box_shadow['vertical'] ) ) ? $box_shadow['vertical'] . 'px ' : '0 ';
						$blur       = ( ! empty( $box_shadow['blur'] ) ) ? $box_shadow['blur'] . 'px ' : '0 ';
						$spread     = ( ! empty( $box_shadow['spread'] ) ) ? $box_shadow['spread'] . 'px ' : '0 ';
						$position   = ( ! empty( $settings_value[ $state . 'box_shadow_box_shadow_position' ] ) ) ? $settings_value[ $state . 'box_shadow_box_shadow_position' ] . ' ' : 'outline ';

						$settings .= $prefix_states . 'box-shadow: ' . $position . $horizontal . $vertical . $blur . $spread . $color . ';';
					}
				}
			}
			break;
		case 'JButtonsBorderType':
			$states = array( 'button_', 'button_hover_' );

			foreach ( $states as $state ) {
				$prefix_states = '--jkit-' . str_replace( '_', '-', $state );

				if ( ! empty( $settings_value[ $state . 'border_border' ] ) ) {
					$settings .= $prefix_states . 'border-style: ' . $settings_value[ $state . 'border_border' ] . ';';

					if ( 'none' === $settings_value[ $state . 'border_border' ] ) {
						continue;
					}

					foreach ( $settings_value as $key => $value ) {
						if ( $state . 'border_border' !== $key ) {
							$explode = explode( $state, $key );

							if ( ! isset( $explode[1] ) ) {
								continue;
							}

							$suffix = str_replace( '_', '-', $explode[1] );

							if ( strpos( $suffix, 'hover' ) !== false ) {
								continue;
							}

							if ( is_array( $settings_value[ $key ] ) ) {
								$unit   = ( ! empty( $settings_value[ $key ]['unit'] ) ) ? $settings_value[ $key ]['unit'] . ' ' : 'px ';
								$top    = ( ! empty( $settings_value[ $key ]['top'] ) ) ? $settings_value[ $key ]['top'] . $unit : '';
								$right  = ( ! empty( $settings_value[ $key ]['right'] ) ) ? $settings_value[ $key ]['right'] . $unit : '';
								$bottom = ( ! empty( $settings_value[ $key ]['bottom'] ) ) ? $settings_value[ $key ]['bottom'] . $unit : '';
								$left   = ( ! empty( $settings_value[ $key ]['left'] ) ) ? $settings_value[ $key ]['left'] . $unit : '';

								if ( ! empty( $top ) || ! empty( $right ) || ! empty( $bottom ) || ! empty( $left ) ) {
									$settings .= $prefix_states . $suffix . ': ' . $top . $right . $bottom . $left . ';';
								}
							} else {
								$settings .= $prefix_states . $suffix . ': ' . $settings_value[ $key ] . ';';
							}
						}
					}
				}
			}
			break;
	}
}

$css .= '
body {
    ' . $settings . '
}
';
