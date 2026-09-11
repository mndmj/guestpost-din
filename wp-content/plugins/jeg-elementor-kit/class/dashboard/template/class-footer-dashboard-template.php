<?php
/**
 * Footer_Dashboard_Template
 *
 * @author Jegtheme
 * @since 2.0.0
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Dashboard\Template;

use Jeg\Elementor_Kit\Dashboard\Dashboard;

/**
 * Class Footer_Dashboard_Template
 *
 * @package jeg-kit
 */
class Footer_Dashboard_Template extends Template_Dashboard_Abstract {
	/**
	 * Config
	 *
	 * @return array
	 */
	public function config() {
		$config = parent::config();

		$config['data'] = jkit_get_element_data( Dashboard::$jkit_footer );
		$config['type'] = Dashboard::$jkit_footer;

		return $config;
	}

	/**
	 * Template deafult title
	 *
	 * @return string
	 */
	public static function default_title() {
		return esc_html__( 'Footer Template', 'jeg-elementor-kit' );
	}

	/**
	 * Language
	 *
	 * @return array
	 */
	public function language() {
		$create_description = defined( 'JEG_KIT_PRO' )
			? esc_html__( 'Add footer template to use them across your website. You can create multiple footer and select where to use them.', 'jeg-elementor-kit' )
			: esc_html__( 'Add footer template to use them across your website. You can create multiple footer and select where to use them. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );

		return array_merge(
			array(
				'createfirst'         => esc_html__( 'Create Footer Template', 'jeg-elementor-kit' ),
				'createdescription'   => $create_description,
				'addnewelement'       => esc_html__( 'Add New Footer', 'jeg-elementor-kit' ),
				'createelement'       => esc_html__( 'Create Footer', 'jeg-elementor-kit' ),
				'createconditiondesc' => esc_html__( 'Create filter where your footer will be shown, leave empty to show it everywhere.', 'jeg-elementor-kit' ),
				'globalelement'       => esc_html__( 'Global Footer', 'jeg-elementor-kit' ),
				'manageelement'       => esc_html__( 'Manage Footer', 'jeg-elementor-kit' ),
				'managedescription'   => esc_html__( 'Drag footer to change sequence. Smaller sequence will prioritized to shown when criteria match.', 'jeg-elementor-kit' ),
				'activeelement'       => esc_html__( 'Active Footer', 'jeg-elementor-kit' ),
				'inactiveelement'     => esc_html__( 'Inactive Footer', 'jeg-elementor-kit' ),
				'inactiveelementdesc' => esc_html__( 'Drag the template below to disable the template.', 'jeg-elementor-kit' ),
				'deleteelement'       => esc_html__( 'Delete Footer', 'jeg-elementor-kit' ),
			),
			parent::language()
		);
	}
}
