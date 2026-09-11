<?php
/**
 * WPML Testimonials module integration.
 *
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Integrations\WPML;

/**
 * Class Testimonials_Module
 *
 * @package Jeg\Elementor_Kit\Integrations\WPML
 */
class Testimonials_Module extends \WPML_Elementor_Module_With_Items {
	/**
	 * Get repeater field key.
	 *
	 * @return string
	 */
	public function get_items_field() {
		return 'sg_testimonials_list';
	}

	/**
	 * Get translatable repeater fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array( 'sg_testimonials_list_client_name', 'sg_testimonials_list_designation', 'sg_testimonials_list_review' );
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
			case 'sg_testimonials_list_client_name':
				return esc_html__( 'Jeg Kit Testimonials: Testimonials: Client Name', 'jeg-elementor-kit' );
			case 'sg_testimonials_list_designation':
				return esc_html__( 'Jeg Kit Testimonials: Testimonials: Designation', 'jeg-elementor-kit' );
			case 'sg_testimonials_list_review':
				return esc_html__( 'Jeg Kit Testimonials: Testimonials: Review', 'jeg-elementor-kit' );
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
			case 'sg_testimonials_list_client_name':
			case 'sg_testimonials_list_designation':
			case 'sg_testimonials_list_review':
				return 'LINE';
			default:
				return '';
		}
	}
}
