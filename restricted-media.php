<?php
/**
 * Plugin Name: Restricted Media (Proof of Concept)
 * Description: Restrict access to designated media files.
 * Author: 10up
 * Version: 0.1
 * Author URI: https://10up.com
 *
 * Work in this plugin is derived from https://github.com/humanmade/S3-Uploads
 */

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';
require_once __DIR__ . '/classes/class-stream-wrapper.php';
require_once __DIR__ . '/classes/class-s3-image-editor-imagick.php';

if ( ! class_exists( '\Aws\S3\S3Client' ) ) {
	// Require AWS Autoloader file.
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'S3_UPLOADS_KEY' ) || ! defined( 'S3_UPLOADS_SECRET' ) || ! defined( 'S3_UPLOADS_REGION' ) || ! defined( 'S3_UPLOADS_BUCKET' ) ) {
	wp_die( 'Restricted Media requires S3_UPLOADS_KEY, S3_UPLOADS_SECRET, S3_UPLOADS_BUCKET, and S3_UPLOADS_REGION constants defined in wp-config.php.' );
}

/**
 * Restricted media base class
 */
class RestrictedMedia {

	/**
	 * We use this variable to detect if an upload being handled is going to s3
	 *
	 * @var array|boolean
	 */
	public $s3_upload = null;

