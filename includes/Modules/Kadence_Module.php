<?php
/**
 * Kadence header and layout for location pages.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matches location pages to the transparent full-width header used by pages.
 */
final class Kadence_Module {

	const VERSION = '1';

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
	 * Registers theme options, per-post meta and a one-time backfill.
	 */
	public function register() {
		add_filter( 'kadence_theme_options', array( $this, 'options' ) );
		add_filter( 'kadence_theme_options_defaults', array( $this, 'options' ) );
		add_filter( 'kadence_post_layout', array( $this, 'layout' ) );

		$cpt = (string) $this->config->get( 'cpt' );
		if ( '' !== $cpt ) {
			add_action( 'save_post_' . $cpt, array( $this, 'apply' ), 30 );
		}
		add_action( 'init', array( $this, 'backfill' ), 30 );
	}

	/**
	 * Kadence treats transparent_header_{post_type} as "disable on this type".
	 *
	 * @param mixed $options Theme options.
	 * @return mixed
	 */
	public function options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$cpt = (string) $this->config->get( 'cpt' );
		if ( '' === $cpt ) {
			return $options;
		}

		$options[ 'transparent_header_' . $cpt ] = false;
		$options[ $cpt . '_layout' ]             = 'fullwidth';
		$options[ $cpt . '_content_style' ]      = 'unboxed';
		$options[ $cpt . '_vertical_padding' ]   = 'hide';
		$options[ $cpt . '_title' ]              = false;
		$options[ $cpt . '_feature' ]            = false;
		return $options;
	}

	/**
	 * Keeps the singular layout full width when Kadence asks.
	 *
	 * @param string $layout Current layout.
	 * @return string
	 */
	public function layout( $layout ) {
		if ( is_singular( (string) $this->config->get( 'cpt' ) ) ) {
			return 'fullwidth';
		}
		return $layout;
	}

	/**
	 * Writes the per-page Kadence meta used by the prototype pages.
	 *
	 * @param int $post_id Post ID.
	 */
	public function apply( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( get_post_type( $post_id ) !== (string) $this->config->get( 'cpt' ) ) {
			return;
		}

		$map = array(
			'_kad_post_transparent'      => 'enable',
			'_kad_post_title'            => 'hide',
			'_kad_post_layout'           => 'fullwidth',
			'_kad_post_content_style'    => 'unboxed',
			'_kad_post_vertical_padding' => 'hide',
			'_kad_post_feature'          => 'hide',
		);
		foreach ( $map as $key => $value ) {
			if ( get_post_meta( $post_id, $key, true ) !== $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Applies the layout once to location pages that already exist.
	 */
	public function backfill() {
		$key = 'lrps_kadence_layout_' . $this->config->prefix();
		if ( get_option( $key ) === self::VERSION ) {
			return;
		}

		$cpt   = (string) $this->config->get( 'cpt' );
		$paged = 1;
		do {
			$ids = get_posts(
				array(
					'post_type'      => $cpt,
					'post_status'    => 'any',
					'posts_per_page' => 200,
					'paged'          => $paged,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $id ) {
				$this->apply( (int) $id );
			}
			++$paged;
		} while ( $paged <= 40 );

		update_option( $key, self::VERSION, false );
	}
}
