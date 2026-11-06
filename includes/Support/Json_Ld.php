<?php
/**
 * Serializes a schema.org graph into a script tag.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds JSON-LD without string-concatenated JSON.
 */
final class Json_Ld {

	/**
	 * Returns a script tag or an empty string when encoding fails.
	 *
	 * @param array<string,mixed> $data Schema graph.
	 * @return string
	 */
	public static function script( array $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$json  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $flags ) : json_encode( $data, $flags );
		if ( ! is_string( $json ) || '' === $json ) {
			return '';
		}

		$json = str_replace( '</', '<\/', $json );

		return '<script type="application/ld+json">' . $json . '</script>';
	}
}