	/**
	 * Setup file stream and hooks
	 */
	public function __construct() {
		$this->register_stream_wrapper();

		add_filter( 'wp_handle_sideload_prefilter', [ $this, 'maybe_init_upload_s3' ] );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'maybe_init_upload_s3' ] );
		add_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_s3' ], 10, 2 );
		add_filter( 'wp_read_image_metadata', [ $this, 'maybe_read_image_metadata' ], 10, 2 );
		add_filter( 'wp_image_editors', array( $this, 'maybe_filter_editors' ), 9 );

		add_filter( 'wp_handle_upload', [ $this, 'maybe_continue_upload_s3' ], 10, 2 );

		add_filter( 'wp_generate_attachment_metadata', [ $this, 'maybe_end_upload_s3' ], 10, 3 );

		add_action( 'init', [ $this, 'setup_rewrite_rules' ] );
		add_action( 'template_redirect', [ $this, 'maybe_show_private_media' ] );
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

	public function maybe_filter_editors( $editors ) {
		if ( ! empty ( $this->s3_upload ) ) {

			if ( ( $position = array_search( 'WP_Image_Editor_Imagick', $editors ) ) !== false ) {
				unset( $editors[ $position ] );
			}

			array_unshift( $editors, 'S3_Image_Editor_Imagick' );
		}

		return $editors;
	}

	/**
	 * Filters wp_read_image_metadata. exif_read_data() doesn't work on
	 * file streams so we need to make a temporary local copy to extract
	 * exif data from.
	 *
	 * @param array  $meta
	 * @param string $file
	 * @return array|bool
	 */
	public function maybe_read_image_metadata( $meta, $file ) {
		if ( ! empty ( $this->s3_upload ) ) {
			remove_filter( 'wp_read_image_metadata', array( $this, 'maybe_read_image_metadata' ), 10 );

			$temp_file = $this->copy_image_from_s3( $file );
			$meta      = wp_read_image_metadata( $temp_file );

			add_filter( 'wp_read_image_metadata', array( $this, 'maybe_read_image_metadata' ), 10, 2 );
			unlink( $temp_file );
		}

		return $meta;
	}

	/**
	 * On private media request, conditionally show file
	 */
	public function maybe_show_private_media() {
		global $wp_query;

		$private_media_id = get_query_var( 'private_media_id' );

		if ( empty( $private_media_id ) ) {
			return;
		}

		$is_private = get_post_meta( $private_media_id, 'blackstone_s3_file', true );

		if ( empty( $is_private ) ) {
			wp_die( 'Error has occurred.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not authorized.' );
		}

		remove_filter( 'wp_get_attachment_url', [ $this, 'maybe_get_s3' ], 10, 2 );
		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );

		$result = $this->s3()->getObject( [
			'Bucket' => S3_UPLOADS_BUCKET,
			'Key'    => str_replace( trailingslashit( $this->get_s3_url() ), '', wp_get_attachment_url( $private_media_id ) ),
		] );

		header( "Content-Type: {$result['ContentType']}" );
		echo $result['Body'];

		exit;
	}

	/**
	 * Setup private media rewrite rules
	 */
	public function setup_rewrite_rules() {
		add_rewrite_tag( '%private_media_id%', '.+' );
		add_rewrite_rule( '^private/?([^/]*)/?','index.php?private_media_id=$matches[1]','top');
	}

	/**
	 * Maybe filter in S3 private URL
	 *
	 * @param  string $url  Url
	 * @param  int $post_id Post id
	 * @return string
	 */
	public function maybe_get_s3( $url, $post_id ) {
		$is_private = get_post_meta( $post_id, 'blackstone_s3_file', true );

		if ( $is_private ) {
			$url = home_url() . '/private/' . $post_id;
		}

		return $url;
	}

	/**
	 * Maybe finish s3 upload
	 * @param  array $metadata    Media meta data
	 * @param  int $attachment_id Attachment id
	 * @param  string  $context   Current context
	 * @return array
	 */
	public function maybe_end_upload_s3( $metadata, $attachment_id, $context ) {
		if ( $this->s3_upload ) {
			update_post_meta( $attachment_id, 'blackstone_s3_file', true );

			remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );

			$this->s3_upload = null;
		}

		return $metadata;
	}

	/**
	 * Maybe continue s3 upload
	 *
	 * @param  array $file_array File array
	 * @param  string $action    WP hook
	 * @return array
	 */
	public function maybe_continue_upload_s3( $file_array, $action ) {
		if ( $this->s3_upload ) {
			$this->s3_upload = $file_array;
		}

		return $file_array;
	}

	/**
	 * Maybe start s3 upload
	 *
	 * @param  array $file_array File array
	 * @return array
	 */
	public function maybe_init_upload_s3( $file_array ) {

		// Determine if we should upload the file to S3
		if ( apply_filters( 'blackstone_store_private', false, $file_array ) ) {
			$this->s3_upload = true;
			add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ], 10, 1 );
		}

		return $file_array;
	}

	/**
	 * Get s3 URL
	 *
	 * @return string
	 */
	public function get_s3_url() {
		$bucket = strtok( S3_UPLOADS_BUCKET, '/' );
		$path   = substr( S3_UPLOADS_BUCKET, strlen( $bucket ) );

		return 'https://' . $bucket . '.s3.amazonaws.com';
	}

	/**
	 * Filter in s3 upload directories
	 *
	 * @param  array $dirs Current upload dirs
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {

		$dirs['path']    = str_replace( WP_CONTENT_DIR, 's3://' . S3_UPLOADS_BUCKET, $dirs['path'] );
		$dirs['basedir'] = str_replace( WP_CONTENT_DIR, 's3://' . S3_UPLOADS_BUCKET, $dirs['basedir'] );

		if ( ! defined( 'S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! S3_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) {

			if ( defined( 'S3_UPLOADS_USE_LOCAL' ) && S3_UPLOADS_USE_LOCAL ) {
				$dirs['url']     = str_replace( 's3://' . S3_UPLOADS_BUCKET, $dirs['baseurl'] . '/s3/' . S3_UPLOADS_BUCKET, $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . S3_UPLOADS_BUCKET, $dirs['baseurl'] . '/s3/' . S3_UPLOADS_BUCKET, $dirs['basedir'] );

			} else {
				$dirs['url']     = str_replace( 's3://' . S3_UPLOADS_BUCKET, $this->get_s3_url(), $dirs['path'] );
				$dirs['baseurl'] = str_replace( 's3://' . S3_UPLOADS_BUCKET, $this->get_s3_url(), $dirs['basedir'] );
			}
		}

		return $dirs;
	}

	/**
	 * Maybe move temp file to s3
	 *
	 * @param  array  $file File array
	 * @return array
	 */
	public function maybe_move_temp_file_to_s3( array $file ) {
		$upload_dir = wp_upload_dir();
		$new_path   = $upload_dir['basedir'] . '/tmp/' . basename( $file['tmp_name'] );

		copy( $file['tmp_name'], $new_path );
		unlink( $file['tmp_name'] );
		$file['tmp_name'] = $new_path;

		return $file;
	}

	/**
	 * Register the stream wrapper for s3
	 */
	public function register_stream_wrapper() {
		Stream_Wrapper::register( $this->s3() );

		$object_acl = defined( 'S3_UPLOADS_OBJECT_ACL' ) ? S3_UPLOADS_OBJECT_ACL : 'private';
		stream_context_set_option( stream_context_get_default(), 's3', 'ACL', $object_acl );

		stream_context_set_option( stream_context_get_default(), 's3', 'seekable', true );
	}

	/**
	 * Init s3 class
	 *
	 * @return \Aws\S3\S3Client
	 */
	public function s3() {
		static $s3;

		if ( ! empty( $s3 ) ) {
			return $s3;
		}

		$params = array( 'version' => 'latest' );

		$params['credentials']['key']    = S3_UPLOADS_KEY;
		$params['credentials']['secret'] = S3_UPLOADS_SECRET;

		$params['signature'] = 'v4';
		$params['region']    = S3_UPLOADS_REGION;

		if ( defined( 'WP_PROXY_HOST' ) && defined( 'WP_PROXY_PORT' ) ) {
			$proxy_auth    = '';
			$proxy_address = WP_PROXY_HOST . ':' . WP_PROXY_PORT;

			if ( defined( 'WP_PROXY_USERNAME' ) && defined( 'WP_PROXY_PASSWORD' ) ) {
				$proxy_auth = WP_PROXY_USERNAME . ':' . WP_PROXY_PASSWORD . '@';
			}

			$params['request.options']['proxy'] = $proxy_auth . $proxy_address;
		}

		$s3 = \Aws\S3\S3Client::factory( $params );

		return $s3;
	}
}

new RestrictedMedia();

