<?php
/**
 * Shortcodes that bind a location record to Elementor.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Catalog;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;
use LocalRank\ProgrammaticSeo\Data\Tokens;
use LocalRank\ProgrammaticSeo\Media\Library;
use LocalRank\ProgrammaticSeo\Support\Link_Policy;
use LocalRank\ProgrammaticSeo\Support\Map_Url;
use LocalRank\ProgrammaticSeo\Support\Schema_Builder;
use LocalRank\ProgrammaticSeo\Support\Slug;
use LocalRank\ProgrammaticSeo\Support\Token_Expander;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public binding layer described in stage 3.
 */
final class Shortcode_Module {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

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
	 * Context.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Tokens.
	 *
	 * @var Tokens
	 */
	private $tokens;

	/**
	 * Media.
	 *
	 * @var Library
	 */
	private $media;

	/**
	 * Saved templates.
	 *
	 * @var Template_Module
	 */
	private $templates;

	/**
	 * Schema.
	 *
	 * @var Schema_Module
	 */
	private $schema;

	/**
	 * Prevents city content from rendering itself.
	 *
	 * @var bool
	 */
	private $rendering_content = false;

	/**
	 * @param Repository      $config    Configuration.
	 * @param Catalog         $catalog   Catalog.
	 * @param Record_Reader   $reader    Reader.
	 * @param Store           $store     Context.
	 * @param Tokens          $tokens    Tokens.
	 * @param Library         $media     Media.
	 * @param Template_Module $templates Templates.
	 * @param Schema_Module   $schema    Schema.
	 */
	public function __construct( Repository $config, Catalog $catalog, Record_Reader $reader, Store $store, Tokens $tokens, Library $media, Template_Module $templates, Schema_Module $schema ) {
		$this->config    = $config;
		$this->catalog   = $catalog;
		$this->reader    = $reader;
		$this->store     = $store;
		$this->tokens    = $tokens;
		$this->media     = $media;
		$this->templates = $templates;
		$this->schema    = $schema;
	}

	/**
	 * Registers shortcodes.
	 */
	public function register() {
		$map = array(
			'city'                     => 'city_name',
			'city_name'                => 'city_name',
			'state'                    => 'state',
			'city_state'               => 'city_state',
			'service_name'             => 'service_name',
			'service_hero_suffix'      => 'hero_suffix',
			'service_hero_description' => 'hero_description',
			'page_title'               => 'page_title',
			'current_url'              => 'current_url',
			'city_content'             => 'city_content',
			'location_url'             => 'location_url',
			'map_embed_url'            => 'map_url',
			'faq_question'             => 'faq_question',
			'faq_answer'               => 'faq_answer',
			'breadcrumbs'              => 'breadcrumbs',
			'problems_section'         => 'problems',
			'service_problems'         => 'problems',
			'service_gallery'          => 'gallery',
		);

		foreach ( $map as $name => $method ) {
			add_shortcode( $this->name( $name ), array( $this, $method ) );
		}

		$levels = $this->config->get( 'levels' );
		if ( $this->config->module( 'schema' ) && is_array( $levels ) ) {
			foreach ( $levels as $level ) {
				if ( ! is_array( $level ) || empty( $level['schema_shortcode'] ) ) {
					continue;
				}
				$key = (string) $level['key'];
				add_shortcode(
					$this->name( (string) $level['schema_shortcode'] ),
					static function () use ( $key ) {
						return \LocalRank\ProgrammaticSeo\Plugin::instance()->schema()->render_level( $key );
					}
				);
			}
		}

		if ( $this->config->module( 'section_label' ) && ! shortcode_exists( 'section_label' ) ) {
			add_shortcode( 'section_label', array( $this, 'section_label' ) );
		}
	}

