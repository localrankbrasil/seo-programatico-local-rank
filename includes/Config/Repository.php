<?php
/**
 * Reads and stores the project configuration.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One autoloaded option, sanitized on every read.
 */
final class Repository {

	const OPTION = 'lrps_config';

	/**
	 * Cached sanitized configuration.
	 *
	 * @var array<string,mixed>|null
	 */
	private $config = null;

	/**
	 * Returns the full configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function all() {
		if ( null !== $this->config ) {
			return $this->config;
		}

		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$clean = Sanitizer::clean( $stored, Defaults::all(), 'stored' );
		$config = $clean['config'];

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'lrps_config', $config );
			if ( is_array( $filtered ) ) {
				$again  = Sanitizer::clean( $filtered, $config, 'stored' );
				$config = $again['config'];
			}
		}

		$this->config = $config;
		return $this->config;
	}

	/**
	 * Reads one top-level value.
	 *
	 * @param string $key Configuration key.
	 * @return mixed
	 */
	public function get( $key ) {
		$config = $this->all();
		return isset( $config[ $key ] ) ? $config[ $key ] : null;
	}

	/**
	 * Reads a logical ACF field name.
	 *
	 * @param string $logical Field key such as city_slug.
	 * @return string
	 */
	public function field( $logical ) {
		$fields = $this->get( 'fields' );
		if ( ! is_array( $fields ) || empty( $fields[ $logical ] ) ) {
			return '';
		}
		return (string) $fields[ $logical ];
	}

	/**
	 * Whether a module is enabled.
	 *
	 * @param string $module Module id.
	 * @return bool
	 */
	public function module( $module ) {
		$modules = $this->get( 'modules' );
		return is_array( $modules ) && ! empty( $modules[ $module ] );
	}

	/**
	 * Project prefix.
	 *
	 * @return string
	 */
	public function prefix() {
		return (string) $this->get( 'prefix' );
	}

	/**
	 * Saves a sanitized configuration and clears the memory cache.
	 *
	 * @param array<string,mixed> $config Sanitized config.
	 */
	public function save( array $config ) {
		update_option( self::OPTION, $config, true );
		$this->config = null;
	}

	/**
	 * Forgets the in-request cache.
	 */
	public function flush() {
		$this->config = null;
	}
}
