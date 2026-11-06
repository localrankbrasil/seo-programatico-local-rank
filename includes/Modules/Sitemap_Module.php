<?php
/**
 * HTML sitemap of pages, posts and location pages.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Data\Catalog;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders [prefix_html_sitemap] from published content.
 */
final class Sitemap_Module {

	const TTL = 43200;

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
	 * @param Repository    $config  Configuration.
	 * @param Catalog       $catalog Catalog.
	 * @param Record_Reader $reader  Reader.
	 */
	public function __construct( Repository $config, Catalog $catalog, Record_Reader $reader ) {
		$this->config  = $config;
		$this->catalog = $catalog;
		$this->reader  = $reader;
	}

	/**
	 * Registers the shortcode and cache busting.
	 */
	public function register() {
		add_shortcode( $this->config->prefix() . '_html_sitemap', array( $this, 'shortcode' ) );
		add_action( 'save_post', array( $this, 'bust' ) );
		add_action( 'trashed_post', array( $this, 'bust' ) );
		add_action( 'untrashed_post', array( $this, 'bust' ) );
		add_action( 'deleted_post', array( $this, 'bust' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts  = shortcode_atts(
			array( 'group' => 'state' ),
			is_array( $atts ) ? $atts : array(),
			$this->config->prefix() . '_html_sitemap'
		);
		$group = ( 'none' === $atts['group'] ) ? 'none' : 'state';
		$key   = $this->cache_key( $group );
		$html  = get_transient( $key );
		if ( is_string( $html ) && '' !== $html ) {
			return $html;
		}

		$html = $this->render( $group );
		set_transient( $key, $html, self::TTL );
		return $html;
	}

	/**
	 * Drops the cache when public content changes.
	 *
	 * @param int $post_id Post ID.
	 */
	public function bust( $post_id ) {
		$type = get_post_type( $post_id );
		$cpt  = (string) $this->config->get( 'cpt' );
		if ( ! in_array( $type, array( 'page', 'post', $cpt ), true ) ) {
			return;
		}
		delete_transient( $this->cache_key( 'state' ) );
		delete_transient( $this->cache_key( 'none' ) );
	}

	/**
	 * Builds the sitemap HTML.
	 *
	 * @param string $group state|none.
	 * @return string
	 */
	private function render( $group ) {
		$prefix = $this->config->prefix();
		$class  = $prefix . '-html-sitemap';
		$pages  = $this->static_pages();
		$posts  = $this->blog_posts();
		$cities = $this->locations();

		$html  = $this->css( $class );
		$html .= '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'HTML sitemap', 'local-rank-programmatic-seo' ) . '">';

		if ( ! empty( $pages ) ) {
			$html .= '<section class="' . esc_attr( $class ) . '__section"><h3>' . esc_html__( 'Pages', 'local-rank-programmatic-seo' ) . '</h3>';
			$html .= $this->link_list( $pages );
			$html .= '</section>';
		}

		if ( ! empty( $posts ) ) {
			$html .= '<section class="' . esc_attr( $class ) . '__section"><h3>' . esc_html__( 'Blog', 'local-rank-programmatic-seo' ) . '</h3>';
			$html .= $this->link_list( $posts );
			$html .= '</section>';
		}

		if ( ! empty( $cities ) ) {
			$html .= '<section class="' . esc_attr( $class ) . '__section"><h3>' . esc_html__( 'Location Pages', 'local-rank-programmatic-seo' ) . '</h3>';
			if ( 'state' === $group ) {
				$by_state = array();
				foreach ( $cities as $city ) {
					$by_state[ $city['state'] ][] = $city;
				}
				foreach ( $this->state_order( array_keys( $by_state ) ) as $code => $label ) {
					if ( empty( $by_state[ $code ] ) ) {
						continue;
					}
					$html .= '<h4>' . esc_html( $label ) . '</h4>';
					$html .= $this->city_list( $by_state[ $code ], $class );
				}
			} else {
				$html .= $this->city_list( $cities, $class );
			}
			$html .= '</section>';
		}

		$html .= '</nav>';
		return $html;
	}

	/**
	 * Published pages, skipping noindex and configured IDs.
	 *
	 * @return array<int,array{label:string,url:string,slug:string}>
	 */
	private function static_pages() {
		$pages = get_pages(
			array(
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
			)
		);
		if ( ! is_array( $pages ) ) {
			return array();
		}

		$items = array();
		foreach ( $pages as $page ) {
			if ( ! $page instanceof \WP_Post || $this->skipped( $page->ID, $page->post_name ) ) {
				continue;
			}
			$url = get_permalink( $page );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$items[] = array(
				'label' => get_the_title( $page ),
				'url'   => $url,
				'slug'  => $page->post_name,
			);
		}

		$settings = $this->settings();
		$rank     = array();
		foreach ( $settings['page_slugs'] as $index => $slug ) {
			$rank[ $slug ] = $index;
		}
		usort(
			$items,
			static function ( $left, $right ) use ( $rank ) {
				$left_rank  = isset( $rank[ $left['slug'] ] ) ? $rank[ $left['slug'] ] : 100;
				$right_rank = isset( $rank[ $right['slug'] ] ) ? $rank[ $right['slug'] ] : 100;
				if ( $left_rank !== $right_rank ) {
					return $left_rank < $right_rank ? -1 : 1;
				}
				return strnatcasecmp( $left['label'], $right['label'] );
			}
		);

		return $items;
	}

	/**
	 * Recent published posts.
	 *
	 * @return array<int,array{label:string,url:string}>
	 */
	private function blog_posts() {
		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
			)
		);
		$items = array();
		if ( ! is_array( $posts ) ) {
			return $items;
		}
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || $this->skipped( $post->ID, $post->post_name ) ) {
				continue;
			}
			$url = get_permalink( $post );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			$items[] = array(
				'label' => get_the_title( $post ),
				'url'   => $url,
			);
		}
		return $items;
	}

	/**
	 * City rows with their service children.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function locations() {
		$cpt   = (string) $this->config->get( 'cpt' );
		$posts = get_posts(
			array(
				'post_type'              => $cpt,
				'post_status'            => 'publish',
				'posts_per_page'         => 2000,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
			)
		);
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$order = array();
		$index = 0;
		foreach ( $this->catalog->indexes()['service'] as $slug ) {
			$order[ $slug ] = $index;
			++$index;
		}

		$trade = trim( (string) $this->config->get( 'trade_label' ) );
		$tree  = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || $this->skipped( $post->ID, $post->post_name ) ) {
				continue;
			}
			$record = $this->reader->read( $post->ID );
			if ( empty( $record['valid'] ) ) {
				continue;
			}

			$key   = (string) $record['state'] . '|' . (string) $record['city_slug'];
			$place = (string) $record['city_name'] . ', ' . (string) $record['state'];
			if ( ! isset( $tree[ $key ] ) ) {
				$tree[ $key ] = array(
					'label'    => ( '' !== $trade ? $trade . ' in ' : '' ) . $place,
					'state'    => (string) $record['state'],
					'sort'     => $place,
					'url'      => '',
					'services' => array(),
				);
			}

			$url   = get_permalink( $post );
			$url   = is_string( $url ) ? $url : '';
			$title = trim( get_the_title( $post ) );

			if ( 'city' === (string) $record['level_key'] ) {
				$tree[ $key ]['url'] = $url;
				if ( '' !== $title ) {
					$tree[ $key ]['label'] = $title;
				}
				continue;
			}

			if ( 'service' !== (string) $record['level_key'] || ! isset( $order[ (string) $record['service_slug'] ] ) ) {
				continue;
			}

			$fallback = (string) $record['service_name'];
			if ( '' !== $trade ) {
				$fallback .= ' ' . $trade;
			}
			$fallback .= ' in ' . $place;
			$tree[ $key ]['services'][ (string) $record['service_slug'] ] = array(
				'label' => '' !== $title ? $title : $fallback,
				'url'   => $url,
				'order' => $order[ (string) $record['service_slug'] ],
			);
		}

		foreach ( $tree as $key => $row ) {
			if ( '' === (string) $row['url'] ) {
				unset( $tree[ $key ] );
				continue;
			}
			$services = $row['services'];
			uasort(
				$services,
				static function ( $left, $right ) {
					if ( $left['order'] === $right['order'] ) {
						return 0;
					}
					return $left['order'] < $right['order'] ? -1 : 1;
				}
			);
			$tree[ $key ]['services'] = $services;
		}

		$rank = array();
		$position = 0;
		foreach ( $this->state_order( array() ) as $code => $label ) {
			unset( $label );
			$rank[ $code ] = $position;
			++$position;
		}
		uasort(
			$tree,
			static function ( $left, $right ) use ( $rank ) {
				$left_rank  = isset( $rank[ $left['state'] ] ) ? $rank[ $left['state'] ] : 99;
				$right_rank = isset( $rank[ $right['state'] ] ) ? $rank[ $right['state'] ] : 99;
				if ( $left_rank !== $right_rank ) {
					return $left_rank < $right_rank ? -1 : 1;
				}
				return strnatcasecmp( $left['sort'], $right['sort'] );
			}
		);

		return array_values( $tree );
	}

	/**
	 * State code to label, in configured order, then any extra states.
	 *
	 * @param string[] $present States that actually have cities.
	 * @return array<string,string>
	 */
	private function state_order( array $present ) {
		$settings = $this->settings();
		$order    = $settings['states'];
		foreach ( $present as $code ) {
			$code = strtoupper( (string) $code );
			if ( '' !== $code && ! isset( $order[ $code ] ) ) {
				$order[ $code ] = $code;
			}
		}
		return $order;
	}

	/**
	 * Whether a post is left out of the sitemap.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slug    Post slug.
	 * @return bool
	 */
	private function skipped( $post_id, $slug ) {
		$settings = $this->settings();
		if ( in_array( (int) $post_id, $settings['skip_ids'], true ) ) {
			return true;
		}
		if ( preg_match( '/^(thank-you|obrigado|thankyou)/', (string) $slug ) ) {
			return true;
		}
		return '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
	}

	/**
	 * Sitemap settings with safe defaults.
	 *
	 * @return array{skip_ids:int[],page_slugs:string[],states:array<string,string>,accent:string,text:string,font:string}
	 */
	private function settings() {
		$raw = $this->config->get( 'sitemap' );
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'skip_ids'   => isset( $raw['skip_ids'] ) && is_array( $raw['skip_ids'] ) ? array_map( 'intval', $raw['skip_ids'] ) : array(),
			'page_slugs' => isset( $raw['page_slugs'] ) && is_array( $raw['page_slugs'] ) ? $raw['page_slugs'] : array(),
			'states'     => isset( $raw['states'] ) && is_array( $raw['states'] ) ? $raw['states'] : array(),
			'accent'     => isset( $raw['accent'] ) ? (string) $raw['accent'] : '#bf9a33',
			'text'       => isset( $raw['text'] ) ? (string) $raw['text'] : '#1a1a1e',
			'font'       => isset( $raw['font'] ) ? (string) $raw['font'] : '',
		);
	}

	/**
	 * @param array<int,array{label:string,url:string}> $items Links.
	 * @return string
	 */
	private function link_list( array $items ) {
		$html = '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param array<int,array<string,mixed>> $cities City rows.
	 * @param string                         $class  Root class.
	 * @return string
	 */
	private function city_list( array $cities, $class ) {
		$html = '<ul class="' . esc_attr( $class ) . '__cities">';
		foreach ( $cities as $city ) {
			$html .= '<li class="' . esc_attr( $class ) . '__city">';
			$html .= '<a href="' . esc_url( (string) $city['url'] ) . '">' . esc_html( (string) $city['label'] ) . '</a>';
			if ( ! empty( $city['services'] ) && is_array( $city['services'] ) ) {
				$html .= '<ul class="' . esc_attr( $class ) . '__services">';
				foreach ( $city['services'] as $service ) {
					$html .= '<li class="' . esc_attr( $class ) . '__service"><a href="' . esc_url( (string) $service['url'] ) . '">' . esc_html( (string) $service['label'] ) . '</a></li>';
				}
				$html .= '</ul>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Inline presentation. Colors come from the project so another client can restyle it.
	 *
	 * @param string $class Root class.
	 * @return string
	 */
	private function css( $class ) {
		$settings = $this->settings();
		$accent   = $settings['accent'];
		$text     = $settings['text'];
		$font     = '' !== $settings['font'] ? $settings['font'] : 'inherit';
		$selector = '.' . $class;

		return '<style id="' . esc_attr( $class ) . '-css">'
			. $selector . '{font-family:' . $font . ';color:' . $text . '}'
			. $selector . ' h3{margin:40px 0 25px;font-size:24px;font-weight:700;line-height:1;color:' . $text . '}'
			. $selector . '>section:first-child>h3{margin-top:0}'
			. $selector . ' h4{margin:30px 0 15px;font-size:18px;font-weight:700;line-height:1.25;color:' . $accent . '}'
			. $selector . ' ul{margin:20px 0 30px;padding-left:0;list-style:none}'
			. $selector . ' li{position:relative;display:flex;flex-wrap:wrap;align-items:flex-start;margin:0 0 6px;font-size:16px;line-height:1.5;letter-spacing:.5px}'
			. $selector . ' li::before{content:"";display:block;flex:0 0 18px;width:18px;height:2px;margin:11px 10px 0 0;background:' . $accent . ';border-radius:1px}'
			. $selector . ' li>a{flex:1 1 calc(100% - 28px);color:inherit;text-decoration:underline}'
			. $selector . ' li>a:hover,' . $selector . ' li>a:focus{color:' . $accent . '}'
			. $selector . '__cities>li{margin:0 0 22px}'
			. $selector . '__city>ul' . $selector . '__services{flex:0 0 100%;width:100%;margin:0;padding-left:0}'
			. $selector . '__service{margin:0 0 6px 28px}'
			. '</style>';
	}

	/**
	 * Cache key for one grouping mode.
	 *
	 * @param string $group state|none.
	 * @return string
	 */
	private function cache_key( $group ) {
		$settings = $this->settings();
		$payload  = $this->config->prefix() . '|' . $group . '|' . implode( ',', $settings['skip_ids'] ) . '|' . (string) $this->config->get( 'trade_label' );
		return 'lrps_sitemap_' . md5( $payload );
	}
}
