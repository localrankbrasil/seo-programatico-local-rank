<?php
/**
 * PSR-4 autoloader for the plugin classes.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads classes from the includes directory.
 */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads a class file when it belongs to this plugin.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = LRPS_PATH . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
}
