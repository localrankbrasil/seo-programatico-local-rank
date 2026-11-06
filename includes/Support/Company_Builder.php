<?php
/**
 * Business entity for pages that are not localized service pages.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires a real address before emitting LocalBusiness-like types.
 */
final class Company_Builder {

	/**
	 * Schema types the agency can select without inventing a branch.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array(
			'LocalBusiness',
			'HomeAndConstructionBusiness',
			'GeneralContractor',
			'ProfessionalService',
			'Store',
			'Organization',
		);
	}

	/**
	 * Builds the business node.
	 *
	 * Address is included only when every postal component exists.
	 * Localized pages must not call this with a city of coverage.
	 *
	 * @param array<string,string> $company      Company settings.
	 * @param string               $id           Stable @id.
	 * @param bool                 $with_address Whether a postal address is required and included.
	 * @return array<string,mixed>|null
	 */
	public static function entity( array $company, $id, $with_address = true ) {
		$name = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) ( isset( $company['name'] ) ? $company['name'] : '' ) ) ) );
		$id   = trim( (string) $id );
		if ( '' === $name || '' === $id ) {
			return null;
		}

		$type = isset( $company['type'] ) ? (string) $company['type'] : 'LocalBusiness';
		if ( ! in_array( $type, self::types(), true ) ) {
			$type = 'LocalBusiness';
		}

		$node = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => $id,
			'name'     => $name,
		);

		$url = trim( (string) ( isset( $company['url'] ) ? $company['url'] : '' ) );
		if ( preg_match( '#^https?://#i', $url ) ) {
			$node['url'] = $url;
		}

		$telephone = trim( (string) ( isset( $company['telephone'] ) ? $company['telephone'] : '' ) );
		if ( '' !== $telephone ) {
			$node['telephone'] = $telephone;
		}

		$image = trim( (string) ( isset( $company['image'] ) ? $company['image'] : '' ) );
		if ( preg_match( '#^https?://#i', $image ) ) {
			$node['image'] = $image;
		}

		if ( $with_address ) {
			$address = self::address( $company );
			if ( null === $address ) {
				return null;
			}
			$node['address'] = $address;
		}

		$geo = self::geo( $company );
		if ( null !== $geo ) {
			$node['geo'] = $geo;
		}

		return $node;
	}

	/**
	 * Builds a PostalAddress when the full address is present.
	 *
	 * @param array<string,string> $company Company settings.
	 * @return array<string,string>|null
	 */
	private static function address( array $company ) {
		$map = array(
			'streetAddress'   => 'street',
			'addressLocality' => 'locality',
			'addressRegion'   => 'region',
			'postalCode'      => 'postal',
			'addressCountry'  => 'country',
		);

		$address = array( '@type' => 'PostalAddress' );
		foreach ( $map as $schema_key => $source_key ) {
			$value = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) ( isset( $company[ $source_key ] ) ? $company[ $source_key ] : '' ) ) ) );
			if ( '' === $value ) {
				return null;
			}
			$address[ $schema_key ] = $value;
		}

		return $address;
	}

	/**
	 * Builds GeoCoordinates when both numbers are plausible.
	 *
	 * @param array<string,string> $company Company settings.
	 * @return array<string,mixed>|null
	 */
	private static function geo( array $company ) {
		$lat = isset( $company['latitude'] ) ? trim( (string) $company['latitude'] ) : '';
		$lng = isset( $company['longitude'] ) ? trim( (string) $company['longitude'] ) : '';
		if ( '' === $lat || '' === $lng || ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		$lat = (float) $lat;
		$lng = (float) $lng;
		if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
			return null;
		}

		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}
}
