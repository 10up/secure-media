<?php
/**
 * Abstract base class
 *
 * @since  1.0
 * @package  advanced-media
 */
namespace AdvancedMedia\Modules;

/**
 * Base class for modules
 */
abstract class Module {

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

			if ( method_exists( $instance, 'setup' ) ) {
				$instance->setup();
			}
		}

		return $instance;
	}
}
