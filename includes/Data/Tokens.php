<?php
/**
 * Token values shared by shortcodes, schema and Elementor.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Data;

use LocalRank\ProgrammaticSeo\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the closed token map for one record.
 */
final class Tokens {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * @param Repository $config Configuration.
	 */
	public function __construct( Repository $config ) {
		$this->config = $config;
	}

	/**
	 * Token values for a record from Record_Reader::read().
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,string>
	 */
	public function for_record( array $record ) {
		$city  = isset( $record['city_name'] ) ? (string) $record['city_name'] : '';
		$state = isset( $record['state'] ) ? (string) $record['state'] : '';
		$place = ( '' !== $city && '' !== $state ) ? $city . ', ' . $state : '';

		return array(
			'city_name'    => $city,
			'city'         => $city,
			'state'        => $state,
			'city_state'   => $place,
			'service_name' => isset( $record['service_name'] ) ? (string) $record['service_name'] : '',
			'service'      => isset( $record['service_name'] ) ? (string) $record['service_name'] : '',
			'service_slug' => isset( $record['service_slug'] ) ? (string) $record['service_slug'] : '',
			'trade_label'  => (string) $this->config->get( 'trade_label' ),
		);
	}
}
