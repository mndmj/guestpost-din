<?php
/**
 * Post Content View Class
 *
 * @package jeg-kit
 * @author Jegtheme
 * @since 2.2.0
 */

namespace Jeg\Elementor_Kit\Elements\Views;

/**
 * Class Post_Content_View
 *
 * @package Jeg\Elementor_Kit\Elements\Views
 */
class Post_Content_View extends View_Abstract {
	/**
	 * Build block content
	 *
	 * @return mixed
	 */
	public function build_content() {
		$content          = '';
		$raw_post_content = '';

		if ( jeg_is_editor_elementor() ) {
			$post = get_posts(
				array(
					'post_type'   => 'post',
					'orderby'     => 'rand',
					'numberposts' => 1,
				)
			);

			$raw_post_content = $post ? $post[0]->post_content : '';
			$content          = $raw_post_content;

			if ( '' === $content ) {
				$content = esc_html__( 'This is dummy post content and will be replaced with real content of your post. ', 'jeg-elementor-kit' ) . esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Donec at orci sed metus malesuada eleifend. Praesent condimentum metus ac euismod efficitur.', 'jeg-elementor-kit' );
			}
		} else {
			global $post;

			$raw_post_content = $post instanceof \WP_Post ? $post->post_content : '';

			ob_start();
			the_content();
			$content = ob_get_clean();
		}

		$inner_class = 'jkit-post-content__inner';

		if ( function_exists( 'has_blocks' ) && has_blocks( $raw_post_content ) ) {
			$inner_class .= ' entry-content content-inner wp-block-post-content';
		}

		$content = sprintf( '<div class="%s">%s</div>', esc_attr( $inner_class ), do_shortcode( $content ) );

		return $this->render_wrapper( 'post-content', $content );
	}
}
