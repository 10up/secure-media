<?php
/**
 * WP CLI command
 *
 * @since  1.0
 * @package  secure-media
 */

namespace SecureMedia;

use \WP_CLI_Command as WP_CLI_Command;
use \WP_CLI as WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WP CLI command class
 */
class Command extends WP_CLI_Command {

	/**
	 * Copy all media files to s3
	 *
	 * @subcommand copy-to-s3
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function copy_to_s3( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::line( esc_html__( 'Starting transfer...', 'secure-media' ) );

		$per_page   = 500;
		$index      = 0;
		$transferred = 0;

		$upload_dir     = wp_get_upload_dir();
		$old_upload_dir = SecureMedia::factory()->get_old_upload_dir();

		while ( true ) {
			$posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_type = 'attachment' LIMIT %d OFFSET %d", $per_page, $index ), ARRAY_A );

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $post ) {
				$s3_key = get_post_meta( $post['ID'], 'sm_s3_key', true );

				if ( ! empty( $s3_key ) ) {
					continue;
				}

				$full_file = get_post_meta( $post['ID'], '_wp_attached_file', true );

				if ( empty( $full_file ) ) {
					continue;
				}

				$files = [
					$full_file,
				];

				if ( ! file_exists( $old_upload_dir['basedir'] . '/' . $files[0] ) ) {
					continue;
				}

				$meta = wp_get_attachment_metadata( $post['ID'] );

				foreach ( $meta['sizes'] as $size ) {
					$files[] = dirname( $meta['file'] . '/' . $size['file'] );
				}

				$files = array_unique( $files );

				if ( empty( $files ) ) {
					continue;
				}

				foreach ( $files as $file ) {
					WP_CLI::line( esc_html__( 'Transferring ', 'secure-media' ) . $file );
					if ( copy( $old_upload_dir['basedir'] . '/' . $file, $upload_dir['basedir'] . '/' . $file ) ) {
						$transferred++;
					}
				}

				update_post_meta( $post['ID'], 'sm_s3_key', sanitize_text_field( 'uploads/' . $full_file ) );
			}

			$index += $per_page;
		}

		WP_CLI::success( sprintf( esc_html__( 'Done! %d files transfered', 'secure-media' ), $transferred ) );
	}
}