	/**
	 * Shortcode tag names this module owns, without brackets.
	 *
	 * @return string[]
	 */
	public function owned_tags() {
		$tags = array();
		foreach ( array(
			'city',
			'city_name',
			'state',
			'city_state',
			'service_name',
			'service_hero_suffix',
			'service_hero_description',
			'page_title',
			'current_url',
			'city_content',
			'location_url',
			'map_embed_url',
			'faq_question',
			'faq_answer',
			'breadcrumbs',
			'problems_section',
			'service_problems',
			'service_gallery',
		) as $name ) {
			$tags[] = $this->name( $name );
		}

		$levels = $this->config->get( 'levels' );
		if ( is_array( $levels ) ) {
			foreach ( $levels as $level ) {
				if ( is_array( $level ) && ! empty( $level['schema_shortcode'] ) ) {
					$tags[] = $this->name( (string) $level['schema_shortcode'] );
				}
			}
		}

		if ( $this->config->module( 'section_label' ) ) {
			$tags[] = 'section_label';
		}

		return $tags;
	}

	/**
	 * Expands tokens and then only the shortcodes owned by this plugin.
	 *
	 * @param string $html HTML or text.
	 * @return string
	 */
	public function expand_html( $html ) {
		$html = (string) $html;
		$tags = $this->owned_tags();
		if ( ! empty( $tags ) && false !== strpos( $html, '[' ) ) {
			$pattern = '#https?://(?=\[(?:' . implode( '|', array_map( 'preg_quote', $tags ) ) . ')\b)#i';
			$html    = (string) preg_replace( $pattern, '', $html );
		}
		$html = Token_Expander::expand( $html, $this->token_vars() );
		if ( empty( $tags ) || false === strpos( $html, '[' ) ) {
			return $html;
		}

		$pattern = '/\[(' . implode( '|', array_map( 'preg_quote', $tags ) ) . ')(\s[^\]]*)?\]/';
		return (string) preg_replace_callback(
			$pattern,
			static function ( $matches ) {
				return do_shortcode( '[' . $matches[1] . ( isset( $matches[2] ) ? $matches[2] : '' ) . ']' );
			},
			$html
		);
	}

	/**
	 * City name.
	 *
	 * @return string
	 */
	public function city_name() {
		$record = $this->record();
		return empty( $record['valid'] ) ? '' : esc_html( (string) $record['city_name'] );
	}

	/**
	 * State code.
	 *
	 * @return string
	 */
	public function state() {
		$record = $this->record();
		return empty( $record['valid'] ) ? '' : esc_html( (string) $record['state'] );
	}

	/**
	 * City and state label.
	 *
	 * @return string
	 */
	public function city_state() {
		$vars = $this->token_vars();
		return isset( $vars['city_state'] ) && '' !== $vars['city_state'] ? esc_html( $vars['city_state'] ) : '';
	}

	/**
	 * Service display name.
	 *
	 * @return string
	 */
	public function service_name() {
		$record = $this->record();
		if ( empty( $record['valid'] ) || '' === (string) $record['service_name'] ) {
			return '';
		}
		return esc_html( (string) $record['service_name'] );
	}

	/**
	 * Hero suffix from the service catalog.
	 *
	 * @return string
	 */
	public function hero_suffix() {
		$service = $this->service_config();
		if ( null === $service || '' === (string) $service['hero_suffix'] ) {
			return '';
		}
		return esc_html( (string) $service['hero_suffix'] );
	}

	/**
	 * Hero description from the service catalog.
	 *
	 * @return string
	 */
	public function hero_description() {
		$service = $this->service_config();
		if ( null === $service || '' === (string) $service['hero_description'] ) {
			return '';
		}
		$text = Token_Expander::expand( (string) $service['hero_description'], $this->token_vars() );
		return esc_html( $text );
	}

