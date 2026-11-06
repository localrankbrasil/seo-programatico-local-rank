<?php
/**
 * In-request view of the city and service catalogs.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Data;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Support\Dimensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Looks up approved cities and services by slug.
 */
final class Catalog {

	/**
	 * Cities keyed by slug.
	 *
	 * @var array<string,array<string,string>>
	 */
	private $cities = array();

	/**
	 * Services keyed by slug.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $services = array();

	/**
	 * Extra catalogs keyed by dimension key. Items are keyed by slug.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $dimensions = array();

	/**
	 * Reserved first-segment slugs.
	 *
	 * @var string[]
	 */
	private $reserved = array();

	/**
	 * Builds indexes from the sanitized configuration.
	 *
	 * @param Repository $config Configuration.
	 */
	public function __construct( Repository $config ) {
		$cities = $config->get( 'cities' );
		if ( is_array( $cities ) ) {
			foreach ( $cities as $city ) {
				if ( is_array( $city ) && ! empty( $city['slug'] ) ) {
					$this->cities[ (string) $city['slug'] ] = $city;
				}
			}
		}

		$services = $config->get( 'services' );
		if ( is_array( $services ) ) {
			foreach ( $services as $service ) {
				if ( is_array( $service ) && ! empty( $service['slug'] ) ) {
					$this->services[ (string) $service['slug'] ] = $service;
				}
			}
		}

		$reserved = $config->get( 'reserved_slugs' );
		$this->reserved = is_array( $reserved ) ? array_values( $reserved ) : array();

		$dimensions = $config->get( 'dimensions' );
		if ( is_array( $dimensions ) ) {
			foreach ( $dimensions as $dimension ) {
				if ( ! is_array( $dimension ) || empty( $dimension['key'] ) ) {
					continue;
				}
				$items = array();
				$rows  = isset( $dimension['items'] ) && is_array( $dimension['items'] ) ? $dimension['items'] : array();
				foreach ( $rows as $item ) {
					if ( is_array( $item ) && ! empty( $item['slug'] ) ) {
						$items[ (string) $item['slug'] ] = $item;
					}
				}
				$dimension['items']                      = $items;
				$this->dimensions[ (string) $dimension['key'] ] = $dimension;
			}
		}
	}

	/**
	 * Slug lists consumed by the rewrite compiler.
	 *
	 * @return array<string,string[]>
	 */
	public function indexes() {
		$indexes = array(
			'city'    => array_keys( $this->cities ),
			'service' => array_keys( $this->services ),
			'state'   => $this->state_slugs(),
		);
		foreach ( $this->dimensions as $key => $dimension ) {
			$items = isset( $dimension['items'] ) && is_array( $dimension['items'] ) ? $dimension['items'] : array();
			$indexes[ $key ] = array_keys( $items );
		}
		return $indexes;
	}

	/**
	 * Parent maps for child dimensions.
	 *
	 * @return array<string,array{segment:string,map:array<string,string>}>
	 */
	public function relations() {
		$rows = array();
		foreach ( $this->dimensions as $dimension ) {
			$rows[] = $dimension;
		}
		return Dimensions::relations( $rows );
	}

	/**
	 * One extra catalog.
	 *
	 * @param string $key Dimension key.
	 * @return array<string,mixed>|null
	 */
	public function dimension( $key ) {
		return isset( $this->dimensions[ (string) $key ] ) ? $this->dimensions[ (string) $key ] : null;
	}

	/**
	 * One item from an extra catalog.
	 *
	 * @param string $key  Dimension key.
	 * @param string $slug Item slug.
	 * @return array<string,mixed>|null
	 */
	public function item( $key, $slug ) {
		$dimension = $this->dimension( $key );
		if ( ! is_array( $dimension ) || empty( $dimension['items'] ) || ! is_array( $dimension['items'] ) ) {
			return null;
		}
		return isset( $dimension['items'][ (string) $slug ] ) ? $dimension['items'][ (string) $slug ] : null;
	}

	/**
	 * Reserved slugs.
	 *
	 * @return string[]
	 */
	public function reserved() {
		return $this->reserved;
	}

	/**
	 * Whether a city slug is approved.
	 *
	 * @param string $slug City slug.
	 * @return bool
	 */
	public function is_city( $slug ) {
		return isset( $this->cities[ (string) $slug ] );
	}

	/**
	 * Whether a service slug is approved.
	 *
	 * @param string $slug Service slug.
	 * @return bool
	 */
	public function is_service( $slug ) {
		return isset( $this->services[ (string) $slug ] );
	}

	/**
	 * One city row.
	 *
	 * @param string $slug City slug.
	 * @return array<string,string>|null
	 */
	public function city( $slug ) {
		return isset( $this->cities[ (string) $slug ] ) ? $this->cities[ (string) $slug ] : null;
	}

	/**
	 * One service row.
	 *
	 * @param string $slug Service slug.
	 * @return array<string,mixed>|null
	 */
	public function service( $slug ) {
		return isset( $this->services[ (string) $slug ] ) ? $this->services[ (string) $slug ] : null;
	}

	/**
	 * All service rows in catalog order.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function services() {
		return array_values( $this->services );
	}

	/**
	 * Lowercase state codes present in the city catalog.
	 *
	 * @return string[]
	 */
	public function state_slugs() {
		$states = array();
		foreach ( $this->cities as $city ) {
			$state = strtolower( (string) $city['state'] );
			if ( '' !== $state ) {
				$states[] = $state;
			}
		}
		$states = array_values( array_unique( $states ) );
		sort( $states );
		return $states;
	}
}
