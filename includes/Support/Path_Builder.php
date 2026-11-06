<?php
/**
 * Builds the public path of a programmatic page.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Joins the segments of a level after checking each catalog.
 */
final class Path_Builder {

	/**
	 * Returns a path without a leading slash, or an empty string.
	 *
	 * @param array<string,mixed>              $level     Level configuration.
	 * @param array<string,string>             $values    Segment values keyed by segment name.
	 * @param array<string,string[]>           $catalogs  Allowed slugs keyed by segment name.
	 * @param array<string,array{segment:string,map:array<string,string>}> $relations Parent slug of a child segment.
	 * @return string
	 */
	public static function path( array $level, array $values, array $catalogs, array $relations = array() ) {
		$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		if ( empty( $segments ) ) {
			return '';
		}

		$declared = isset( $level['prefix'] ) && is_array( $level['prefix'] ) ? $level['prefix'] : array();
		$prefix   = self::prefix( $level );
		if ( count( $declared ) !== count( $prefix ) ) {
			return '';
		}

		$parts = array();
		foreach ( $prefix as $literal ) {
			$parts[] = $literal;
		}

		foreach ( $segments as $segment ) {
			$segment = (string) $segment;
			$slug    = isset( $values[ $segment ] ) ? (string) $values[ $segment ] : '';
			if ( '' === $slug ) {
				return '';
			}

			$allowed = isset( $catalogs[ $segment ] ) && is_array( $catalogs[ $segment ] ) ? $catalogs[ $segment ] : array();
			if ( ! in_array( $slug, $allowed, true ) ) {
				return '';
			}

			if ( isset( $relations[ $segment ] ) && is_array( $relations[ $segment ] ) ) {
				$parent_segment = isset( $relations[ $segment ]['segment'] ) ? (string) $relations[ $segment ]['segment'] : '';
				$map            = isset( $relations[ $segment ]['map'] ) && is_array( $relations[ $segment ]['map'] ) ? $relations[ $segment ]['map'] : array();
				$expected       = isset( $map[ $slug ] ) ? (string) $map[ $slug ] : '';
				$actual         = isset( $values[ $parent_segment ] ) ? (string) $values[ $parent_segment ] : '';
				if ( '' === $expected || $actual !== $expected ) {
					return '';
				}
			}

			$parts[] = $slug;
		}

		return implode( '/', $parts );
	}

	/**
	 * Literal slugs that sit in front of the catalog, such as a static Services page.
	 *
	 * @param array<string,mixed> $level Level configuration.
	 * @return string[]
	 */
	public static function prefix( array $level ) {
		$raw = isset( $level['prefix'] ) && is_array( $level['prefix'] ) ? $level['prefix'] : array();
		$prefix = array();
		foreach ( $raw as $piece ) {
			$piece = (string) $piece;
			if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $piece ) ) {
				return array();
			}
			$prefix[] = $piece;
		}
		return $prefix;
	}
}
