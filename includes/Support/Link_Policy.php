<?php
/**
 * Decides whether a resolved permalink can be printed.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drops empty destinations and optional self-links.
 */
final class Link_Policy {

	/**
	 * Returns the URL that should be shown for a target post.
	 *
	 * @param int    $target_id  Resolved post ID.
	 * @param int    $current_id Post currently being rendered.
	 * @param string $url        Permalink, already absolute when available.
	 * @param bool   $skip_self  When true, the current post is not linked.
	 * @return string
	 */
	public static function select( $target_id, $current_id, $url, $skip_self ) {
		$target_id  = (int) $target_id;
		$current_id = (int) $current_id;
		$url        = (string) $url;

		if ( $target_id <= 0 || '' === $url ) {
			return '';
		}

		if ( $skip_self && $target_id === $current_id ) {
			return '';
		}

		return $url;
	}
}
