<?php
/**
 * Pro Elements Class
 *
 * @package jeg-elementor-kit
 * @author Jegtheme
 * @since 3.0.0
 */
namespace Jeg\Elementor_Kit\Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\Modules\Promotions\Widgets\Pro_Widget_Promotion as Elementor_Pro_Widget_Promotion;
use Elementor\Core\Utils\Promotions\Filtered_Promotions_Manager;

class Element_Pro extends Elementor_Pro_Widget_Promotion {
	/**
	 * The widget data.
	 *
	 * @var array
	 */
	private $widget_data;

	public function hide_on_search() {
		return true;
	}

	public function show_in_panel() {
		return false;
	}

	public function get_name() {
		return $this->widget_data['widget_name'];
	}

	public function get_title() {
		return $this->widget_data['widget_title'];
	}

	// public function get_categories() {
	// return $this->widget_data['widget_category'];
	// }

	/**
	 * Enqueue custom styles.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return array( 'jkit-notice-banner' );
	}

	/**
	 * Enqueue custom scripts.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'jkit-notice-banner' );
	}

	public function on_import( $element ) {
		$element['settings']['__should_import'] = true;

		return $element;
	}

	protected function register_controls() {}

	protected function render() {
		if ( $this->is_editor_render() ) {
			$this->render_promotion();
		} else {
			$this->render_empty_content();
		}
	}

	private function is_editor_render(): bool {
		return \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	private function render_promotion() {
		echo jkit_get_template_part( 'templates/banner/upgrade-to-pro' );
	}

	private function get_promotion_image_url(): string {
		return ELEMENTOR_ASSETS_URL . 'images/go-pro.svg';
	}

	private function render_empty_content() {
		echo ' ';
	}

	protected function content_template() {}

	public function __construct( $data = array(), $args = null ) {
		$this->widget_data = array(
			'widget_name'  => $args['widget_name'],
			'widget_title' => $args['widget_title'],
			// 'widget_category' => $args['widget_category'],
		);

		// add_filter(
		// 	'elementor/editor/promotion/get_elements_promotion',
		// 	function ( $promotions ) {
		// 		$promotions['title'] = 'ASD';
		// 		return $promotions;
		// 	}
		// );

		parent::__construct( $data, $args );
	}

	public function render_plain_content( $instance = array() ) {}
}
