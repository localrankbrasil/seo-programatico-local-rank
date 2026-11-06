<?php
/**
 * Business JSON-LD for pages that are not location pages.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Support\Company_Builder;
use LocalRank\ProgrammaticSeo\Support\Json_Ld;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the real business address outside the programmatic post type.
 */
final class Company_Schema_Module {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * Context.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Whether the script was already printed.
	 *
	 * @var bool
	 */
	private $printed = false;

	/**
	 * @param Repository $config Configuration.
	 * @param Store      $store  Context.
	 */
	public function __construct( Repository $config, Store $store ) {
		$this->config = $config;
		$this->store  = $store;
	}

	/**
	 * Registers the head hook.
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/**
	 * Prints the business node when the context is not a location page.
	 */
	public function output() {
		if ( $this->printed || ! $this->should_output() ) {
			return;
		}

		$company = $this->config->get( 'company' );
		if ( ! is_array( $company ) || empty( $company['enabled'] ) ) {
			return;
		}

		$id = ! empty( $company['id'] ) ? (string) $company['id'] : home_url( '/#business' );
		$entity = Company_Builder::entity( $company, $id, true );
		if ( null === $entity ) {
			return;
		}

		$this->printed = true;
		echo Json_Ld::script( $entity ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-LD encoded by Json_Ld.
	}

	/**
	 * Skips admin, feeds, previews, 404s and location pages.
	 *
	 * @return bool
	 */
	private function should_output() {
		if ( is_admin() || is_feed() || is_preview() || is_404() ) {
			return false;
		}
		if ( is_singular( (string) $this->config->get( 'cpt' ) ) || $this->store->is_location() ) {
			return false;
		}
		return true;
	}
}
