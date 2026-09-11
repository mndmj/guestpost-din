<?php
/**
 * WPML Animated Text module integration.
 *
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Integrations\WPML;

/**
 * Class Animated_Text_Module
 *
 * @package Jeg\Elementor_Kit\Integrations\WPML
 */
class Animated_Text_Module extends \WPML_Elementor_Module_With_Items {
	/**
	 * Get repeater field key.
	 *
	 * @return string
	 */
	public function get_items_field() {
		return 'sg_text_rotating_list';
	}

	/**
	 * Get translatable repeater fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array( 'sg_text_rotating_list_text' );
	}

	/**
	 * Get translation label.
	 *
	 * @param string $field Field key.
	 *
	 * @return string
	 */
	protected function get_title( $field ) {
		switch ( $field ) {
			case 'sg_text_rotating_list_text':
				return esc_html__( 'Jeg Kit Animated Text: Text: Rotating Text', 'jeg-elementor-kit' );
			default:
				return '';
		}
	}

	/**
	 * Get WPML editor type.
	 *
	 * @param string $field Field key.
	 *
	 * @return string
	 */
	protected function get_editor_type( $field ) {
		switch ( $field ) {
			case 'sg_text_rotating_list_text':
				return 'LINE';
			default:
				return '';
		}
	}
}
