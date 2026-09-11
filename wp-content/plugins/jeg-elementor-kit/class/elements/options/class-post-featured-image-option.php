<?php
/**
 * Post Featured Image Option Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 1.5.0
 */

namespace Jeg\Elementor_Kit\Elements\Options;

/**
 * Class Post_Featured_Image_Option
 *
 * @package Jeg\Elementor_Kit\Elements\Options
 */
class Post_Featured_Image_Option extends Option_Abstract {
	/**
	 * Show color scheme flag for element.
	 *
	 * @return bool
	 */
	public function show_color_scheme() {
		return false;
	}

	/**
	 * Compatibility column
	 *
	 * @return array
	 */
	public function compatible_column() {
		return array();
	}

	/**
	 * Override function to remove compatible column alert
	 */
	public function set_compatible_column_option() {
	}

	/**
	 * Element name
	 *
	 * @return string
	 */
	public function get_element_name() {
		return esc_html__( 'Jeg Kit - Post Featured Image', 'jeg-elementor-kit' );
	}

	/**
	 * Element category
	 *
	 * @return string
	 */
	public function get_category() {
		return esc_html__( 'Jeg Kit - Single Post', 'jeg-elementor-kit' );
	}

	/**
	 * Element options
	 */
	public function set_options() {
		$this->set_style_option();
		$this->set_element_options();

		parent::set_options();
	}

	/**
	 * Option segments
	 */
	public function set_segments() {
		$this->segments['segment_image'] = array(
			'name'     => esc_html__( 'Post Featured Image', 'jeg-elementor-kit' ),
			'priority' => 10,
		);

		$this->set_style_segment();
	}

	/**
	 * Style segments
	 */
	public function set_style_segment() {
		$this->segments['style_image'] = array(
			'name'      => esc_html__( 'Post Featured Image', 'jeg-elementor-kit' ),
			'priority'  => 11,
			'kit_style' => true,
		);

		parent::set_style_segment();
	}

	/**
	 * Set element option
	 */
	public function set_element_options() {
		$this->options['sg_image_size'] = array(
			'type'    => 'imagesize',
			'title'   => esc_html__( 'Image Size', 'jeg-elementor-kit' ),
			'segment' => 'segment_image',
			'default' => 'large',
		);

		$this->options['sg_image_link_to'] = array(
			'type'    => 'select',
			'title'   => esc_html__( 'Link To', 'jeg-elementor-kit' ),
			'default' => 'none',
			'segment' => 'segment_image',
			'options' => array(
				'none'   => esc_html__( 'None', 'jeg-elementor-kit' ),
				'home'   => esc_html__( 'Home URL', 'jeg-elementor-kit' ),
				'post'   => esc_html__( 'Post URL', 'jeg-elementor-kit' ),
				'media'  => esc_html__( 'Media URL', 'jeg-elementor-kit' ),
				'custom' => esc_html__( 'Custom URL', 'jeg-elementor-kit' ),
			),
		);

		$this->options['sg_image_link_to_custom'] = array(
			'type'       => 'link',
			'title'      => esc_html__( 'Link', 'jeg-elementor-kit' ),
			'segment'    => 'segment_image',
			'dependency' => array(
				array(
					'field'    => 'sg_image_link_to',
					'operator' => '==',
					'value'    => 'custom',
				),
			),
		);
	}

