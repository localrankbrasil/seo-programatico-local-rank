<?php
/**
 * Holds the location post that should win over Elementor's global post.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Context;

use LocalRank\ProgrammaticSeo\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A small stack so nested Saved Templates keep the location page.
 */
final class Store {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * Nested post IDs. The last one is the effective record.
	 *
	 * @var int[]
	 */
	private $stack = array();

	/**
	 * @param Repository $config Configuration.
	 */
	public function __construct( Repository $config ) {
		$this->config = $config;
	}

	/**
	 * Fixes the queried location page before templates render.
	 */
	public function lock_queried() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( $this->post_type() ) ) {
			return;
		}

		$id = (int) get_queried_object_id();
		if ( $id > 0 ) {
			$this->push( $id );
		}
	}

	/**
	 * Pushes a post ID onto the context stack.
	 *
	 * @param int $post_id Post ID.
	 */
	public function push( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			$this->stack[] = $post_id;
		}
	}

	/**
	 * Restores the previous context.
	 */
	public function pop() {
		array_pop( $this->stack );
	}

	/**
	 * Effective location post, or zero.
	 *
	 * @return int
	 */
	public function effective_id() {
		if ( ! empty( $this->stack ) ) {
			return (int) end( $this->stack );
		}

		return $this->queried_id();
	}

	/**
	 * Whether the effective post belongs to the programmatic post type.
	 *
	 * @return bool
	 */
	public function is_location() {
		$id = $this->effective_id();
		if ( $id <= 0 || ! function_exists( 'get_post_type' ) ) {
			return false;
		}

		return get_post_type( $id ) === $this->post_type();
	}

	/**
	 * Configured post type.
	 *
	 * @return string
	 */
	public function post_type() {
		return (string) $this->config->get( 'cpt' );
	}

	/**
	 * Falls back to the queried object, then the global post.
	 *
	 * @return int
	 */
	private function queried_id() {
		if ( function_exists( 'get_queried_object_id' ) ) {
			$id = (int) get_queried_object_id();
			if ( $id > 0 && function_exists( 'get_post_type' ) && get_post_type( $id ) === $this->post_type() ) {
				return $id;
			}
		}

		if ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post && $GLOBALS['post']->post_type === $this->post_type() ) {
			return (int) $GLOBALS['post']->ID;
		}

		return 0;
	}
}
