<?php
/**
 * Rules for queries that must match exactly one record.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collapses a list of IDs into one ID or zero.
 */
final class Ids {

	/**
	 * Returns the only positive ID, or zero when the set is empty or duplicated.
	 *
	 * @param int[] $ids Candidate IDs.
	 * @return int
	 */
	public static function only_one( array $ids ) {
		$clean = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}

		$clean = array_values( array_unique( $clean ) );
		if ( 1 !== count( $clean ) ) {
			return 0;
		}

		return $clean[0];
	}
}
