<?php
/**
 * Plugin Name: Advanced Media
 * Description: Advanced media functionality.
 * Author: Taylor Lovett, 10up
 * Version: 0.5
 * Author URI: https://10up.com
 *
 * Work in this plugin is derived from https://github.com/humanmade/S3-Uploads
 */

namespace AdvancedMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'AM_URL', plugin_dir_url( __FILE__ ) );
define( 'AM_PATH', plugin_dir_path( __FILE__ ) );
define( 'AM_VERSION', '0.5' );

/**
 * PSR-4-ish autoloading
 *
 * @since 1.0
 */
spl_autoload_register(
	function( $class ) {
			// project-specific namespace prefix.
			$prefix = 'AdvancedMedia\\';

			// base directory for the namespace prefix.
			$base_dir = __DIR__ . '/inc/classes/';

			// does the class use the namespace prefix?
			$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}
			$relative_class = substr( $class, $len );
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
			// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

require_once __DIR__ . '/inc/utils.php';
require_once __DIR__ . '/vendor/autoload.php';

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = Utils\is_network_activated( plugin_basename( __FILE__ ) );

define( 'AM_IS_NETWORK', (bool) $network_activated );

require_once __DIR__ . '/inc/settings.php';

Settings\setup();

Storage::factory();


