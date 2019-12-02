<?php
/**
 * S3 storage module
 *
 * @since  1.0
 * @package  advanced-media
 */

namespace AdvancedMedia\Modules\S3;

use AdvancedMedia\Utils;

/**
 * Module class
 */
class Module extends \AdvancedMedia\Modules\Module {

	/**
	 * URL slug to serve private media under
	 */
	const MEDIA_URL_SLUG = 'private-media';

	/**
	 * Setup file stream and hooks
	 */
	public function setup() {
		$this->register_stream_wrapper();

		add_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_private_media_url' ], 10, 2 );
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
	}

	/**
	 * Show visibility status on edit attachment page
	 */
	public function modify_submit_box() {
		global $post;

		$private = get_post_meta( $post->ID, 'am_private_media', true );

		echo '<div class="misc-pub-section misc-pub-visibility">';

		esc_html_e( 'Visibility:', 'advanced-media' );

		echo ' <strong>';

		if ( ! empty( $private ) ) {
			esc_html_e( 'Private', 'advanced-media' );
		} else {
			esc_html_e( 'Public', 'advanced-media' );
		}

		echo '</strong></div>';
	}

	/**
	 * Maybe make media public on post update
	 *
	 * @param  string $new_status New status
	 * @param  string $old_status Old status
	 * @param  WP_Post $post      Post object
	 */
	public function maybe_publish_media( $new_status, $old_status, $post ) {
		// If post is currently published do nothing
		if ( 'publish' === $old_status ) {
			return;
		}

		// If we are not publishing post do nothing
		if ( 'publish' !== $new_status ) {
			return;
		}

		$children = new \WP_Query(
			[
				'post_type'              => 'attachment',
				'post_parent'            => $post->ID,
				'post_status'            => 'any',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);

		$sizes = get_intermediate_image_sizes();

		$src_keys = [];
		$src_ids  = [];

		foreach ( $children->posts as $child_id ) {
			$src_ids[] = (int) $child_id;

			foreach ( $sizes as $size ) {
				$src = wp_get_attachment_image_src( $child_id, $size );

				$src_keys[] = $src[0];
			}
		}

		$thumb = get_post_meta( $post->ID, '_thumbnail_id', true );

		if ( ! empty( $thumb ) ) {
			$src_ids[] = (int) $thumb;

			foreach ( $sizes as $size ) {
				$src = wp_get_attachment_image_src( $thumb, $size );

				$src_keys[] = $src[0];
			}
		}

		$dom = new \DOMDocument();
		@$dom->loadHTML( $post->post_content );
		$imgs = $dom->getElementsByTagName( 'img' );

		$new_content = $post->post_content;

		foreach ( $imgs as $img ) {
			$src = $img->attributes->getNamedItem( 'src' )->value;

			if ( ! preg_match( '#^(' . home_url() . '/' . self::MEDIA_URL_SLUG . '|/?' . self::MEDIA_URL_SLUG . ')#', $src ) ) {
				$id = attachment_url_to_postid( $src );

				if ( ! empty( $id ) ) {
					foreach ( $sizes as $size ) {
						$src = wp_get_attachment_image_src( $id, $size );

						$src_keys[] = $src[0];
					}
				}
			} else {
				/**
				 * This could happen if we added a private media file that was a child of a
				 * different draft post. We still make it publish
				 */
				if ( is_numeric( basename( $src ) ) ) {
					// In this case the private media file is using an ID in the url
					$id = basename( $src );

					foreach ( $sizes as $size ) {
						$src = wp_get_attachment_image_src( $id, $size );

						$src_keys[] = $src[0];
					}
				} else {
					$src = str_replace( home_url() . '/' . self::MEDIA_URL_SLUG, S3Client::factory()->get_bucket_url(), $src );

					$id = attachment_url_to_postid( $src );

					if ( ! empty( $id ) ) {
						foreach ( $sizes as $size ) {
							$src = wp_get_attachment_image_src( $id, $size );

							$src_keys[] = $src[0];
						}
					}
				}

				$src_ids[] = (int) $id;
			}
		}

		foreach ( $src_keys as $key => $src_key ) {
			$src_keys[ $key ] = ltrim( ltrim( wp_parse_url( $this->convert_url_to_file_path( $src_key ), PHP_URL_PATH ), '/' ), self::MEDIA_URL_SLUG . '/' );
		}

		$src_keys = array_unique( $src_keys );
		$src_ids  = array_unique( $src_ids );

		foreach ( $src_keys as $src ) {
			try {
				S3Client::factory()->update_acl_async( 'public-read', $src );
			} catch ( \Exception $e ) {
				// Do nothing.
			}
		}

		foreach ( $src_ids as $src_id ) {
			delete_post_meta( $src_id, 'am_private_media' );
		}

		$new_content = $post->post_content;

		foreach ( $imgs as $img ) {
			$src = $img->attributes->getNamedItem( 'src' )->value;

			if ( preg_match( '#' . self::MEDIA_URL_SLUG . '/[0-9]+/?$#', $src ) ) {
				$id = preg_replace( '#^.*/([0-9]+)$#', '$1', trim( $src, '/') );

				$new_src = S3Client::factory()->get_bucket_url() . '/' . $this->get_object_key( $id );

				$new_content = str_replace( $src, $new_src, $new_content );
			}
		}

		$new_content = preg_replace( '#' . home_url() . '/' . self::MEDIA_URL_SLUG . '#i', S3Client::factory()->get_bucket_url(), $new_content );

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
	 * If media is private, replace source set with private URLS
	 *
	 * @param  array $sources       Current sources
	 * @param  array $size_array    Array of sizes
	 * @param  string $image_src     Image src path
	 * @param  array $image_meta    Image meta
	 * @param  int $attachment_id Attachment ID
	 * @return array
	 */
	public function maybe_use_private_media_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		$is_private = get_post_meta( $attachment_id, 'am_private_media', true );

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
	 * @param  string $url  URL
	 * @param  int $id   Media id
	 * @param  string|array $size Image size
	 * @return string
	 */
	public function maybe_downsize_private_media( $url, $id, $size ) {
		$is_private = get_post_meta( $id, 'am_private_media', true );

		if ( ! $is_private ) {
			return $url;
		}

		remove_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_private_media_url' ], 10, 2 );

		$is_image = wp_attachment_is_image( $id );

		$img_url          = wp_get_attachment_url( $id );
		$meta             = wp_get_attachment_metadata( $id );
		$width            = $height = 0;
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
				add_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_private_media_url' ], 10, 2 );

				return false;
			}
		}

		// try for a new style intermediate size
		if ( $intermediate = image_get_intermediate_size( $id, $size ) ) {
			$img_url         = str_replace( $img_url_basename, $intermediate['file'], $img_url );
			$width           = $intermediate['width'];
			$height          = $intermediate['height'];
			$is_intermediate = true;
		} elseif ( $size == 'thumbnail' ) {
			// fall back to the old thumbnail
			if ( ( $thumb_file = wp_get_attachment_thumb_file( $id ) ) && $info = getimagesize( $thumb_file ) ) {
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

			add_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_private_media_url' ], 10, 2 );

			$img_url = str_replace( S3Client::factory()->get_bucket_url(), home_url() . '/' . self::MEDIA_URL_SLUG, $img_url );
			$img_url = $this->convert_file_path_to_url( $img_url );

			return array( $img_url, $width, $height, $is_intermediate );
		}

		return false;
	}

	/**
	 * Get a local copy of the file.
	 *
	 * @param  string $file
	 * @return string
	 */
	public function copy_image_from_s3( $file ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
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
		if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
			unset( $editors[ $position ] );
		}

		array_unshift( $editors, '\AdvancedMedia\Modules\S3\S3ImageEditorImagick' );

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
		global $wp_query;

		$private_media = rtrim( get_query_var( 'private_media' ) );

		if ( empty( $private_media ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not authorized.', '', [ 'response' => 401 ] );
		}

		$private_media = rtrim( $private_media, '/' );

		try {
			if ( is_numeric( $private_media ) ) {
				$result = S3Client::factory()->get( $this->get_object_key( $private_media ) );
			} else {
				$key = $this->convert_url_to_file_path( $private_media );

				$result = S3Client::factory()->get( $key );
			}
		} catch ( \Exception $e ) {
			wp_die( 'Not found.', '', [ 'response' => 404 ] );
		}

		header( "Content-Type: {$result['ContentType']}" );
		echo $result['Body'];

		exit;
	}

	/**
	 * Convert file path to URL. When showing private media, we replace . with - so nginx doesn't try to
	 * serve a non-existent static file.
	 *
	 * @param  string $url Media url
	 * @return string
	 */
	protected function convert_url_to_file_path( $url ) {
		return preg_replace( '#^(.*)\-(.*)$#', '$1.$2', $url );
	}

	/**
	 * Convert URL to file path. When showing private media, we replace . with - so nginx doesn't try to
	 * serve a non-existent static file.
	 *
	 * @param  string $path File path
	 * @return string
	 */
	protected function convert_file_path_to_url( $path ) {
		return preg_replace( '#^(.*)\.(.*)$#', '$1-$2', $path );
	}

	/**
	 * Setup private media rewrite rules.
	 */
	public function setup_rewrite_rules() {
		add_rewrite_tag( '%private_media%', '.+' );
		add_rewrite_rule( '^' . self::MEDIA_URL_SLUG . '/(.*)','index.php?private_media=$matches[1]','top');
	}

	/**
	 * Maybe filter in S3 private URL
	 *
	 * @param  string $url  Url
	 * @param  int $post_id Post id
	 * @return string
	 */
	public function maybe_get_private_media_url( $url, $post_id ) {
		$is_private = get_post_meta( $post_id, 'am_private_media', true );

		if ( $is_private ) {
			$url = home_url() . '/' . self::MEDIA_URL_SLUG . '/' . $post_id;
		}

		return $url;
	}

	/**
	 * Maybe finish s3 upload
	 *
	 * @param  array $metadata    Media meta data
	 * @param  int $attachment_id Attachment id
	 * @param  string  $context   Current context
	 * @return array
	 */
	public function end_upload_s3( $metadata, $attachment_id ) {
		$post = get_post( $attachment_id );

		if ( ! empty( $post->post_parent ) ) {
			if ( 'publish' !== get_post_status( $post->post_parent ) ) {
				update_post_meta( $attachment_id, 'am_private_media', true );

				S3Client::factory()->update_acl_async( 'private', $this->get_object_key( $attachment_id ) );
			}
		}

		return $metadata;
	}

	/**
	 * Get object key for media item
	 *
	 * @param  int $id Attachment id
	 * @return string
	 */
	public function get_object_key( $id ) {
		$post = get_post( $id );

		if ( empty( $post ) ) {
			return null;
		}

		return ltrim( wp_parse_url( $post->guid, PHP_URL_PATH ), '/' );
	}

	/**
	 * Filter in s3 upload directories
	 *
	 * @param  array $dirs Current upload dirs
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {


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
}
