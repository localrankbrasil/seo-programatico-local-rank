<?php
/**
 * Optional code override for one client.
 *
 * Copy this file to wp-content/mu-plugins/lrps-client.php and put the
 * exported project JSON beside it. The file replaces the runtime project.
 * Keys omitted from the JSON keep the value already saved in WordPress.
 *
 * @package LocalRank\ProgrammaticSeo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'lrps_config',
	static function ( $config ) {
		$path = __DIR__ . '/projeto.json';
		if ( ! is_readable( $path ) ) {
			return $config;
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $decoded ) ) {
			return $config;
		}

		return $decoded;
	}
);
