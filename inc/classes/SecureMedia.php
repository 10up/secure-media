<?php
/**
 * S3 storage module
 *
 * @since  1.0
 * @package  secure-media
 */

namespace SecureMedia;

use SecureMedia\Utils;

/**
 * Main class
 */
class SecureMedia {

	/**
	 * URL slug to serve private media under
	 *
	 * @var  string
	 */
	const MEDIA_URL_SLUG = 'private-media';

	/**
	 * Old upload directories
	 *
	 * @var array
	 */
	protected $old_upload_dirs;

	/**
	 * Setup file stream and hooks
	 */
	public function setup() {
		if ( ! $this->is_setup() ) {
			return;
		}

		$this->register_stream_wrapper();

		add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );
		add_filter( 'wp_read_image_metadata', [ $this, 'read_image_metadata' ], 10, 2 );
		add_filter( 'wp_image_editors', [ $this, 'filter_editors' ], 9 );
		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );

		add_filter( 'wp_generate_attachment_metadata', [ $this, 'end_upload_s3' ], 10, 2 );

		add_action( 'init', [ $this, 'setup_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'maybe_show_private_media' ] );

		add_filter( 'image_downsize', [ $this, 'maybe_downsize_private_media' ], 10, 3 );

		add_filter( 'wp_calculate_image_srcset', [ $this, 'maybe_use_private_media_srcset' ], 10, 5 );

		add_action( 'transition_post_status', [ $this, 'maybe_publish_media' ], 10, 3 );

		add_action( 'attachment_submitbox_misc_actions', [ $this, 'modify_submit_box' ] );

		add_action( 'edit_attachment', [ $this, 'update_visibility' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_filter( 'attachment_fields_to_edit', [ $this, 'attachment_fields' ], 10, 2 );

		add_action( 'wp_ajax_sm_set_visibility', [ $this, 'ajax_set_visibility' ] );

		add_action( 'template_redirect', [ $this, 'maybe_redirect_private_media' ] );
	}

	/**
	 * Redirect media pages on the front end if private and user doesn't have access
	 */
	public function maybe_redirect_private_media() {
		if ( is_attachment() && ! current_user_can( $this->get_view_private_media_capability() ) ) {
			global $post;

			$private = get_post_meta( $post->ID, 'sm_private_media', true );

			if ( ! empty( $private ) ) {
				wp_safe_redirect( home_url(), 301 );

				exit;
			}
		}
	}

	/**
	 * Get required cap for viewing private media
	 *
	 * @return string
	 */
	public function get_view_private_media_capability() {
		return apply_filters( 'sm_private_media_capability', 'edit_posts' );
	}

	/**
	 * Set visibility via ajax
	 *
	 * @return void
	 */
	public function ajax_set_visibility() {
		if ( ! isset( $_POST['public'] ) || empty( $_POST['postId'] ) ) {
			wp_send_json_error();
		}

		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'secure-media' ) ) {
			wp_send_json_error();
		}

		$this->set_media_visibility( $_POST['postId'], (bool) $_POST['public'] );

		wp_send_json_success();
	}

	/**
	 * Add credits to attachment fields
	 *
	 * @param array    $form_fields Form fields
	 * @param \WP_Post $post Post object
	 * @return array
	 */
	public function attachment_fields( $form_fields, $post ) {
		global $pagenow;

		if ( ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) && 'attachment' !== get_post_type() ) {
			return $form_fields;
		}

		$form_fields['sm_visibility'] = [
			'label' => esc_html__( 'Visible to Public', 'secure-media' ),
			'value' => (int) ( empty( get_post_meta( $post->ID, 'sm_private_media', true ) ) ),
			'helps' => esc_html__( 'Changing this will not update post content URLs.', 'secure-media' ),
		];

		return $form_fields;
	}

	/**
	 * Enqueue scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'sm_admin', SM_URL . 'dist/js/admin-script.js', [ 'jquery' ], SM_VERSION, true );

		wp_localize_script(
			'sm_admin',
			'smAdmin',
			[
				'nonce' => wp_create_nonce( 'secure-media' ),
			]
		);

		wp_enqueue_style( 'sm_admin', SM_URL . 'dist/css/admin-styles.css', [], SM_VERSION );
	}

	/**
	 * Update visibility via checkbox on edit form
	 *
	 * @param  int $post_id Post id
	 */
	public function update_visibility( $post_id ) {
		if ( ! isset( $_POST['attachment_visibility_nonce'] ) || ! wp_verify_nonce( $_POST['attachment_visibility_nonce'], 'attachment_edit_visibility' ) ) {
			return;
		}

		$current_visibility = ! ( (bool) get_post_meta( $post_id, 'sm_private_media', true ) );
		$new_visibility     = ! empty( $_POST['attachment_visibility'] );

		if ( $current_visibility !== $new_visibility ) {
			$this->set_media_visibility( $post_id, $new_visibility );
		}
	}

	/**
	 * Determine if we have proper s3 settings to use the module
	 *
	 * @return boolean
	 */
	protected function is_setup() {
		$settings = Utils\get_settings();

		return ( ! empty( $settings['s3_region'] ) && ! empty( $settings['s3_bucket'] ) && ! empty( $settings['s3_access_key_id'] ) && ! empty( $settings['s3_secret_access_key'] ) );
	}

	/**
	 * Set media visibility
	 *
	 * @param  int     $post_id Post ID
	 * @param  boolean $public  True for public
	 * @return array Returns affected object keys
	 */
	public function set_media_visibility( $post_id, $public ) {
		// Not created while plugin was active
		if ( empty( get_post_meta( $post_id, 'sm_s3_key', true ) ) ) {
			return;
		}

		global $wpdb;

		$sizes   = get_intermediate_image_sizes();
		$sizes[] = false;
		$keys    = [];

		if ( preg_match( '#^image/#', get_post_mime_type( $post_id ) ) ) {
			foreach ( $sizes as $size ) {
				$src = wp_get_attachment_image_src( $post_id, $size );

				$keys[] = $this->get_object_key( $src[0] );
			}
		} else {
			$keys[] = $this->get_object_key( $post_id );
		}

		$keys = array_unique( $keys );

		$acl = $public ? 'public-read' : 'private';

		foreach ( $keys as $key ) {
			try {
				if ( Utils\get_settings( 's3_serve_from_wp' ) ) {
					if ( $public ) {
						S3Client::factory()->save( WP_CONTENT_DIR . '/' . $key, $key );
					} else {
						unlink( WP_CONTENT_DIR . '/' . $key );
					}
				}

				S3Client::factory()->update_acl( $acl, $key );
			} catch ( \Exception $e ) {
				// Do nothing
			}
		}

		if ( Utils\get_settings( 's3_serve_from_wp' ) ) {
			if ( $public ) {
				$wpdb->update(
					$wpdb->posts,
					[
						'guid' => WP_CONTENT_URL . '/' . $this->get_object_key( $post_id ),
					],
					[
						'ID' => $post_id,
					]
				);
			} else {
				$wpdb->update(
					$wpdb->posts,
					[
						'guid' => S3Client::factory()->get_bucket_url() . '/' . $this->get_object_key( $post_id ),
					],
					[
						'ID' => $post_id,
					]
				);
			}
		}

		if ( $public ) {
			delete_post_meta( $post_id, 'sm_private_media' );
		} else {
			update_post_meta( $post_id, 'sm_private_media', true );
		}

		return $keys;
	}

	/**
	 * Show visibility status on edit attachment page
	 */
	public function modify_submit_box() {
		global $post;

		$private = get_post_meta( $post->ID, 'sm_private_media', true );

		?>
		<div class="misc-pub-section misc-pub-visibility">

			<?php wp_nonce_field( 'attachment_edit_visibility', 'attachment_visibility_nonce' ); ?>

			<?php esc_html_e( 'Visible to Public:', 'secure-media' ); ?>

			<label for="attachment_visibility">
				<strong>
					<input <?php checked( true, empty( $private ) ); ?> id="attachment_visibility" type="checkbox" name="attachment_visibility" value="public">
				</strong>
			</label>
			<div class="sm-post-box-visible"><em>Changing this will not update post content URLs.</em></div>

		</div>

		<?php
	}

	/**
	 * Get attachments for a particular post including featured image
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	public function get_post_attachments( $post_id ) {
		$children = new \WP_Query(
			[
				'post_type'              => 'attachment',
				'post_parent'            => $post_id,
				'post_status'            => 'any',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);

		$attachments = $children->posts;

		$thumb = get_post_meta( $post_id, '_thumbnail_id', true );

		if ( ! empty( $thumb ) ) {
			$attachments[] = $thumb;
		}

		$attachments = array_map( 'absint', $attachments );

		return array_unique( $attachments );
	}

	/**
	 * Get private media parse from post content
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	public function get_content_private_media( $post_id ) {
		$post = get_post( $post_id );

		$dom = new \DOMDocument();
		@$dom->loadHTML( $post->post_content );

		$imgs    = $dom->getElementsByTagName( 'img' );
		$iframes = $dom->getElementsByTagName( 'iframe' );
		$links   = $dom->getElementsByTagName( 'a' );

		$media = [];

		foreach ( $imgs as $img ) {
			$url = $img->attributes->getNamedItem( 'src' )->value;

			if ( $this->is_private_media_url( $url ) ) {
				$media[] = $url;
			}
		}

		/**
		 * Get srcsets
		 *
		 * @todo
		 */

		foreach ( $links as $link ) {
			$url = $link->attributes->getNamedItem( 'href' )->value;

			if ( $this->is_private_media_url( $url ) ) {
				$media[] = $url;
			}
		}

		foreach ( $iframes as $iframe ) {
			$url = $iframe->attributes->getNamedItem( 'src' )->value;

			if ( $this->is_private_media_url( $url ) ) {
				$media[] = $url;
			}
		}

		return $media;
	}

	/**
	 * Make all post media public for a given post
	 *
	 * @param int $post_id Post id
	 * @return void
	 */
	public function publicize_post_media_visibility( $post_id ) {
		$post = get_post( $post_id );

		$media_keys = [];

		$media_ids = $this->get_post_attachments( $post->ID );

		$content_media = $this->get_content_private_media( $post->ID );

		foreach ( $content_media as $media_url ) {
			$post_id = $this->get_post_id_from_media_url( $media_url );

			if ( ! empty( $post_id ) ) {
				$media_ids[] = $post_id;
			}
		}

		// Make all media public
		foreach ( $media_ids as $id ) {
			$media_keys = array_merge( $media_keys, $this->set_media_visibility( $id, true ) );
		}

		$media_keys = array_unique( $media_keys );

		$new_content = $post->post_content;

		/**
		 * Handle srcets
		 *
		 * @todo
		 */

		foreach ( $content_media as $url ) {

			$url_or_id = $url;

			if ( preg_match( '#/[0-9]+/?$#', $url ) ) {
				$url_or_id = preg_replace( '#^.*/([0-9]+)$#', '$1', trim( $url, '/' ) );
			}

			if ( ! Utils\get_settings( 's3_permanent' ) ) {
				$new_url = WP_CONTENT_URL . '/' . $this->get_object_key( $url_or_id );
			} else {
				$new_url = S3Client::factory()->get_bucket_url() . '/' . $this->get_object_key( $url_or_id );
			}

			$new_content = str_replace( $url, $new_url, $new_content );
		}

		if ( $post->post_content !== $new_content ) {
			remove_action( 'transition_post_status', [ $this, 'maybe_publish_media' ], 10, 3 );

			wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $new_content,
				]
			);

			add_action( 'transition_post_status', [ $this, 'maybe_publish_media' ], 10, 3 );
		}
	}

	/**
	 * Maybe make media public on post update
	 *
	 * @param string  $new_status New status
	 * @param string  $old_status Old status
	 * @param WP_Post $post      Post object
	 */
	public function maybe_publish_media( $new_status, $old_status, $post ) {

		// If we are not publishing post do nothing
		if ( 'publish' !== $new_status ) {
			return;
		}

		if ( 'attachment' === $post->post_type ) {
			return;
		}

		$this->publicize_post_media_visibility( $post->ID );
	}

	/**
	 * Get media ID from URL. Will strip size if appended to URL
	 *
	 * @param string $url Attachment URL
	 * @return int
	 */
	public function get_post_id_from_media_url( $url ) {
		global $wpdb;

		// Handle http..../private-media/ID
		if ( preg_match( '#' . self::MEDIA_URL_SLUG . '/([0-9]+)$#i', $url ) ) {
			return (int) preg_replace( '#.*' . self::MEDIA_URL_SLUG . '/([0-9]+)$#i', '$1', $url );
		}

		// Turn last dash into . e.g. uploads/1/12/file-png
		if ( preg_match( '#\-([a-z0-9]+)$#i', $url, $matches ) ) {
			$url = preg_replace( '#\-' . $matches[1] . '$#', '.' . $matches[1], $url );
		}

		$path = preg_replace( '#^.*?uploads/#', 'uploads/', $url );

		$new_path = preg_replace( '#-[0-9]+x[0-9]+\.(.*?)$#', '.$1', $path );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'sm_s3_key' AND meta_value = %s", $new_path ), ARRAY_A );

		if ( ! empty( $row ) ) {
			return (int) $row['post_id'];
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'sm_s3_key' AND meta_value = %s", $path ), ARRAY_A );

		if ( ! empty( $row ) ) {
			return (int) $row['post_id'];
		}

		return null;
	}

	/**
	 * Check if URL is something like test.com/private-media/...
	 *
	 * @param string $url Private media URL
	 * @return boolean
	 */
	public function is_private_media_url( $url ) {
		return preg_match( '#^(' . home_url() . '/' . self::MEDIA_URL_SLUG . '|/?' . self::MEDIA_URL_SLUG . ')#', $url );
	}

	/**
	 * Check if URL is the s3 bucket url
	 *
	 * @param string $url Private media URL
	 * @return boolean
	 */
	public function is_s3_url( $url ) {
		return preg_match( '#^' . S3Client::factory()->get_bucket_url() . '#', $url );
	}

	/**
	 * Given any type of media path, URL, or ID, get the object key
	 *
	 * @param mixed $src_or_id Either a URL, path, or ID
	 * @return string
	 */
	public function get_object_key( $src_or_id ) {
		if ( is_numeric( $src_or_id ) ) {
			return get_post_meta( $src_or_id, 'sm_s3_key', true );
		}

		$url = trim( $src_or_id, '/' );

		// Account for url/private-media/ID
		if ( preg_match( '#' . self::MEDIA_URL_SLUG . '/[0-9]+$#', $url ) ) {
			return get_post_meta( preg_replace( '#^.*' . self::MEDIA_URL_SLUG . '/([0-9]+)$#', '$1', $url ), 'sm_s3_key', true );
		}

		// First strip any url
		$key = preg_replace( '#^.*?uploads/#', 'uploads/', $url );

		// If this matches key doesn't end in a file extension so we need to convert it.
		if ( ! preg_match( '#/.*\.[^\-]+$#', $url ) ) {
			$key = preg_replace( '#^(.*)\-(.*)$#', '$1.$2', $key );
		}

		return $key;
	}

	/**
	 * If media is private, replace source set with private URLS
	 *
	 * @param  array  $sources       Current sources
	 * @param  array  $size_array    Array of sizes
	 * @param  string $image_src     Image src path
	 * @param  array  $image_meta    Image meta
	 * @param  int    $attachment_id Attachment ID
	 * @return array
	 */
	public function maybe_use_private_media_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$is_private = get_post_meta( $attachment_id, 'sm_private_media', true );

		if ( ! $is_private ) {
			return $sources;
		}

		foreach ( $sources as $key => $source ) {
			$sources[ $key ]['url'] = str_replace( S3Client::factory()->get_bucket_url(), home_url() . '/' . self::MEDIA_URL_SLUG, $source['url'] );
		}

		return $sources;
	}

	/**
	 * During image_downsize, we conditionally substitute the URL if it's private media
	 *
	 * @param  string       $url  URL
	 * @param  int          $id   Media id
	 * @param  string|array $size Image size
	 * @return string
	 */
	public function maybe_downsize_private_media( $url, $id, $size ) {
		$is_private = get_post_meta( $id, 'sm_private_media', true );

		if ( ! $is_private ) {
			return $url;
		}

		remove_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );

		$is_image = wp_attachment_is_image( $id );

		$img_url          = wp_get_attachment_url( $id );
		$meta             = wp_get_attachment_metadata( $id );
		$width            = 0;
		$height           = 0;
		$is_intermediate  = false;
		$img_url_basename = wp_basename( $img_url );

		// If the file isn't an image, attempt to replace its URL with a rendered image from its meta.
		// Otherwise, a non-image type could be returned.
		if ( ! $is_image ) {
			if ( ! empty( $meta['sizes'] ) ) {
				$img_url          = str_replace( $img_url_basename, $meta['sizes']['full']['file'], $img_url );
				$img_url_basename = $meta['sizes']['full']['file'];
				$width            = $meta['sizes']['full']['width'];
				$height           = $meta['sizes']['full']['height'];
			} else {
				add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );

				return false;
			}
		}

		$intermediate = image_get_intermediate_size( $id, $size );

		// try for a new style intermediate size
		if ( $intermediate ) {
			$img_url         = str_replace( $img_url_basename, $intermediate['file'], $img_url );
			$width           = $intermediate['width'];
			$height          = $intermediate['height'];
			$is_intermediate = true;
		} elseif ( 'thumbnail' === $size ) {
			$thumb_file = wp_get_attachment_thumb_file( $id );
			$info       = getimagesize( $thumb_file );

			// fall back to the old thumbnail
			if ( $thumb_file && $info ) {
				$img_url         = str_replace( $img_url_basename, wp_basename( $thumb_file ), $img_url );
				$width           = $info[0];
				$height          = $info[1];
				$is_intermediate = true;
			}
		}

		if ( ! $width && ! $height && isset( $meta['width'], $meta['height'] ) ) {
			// any other type: use the real image
			$width  = $meta['width'];
			$height = $meta['height'];
		}

		if ( $img_url ) {
			// we have the actual image size, but might need to further constrain it if content_width is narrower
			list( $width, $height ) = image_constrain_size_for_editor( $width, $height, $size );

			add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );

			$img_url = str_replace( S3Client::factory()->get_bucket_url(), home_url() . '/' . self::MEDIA_URL_SLUG, $img_url );
			$img_url = preg_replace( '#^(.*)\.(.*)$#', '$1-$2', $img_url );

			return array( $img_url, $width, $height, $is_intermediate );
		}

		return false;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param  string $file File path
	 * @return string
	 */
	public function copy_image_from_s3( $file ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_filename = wp_tempnam( $file );

		copy( $file, $temp_filename );
		return $temp_filename;
	}

	/**
	 * Add S3 Imagick editor class
	 *
	 * @param  array $editors Current editors
	 * @return array
	 */
	public function filter_editors( $editors ) {
		$position = array_search( 'WP_Image_Editor_Imagick', $editors, true );

		if ( false !== $position ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, '\SecureMedia\S3ImageEditorImagick' );

		return $editors;
	}

	/**
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array  $meta Image meta
	 * @param string $file File path
	 * @return array|bool
	 */
	public function read_image_metadata( $meta, $file ) {
		remove_filter( 'wp_read_image_metadata', array( $this, 'read_image_metadata' ), 10 );

		$temp_file = $this->copy_image_from_s3( $file );
		$meta      = wp_read_image_metadata( $temp_file );

		add_filter( 'wp_read_image_metadata', array( $this, 'read_image_metadata' ), 10, 2 );
		unlink( $temp_file );

		return $meta;
	}

	/**
	 * On private media request, conditionally show file
	 */
	public function maybe_show_private_media() {
		$private_media = rtrim( get_query_var( 'private_media' ) );

		if ( empty( $private_media ) ) {
			return;
		}

		if ( ! current_user_can( $this->get_view_private_media_capability() ) ) {
			wp_die( 'Not authorized.', '', [ 'response' => 401 ] );
		}

		$private_media = rtrim( $private_media, '/' );

		try {
			if ( is_numeric( $private_media ) ) {
				$result = S3Client::factory()->get( $this->get_object_key( $private_media ) );
			} else {
				$key = $this->get_object_key( $private_media );

				$result = S3Client::factory()->get( $key );
			}
		} catch ( \Exception $e ) {
			wp_die( 'Not found.', '', [ 'response' => 404 ] );
		}

		header( "Content-Type: {$result['ContentType']}" );

		// phpcs:disable
		echo $result['Body'];
		// phpcs:enable

		exit;
	}

	/**
	 * Setup private media rewrite rules.
	 */
	public function setup_rewrite_rules() {
		add_rewrite_tag( '%private_media%', '.+' );
		add_rewrite_rule( '^' . self::MEDIA_URL_SLUG . '/(.*)', 'index.php?private_media=$matches[1]', 'top' );
	}

	/**
	 * Filter in correct attachment URL
	 *
	 * @param  string $url  Url
	 * @param  int    $post_id Post id
	 * @return string
	 */
	public function filter_attachment_url( $url, $post_id ) {
		// This happens for images uploaded when Secure Media was not active
		if ( empty( get_post_meta( $post_id, 'sm_s3_key', true ) ) ) {
			$upload_dir = wp_get_upload_dir();

			return str_replace( $upload_dir['url'], $this->old_upload_dirs['url'], $url );
		}

		$is_private = get_post_meta( $post_id, 'sm_private_media', true );

		if ( $is_private ) {
			$url = home_url() . '/' . self::MEDIA_URL_SLUG . '/' . $post_id;
		} else {
			if ( Utils\get_settings( 's3_serve_from_wp' ) ) {
				$url = WP_CONTENT_URL . '/' . get_post_meta( $post_id, 'sm_s3_key', true );
			}
		}

		return $url;
	}

	/**
	 * Maybe finish s3 upload
	 *
	 * @param  array $metadata    Media meta data
	 * @param  int   $attachment_id Attachment id
	 * @return array
	 */
	public function end_upload_s3( $metadata, $attachment_id ) {
		$post = get_post( $attachment_id );

		update_post_meta( $attachment_id, 'sm_s3_key', sanitize_text_field( 'uploads/' . get_post_meta( $attachment_id, '_wp_attached_file', true ) ) );

		if ( ! empty( $post->post_parent ) ) {
			$this->set_media_visibility( $attachment_id, ( 'publish' === get_post_status( $post->post_parent ) ) );
		} else {
			$this->set_media_visibility( $attachment_id, ! apply_filters( 'sm_secure_all_new_media', true, $attachment_id ) );
		}

		return $metadata;
	}

	/**
	 * Filter in s3 upload directories
	 *
	 * @param  array $dirs Current upload dirs
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {

		$this->old_upload_dirs = $dirs;

		$dirs['path']    = str_replace( WP_CONTENT_DIR, 's3://' . Utils\get_settings( 's3_bucket' ), $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 's3://' . Utils\get_settings( 's3_bucket' ), $dirs['basedir'] );
		$dirs['url']     = str_replace( 's3://' . Utils\get_settings( 's3_bucket' ), S3Client::factory()->get_bucket_url(), $dirs['path'] );
		$dirs['baseurl'] = str_replace( 's3://' . Utils\get_settings( 's3_bucket' ), S3Client::factory()->get_bucket_url(), $dirs['basedir'] );

		return $dirs;
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		StreamWrapper::register( S3Client::factory()->client() );

		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', 'public-read' );

		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
	}

	/**
	 * Create/return instance of the class
	 *
	 * @return mixed
	 */
	public static function factory() {
		static $instance;

		if ( empty( $instance ) ) {
			$class    = get_called_class();
			$instance = new $class();

			$instance->setup();
		}

		return $instance;
	}
}