	/**
	 * Add Additional Style.
	 */
	public function additional_style() {
		$this->options['st_image_alignment'] = array(
			'type'       => 'radio',
			'title'      => esc_html__( 'Alignment', 'jeg-elementor-kit' ),
			'segment'    => 'style_image',
			'options'    => array(
				'left'   => array(
					'title' => esc_html__( 'Left', 'jeg-elementor-kit' ),
					'icon'  => 'fas fa-align-left',
				),
				'center' => array(
					'title' => esc_html__( 'Center', 'jeg-elementor-kit' ),
					'icon'  => 'fas fa-align-center',
				),
				'right'  => array(
					'title' => esc_html__( 'Right', 'jeg-elementor-kit' ),
					'icon'  => 'fas fa-align-right',
				),
			),
			'responsive' => true,
			'default'    => 'center',
			'selectors'  => '.jeg-elementor-kit.jkit-post-featured-image',
			'attribute'  => 'text-align',
		);

		$this->options['st_image_size'] = array(
			'type'       => 'slider',
			'title'      => esc_html__( 'Width', 'jeg-elementor-kit' ),
			'segment'    => 'style_image',
			'options'    => array(
				'min'  => 0,
				'max'  => 1000,
				'step' => 1,
			),
			'units'      => array( 'px', 'em', '%' ),
			'responsive' => true,
			'selectors'  => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute'  => 'max-width',
		);

		$this->options['st_image_height'] = array(
			'type'       => 'slider',
			'title'      => esc_html__( 'Height', 'jeg-elementor-kit' ),
			'segment'    => 'style_image',
			'options'    => array(
				'min'  => 0,
				'max'  => 1000,
				'step' => 1,
			),
			'units'      => array( 'px', 'em', '%' ),
			'responsive' => true,
			'selectors'  => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute'  => 'max-height',
		);

		$this->options['st_image_object_fit'] = array(
			'type'       => 'select',
			'title'      => esc_html__( 'Object Fit', 'jeg-elementor-kit' ),
			'default'    => '',
			'segment'    => 'style_image',
			'options'    => array(
				''        => esc_html__( 'Default', 'jeg-elementor-kit' ),
				'contain' => esc_html__( 'Contain', 'jeg-elementor-kit' ),
				'cover'   => esc_html__( 'Cover', 'jeg-elementor-kit' ),
				'fill'    => esc_html__( 'Fill', 'jeg-elementor-kit' ),
				'none'    => esc_html__( 'None', 'jeg-elementor-kit' ),
			),
			'responsive' => true,
			'selectors'  => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute'  => 'object-fit',
		);

		$this->options['st_image_object_position'] = array(
			'type'       => 'select',
			'title'      => esc_html__( 'Object Position', 'jeg-elementor-kit' ),
			'default'    => 'center center',
			'segment'    => 'style_image',
			'options'    => array(
				'center center' => esc_html__( 'Center Center', 'jeg-elementor-kit' ),
				'center left'   => esc_html__( 'Center Left', 'jeg-elementor-kit' ),
				'center right'  => esc_html__( 'Center Right', 'jeg-elementor-kit' ),
				'top center'    => esc_html__( 'Top Center', 'jeg-elementor-kit' ),
				'top left'      => esc_html__( 'Top Left', 'jeg-elementor-kit' ),
				'top right'     => esc_html__( 'Top Right', 'jeg-elementor-kit' ),
				'bottom center' => esc_html__( 'Bottom Center', 'jeg-elementor-kit' ),
				'bottom left'   => esc_html__( 'Bottom Left', 'jeg-elementor-kit' ),
				'bottom right'  => esc_html__( 'Bottom Right', 'jeg-elementor-kit' ),
				'custom'        => esc_html__( 'Custom', 'jeg-elementor-kit' ),
			),
			'responsive' => true,
			'selectors'  => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute'  => 'object-position',
		);

		// Custom numeric position (horizontal / vertical)
		$this->options['st_image_object_position_custom'] = array(
			'type'               => 'dimension',
			'title'              => esc_html__( 'Object Position (Custom)', 'jeg-elementor-kit' ),
			'segment'            => 'style_image',
			'units'              => array( '%', 'px' ),
			'allowed_dimensions' => array( 'left', 'top' ),
			'default'            => array( 'left' => '50', 'top' => '50', 'unit' => '%', 'right' => '0', 'bottom' => '0' ),
			'selectors'          => array(
				'custom' => array(
					'{{WRAPPER}} .jeg-elementor-kit.jkit-post-featured-image .post-featured-image img' => 'object-position: {{LEFT}}{{UNIT}} {{TOP}}{{UNIT}};',
				),
			),
			'responsive'         => true,
			'dependency'         => array(
				array(
					'field'    => 'st_image_object_position_responsive',
					'operator' => '==',
					'value'    => 'custom',
				),
			),
		);



		$this->options['st_image_opacity'] = array(
			'type'         => 'slider',
			'title'        => esc_html__( 'Opacity', 'jeg-elementor-kit' ),
			'segment'      => 'style_image',
			'options'      => array(
				'min'  => 0,
				'max'  => 100,
				'step' => 1,
			),
			'units'        => array( '%' ),
			'default_unit' => '%',
			'responsive'   => true,
			'selectors'    => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute'    => 'opacity',
		);

		$this->options['st_image_rotate'] = array(
			'type'       => 'slider',
			'title'      => esc_html__( 'Rotate', 'jeg-elementor-kit' ),
			'segment'    => 'style_image',
			'options'    => array(
				'min'  => -360,
				'max'  => 360,
				'step' => 1,
			),
			'responsive' => true,
			'selectors'  => array(
				'custom' => array(
					'{{WRAPPER}} .jeg-elementor-kit.jkit-post-featured-image .post-featured-image img' => '-moz-transform: rotate({{SIZE}}deg); -webkit-transform: rotate({{SIZE}}deg); -o-transform: rotate({{SIZE}}deg); -ms-transform: rotate({{SIZE}}deg); transform: rotate({{SIZE}}deg);',
				),
			),
		);

		$this->options['st_image_boxshadow'] = array(
			'type'      => 'boxshadow',
			'title'     => esc_html__( 'Box Shadow', 'jeg-elementor-kit' ),
			'segment'   => 'style_image',
			'selectors' => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
		);

		$this->options['st_image_border'] = array(
			'type'      => 'border',
			'title'     => esc_html__( 'Border', 'jeg-elementor-kit' ),
			'segment'   => 'style_image',
			'selectors' => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
		);

		$this->options['st_image_border_radius'] = array(
			'type'      => 'dimension',
			'title'     => esc_html__( 'Border Radius', 'jeg-elementor-kit' ),
			'segment'   => 'style_image',
			'units'     => array( 'px', '%', 'em' ),
			'selectors' => '.jeg-elementor-kit.jkit-post-featured-image .post-featured-image img',
			'attribute' => 'border-radius',
		);

		$this->options['st_image_hover_animation'] = array(
			'type'    => 'hoveranimation',
			'title'   => esc_html__( 'Hover Animation', 'jeg-elementor-kit' ),
			'segment' => 'style_image',
		);

		parent::additional_style();
	}
}