	/**
	 * Calculated page name.
	 *
	 * @return string
	 */
	public function page_title() {
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}
		$schema = $this->config->get( 'schema' );
		if ( ! is_array( $schema ) ) {
			return '';
		}
		$key = '' !== (string) $record['service_slug'] ? 'service_name' : 'city_name';
		$name = Schema_Builder::render_template( isset( $schema[ $key ] ) ? (string) $schema[ $key ] : '', $this->token_vars() );
		return '' === $name ? '' : esc_html( $name );
	}

	/**
	 * Canonical permalink.
	 *
	 * @return string
	 */
	public function current_url() {
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}
		$url = get_permalink( (int) $record['post_id'] );
		return is_string( $url ) ? esc_url( $url ) : '';
	}

	/**
	 * Localized editorial content.
	 *
	 * @return string
	 */
	public function city_content() {
		if ( $this->rendering_content ) {
			return '';
		}
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}
		$raw = $this->reader->meta( (int) $record['post_id'], 'city_content' );
		if ( '' === trim( $raw ) ) {
			return '';
		}
		$this->rendering_content = true;
		$html = wp_kses_post( $raw );
		if ( function_exists( 'apply_filters' ) ) {
			$html = apply_filters( 'the_content', $html );
		} else {
			$html = $this->expand_html( $html );
		}
		$this->rendering_content = false;
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Permalink of a published city or service page.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function location_url( $atts ) {
		$atts = shortcode_atts(
			array(
				'service'      => '',
				'target'       => '',
				'skip_current' => $this->config->get( 'skip_self_links' ) ? '1' : '0',
			),
			is_array( $atts ) ? $atts : array(),
			$this->name( 'location_url' )
		);

		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}

		$extra = isset( $record['extra'] ) && is_array( $record['extra'] ) ? $record['extra'] : array();
		if ( 'city' === $atts['target'] ) {
			$level = $this->reader->city_level();
			$id    = is_array( $level ) ? $this->reader->find_id( $level, (string) $record['city_slug'], '', (string) $record['state'], $extra ) : 0;
		} else {
			$service = Slug::normalize( $atts['service'] );
			$level   = $this->reader->service_level();
			if ( '' === $service || ! is_array( $level ) ) {
				return '';
			}
			$id = $this->reader->find_id( $level, (string) $record['city_slug'], $service, (string) $record['state'], $extra );
		}

		$url  = $id > 0 ? get_permalink( $id ) : '';
		$url  = is_string( $url ) ? $url : '';
		$skip = in_array( strtolower( (string) $atts['skip_current'] ), array( '1', 'yes', 'true' ), true );
		$url  = Link_Policy::select( (int) $id, (int) $record['post_id'], $url, $skip );
		return '' === $url ? '' : esc_url( $url );
	}

	/**
	 * Sanitized map embed URL.
	 *
	 * @return string
	 */
	public function map_url() {
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}
		$url = Map_Url::sanitize( $this->reader->meta( (int) $record['post_id'], 'map_embed' ) );
		return '' === $url ? '' : esc_url( $url );
	}

	/**
	 * FAQ question.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function faq_question( $atts ) {
		$row = $this->faq_row( $atts );
		return null === $row ? '' : esc_html( $row['question'] );
	}

	/**
	 * FAQ answer.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function faq_answer( $atts ) {
		$row = $this->faq_row( $atts );
		return null === $row ? '' : wp_kses_post( $row['answer'] );
	}

	/**
	 * Visual breadcrumb. The last item is not a link.
	 *
	 * @return string
	 */
	public function breadcrumbs() {
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return '';
		}

		$current = get_permalink( (int) $record['post_id'] );
		$current = is_string( $current ) ? $current : '';
		$items   = $this->schema->breadcrumb_items( $record, $current );
		if ( empty( $items ) ) {
			if ( '' !== (string) $record['service_slug'] ) {
				$this->log( 'Missing parent city page for breadcrumbs on post ' . (int) $record['post_id'] );
			}
			return '';
		}

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'lrps_breadcrumb_items', $items, $record );
			if ( is_array( $filtered ) ) {
				$items = $filtered;
			}
		}

		$prefix = esc_attr( $this->config->prefix() );
		$html   = '<nav class="lrps-breadcrumbs ' . $prefix . '-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'local-rank-programmatic-seo' ) . '"><ol class="lrps-breadcrumbs__list">';
		$last   = count( $items ) - 1;
		foreach ( $items as $index => $item ) {
			$label = esc_html( isset( $item['name'] ) ? (string) $item['name'] : '' );
			if ( '' === $label ) {
				continue;
			}
			if ( $index === $last ) {
				$html .= '<li class="lrps-breadcrumbs__item lrps-breadcrumbs__item--current" aria-current="page"><span>' . $label . '</span></li>';
				continue;
			}
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			if ( '' === $url ) {
				$html .= '<li class="lrps-breadcrumbs__item"><span>' . $label . '</span></li>';
				continue;
			}
			$html .= '<li class="lrps-breadcrumbs__item"><a href="' . esc_url( $url ) . '">' . $label . '</a></li>';
		}
		$html .= '</ol></nav>';
		return $html;
	}

	/**
	 * Problems template.
	 *
	 * @return string
	 */
	public function problems() {
		return $this->templates->problems();
	}

	/**
	 * Gallery template.
	 *
	 * @return string
	 */
	public function gallery() {
		return $this->templates->gallery();
	}

	/**
	 * Section label used by the existing templates.
	 *
	 * Nested shortcodes inside the text attribute are intentionally not supported.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function section_label( $atts ) {
		$atts = shortcode_atts(
			array(
				'tag'   => 'h2',
				'text'  => '',
				'class' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'section_label'
		);

		$allowed = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'div' );
		$tag     = strtolower( (string) $atts['tag'] );
		if ( ! in_array( $tag, $allowed, true ) ) {
			$tag = 'h2';
		}

		$text = Token_Expander::expand( (string) $atts['text'], $this->token_vars() );
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		$design = $this->config->get( 'design' );
		$class  = is_array( $design ) && ! empty( $design['section_class'] ) ? (string) $design['section_class'] : '';
		if ( '' === $class ) {
			$class = 'lrps-section-label ' . $this->config->prefix() . '-section-label';
		}
		$extra = $this->class_list( (string) $atts['class'] );
		if ( '' !== $extra ) {
			$class .= ' ' . $extra;
		}

		return '<' . $tag . ' class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</' . $tag . '>';
	}

	/**
	 * Service image used by Dynamic Tags.
	 *
	 * @param string $which hero_image or cover_image.
	 * @return array{id:int,url:string}
	 */
	public function service_image( $which ) {
		$service = $this->service_config();
		if ( null === $service ) {
			return array(
				'id'  => 0,
				'url' => '',
			);
		}
		$relative = isset( $service[ $which ] ) ? (string) $service[ $which ] : '';
		return $this->media->resolve( $relative );
	}

	/**
	 * Alt text for the service cover.
	 *
	 * @return string
	 */
	public function cover_alt() {
		$record = $this->record();
		if ( empty( $record['valid'] ) || '' === (string) $record['service_name'] ) {
			return '';
		}
		$vars = $this->token_vars();
		if ( '' === $vars['city_state'] ) {
			return '';
		}
		return $record['service_name'] . ' in ' . $vars['city_state'];
	}

	/**
	 * Current record, or an invalid one.
	 *
	 * @return array<string,mixed>
	 */
	public function record() {
		return $this->reader->read( $this->store->effective_id() );
	}

	/**
	 * Token map for the current record.
	 *
	 * @return array<string,string>
	 */
	public function token_vars() {
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return array();
		}
		return $this->tokens->for_record( $record );
	}

	/**
	 * Keeps extra CSS classes from a shortcode attribute.
	 *
	 * @param string $value Raw class attribute.
	 * @return string
	 */
	private function class_list( $value ) {
		$classes = array();
		foreach ( preg_split( '/\s+/', trim( $value ) ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}
		return implode( ' ', $classes );
	}

	/**
	 * Prefixed shortcode name.
	 *
	 * @param string $suffix Suffix.
	 * @return string
	 */
	private function name( $suffix ) {
		return $this->config->prefix() . '_' . $suffix;
	}

	/**
	 * Service catalog row for a valid service record.
	 *
	 * @return array<string,mixed>|null
	 */
	private function service_config() {
		$record = $this->record();
		if ( empty( $record['valid'] ) || '' === (string) $record['service_slug'] ) {
			return null;
		}
		return $this->catalog->service( (string) $record['service_slug'] );
	}

	/**
	 * FAQ row from a shortcode index.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return array{question:string,answer:string}|null
	 */
	private function faq_row( $atts ) {
		$atts = shortcode_atts(
			array( 'index' => '1' ),
			is_array( $atts ) ? $atts : array()
		);
		$record = $this->record();
		if ( empty( $record['valid'] ) ) {
			return null;
		}
		return $this->reader->faq_at( (int) $record['post_id'], (int) $atts['index'] );
	}

	/**
	 * Debug log.
	 *
	 * @param string $message Message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[LRPS] ' . $message );
		}
	}
}
