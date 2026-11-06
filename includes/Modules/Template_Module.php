<?php
/**
 * Renders an allow-listed Elementor Saved Template for the current service.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Catalog;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Problems and Gallery stay empty when the service has no published template.
 */
final class Template_Module {

	/**
	 * Catalog.
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Reader.
	 *
	 * @var Record_Reader
	 */
	private $reader;

	/**
	 * Context stack.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * HTML cache keyed by template and location post.
	 *
	 * @var array<string,string>
	 */
	private $cache = array();

	/**
	 * @param Catalog       $catalog Catalog.
	 * @param Record_Reader $reader  Reader.
	 * @param Store         $store   Context.
	 */
	public function __construct( Catalog $catalog, Record_Reader $reader, Store $store ) {
		$this->catalog = $catalog;
		$this->reader  = $reader;
		$this->store   = $store;
	}

	/**
	 * Problems section for the current service.
	 *
	 * @return string
	 */
	public function problems() {
		return $this->mapped( 'problems_template_id' );
	}

	/**
	 * Gallery for the current service.
	 *
	 * @return string
	 */
	public function gallery() {
		return $this->mapped( 'gallery_template_id' );
	}

	/**
	 * Template IDs the catalog is allowed to render.
	 *
	 * @return int[]
	 */
	public function allowed_ids() {
		$ids = array();
		foreach ( $this->catalog->services() as $service ) {
			foreach ( array( 'problems_template_id', 'gallery_template_id' ) as $key ) {
				$id = isset( $service[ $key ] ) ? (int) $service[ $key ] : 0;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Renders the template mapped on the current service.
	 *
	 * @param string $key problems_template_id or gallery_template_id.
	 * @return string
	 */
	private function mapped( $key ) {
		if ( ! \LocalRank\ProgrammaticSeo\Plugin::instance()->config()->module( 'templates' ) ) {
			return '';
		}
		if ( ! $this->store->is_location() ) {
			return '';
		}

		$record = $this->reader->read( $this->store->effective_id() );
		if ( empty( $record['valid'] ) || '' === (string) $record['service_slug'] ) {
			return '';
		}

		$service = $this->catalog->service( (string) $record['service_slug'] );
		if ( null === $service ) {
			return '';
		}

		$template_id = isset( $service[ $key ] ) ? (int) $service[ $key ] : 0;
		return $this->render( $template_id );
	}

	/**
	 * Renders one Saved Template while the location post stays fixed.
	 *
	 * @param int $template_id Elementor library ID.
	 * @return string
	 */
	private function render( $template_id ) {
		$template_id = (int) $template_id;
		if ( $template_id <= 0 || ! in_array( $template_id, $this->allowed_ids(), true ) ) {
			return '';
		}
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}

		$context = $this->store->effective_id();
		$cache_key = $template_id . ':' . $context;
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		$post = get_post( $template_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || 'elementor_library' !== $post->post_type ) {
			$this->cache[ $cache_key ] = '';
			return '';
		}

		$this->store->push( $context );
		$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true );
		$this->store->pop();

		$this->cache[ $cache_key ] = is_string( $html ) ? $html : '';
		return $this->cache[ $cache_key ];
	}
}
