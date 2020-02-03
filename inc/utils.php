<?php
/**
 * AM utility functions
 *
 * @since  1.0
 * @package advanced-media
 */

namespace AdvancedMedia\Utils;

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
	static $settings = null;
	if ( null === $settings ) {
		$defaults = [
			's3_secret_access_key' => defined( 'AM_S3_SECRET_ACCESS_KEY' ) ? AM_S3_SECRET_ACCESS_KEY : '',
			's3_access_key_id'     => defined( 'AM_S3_ACCESS_KEY_ID' ) ? AM_S3_ACCESS_KEY_ID : '',
			's3_bucket'            => defined( 'AM_S3_BUCKET' ) ? AM_S3_BUCKET : '',
			's3_region'            => defined( 'AM_S3_REGION' ) ? AM_S3_REGION : 'us-west-1',
			'show_single_view'     => 'no',
		];

		$settings = ( AM_IS_NETWORK ) ? get_site_option( 'am_settings', [] ) : get_option( 'am_settings', [] );
		if ( empty( $settings ) ) {
			$settings = $defaults;
		} else {
			foreach ( $settings as $key => $setting ) {
				if ( empty( $setting ) && ! empty( $defaults[ $key ] ) ) {
					$settings[ $key ] = $defaults[ $key ];
				}
			}
		}
	}

	if ( ! empty( $setting_key ) ) {
		return $settings[ $setting_key ];
	}

	return $settings;
}
