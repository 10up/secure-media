<?php
/**
 * Create the Secure Media setting spage
 *
 * @package secure-media
 * @since   1.0
 */

namespace SecureMedia\Settings;

use SecureMedia\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Setup actions and filters for all things settings
 *
 * @since 1.0
 */
function setup() {
	if ( SM_IS_NETWORK ) {
		add_action( 'wpmu_options', __NAMESPACE__ . '\ms_settings' );
		add_action( 'admin_init', __NAMESPACE__ . '\ms_save_settings' );
	} else {
		add_action( 'admin_init', __NAMESPACE__ . '\register_settings' );
	}
}

/**
 * Set options in multisite
 *
 * @since 1.0
 */
function ms_save_settings() {
	global $pagenow;

	if ( ! is_network_admin() || 'settings.php' !== $pagenow || ! is_super_admin() ) {
		return;
	}

	if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'siteoptions' ) ) {
		return;
	}

	if ( ! isset( $_POST['sm_settings'] ) ) {
		return;
	}

	update_site_option( 'sm_settings', sanitize_settings( $_POST['sm_settings'] ) );
}

/**
 * Output multisite settings
 *
 * @since 1.0
 */
function ms_settings() {
	$setting = Utils\get_settings();
	?>

	<h2><?php esc_html_e( 'Secure Media', 'secure-media' ); ?></h2>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'AWS S3 Access Key ID', 'secure-media' ); ?></th>
				<td>
					<input name="sm_settings[s3_access_key_id]" type="text" id="sm_s3_access_key_id" value="<?php echo esc_attr( $setting['s3_access_key_id'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'AWS S3 Secret Access Key', 'secure-media' ); ?></th>
				<td>
					<input name="sm_settings[s3_secret_access_key]" type="password" id="sm_s3_secret_access_key" value="<?php echo esc_attr( $setting['s3_secret_access_key'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'AWS S3 Bucket', 'secure-media' ); ?></th>
				<td>
					<input name="sm_settings[s3_bucket]" type="text" id="sm_s3_bucket" value="<?php echo esc_attr( $setting['s3_bucket'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'AWS S3 Region', 'secure-media' ); ?></th>
				<td>
					<input name="sm_settings[s3_region]" type="text" id="sm_s3_region" value="<?php echo esc_attr( $setting['s3_region'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Public File Location', 'secure-media' ); ?></th>
				<td>
					<input name="sm_settings[s3_serve_from_wp]" type="checkbox" id="sm_s3_serve_from_wp" value="1" <?php checked( true, (bool) $setting['s3_serve_from_wp'] ); ?>> <label for="sm_s3_serve_from_wp"><?php esc_html_e( 'Store and serve public media from WordPress', 'secure-media' ); ?></label>
				</td>
			</tr>
		</tbody>
	</table>

	<?php
}

/**
 * Sanitize all settings
 *
 * @param array $settings New settings
 * @return array
 */
function sanitize_settings( $settings ) {
	foreach ( $settings as $key => $setting ) {
		$settings[ $key ] = sanitize_text_field( $setting );
	}

	if ( ! empty( $settings['s3_serve_from_wp'] ) ) {
		$settings['s3_serve_from_wp'] = true;
	} else {
		$settings['s3_serve_from_wp'] = false;
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
		'sm_aws',
		esc_html__( 'AWS Settings', 'secure-media' ),
		'',
		'media'
	);

	register_setting(
		'media',
		'sm_settings',
		[
			'sanitize_callback' => __NAMESPACE__ . '\sanitize_settings',
		]
	);

	add_settings_field(
		's3_access_key_id',
		esc_html__( 'AWS S3 Access Key ID', 'secure-media' ),
		__NAMESPACE__ . '\s3_access_key_field',
		'media',
		'sm_aws'
	);

	add_settings_field(
		's3_secret_access_key',
		esc_html__( 'AWS S3 Secret Access Key', 'secure-media' ),
		__NAMESPACE__ . '\s3_secret_key_field',
		'media',
		'sm_aws'
	);

	add_settings_field(
		's3_bucket',
		esc_html__( 'AWS S3 Bucket', 'secure-media' ),
		__NAMESPACE__ . '\s3_bucket_field',
		'media',
		'sm_aws'
	);

	add_settings_field(
		's3_region',
		esc_html__( 'AWS S3 Region', 'secure-media' ),
		__NAMESPACE__ . '\s3_region_field',
		'media',
		'sm_aws'
	);

	add_settings_field(
		's3_serve_from_wp',
		esc_html__( 'Public File Location', 'secure-media' ),
		__NAMESPACE__ . '\s3_serve_from_wp',
		'media',
		'sm_aws'
	);
}

/**
 * Output serve field
 *
 * @since 1.0
 */
function s3_serve_from_wp() {
	$value = Utils\get_settings( 's3_serve_from_wp' );
	?>

	<input name="sm_settings[s3_serve_from_wp]" type="checkbox" id="sm_s3_serve_from_wp" value="1" <?php checked( true, (bool) $value ); ?>> <label for="sm_s3_serve_from_wp"><?php esc_html_e( 'Store and serve public media from WordPress', 'secure-media' ); ?></label>

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

	<input name="sm_settings[s3_bucket]" type="text" id="sm_s3_bucket" value="<?php echo esc_attr( $value ); ?>" class="regular-text"> <?php echo esc_html_e( 'Make sure this bucket is created and configured in AWS.', 'secure-media' ); ?>

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

	<input name="sm_settings[s3_region]" type="text" id="sm_s3_region" value="<?php echo esc_attr( $value ); ?>" class="regular-text">

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

	<input name="sm_settings[s3_secret_access_key]" type="password" id="sm_s3_secret_access_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text">

	<?php
}

/**
 * Output access key field
 *
 * @since 1.0
 */
function s3_access_key_field() {
	$value = Utils\get_settings( 's3_access_key_id' );
	?>

	<input name="sm_settings[s3_access_key_id]" type="text" id="sm_s3_access_key_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">

	<?php
}
