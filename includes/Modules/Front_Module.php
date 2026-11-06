<?php
/**
 * Front-end classes and component CSS.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads presentation that belongs to the components, not to a client theme.
 */
final class Front_Module {

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
	 * Reader.
	 *
	 * @var Record_Reader
	 */
	private $reader;

	/**
	 * @param Repository    $config Configuration.
	 * @param Store         $store  Context.
	 * @param Record_Reader $reader Reader.
	 */
	public function __construct( Repository $config, Store $store, Record_Reader $reader ) {
		$this->config = $config;
		$this->store  = $store;
		$this->reader = $reader;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Adds level and service classes for template CSS.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( ! $this->store->is_location() ) {
			return $classes;
		}
		$record = $this->reader->read( $this->store->effective_id() );
		if ( empty( $record['valid'] ) ) {
			return $classes;
		}

		$classes[] = 'lrps-location';
		$classes[] = 'lrps-level-' . sanitize_html_class( (string) $record['level_key'] );
		if ( '' !== (string) $record['service_slug'] ) {
			$classes[] = 'lrps-service-' . sanitize_html_class( (string) $record['service_slug'] );
		}
		return $classes;
	}

	/**
	 * Enqueues breadcrumb and map rules on location pages.
	 */
	public function assets() {
		if ( ! is_singular( (string) $this->config->get( 'cpt' ) ) ) {
			return;
		}

		$css = $this->css();
		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'lrps-front', false, array(), LRPS_VERSION );
		wp_enqueue_style( 'lrps-front' );
		wp_add_inline_style( 'lrps-front', $css );
	}

	/**
	 * Component CSS using the project colors and map class.
	 *
	 * @return string
	 */
	private function css() {
		$parts = array();
		if ( $this->config->module( 'breadcrumbs_css' ) ) {
			$crumbs = $this->config->get( 'breadcrumbs' );
			$crumbs = is_array( $crumbs ) ? $crumbs : array();
			$color  = isset( $crumbs['color'] ) ? (string) $crumbs['color'] : '#8e92a5';
			$hover  = isset( $crumbs['hover'] ) ? (string) $crumbs['hover'] : '#bf9a33';
			$size   = isset( $crumbs['font_size'] ) ? (string) $crumbs['font_size'] : '11px';
			$parts[] = '.lrps-breadcrumbs{--lrps-crumb-color:' . $color . ';--lrps-crumb-hover:' . $hover . ';--lrps-crumb-size:' . $size . '}';
			$parts[] = '.lrps-breadcrumbs__list{display:flex;flex-wrap:wrap;gap:.35em;list-style:none;margin:0;padding:0;font-size:var(--lrps-crumb-size,11px);letter-spacing:.04em;text-transform:uppercase}';
			$parts[] = '.lrps-breadcrumbs__item{color:var(--lrps-crumb-color,#8e92a5)}';
			$parts[] = '.lrps-breadcrumbs__item a{color:inherit;text-decoration:none}';
			$parts[] = '.lrps-breadcrumbs__item a:hover,.lrps-breadcrumbs__item a:focus{color:var(--lrps-crumb-hover,#bf9a33)}';
		}

		$design = $this->config->get( 'design' );
		$custom_section = is_array( $design ) && ! empty( $design['section_class'] );
		if ( $this->config->module( 'section_label' ) && ! $custom_section ) {
			$crumbs = $this->config->get( 'breadcrumbs' );
			$accent = is_array( $crumbs ) && isset( $crumbs['hover'] ) ? (string) $crumbs['hover'] : '#bf9a33';
			$parts[] = '.lrps-section-label{display:inline-flex;align-items:center;gap:10px;font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:' . $accent . '}';
			$parts[] = '.lrps-section-label::before{content:"";display:block;width:32px;height:1px;background:currentColor}';
		}

		$map = $this->config->get( 'map' );
		$class = is_array( $map ) && ! empty( $map['class'] ) ? (string) $map['class'] : '';
		$classes = array( 'lrps-map', $this->config->prefix() . '-map' );
		if ( '' !== $class ) {
			$classes[] = $class;
		}
		$classes = array_unique( $classes );
		foreach ( $classes as $selector ) {
			$parts[] = '.' . $selector . '{position:relative;width:100%;height:100%;min-height:365px;overflow:hidden}';
			$parts[] = '.' . $selector . ' iframe{position:absolute;inset:0;width:100%;height:100%;border:0;display:block}';
		}

		return implode( '', $parts );
	}
}
