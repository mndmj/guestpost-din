<?php
/**
 * WPML Portfolio Gallery module integration.
 *
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Integrations\WPML;

/**
 * Class Portfolio_Gallery_Module
 *
 * @package Jeg\Elementor_Kit\Integrations\WPML
 */
class Portfolio_Gallery_Module extends \WPML_Elementor_Module_With_Items {
	/**
	 * Get repeater field key.
	 *
	 * @return string
	 */
	public function get_items_field() {
		return 'sg_gallery_list';
	}

	/**
	 * Get translatable repeater fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array( 'sg_gallery_list_title', 'sg_gallery_list_subtitle', 'sg_gallery_list_more_link' => array( 'url' ), 'sg_gallery_list_more_text' );
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
			case 'sg_gallery_list_title':
				return esc_html__( 'Jeg Kit Portfolio Gallery: Gallery: Title', 'jeg-elementor-kit' );
			case 'sg_gallery_list_subtitle':
				return esc_html__( 'Jeg Kit Portfolio Gallery: Gallery: Sub Title', 'jeg-elementor-kit' );
			case 'url':
				return esc_html__( 'Jeg Kit Portfolio Gallery: Gallery: View More Link', 'jeg-elementor-kit' );
			case 'sg_gallery_list_more_text':
				return esc_html__( 'Jeg Kit Portfolio Gallery: Gallery: View More Text', 'jeg-elementor-kit' );
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
			case 'sg_gallery_list_title':
			case 'sg_gallery_list_subtitle':
			case 'sg_gallery_list_more_text':
				return 'LINE';
			case 'url':
				return 'LINK';
			default:
				return '';
		}
	}
}
