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
		esc_html__( 'AWS S3 Access Key ID', 'samsunglynk' ),
		__NAMESPACE__ . '\s3_access_key_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_secret_access_key',
		esc_html__( 'AWS S3 Secret Access Key', 'samsunglynk' ),
		__NAMESPACE__ . '\s3_secret_key_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_bucket',
		esc_html__( 'AWS S3 Bucket', 'samsunglynk' ),
		__NAMESPACE__ . '\s3_bucket_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_region',
		esc_html__( 'AWS S3 Region', 'samsunglynk' ),
		__NAMESPACE__ . '\s3_region_field',
		'media',
		'am_aws'
	);

	add_settings_field(
		's3_storage_method',
		esc_html__( 'AWS S3 Image Storage Method', 'samsunglynk' ),
		__NAMESPACE__ . '\s3_storage_method',
		'media',
		'am_aws'
	);
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

/**
 * Output storage type field
 *
 * @since 1.0
 */
function s3_storage_method() {
	$value = Utils\get_settings( 's3_storage_method' );
	?>
	<input name="am_settings[s3_storage_method]" <?php checked( 'all', $value ); ?> type="radio" id="am_storage_method_all" value="all"> <label for="am_storage_method_all">Store all media in S3</label><br>
	<input name="am_settings[s3_storage_method]" <?php checked( 'private', $value ); ?> type="radio" id="am_storage_method_private" value="private"> <label for="am_storage_method_private">Only store private media in S3</label>
	<?php
}
