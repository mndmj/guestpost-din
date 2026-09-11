<?php
/**
 * Header_Dashboard_Template
 *
 * @author Jegtheme
 * @since 2.0.0
 * @package jeg-kit
 */

namespace Jeg\Elementor_Kit\Dashboard\Template;

use Jeg\Elementor_Kit\Dashboard\Dashboard;

/**
 * Class Header_Dashboard_Template
 *
 * @package jeg-kit
 */
class Header_Dashboard_Template extends Template_Dashboard_Abstract {
	/**
	 * Config
	 *
	 * @return array
	 */
	public function config() {
		$config = parent::config();

		$config['data'] = jkit_get_element_data( Dashboard::$jkit_header );
		$config['type'] = Dashboard::$jkit_header;

		return $config;
	}

	/**
	 * Template deafult title
	 *
	 * @return string
	 */
	public static function default_title() {
		return esc_html__( 'Header Template', 'jeg-elementor-kit' );
	}

	/**
	 * Language
	 *
	 * @return array
	 */
	public function language() {
		$create_description = defined( 'JEG_KIT_PRO' )
			? esc_html__( 'Add header template to use them across your website. You can create multiple header and select where to use them.', 'jeg-elementor-kit' )
			: esc_html__( 'Add header template to use them across your website. You can create multiple header and select where to use them. Upgrade to Pro to make more Templates.', 'jeg-elementor-kit' );

		return array_merge(
			array(
				'createfirst'         => esc_html__( 'Create Header Template', 'jeg-elementor-kit' ),
				'createdescription'   => $create_description,
				'addnewelement'       => esc_html__( 'Add New header', 'jeg-elementor-kit' ),
				'createelement'       => esc_html__( 'Create Header', 'jeg-elementor-kit' ),
				'createconditiondesc' => esc_html__( 'Create filter where your header will be shown, leave empty to show it everywhere.', 'jeg-elementor-kit' ),
				'globalelement'       => esc_html__( 'Global Header', 'jeg-elementor-kit' ),
				'manageelement'       => esc_html__( 'Manage Header', 'jeg-elementor-kit' ),
				'managedescription'   => esc_html__( 'Drag header to change sequence. Smaller sequence will prioritized to shown when criteria match.', 'jeg-elementor-kit' ),
				'activeelement'       => esc_html__( 'Active Header', 'jeg-elementor-kit' ),
				'inactiveelement'     => esc_html__( 'Inactive Header', 'jeg-elementor-kit' ),
				'inactiveelementdesc' => esc_html__( 'Drag the template below to disable the template.', 'jeg-elementor-kit' ),
				'deleteelement'       => esc_html__( 'Delete Header', 'jeg-elementor-kit' ),
			),
			parent::language()
		);
	}
}
