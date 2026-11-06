<?php
/**
 * Compiles catalog-limited rewrite rules.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns levels and catalogs into ordered rewrite rules.
 */
final class Rewrite_Compiler {

	/**
	 * Builds rules from the most specific level to the least specific.
	 *
	 * A level is skipped when one of its catalogs is empty, so a broad
	 * pattern never captures institutional URLs.
	 *
	 * @param string                         $cpt       Post type slug.
	 * @param array<int,array<string,mixed>> $levels    Level definitions.
	 * @param array<string,string[]>         $catalogs  Slugs keyed by segment.
	 * @param string[]                       $reserved  First-segment slugs that must stay untouched.
	 * @return array<int,array<string,string>>
	 */
	public static function compile( $cpt, array $levels, array $catalogs, array $reserved ) {
		$cpt = (string) $cpt;
		if ( '' === $cpt ) {
			return array();
		}

		$levels = self::sort_for_rules( $levels );
		$rules  = array();

		foreach ( $levels as $level ) {
			$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? array_values( $level['segments'] ) : array();
			if ( empty( $segments ) ) {
				continue;
			}

			$declared = isset( $level['prefix'] ) && is_array( $level['prefix'] ) ? $level['prefix'] : array();
			$prefix   = Path_Builder::prefix( $level );
			if ( count( $declared ) !== count( $prefix ) ) {
				continue;
			}

			$groups = array();
			$vars   = array();
			$skip   = false;
			$capture = 0;

			foreach ( $prefix as $literal ) {
				$groups[] = preg_quote( $literal, '/' );
			}

			foreach ( $segments as $index => $segment ) {
				$segment = (string) $segment;
				$slugs   = isset( $catalogs[ $segment ] ) && is_array( $catalogs[ $segment ] ) ? $catalogs[ $segment ] : array();
				if ( 0 === $index && empty( $prefix ) ) {
					$slugs = self::without_reserved( $slugs, $reserved );
				}
				if ( empty( $slugs ) ) {
					$skip = true;
					break;
				}

				++$capture;
				$groups[] = '(' . self::alternation( $slugs ) . ')';
				$vars[]   = 'lrps_' . $segment . '=$matches[' . $capture . ']';
			}

			if ( $skip ) {
				continue;
			}

			$key    = isset( $level['key'] ) ? (string) $level['key'] : '';
			$vars[] = 'lrps_level=' . rawurlencode( $key );

			$rules[] = array(
				'regex' => '^' . implode( '/', $groups ) . '/?$',
				'query' => 'index.php?post_type=' . rawurlencode( $cpt ) . '&' . implode( '&', $vars ),
				'level' => $key,
			);
		}

		return $rules;
	}

	/**
	 * Picks the level whose segments are all present in the request.
	 *
	 * Longer levels win, which keeps /city/service/ from resolving as /city/.
	 *
	 * @param array<int,array<string,mixed>> $levels Level definitions.
	 * @param array<string,string>           $vars   Request values keyed by segment.
	 * @return array<string,mixed>|null
	 */
	public static function match_level( array $levels, array $vars ) {
		$levels = self::sort_by_specificity( $levels );

		foreach ( $levels as $level ) {
			$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
			if ( empty( $segments ) ) {
				continue;
			}

			$complete = true;
			foreach ( $segments as $segment ) {
				$value = isset( $vars[ $segment ] ) ? (string) $vars[ $segment ] : '';
				if ( '' === $value ) {
					$complete = false;
					break;
				}
			}

			if ( $complete ) {
				return $level;
			}
		}

		return null;
	}

	/**
	 * Sorts a copy of the levels by segment count, descending.
	 *
	 * @param array<int,array<string,mixed>> $levels Levels.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sort_by_specificity( array $levels ) {
		$copy = array_values( $levels );
		usort(
			$copy,
			static function ( $left, $right ) {
				$left_count  = isset( $left['segments'] ) && is_array( $left['segments'] ) ? count( $left['segments'] ) : 0;
				$right_count = isset( $right['segments'] ) && is_array( $right['segments'] ) ? count( $right['segments'] ) : 0;
				if ( $left_count === $right_count ) {
					return 0;
				}
				return ( $left_count > $right_count ) ? -1 : 1;
			}
		);

		return $copy;
	}

	/**
	 * Orders rewrite rules so a longer public path wins over a shorter one.
	 *
	 * A literal prefix counts. /services/service/city/ is registered before
	 * /services/service/, and both stay ahead of a shorter catalog path.
	 *
	 * @param array<int,array<string,mixed>> $levels Levels.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sort_for_rules( array $levels ) {
		$copy = array_values( $levels );
		usort(
			$copy,
			static function ( $left, $right ) {
				$left_count  = self::path_length( is_array( $left ) ? $left : array() );
				$right_count = self::path_length( is_array( $right ) ? $right : array() );
				if ( $left_count === $right_count ) {
					return 0;
				}
				return ( $left_count > $right_count ) ? -1 : 1;
			}
		);

		return $copy;
	}

	/**
	 * Counts literal prefix pieces plus catalog segments.
	 *
	 * @param array<string,mixed> $level Level.
	 * @return int
	 */
	private static function path_length( array $level ) {
		$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? count( $level['segments'] ) : 0;
		return count( Path_Builder::prefix( $level ) ) + $segments;
	}

	/**
	 * Builds a preg alternation.
	 *
	 * @param string[] $slugs Slugs.
	 * @return string
	 */
	public static function alternation( array $slugs ) {
		$quoted = array();
		foreach ( $slugs as $slug ) {
			$slug = (string) $slug;
			if ( '' === $slug ) {
				continue;
			}
			$quoted[] = preg_quote( $slug, '/' );
		}

		return implode( '|', $quoted );
	}

	/**
	 * Removes reserved first segments from a catalog.
	 *
	 * @param string[] $slugs    Catalog slugs.
	 * @param string[] $reserved Reserved slugs.
	 * @return string[]
	 */
	private static function without_reserved( array $slugs, array $reserved ) {
		$blocked = array();
		foreach ( $reserved as $slug ) {
			$blocked[] = (string) $slug;
		}

		$kept = array();
		foreach ( $slugs as $slug ) {
			$slug = (string) $slug;
			if ( '' === $slug || in_array( $slug, $blocked, true ) ) {
				continue;
			}
			$kept[] = $slug;
		}

		return array_values( array_unique( $kept ) );
	}
}
