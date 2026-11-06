<?php
/**
 * Slug normalization that does not require WordPress.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns labels into URL-safe slugs.
 */
final class Slug {

	/**
	 * Normalizes a value into a lowercase slug.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = self::strip_accents( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		$value = trim( (string) $value, '-' );

		return $value;
	}

	/**
	 * Reduces common Latin accents so city names stay readable as slugs.
	 *
	 * @param string $value Lowercase text.
	 * @return string
	 */
	private static function strip_accents( $value ) {
		$map = array(
			'à' => 'a',
			'á' => 'a',
			'â' => 'a',
			'ã' => 'a',
			'ä' => 'a',
			'å' => 'a',
			'è' => 'e',
			'é' => 'e',
			'ê' => 'e',
			'ë' => 'e',
			'ì' => 'i',
			'í' => 'i',
			'î' => 'i',
			'ï' => 'i',
			'ò' => 'o',
			'ó' => 'o',
			'ô' => 'o',
			'õ' => 'o',
			'ö' => 'o',
			'ù' => 'u',
			'ú' => 'u',
			'û' => 'u',
			'ü' => 'u',
			'ç' => 'c',
			'ñ' => 'n',
			'ý' => 'y',
			'ÿ' => 'y',
		);

		return strtr( $value, $map );
	}
}
