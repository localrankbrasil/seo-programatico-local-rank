<?php
/**
 * Replaces braced tokens without parsing shortcodes.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Expands a closed set of {tokens}.
 */
final class Token_Expander {

	/**
	 * Replaces only the tokens present in the map.
	 *
	 * Unknown braces are left untouched so editorial text is preserved.
	 *
	 * @param string               $text Source text.
	 * @param array<string,string> $vars Token values without braces.
	 * @return string
	 */
	public static function expand( $text, array $vars ) {
		$text = (string) $text;
		if ( '' === $text || empty( $vars ) || false === strpos( $text, '{' ) ) {
			return $text;
		}

		$map = array();
		foreach ( $vars as $key => $value ) {
			$key = (string) $key;
			if ( ! preg_match( '/^[a-z0-9_]+$/', $key ) ) {
				continue;
			}
			$map[ '{' . $key . '}' ] = (string) $value;
		}

		if ( empty( $map ) ) {
			return $text;
		}

		return strtr( $text, $map );
	}
}
