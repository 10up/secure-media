<?php
/**
 * Single view module
 *
 * @since  1.0
 * @package  advanced-media
 */

namespace AdvancedMedia\Modules\SingleViews;

use AdvancedMedia\Utils;

/**
 * Module class
 */
class Module extends \AdvancedMedia\Modules\Module {

	/**
	 * Setup hooks
	 */
	public function setup() {
		if ( 'no' !== Utils\get_settings( 'show_single_view' ) ) {
			return;
		}

		add_filter( 'template_redirect', [ $this, 'maybe_redirect_media' ] );
		add_filter( 'attachment_link', [ $this, 'cleanup_attachment_link' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'hide_front_end_functionality' ] );
		add_filter( 'media_row_actions', [ $this, 'modify_row_actions' ], 10, 2 );
	}

	/**
	 * Hide front end view functionality in admin
	 */
	public function hide_front_end_functionality() {
		global $pagenow;

		if ( 'post.php' === $pagenow && 'attachment' === get_post_type( $_GET['post'] ) ) {
			?>
			<style type="text/css">
			.post-type-attachment #edit-slug-box {
				display: none;
			}
			</style>
			<?php
		}
	}

	/**
	 * Remove view from media cations
	 *
	 * @param  array $actions Current actions
	 * @param  WP_Post $post  Current post
	 * @return array
	 */
	public function modify_row_actions( $actions, $post ) {
		unset( $actions['view'] );

		return $actions;
	}

	/**
	 * Return empty attachment link for media
	 *
	 * @param  string $link Current link
	 * @return string
	 */
	public function cleanup_attachment_link( $link ) {
	    return '';
	}

	/**
	 * Redirect media pages on the front end
	 */
	public function maybe_redirect_media() {
		if ( is_attachment() ) {
			global $post;

			if ( $post && $post->post_parent ) {
				wp_redirect( get_permalink( $post->post_parent ), 301 );
			} else {
				wp_redirect( home_url(), 301 );
			}

			exit;
		}
	}
}
