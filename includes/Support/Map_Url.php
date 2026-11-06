<?php
/**
 * Allow-list for Google Maps embed URLs.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accepts only HTTPS embed URLs from Google Maps.
 */
final class Map_Url {

	/**
	 * Hosts allowed to embed a map.
	 *
	 * @return string[]
	 */
	public static function allowed_hosts() {
		return array( 'www.google.com', 'google.com' );
	}

	/**
	 * Returns a safe embed URL or an empty string.
	 *
	 * @param mixed $url Raw field value.
	 * @return string
	 */
	public static function sanitize( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '/[\s<>"\']/', $url ) ) {
			return '';
		}

		if ( preg_match( '/^(javascript|data|vbscript):/i', $url ) ) {
			return '';
		}

		$parts = parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( 'https' !== $scheme ) {
			return '';
		}

		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return '';
		}

		if ( isset( $parts['fragment'] ) && '' !== (string) $parts['fragment'] ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! in_array( $host, self::allowed_hosts(), true ) ) {
			return '';
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( 0 !== strpos( $path, '/maps/embed' ) ) {
			return '';
		}

		return $url;
	}
}
