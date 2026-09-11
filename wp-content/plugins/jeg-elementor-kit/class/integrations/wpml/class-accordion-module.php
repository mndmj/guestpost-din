<?php
/**
 * WPML Accordion module integration.
 *
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Integrations\WPML;

/**
 * Class Accordion_Module
 *
 * @package Jeg\Elementor_Kit\Integrations\WPML
 */
class Accordion_Module extends \WPML_Elementor_Module_With_Items {
	/**
	 * Get repeater field key.
	 *
	 * @return string
	 */
	public function get_items_field() {
		return 'sg_accordion_list';
	}

	/**
	 * Get translatable repeater fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array( 'sg_accordion_list_title', 'sg_accordion_list_content' );
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
			case 'sg_accordion_list_title':
				return esc_html__( 'Jeg Kit Accordion: Accordion: Title', 'jeg-elementor-kit' );
			case 'sg_accordion_list_content':
				return esc_html__( 'Jeg Kit Accordion: Accordion: Content Description', 'jeg-elementor-kit' );
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
			case 'sg_accordion_list_title':
				return 'LINE';
			case 'sg_accordion_list_content':
				return 'VISUAL';
			default:
				return '';
		}
	}
}
