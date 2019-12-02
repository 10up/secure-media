<?php
/**
 * Create the AM setting spage
 *
 * @package advanced-media
 * @since   1.0
 */
namespace AdvancedMedia\Settings;

use AdvancedMedia\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Setup actions and filters for all things settings
 *
 * @since  1.0
 */
function setup() {
	if ( AM_IS_NETWORK ) {
		add_action( 'network_admin_menu', __NAMESPACE__ . '\action_admin_menu' );
	} else {
		add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	}
}

/**
 * Sanitize all settings
 *
 * @param  array $settings New settings
 * @return array
 */
function sanitize_settings( $settings ) {
	foreach ( $settings as $key => $setting ) {
		$settings[ $key ] = sanitize_text_field( $setting );
	}

	return $settings;
}

/**
 * Register settings
 *
 * @since 1.0
 */
function register_settings() {
	add_settings_section(
		'am_aws',
		esc_html__( 'AWS Settings', 'advanced-media' ),
		'',
		'media'
	);

	register_setting(
		'media',
		'am_settings',
		[
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
		]
	);

	add_settings_field(
		's3_access_key_id',
		esc_html__( 'AWS S3 Access Key ID', 'advanced-media' ),
		__NAMESPACE__ . '\s3_access_key_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_secret_access_key',
		esc_html__( 'AWS S3 Secret Access Key', 'advanced-media' ),
		__NAMESPACE__ . '\s3_secret_key_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_bucket',
		esc_html__( 'AWS S3 Bucket', 'advanced-media' ),
		__NAMESPACE__ . '\s3_bucket_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_region',
		esc_html__( 'AWS S3 Region', 'advanced-media' ),
		__NAMESPACE__ . '\s3_region_field',
		'media',
		'am_aws'
	);

	add_settings_section(
		'am_front_end',
		esc_html__( 'Front End', 'advanced-media' ),
		'',
		'media'
	);

	add_settings_field(
		'show_single_view',
		esc_html__( 'Show single post view for media items', 'advanced-media' ),
		__NAMESPACE__ . '\show_single_view',
		'media',
		'am_front_end'
	);
}

/**
 * Output single view field
 *
 * @since 1.0
 */
function show_single_view() {
	$value = Utils\get_settings( 'show_single_view' );
	?>
	<input name="am_settings[show_single_view]" <?php checked( 'yes', $value ); ?> type="radio" id="am_show_single_view_yes" value="yes"> <label for="am_show_single_view_yes"><?php esc_html_e( 'Yes', 'advanced-media' ); ?></label><br>
	<input name="am_settings[show_single_view]" <?php checked( 'no', $value ); ?> type="radio" id="am_show_single_view_no" value="no"> <label for="am_show_single_view_no"><?php esc_html_e( 'No', 'advanced-media' ); ?></label>
	<?php
}

/**
 * Output bucket field
 *
 * @since 1.0
 */
function s3_bucket_field() {
	$value = Utils\get_settings( 's3_bucket' );
	?>
	<input name="am_settings[s3_bucket]" type="text" id="am_s3_bucket" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
	<?php
}

/**
 * Output region field
 *
 * @since 1.0
 */
function s3_region_field() {
	$value = Utils\get_settings( 's3_region' );
	?>
	<input name="am_settings[s3_region]" type="text" id="am_s3_region" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
	<?php
}

/**
 * Output bucket field
 *
 * @since 1.0
 */
function s3_secret_key_field() {
	$value = Utils\get_settings( 's3_secret_access_key' );
	?>
	<input name="am_settings[s3_secret_access_key]" type="password" id="am_s3_secret_access_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
	<?php
}

/**
 * Output access key field
 *
 * @since 1.0
 */
function s3_access_key_field( $args ) {
	$value = Utils\get_settings( 's3_access_key_id' );
	?>
	<input name="am_settings[s3_access_key_id]" type="text" id="am_s3_access_key_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
	<?php
}
