<?php
/**
 * Jeg Kit Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 1.0.0
 */

namespace Jeg\Elementor_Kit\Elements;

use Elementor\Controls_Manager;
use Jeg\Element\Element as Jeg_Element;

/**
 * Class Element
 *
 * @package Jeg\Elementor_Kit
 */
class Element {
	/**
	 * Element Manager
	 *
	 * @var Elements_Manager
	 */
	public $manager;

	/**
	 * Class instance
	 *
	 * @var Element
	 */
	private static $instance;

	/**
	 * Module constructor.
	 */
	private function __construct() {
		$this->setup_init();
		$this->setup_hook();
	}

	/**
	 * Setup Classes
	 */
	private function setup_init() {
		$this->manager = Jeg_Element::instance()->manager;
	}

	/**
	 * Setup Hooks
	 */
	private function setup_hook() {
		add_action( 'elementor/init', array( $this, 'register_pro_options' ) );
		add_filter( 'jeg_register_elements', array( $this, 'register_element' ) );
		add_filter( 'elementor/widgets/register', array( $this, 'register_element_pro' ), 99 );
		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'add_widget_options' ), 10 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( $this, 'add_column_options' ), 10, 2 );
		add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'add_section_options' ), 10, 2 );
		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'add_container_options' ), 10, 2 );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_categories' ), 99 );
		add_filter( 'elementor/editor/localize_settings', array( $this, 'scripts_localize_settings' ) );
		add_action( 'elementor/editor/templates/panel/category', array( $this, 'pro_promotion_link' ), 99 );
	}

	/**
	 * Get class instance
	 *
	 * @return Element
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Register all elements
	 *
	 * @param array $elements Elements.
	 *
	 * @return array
	 */
	public function register_element( $elements ) {
		$element_config = get_option( 'jkit_elements_enable', array() );

		foreach ( $this->list_elements() as $item ) {
			$item_key = 'jkit_' . strtolower( $item );

			if ( ! isset( $element_config[ $item_key ] ) || filter_var( $element_config[ $item_key ], FILTER_VALIDATE_BOOLEAN ) ) {
				$namespace             = '\Jeg\Elementor_Kit\Elements';
				$elements[ $item_key ] = array(
					'option'    => $namespace . '\Options\\' . $item . '_Option',
					'view'      => $namespace . '\Views\\' . $item . '_View',
					'elementor' => $namespace . '\Elementor\\' . $item . '_Elementor',
				);
			}
		}

		return $elements;
	}

	/**
	 * Register all pro elements
	 *
	 * @since 3.0.0
	 *
	 * @param \Elementor\Widgets_Manager $manager Elementor widgets manager.
	 */
	public function register_element_pro( $manager ) {
		if ( ! class_exists( '\Elementor\Modules\Promotions\Widgets\Pro_Widget_Promotion' ) ) {
			return;
		}

		foreach ( $this->list_pro_elements() as $item => $data ) {
			$item_key = 'jkit_pro_' . strtolower( $item );

			$manager->register(
				new Element_Pro(
					array(),
					array(
						'widget_name'  => $item_key,
						'widget_title' => $data['title'],
						// 'widget_category' => array( 'jkit-pro-elements' ),
					)
				)
			);
		}
	}

	/**
	 * Register Pro Options
	 *
	 * @since 3.0.0
	 */
	public function register_pro_options() {
		foreach ( $this->list_pro_options() as $item => $data ) {
			$item_key = 'jkit_pro_' . strtolower( $item );
			foreach ( $data['hooks'] as $hook ) {
				add_action(
					$hook,
					/**
					 * Register control
					 *
					 * @param $element \Elementor\Widget_Common|\Elementor\Element_Column|\Elementor\Element_Section Elementor element.
					 * @param $args array Arguments.
					 */
					function ( $element, $args ) use ( $item_key, $data ) {
						$current_class = get_class( $element );

						if ( isset( $data['show_locations'] ) ) {
							$show_location = $data['show_locations'];
							$current_class = str_replace( 'Elementor\\', '', $current_class );

							if ( ! in_array( $current_class, $show_location, true ) ) {
								return;
							}
						}

						foreach ( $data['segment'] as $id => $segment ) {
							$section = array(
								'label'     => $segment['name'],
								'tab'       => Controls_Manager::TAB_ADVANCED,
								'condition' => isset( $segment['dependency'] ) ? $this->parse_dependency_option( $segment['dependency'] ) : '',
							);
							$element->start_controls_section( $item_key . '_segment', $section );
							$element->add_control(
								$item_key . 'pro_banner',
								array(
									'type' => Controls_Manager::RAW_HTML,
									'raw'  => pro_banner_template(),
								)
							);
							$element->end_controls_section();
						}
					},
					10,
					2
				);
			}
		}
	}

	/**
	 * Register categories for pro elements
	 *
	 * @since 3.0.0
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_categories( $elements_manager ) {
		$elements_manager->add_category(
			'jeg-elementor-kit',
			array(
				'title' => esc_html__( 'Jeg Kit', 'jeg-elementor-kit' ),
				'icon'  => 'jkit-icon-pro',
			)
		);
		$elements_manager->add_category(
			'jkit-pro-elements',
			array(
				'title'     => esc_html__( 'Jeg Kit Pro', 'jeg-elementor-kit' ),
				'icon'      => 'jkit-icon-pro',
				'active'    => false,
				'promotion' => array(
					'url' => esc_url( JEG_ELEMENT_SERVER_URL ),
				),
			)
		);
		$elements_manager->add_category(
			'jeg-elementor-kit-single-post',
			array(
				'title' => esc_html__( 'Jeg Kit - Single Post', 'jeg-elementor-kit' ),
				'icon'  => 'jkit-icon-pro',
			)
		);
		$elements_manager->add_category(
			'jkit-pro-archive-elements',
			array(
				'title'     => esc_html__( 'Jeg Kit Pro - Archive', 'jeg-elementor-kit' ),
				'icon'      => 'jkit-icon-pro',
				'active'    => false,
				'promotion' => array(
					'url' => esc_url( JEG_ELEMENT_SERVER_URL ),
				),
			)
		);
		$elements_manager->add_category(
			'jkit-pro-woocommerce-elements',
			array(
				'title'     => esc_html__( 'Jeg Kit Pro - WooCommerce', 'jeg-elementor-kit' ),
				'icon'      => 'jkit-icon-pro',
				'active'    => false,
				'promotion' => array(
					'url' => esc_url( JEG_ELEMENT_SERVER_URL ),
				),
			)
		);
		$elements_manager->add_category(
			'jkit-pro-woocommerce-single-product-elements',
			array(
				'title'     => esc_html__( 'Jeg Kit Pro - Single Product', 'jeg-elementor-kit' ),
				'icon'      => 'jkit-icon-pro',
				'active'    => false,
				'promotion' => array(
					'url' => esc_url( JEG_ELEMENT_SERVER_URL ),
				),
			)
		);
	}

	/**
	 * List of elements
	 *
	 * @return array
	 */
	public function list_elements() {
		$default = array(
			'Nav_Menu',
			'Off_Canvas',
			'Search',
			'Icon_Box',
			'Image_Box',
			'Fun_Fact',
			'Progress_Bar',
			'Client_Logo',
			'Testimonials',
			'Accordion',
			'Gallery',
			'Team',
			'Pie_Chart',
			'Portfolio_Gallery',
			'Tabs',
			'Animated_Text',
			'Heading',
			'Countdown',
			'Button',
			'Dual_Button',
			'Video_Button',
			'Social_Share',
			'Post_Block',
			'Post_List',
			'Category_List',
			'Feature_List',
			'Contact_Form_7',
			'Mailchimp',
			'Post_Title',
			'Post_Featured_Image',
			'Post_Comment',
			'Post_Terms',
			'Post_Excerpt',
			'Post_Date',
			'Post_Author',
			'Post_Content',
			'Banner',
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$woo_elements = array(
				'Product_Grid',
				'Product_Carousel',
				'Product_Categories',
			);

			$default = array_merge( $default, $woo_elements );
		}

		return apply_filters( 'jkit_list_elements', $default );
	}

	/**
	 * List of Pro Elements
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function list_pro_elements() {
		$default = array(
			'Hotspot',
			'Timeline',
			'Back_To_Top',
			'Text_Background',
			'Breadcrumb',
			'Text_Marquee',
			'Horizontal_Accordion',
			'Charts',
		);

		$archive_elements = array(
			'Archive_Title',
			'Archive_Description',
		);

		foreach ( $default as $key => $value ) {
			$name              = strtolower( $value );
			$default[ $value ] = array(
				'name'       => strtolower( $value ),
				'title'      => str_replace( '_', ' ', $value ),
				'icon'       => 'jkit_pro_' . $name,
				'categories' => json_encode( array( 'jkit-pro-elements' ) ),
			);

			unset( $default[ $key ] );
		}

		foreach ( $archive_elements as $key => $value ) {
			$name              = strtolower( $value );
			$default[ $value ] = array(
				'name'       => strtolower( $value ),
				'title'      => str_replace( '_', ' ', $value ),
				'icon'       => 'jkit_pro_' . $name,
				'categories' => json_encode( array( 'jkit-pro-archive-elements' ) ),
			);
		}

		if ( class_exists( 'WooCommerce' ) ) {
			$woo_elements = array(
				'Product_Custom_Add_To_Cart',
				'Woocommerce_Cart_Page',
				'Woocommerce_Checkout_Page',
				'Woocommerce_Account_Page',
				'Woocommerce_Menu_Cart',
			);

			$woo_single_elements = array(
				'Product_Single_Title',
				'Product_Single_Breadcrumb',
				'Product_Single_Images',
				'Product_Single_Price',
				'Product_Single_Rating',
				'Product_Single_Description',
				'Product_Single_Content',
				'Product_Single_Related',
				'Product_Single_Add_To_Cart',
				'Product_Single_Stock',
				'Product_Single_Additional_Information',
				'Product_Single_Meta',
				'Product_Single_Data_Tabs',
			);

			foreach ( $woo_elements as $key => $value ) {
				$name                   = strtolower( $value );
				$woo_elements[ $value ] = array(
					'name'       => $name,
					'title'      => str_replace( '_', ' ', $value ),
					'icon'       => 'jkit_pro_' . $name,
					'categories' => json_encode( array( 'jkit-pro-woocommerce-elements' ) ),
				);

				unset( $woo_elements[ $key ] );
			}

			foreach ( $woo_single_elements as $key => $value ) {
				$name                          = strtolower( $value );
				$woo_single_elements[ $value ] = array(
					'name'       => $name,
					'title'      => str_replace( '_', ' ', $value ),
					'icon'       => 'jkit_pro_' . $name,
					'categories' => json_encode( array( 'jkit-pro-woocommerce-elements' ) ),
				);

				unset( $woo_single_elements[ $key ] );
			}

			$another_woo_elements = array(
				'Product_Archive' => array(
					'name'       => 'product_archive',
					'title'      => 'Product Archive',
					'icon'       => 'jkit_pro_product_archive',
					'categories' => json_encode( array( 'jkit-pro-archive-elements' ) ),
				)
			);

			$default = array_merge( $default, $woo_elements, $woo_single_elements, $another_woo_elements );
		}

		return apply_filters( 'jkit_list_pro_elements', $default );
	}

	/**
	 * List of Pro Options
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function list_pro_options() {
		$default = array(
			'Custom_Cursor'             => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
					'elementor/element/common/_section_style/after_section_end',
					'elementor/element/wp-page/document_settings/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
					'Widget_Common',
					'Core\DocumentTypes\Page',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Custom Cursor', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Blend_Mode'                => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
					'elementor/element/common/_section_style/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
					'Widget_Common',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Blend Mode', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Smooth_Scroll'             => array(
				'hooks'          => array(
					'elementor/element/wp-page/document_settings/after_section_end',
				),
				'show_locations' => array(
					'Core\DocumentTypes\Page',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Smooth Scroll', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Parallax_Effects'          => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
					'elementor/element/common/_section_style/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
					'Widget_Common',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Parallax/Scrolling Effects', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Mouse_Effects'             => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
					'elementor/element/common/_section_style/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
					'Widget_Common',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Mouse Effects', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Background_Motion_Effects' => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Background Motion Effects', 'jeg-elementor-kit' ),
						'priority' => 10,
					),
				),
			),
			'Background_Parallax'       => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Parallax/Scrolling Effects', 'jeg-elementor-kit' ),
						'priority' => 10,
					)
				),
			),
			'Background_Mouse_Effects'  => array(
				'hooks'          => array(
					'elementor/element/container/section_layout/after_section_end',
					'elementor/element/section/section_advanced/after_section_end',
					'elementor/element/column/section_advanced/after_section_end',
				),
				'show_locations' => array(
					'Includes\Elements\Container',
					'Element_Section',
					'Element_Column',
				),
				'segment'        => array(
					'segment' => array(
						'name'     => '<i class="jkit-pro-option-additional"></i> ' . esc_html__( 'Background Mouse Effects', 'jeg-elementor-kit' ),
						'priority' => 10,
					)
				),
			),
		);

		return apply_filters( 'jkit_list_pro_options', $default );
	}

	/**
	 * Localize editor settings.
	 *
	 * Filters the editor localized settings.
	 *
	 * @since 3.0.0
	 *
	 * @param array $client_env  Editor configuration.
	 * @param int   $post_id The ID of the current post being edited.
	 */
	public function scripts_localize_settings( $client_env ) {
		if ( ! isset( $client_env['promotionWidgets'] ) ) {
			$client_env['promotionWidgets'] = array();
		}

		// $client_env['jkit_elements'] = $this->list_elements();
		$client_env['promotionWidgets'] = array_merge( $client_env['promotionWidgets'], $this->list_pro_elements() );

		return $client_env;
	}

	/**
	 * Pro Promotion Link
	 */
	public function pro_promotion_link() {
		?>
		<# if ( 'undefined' !==typeof name && name && name.includes('jkit-pro-') ) { #>
			<span class="elementor-panel-heading-promotion jkit-pro-promotion">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=jkit&utm_source=jeg-elementor-kit&utm_medium=elementor-panel' ) ); ?>" target="_blank">
					<i class="eicon-upgrade-crown"></i><?php echo esc_html__( 'Upgrade', 'elementor' ); ?>
				</a>
			</span>
			<# } #>
				<?php
	}

	/**
	 * Make dependency fit with elementor
	 *
	 * @since 3.0.0
	 *
	 * @param  array $dependency Dependency.
	 *
	 * @return array
	 */
	public function parse_dependency_option( $dependency ) {
		$dependencies = array();

		foreach ( $dependency as $depend ) {
			$value                            = true === $depend['value'] ? 'yes' : $depend['value'];
			$dependencies[ $depend['field'] ] = $value;
		}

		return $dependencies;
	}

	/**
	 * Add custom option to elementor widgets
	 *
	 * @param \Elementor\Element_Base $element The edited element.
	 */
	public function add_widget_options( $element ) {
		$element->start_controls_section(
			'jkit_transform_section',
			array(
				'label'     => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Transform', 'jeg-elementor-kit' ),
				'tab'       => Controls_Manager::TAB_ADVANCED,
				'condition' => array(
					'_transform_rotate_popover!' => 'transform',
				),
			)
		);

		$element->add_responsive_control(
			'jkit_transform_rotate',
			array(
				'label'       => esc_html__( 'Rotate', 'jeg-elementor-kit' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 360,
						'step' => 1,
					),
				),
				'selectors'   => array(
					'{{WRAPPER}}:not(.e-transform)' . jkit_optimized_markup_class() => '-moz-transform: rotate({{SIZE}}deg); -webkit-transform: rotate({{SIZE}}deg); -o-transform: rotate({{SIZE}}deg); -ms-transform: rotate({{SIZE}}deg); transform: rotate({{SIZE}}deg);',
					'{{WRAPPER}}.e-transform' . jkit_optimized_markup_class() => '--e-transform-rotateZ: {{SIZE}}deg;',
				),
				'condition'   => array(
					'_transform_rotate_popover!' => 'transform',
				),
				'description' => esc_html__( 'Since Elementor version 3.5.0, it has its own Transform settings. When you use Transform Rotate from Elementor, this setting will be hidden.', 'jeg-elementor-kit' ),
			)
		);

		$element->end_controls_section();

		$element->start_controls_section(
			'jkit_glass_blur_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Glass Blur Effect', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_responsive_control(
			'jkit_glass_blur_level',
			array(
				'label'       => esc_html__( 'Blur', 'jeg-elementor-kit' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 0,
						'max'  => 20,
						'step' => 0.1,
					),
				),
				'description' => esc_html__( 'The blur effect will be set on the widget container. Make sure to set background to transparent to see the blur effect.', 'jeg-elementor-kit' ),
				'selectors'   => array(
					/** `--jkit-option-enabled` is to prevent CSS rendered as an option that does not have a value */
					'{{WRAPPER}}.elementor-widget' . jkit_optimized_markup_class() . ', {{WRAPPER}}.elementor-widget' . jkit_optimized_markup_class() . ' > *' => 'position: relative; --jkit-option-enabled: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}}.elementor-widget' . jkit_optimized_markup_class() . '::before' => 'content: ""; width: 100%; height: 100%; position: absolute; left: 0; top: 0; -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}}); border-radius: inherit; background-color: inherit;',
				),
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Add custom option to elementor columns
	 *
	 * @param \Elementor\Element_Base $column The edited element.
	 * @param array @args The         $args that sent to $element->start_controls_section.
	 */
	public function add_column_options( $column, $args ) {
		$column->start_controls_section(
			'jkit_glass_blur_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Glass Blur Effect', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$column->add_responsive_control(
			'jkit_glass_blur_level',
			array(
				'label'     => esc_html__( 'Blur', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 20,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}.elementor-column > .elementor-element-populated::before' => 'content: ""; width: 100%; height: 100%; position: absolute; left: 0; top: 0; -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}}); border-radius: inherit; background-color: inherit;',
				),
			)
		);

		$column->end_controls_section();

		$column->start_controls_section(
			'jkit_sticky_element_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Sticky Element', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$column->add_control(
			'jkit_sticky_section',
			array(
				'label'        => esc_html__( 'Enable Sticky Element', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'enabled',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled' => 'position: -webkit-sticky; position: sticky; height: fit-content;',
				),
				'prefix_class' => 'jkit-sticky-element--',
			)
		);

		$breakpoint_option = array( 'desktop' => 'Desktop' );

		foreach ( jkit_get_responsive_breakpoints() as $list ) {
			$breakpoint_option[ $list['key'] ] = ucwords( $list['key'] );
		}

		$column->add_control(
			'jkit_sticky_device',
			array(
				'label'              => esc_html__( 'Device', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SELECT2,
				'options'            => $breakpoint_option,
				'multiple'           => true,
				'default'            => array( 'desktop', 'tablet', 'mobile' ),
				'frontend_available' => true,
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
				),
				'label_block'        => true,
			)
		);

		$column->add_control(
			'jkit_sticky_trigger',
			array(
				'label'        => esc_html__( 'Sticky Trigger', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'down',
				'options'      => array(
					'up'   => esc_html__( 'On Scroll Up', 'jeg-elementor-kit' ),
					'down' => esc_html__( 'On Scroll Down', 'jeg-elementor-kit' ),
					'both' => esc_html__( 'On Both', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-element-on--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_position',
			array(
				'label'        => esc_html__( 'Sticky Position', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'sticky',
				'options'      => array(
					'sticky' => esc_html__( 'Sticky', 'jeg-elementor-kit' ),
					'fixed'  => esc_html__( 'Fixed', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-position--',
				'condition'    => array(
					'jkit_sticky_section'  => 'enabled',
					'jkit_sticky_trigger!' => 'both',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_top_position',
			array(
				'label'              => esc_html__( 'Top Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down'                                                                                                                                     => 'top: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both'                                                                                                                                     => 'top: {{SIZE}}{{UNIT}};',
					'#wpadminbar ~ {{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down, #wpadminbar ~ * {{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
					'#wpadminbar ~ {{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both, #wpadminbar ~ * {{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'down', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$column->add_control(
			'jkit_sticky_bottom_position',
			array(
				'label'              => esc_html__( 'Bottom Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--up'   => 'bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'up', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$column->add_control(
			'jkit_sticky_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned' => 'background-color: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_hide_on_scroll',
			array(
				'label'        => esc_html__( 'Hide on Scroll', 'jeg-elementor-klit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'hide-on-scroll',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => 'opacity: 0; pointer-events: none; cursor: default; transform: translate(var(--x-axis-animations, 0), var(--y-axis-animations, 0));',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_hide_threshold',
			array(
				'label'        => esc_html__( 'Scroll Threshold', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'threshold',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled' => 'position: -webkit-sticky; position: sticky; height: fit-content;',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_x_axis_animation',
			array(
				'label'      => esc_html__( 'X Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--x-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_y_axis_animation',
			array(
				'label'      => esc_html__( 'Y Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--y-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$column->add_control(
			'jkit_sticky_transition',
			array(
				'label'      => esc_html__( 'Transition', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 0.1,
				),
				'range'      => array(
					'px' => array(
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned' => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled'               => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
				),
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'jkit_sticky_section',
							'operator' => '===',
							'value'    => 'enabled',
						),
						array(
							'relation' => 'or',
							'terms'    => array(
								array(
									'name'     => 'jkit_sticky_background_color',
									'operator' => '!==',
									'value'    => '',
								),
								array(
									'name'     => 'jkit_sticky_hide_on_scroll',
									'operator' => '===',
									'value'    => 'hide-on-scroll',
								),
							),
						),
					),
				),
			)
		);

		$column->add_control(
			'jkit_sticky_zindex',
			array(
				'label'     => esc_html__( 'Z-Index', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 100,
				'min'       => 0,
				'max'       => 999,
				'step'      => 1,
				'selectors' => array(
					'{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled' => 'z-index: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$column->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'jkit_sticky_shadow',
				'label'     => esc_html__( 'Box Shadow', 'jeg-elementor-kit' ),
				'selector'  => '{{WRAPPER}}.elementor-column.jkit-sticky-element--enabled.sticky-pinned',
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$column->end_controls_section();
	}

	/**
	 * Add custom option to elementor sections
	 *
	 * @param \Elementor\Element_Base $section The edited element.
	 * @param array                   $args The args that sent to $element->start_controls_section.
	 */
	public function add_section_options( $section, $args ) {
		$section->start_controls_section(
			'jkit_glass_blur_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Glass Blur Effect', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$section->add_responsive_control(
			'jkit_glass_blur_level',
			array(
				'label'     => esc_html__( 'Blur', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 20,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}.elementor-section::before' => 'content: ""; width: 100%; height: 100%; position: absolute; left: 0; top: 0; -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}}); border-radius: inherit; background-color: inherit;',
				),
			)
		);

		$section->end_controls_section();

		$section->start_controls_section(
			'jkit_sticky_element_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Sticky Element', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$section->add_control(
			'jkit_sticky_section',
			array(
				'label'        => esc_html__( 'Enable Sticky Element', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'enabled',
				'prefix_class' => 'jkit-sticky-element--',
			)
		);

		$breakpoint_option = array( 'desktop' => 'Desktop' );

		foreach ( jkit_get_responsive_breakpoints() as $list ) {
			$breakpoint_option[ $list['key'] ] = ucwords( $list['key'] );
		}

		$section->add_control(
			'jkit_sticky_device',
			array(
				'label'              => esc_html__( 'Device', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SELECT2,
				'options'            => $breakpoint_option,
				'multiple'           => true,
				'default'            => 'desktop',
				'frontend_available' => true,
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
				),
				'label_block'        => true,
			)
		);

		$section->add_control(
			'jkit_sticky_trigger',
			array(
				'label'        => esc_html__( 'Sticky Trigger', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'down',
				'options'      => array(
					'up'   => esc_html__( 'On Scroll Up', 'jeg-elementor-kit' ),
					'down' => esc_html__( 'On Scroll Down', 'jeg-elementor-kit' ),
					'both' => esc_html__( 'On Both', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-element-on--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_position',
			array(
				'label'        => esc_html__( 'Sticky Position', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'sticky',
				'options'      => array(
					'sticky' => esc_html__( 'Sticky', 'jeg-elementor-kit' ),
					'fixed'  => esc_html__( 'Fixed', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-position--',
				'condition'    => array(
					'jkit_sticky_section'  => 'enabled',
					'jkit_sticky_trigger!' => 'both',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_top_position',
			array(
				'label'              => esc_html__( 'Top Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down'                                                                                                                                      => 'top: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both'                                                                                                                                      => 'top: {{SIZE}}{{UNIT}};',
					'#wpadminbar ~ {{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down, #wpadminbar ~ * {{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
					'#wpadminbar ~ {{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both, #wpadminbar ~ * {{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'down', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$section->add_control(
			'jkit_sticky_bottom_position',
			array(
				'label'              => esc_html__( 'Bottom Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--up'   => 'bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'up', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$section->add_control(
			'jkit_sticky_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned' => 'background-color: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_hide_on_scroll',
			array(
				'label'        => esc_html__( 'Hide on Scroll', 'jeg-elementor-klit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'hide-on-scroll',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => 'opacity: 0; pointer-events: none; cursor: default; transform: translate(var(--x-axis-animations, 0), var(--y-axis-animations, 0));',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_hide_threshold',
			array(
				'label'        => esc_html__( 'Scroll Threshold', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'threshold',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled' => 'position: -webkit-sticky; position: sticky; height: fit-content;',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_x_axis_animation',
			array(
				'label'      => esc_html__( 'X Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--x-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_y_axis_animation',
			array(
				'label'      => esc_html__( 'Y Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--y-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$section->add_control(
			'jkit_sticky_transition',
			array(
				'label'      => esc_html__( 'Transition', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 0.1,
				),
				'range'      => array(
					'px' => array(
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned' => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled'               => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
				),
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'jkit_sticky_section',
							'operator' => '===',
							'value'    => 'enabled',
						),
						array(
							'relation' => 'or',
							'terms'    => array(
								array(
									'name'     => 'jkit_sticky_background_color',
									'operator' => '!==',
									'value'    => '',
								),
								array(
									'name'     => 'jkit_sticky_hide_on_scroll',
									'operator' => '===',
									'value'    => 'hide-on-scroll',
								),
							),
						),
					),
				),
			)
		);

		$section->add_control(
			'jkit_sticky_zindex',
			array(
				'label'     => esc_html__( 'Z-Index', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 100,
				'min'       => 0,
				'max'       => 9999,
				'step'      => 1,
				'selectors' => array(
					'{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled' => 'z-index: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$section->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'jkit_sticky_shadow',
				'label'     => esc_html__( 'Box Shadow', 'jeg-elementor-kit' ),
				'selector'  => '{{WRAPPER}}.elementor-section.jkit-sticky-element--enabled.sticky-pinned',
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$section->end_controls_section();
	}

	/**
	 * Add custom option to elementor containers
	 *
	 * @param \Elementor\Element_Base $container The edited element.
	 * @param array                   $args The args that sent to $element->start_controls_section.
	 */
	public function add_container_options( $container, $args ) {
		$container->start_controls_section(
			'jkit_glass_blur_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Glass Blur Effect', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$container->add_responsive_control(
			'jkit_glass_blur_level',
			array(
				'label'     => esc_html__( 'Blur', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 20,
						'step' => 0.1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}}.elementor-element.e-flex::before' => 'content: ""; width: 100%; height: 100%; position: absolute; left: 0; top: 0; -webkit-backdrop-filter: blur({{SIZE}}{{UNIT}}); backdrop-filter: blur({{SIZE}}{{UNIT}}); border-radius: inherit; background-color: inherit;',
				),
			)
		);

		$container->end_controls_section();

		$container->start_controls_section(
			'jkit_sticky_element_section',
			array(
				'label' => '<i class="jkit-option-additional"></i> ' . esc_html__( 'Sticky Element', 'jeg-elementor-kit' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$container->add_control(
			'jkit_sticky_section',
			array(
				'label'        => esc_html__( 'Enable Sticky Element', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'enabled',
				'prefix_class' => 'jkit-sticky-element--',
			)
		);

		$breakpoint_option = array( 'desktop' => 'Desktop' );

		foreach ( jkit_get_responsive_breakpoints() as $list ) {
			$breakpoint_option[ $list['key'] ] = ucwords( $list['key'] );
		}

		$container->add_control(
			'jkit_sticky_device',
			array(
				'label'              => esc_html__( 'Device', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SELECT2,
				'options'            => $breakpoint_option,
				'multiple'           => true,
				'default'            => 'desktop',
				'frontend_available' => true,
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
				),
				'label_block'        => true,
			)
		);

		$container->add_control(
			'jkit_sticky_trigger',
			array(
				'label'        => esc_html__( 'Sticky Trigger', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'down',
				'options'      => array(
					'up'   => esc_html__( 'On Scroll Up', 'jeg-elementor-kit' ),
					'down' => esc_html__( 'On Scroll Down', 'jeg-elementor-kit' ),
					'both' => esc_html__( 'On Both', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-element-on--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_position',
			array(
				'label'        => esc_html__( 'Sticky Position', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'sticky',
				'options'      => array(
					'sticky' => esc_html__( 'Sticky', 'jeg-elementor-kit' ),
					'fixed'  => esc_html__( 'Fixed', 'jeg-elementor-kit' ),
				),
				'prefix_class' => 'jkit-sticky-position--',
				'condition'    => array(
					'jkit_sticky_section'  => 'enabled',
					'jkit_sticky_trigger!' => 'both',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_top_position',
			array(
				'label'              => esc_html__( 'Top Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down'                                                                                                                                             => 'top: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both'                                                                                                                                             => 'top: {{SIZE}}{{UNIT}};',
					'#wpadminbar ~ {{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down, #wpadminbar ~ * {{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--down' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
					'#wpadminbar ~ {{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both, #wpadminbar ~ * {{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'top: calc({{SIZE}}{{UNIT}} + var(--wpadminbar-height, 0px));',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'down', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$container->add_control(
			'jkit_sticky_bottom_position',
			array(
				'label'              => esc_html__( 'Bottom Position', 'jeg-elementor-kit' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', '%' ),
				'range'              => array(
					'px' => array(
						'min' => 0,
						'max' => 500,
					),
					'%'  => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'            => array(
					'size' => 0,
					'unit' => 'px',
				),
				'selectors'          => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--up'   => 'bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.jkit-sticky-element-on--both' => 'bottom: {{SIZE}}{{UNIT}};',
				),
				'condition'          => array(
					'jkit_sticky_section' => 'enabled',
					'jkit_sticky_trigger' => array( 'up', 'both' ),
				),
				'frontend_available' => true,
			)
		);

		$container->add_control(
			'jkit_sticky_background_color',
			array(
				'label'     => esc_html__( 'Background Color', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned' => 'background-color: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_hide_on_scroll',
			array(
				'label'        => esc_html__( 'Hide on Scroll', 'jeg-elementor-klit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'hide-on-scroll',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => 'opacity: 0; pointer-events: none; cursor: default; transform: translate(var(--x-axis-animations, 0), var(--y-axis-animations, 0));',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_hide_threshold',
			array(
				'label'        => esc_html__( 'Scroll Threshold', 'jeg-elementor-kit' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'threshold',
				'selectors'    => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled' => 'position: -webkit-sticky; position: sticky; height: fit-content;',
				),
				'prefix_class' => 'jkit-sticky-element--',
				'condition'    => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_x_axis_animation',
			array(
				'label'      => esc_html__( 'X Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--x-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_y_axis_animation',
			array(
				'label'      => esc_html__( 'Y Axis Animation', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'%'  => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
					'em' => array(
						'max'  => 200,
						'min'  => -200,
						'step' => 1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned.hide-sticky' => '--y-axis-animations: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'jkit_sticky_section'        => 'enabled',
					'jkit_sticky_hide_on_scroll' => 'hide-on-scroll',
				),
			)
		);

		$container->add_control(
			'jkit_sticky_transition',
			array(
				'label'      => esc_html__( 'Transition', 'jeg-elementor-kit' ),
				'type'       => Controls_Manager::SLIDER,
				'default'    => array(
					'size' => 0.1,
				),
				'range'      => array(
					'px' => array(
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned' => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled'               => 'transition: margin {{SIZE}}s, padding {{SIZE}}s, background {{SIZE}}s, box-shadow {{SIZE}}s, transform {{SIZE}}s, opacity {{SIZE}}s;',
				),
				'conditions' => array(
					'relation' => 'and',
					'terms'    => array(
						array(
							'name'     => 'jkit_sticky_section',
							'operator' => '===',
							'value'    => 'enabled',
						),
						array(
							'relation' => 'or',
							'terms'    => array(
								array(
									'name'     => 'jkit_sticky_background_color',
									'operator' => '!==',
									'value'    => '',
								),
								array(
									'name'     => 'jkit_sticky_hide_on_scroll',
									'operator' => '===',
									'value'    => 'hide-on-scroll',
								),
							),
						),
					),
				),
			)
		);

		$container->add_control(
			'jkit_sticky_zindex',
			array(
				'label'     => esc_html__( 'Z-Index', 'jeg-elementor-kit' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 100,
				'min'       => 0,
				'max'       => 9999,
				'step'      => 1,
				'selectors' => array(
					'{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled' => 'z-index: {{VALUE}};',
				),
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$container->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'      => 'jkit_sticky_shadow',
				'label'     => esc_html__( 'Box Shadow', 'jeg-elementor-kit' ),
				'selector'  => '{{WRAPPER}}.elementor-element.e-flex.jkit-sticky-element--enabled.sticky-pinned',
				'condition' => array(
					'jkit_sticky_section' => 'enabled',
				),
			)
		);

		$container->end_controls_section();
	}
}
