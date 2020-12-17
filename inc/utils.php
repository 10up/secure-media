<?php
/**
 * AM utility functions
 *
 * @since  1.0
 * @package secure-media
 */

namespace SecureMedia\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * Whether plugin is network activated
 *
 * Determines whether plugin is network activated or just on the local site.
 *
 * @since 1.0
 * @param string $plugin the plugin base name.
 * @return bool True if network activated or false.
 */
function is_network_activated( $plugin ) {
	$plugins = get_site_option( 'active_sitewide_plugins' );

	if ( is_multisite() && isset( $plugins[ $plugin ] ) ) {
		return true;
	}

	return false;
}

/**
 * Get plugin settings
 *
 * @param  string $setting_key Setting key
 * @return array
 */
function get_settings( $setting_key = null ) {
	$default_bucket = ( SM_IS_NETWORK ) ? network_site_url() : site_url();
	$default_bucket = preg_replace( '#https?://(www\.)?#i', '', $default_bucket );
	$default_bucket = preg_replace( '#[^\w]#', '', $default_bucket );

	$defaults = [
		's3_secret_access_key' => '',
		's3_access_key_id'     => '',
		's3_bucket'            => $default_bucket,
		's3_region'            => 'us-west-1',
		's3_serve_from_wp'     => true,
	];

	$settings = ( SM_IS_NETWORK ) ? get_site_option( 'sm_settings', [] ) : get_option( 'sm_settings', [] );
	$settings = wp_parse_args( $settings, $defaults );

	if ( ! empty( $setting_key ) ) {
		return $settings[ $setting_key ];
	}

	return $settings;
}
