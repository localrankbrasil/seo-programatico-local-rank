<?php
/**
 * Reads one programmatic record and finds its siblings.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Data;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Support\Ids;
use LocalRank\ProgrammaticSeo\Support\Slug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The only place that turns post meta into a location context.
 */
final class Record_Reader {

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
	 * Request cache of find_id results.
	 *
	 * @var array<string,int>
	 */
	private $found = array();

	/**
	 * @param Repository $config  Configuration.
	 * @param Catalog    $catalog Catalog.
	 */
	public function __construct( Repository $config, Catalog $catalog ) {
		$this->config  = $config;
		$this->catalog = $catalog;
	}

	/**
	 * Reads the context of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public function read( $post_id ) {
		$post_id = (int) $post_id;
		$empty   = $this->empty_record( $post_id );
		$post    = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || $post->post_type !== (string) $this->config->get( 'cpt' ) ) {
			return $empty;
		}

		list( $level, $diverged ) = $this->resolve_level( $post_id );
		$empty['diverged']        = $diverged;
		$empty['published']       = ( 'publish' === $post->post_status );
		if ( ! is_array( $level ) ) {
			return $empty;
		}

		$segments    = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		$needs_city  = in_array( 'city', $segments, true );
		$needs_state = in_array( 'state', $segments, true );
		$city_slug   = '';
		$city_name   = '';
		$state       = '';

		if ( $needs_city ) {
			$city_slug = Slug::normalize( $this->meta( $post_id, 'city_slug' ) );
			$city      = $this->catalog->city( $city_slug );
			if ( null === $city ) {
				$empty['level']     = $level;
				$empty['level_key'] = (string) $level['key'];
				$empty['city_slug'] = $city_slug;
				return $empty;
			}

			$state = $this->state_value( $post_id, $city );
			if ( '' === $state ) {
				return $empty;
			}

			$city_name = $this->text( $this->meta( $post_id, 'city_name' ) );
			if ( '' === $city_name ) {
				$city_name = (string) $city['name'];
			}
		} elseif ( $needs_state ) {
			$state = strtoupper( $this->text( $this->meta( $post_id, 'city_state' ) ) );
			if ( '' === $state ) {
				return $empty;
			}
		}

		$service_slug = '';
		$service_name = '';
		if ( in_array( 'service', $segments, true ) ) {
			$service_slug = Slug::normalize( $this->meta( $post_id, 'service_slug' ) );
			$service      = $this->catalog->service( $service_slug );
			if ( null === $service ) {
				$empty['level']        = $level;
				$empty['level_key']    = (string) $level['key'];
				$empty['city_slug']    = $city_slug;
				$empty['city_name']    = $city_name;
				$empty['state']        = $state;
				$empty['service_slug'] = $service_slug;
				return $empty;
			}
			$service_name = isset( $service['display_name'] ) ? (string) $service['display_name'] : '';
			if ( '' === $service_name ) {
				$service_name = $this->text( $this->meta( $post_id, 'service_name' ) );
			}
		}

		$extra  = array();
		$labels = array();
		foreach ( $segments as $segment ) {
			if ( in_array( $segment, array( 'city', 'service', 'state' ), true ) ) {
				continue;
			}
			$dimension = $this->catalog->dimension( $segment );
			if ( ! is_array( $dimension ) ) {
				return $empty;
			}
			$slug = Slug::normalize( $this->meta_named( $post_id, isset( $dimension['field'] ) ? (string) $dimension['field'] : '' ) );
			$item = $this->catalog->item( $segment, $slug );
			if ( ! is_array( $item ) ) {
				$empty['level']     = $level;
				$empty['level_key'] = (string) $level['key'];
				$empty['city_slug'] = $city_slug;
				$empty['extra']     = $extra;
				return $empty;
			}
			$parent_key = isset( $dimension['parent'] ) ? (string) $dimension['parent'] : '';
			if ( '' !== $parent_key && in_array( $parent_key, $segments, true ) ) {
				$parent_slug = $this->parent_slug( $post_id, $parent_key, $city_slug, $service_slug, $state, $extra );
				if ( (string) $item['parent'] !== $parent_slug ) {
					return $empty;
				}
			}
			$extra[ $segment ]  = $slug;
			$labels[ $segment ] = isset( $item['name'] ) ? (string) $item['name'] : $slug;
		}

		$preview = function_exists( 'is_preview' ) && is_preview();
		$valid   = ( $empty['published'] || $preview );
		if ( $diverged && $this->config->get( 'strict_levels' ) ) {
			$valid = false;
		}

		return array(
			'post_id'      => $post_id,
			'level_key'    => (string) $level['key'],
			'level'        => $level,
			'city_slug'    => $city_slug,
			'city_name'    => $city_name,
			'state'        => $state,
			'service_slug' => $service_slug,
			'service_name' => $service_name,
			'extra'        => $extra,
			'labels'       => $labels,
			'diverged'     => $diverged,
			'valid'        => $valid,
			'published'    => $empty['published'],
		);
	}

	/**
	 * Finds exactly one published post for a level and a set of segments.
	 *
	 * @param array<string,mixed> $level   Level definition.
	 * @param string              $city    City slug.
	 * @param string              $service Service slug.
	 * @param string              $state   State code.
	 * @param array<string,string> $extra  Slugs for dimensions other than city, service and state.
	 * @return int
	 */
	public function find_id( array $level, $city, $service = '', $state = '', array $extra = array() ) {
		$city    = Slug::normalize( $city );
		$service = Slug::normalize( $service );
		$state   = strtoupper( trim( (string) $state ) );
		$clean_extra = array();
		foreach ( $extra as $segment => $slug ) {
			$clean_extra[ (string) $segment ] = Slug::normalize( $slug );
		}
		$extra_key = array();
		foreach ( $clean_extra as $segment => $slug ) {
			$extra_key[] = $segment . '=' . $slug;
		}
		$key = (string) $level['key'] . '|' . $city . '|' . $service . '|' . $state . '|' . implode( ',', $extra_key );
		if ( isset( $this->found[ $key ] ) ) {
			return $this->found[ $key ];
		}

		$segments   = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		$needs_city = in_array( 'city', $segments, true );
		if ( $needs_city && ! $this->catalog->is_city( $city ) ) {
			return $this->remember( $key, 0 );
		}
		if ( ! $needs_city && '' !== $city ) {
			return $this->remember( $key, 0 );
		}

		$needs_service = in_array( 'service', $segments, true );
		if ( $needs_service && ! $this->catalog->is_service( $service ) ) {
			return $this->remember( $key, 0 );
		}
		if ( ! $needs_service && '' !== $service ) {
			return $this->remember( $key, 0 );
		}
		if ( in_array( 'state', $segments, true ) && '' === $state ) {
			return $this->remember( $key, 0 );
		}

		$custom_meta = array();
		foreach ( $segments as $segment ) {
			if ( in_array( $segment, array( 'city', 'service', 'state' ), true ) ) {
				continue;
			}
			$slug = isset( $clean_extra[ $segment ] ) ? (string) $clean_extra[ $segment ] : '';
			$item = $this->catalog->item( $segment, $slug );
			if ( ! is_array( $item ) ) {
				return $this->remember( $key, 0 );
			}
			$dimension  = $this->catalog->dimension( $segment );
			$parent_key = is_array( $dimension ) && isset( $dimension['parent'] ) ? (string) $dimension['parent'] : '';
			if ( '' !== $parent_key && in_array( $parent_key, $segments, true ) ) {
				$parent_slug = '';
				if ( 'city' === $parent_key ) {
					$parent_slug = $city;
				} elseif ( 'service' === $parent_key ) {
					$parent_slug = $service;
				} elseif ( isset( $clean_extra[ $parent_key ] ) ) {
					$parent_slug = (string) $clean_extra[ $parent_key ];
				}
				if ( (string) $item['parent'] !== $parent_slug ) {
					return $this->remember( $key, 0 );
				}
			}
			$field = is_array( $dimension ) && isset( $dimension['field'] ) ? (string) $dimension['field'] : '';
			if ( '' === $field ) {
				return $this->remember( $key, 0 );
			}
			$custom_meta[] = array(
				'key'   => $field,
				'value' => $slug,
			);
		}

		$meta_query = array( 'relation' => 'AND' );
		$meta_query[] = array(
			'key'   => $this->config->field( 'page_level' ),
			'value' => (string) $level['acf_value'],
		);
		if ( $needs_city ) {
			$meta_query[] = array(
				'key'   => $this->config->field( 'city_slug' ),
				'value' => $city,
			);
		}
		if ( '' !== $state ) {
			$meta_query[] = array(
				'key'   => $this->config->field( 'city_state' ),
				'value' => $state,
			);
		}
		if ( $needs_service ) {
			$meta_query[] = array(
				'key'   => $this->config->field( 'service_slug' ),
				'value' => $service,
			);
		}
		foreach ( $custom_meta as $clause ) {
			$meta_query[] = $clause;
		}

		$ids = get_posts(
			array(
				'post_type'              => (string) $this->config->get( 'cpt' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 2,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => (string) $this->config->get( 'taxonomy' ),
						'field'    => 'slug',
						'terms'    => (string) $level['taxonomy_slug'],
					),
				),
				'meta_query'             => $meta_query,
			)
		);

		$resolved = Ids::only_one( is_array( $ids ) ? $ids : array() );
		if ( 0 === $resolved && is_array( $ids ) && count( $ids ) > 1 ) {
			$this->log( 'Duplicate location page for ' . $key );
		}

		return $this->remember( $key, $resolved );
	}

	/**
	 * Finds a level definition by key.
	 *
	 * @param string $key Level key.
	 * @return array<string,mixed>|null
	 */
	public function level( $key ) {
		$levels = $this->config->get( 'levels' );
		if ( ! is_array( $levels ) ) {
			return null;
		}
		foreach ( $levels as $level ) {
			if ( is_array( $level ) && isset( $level['key'] ) && (string) $level['key'] === (string) $key ) {
				return $level;
			}
		}
		return null;
	}

	/**
	 * Level that represents the city page for breadcrumbs and target="city".
	 *
	 * @return array<string,mixed>|null
	 */
	public function city_level() {
		$exact = $this->level( 'city' );
		if ( is_array( $exact ) ) {
			return $exact;
		}

		$levels = $this->config->get( 'levels' );
		if ( ! is_array( $levels ) ) {
			return null;
		}
		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) || empty( $level['segments'] ) || ! is_array( $level['segments'] ) ) {
				continue;
			}
			if ( in_array( 'city', $level['segments'], true ) && ! in_array( 'service', $level['segments'], true ) ) {
				return $level;
			}
		}
		return null;
	}

	/**
	 * Level that represents a service page.
	 *
	 * @return array<string,mixed>|null
	 */
	public function service_level() {
		$exact = $this->level( 'service' );
		if ( is_array( $exact ) ) {
			return $exact;
		}

		$levels = $this->config->get( 'levels' );
		if ( ! is_array( $levels ) ) {
			return null;
		}
		foreach ( $levels as $level ) {
			if ( is_array( $level ) && isset( $level['segments'] ) && is_array( $level['segments'] ) && in_array( 'service', $level['segments'], true ) ) {
				return $level;
			}
		}
		return null;
	}

	/**
	 * FAQ rows for a post. Indexing for shortcodes is 1-based at the edges.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{question:string,answer:string}>
	 */
	public function faqs( $post_id ) {
		$post_id = (int) $post_id;
		$field   = $this->config->field( 'faqs' );
		$q_key   = $this->config->field( 'faq_question' );
		$a_key   = $this->config->field( 'faq_answer' );
		$rows    = array();

		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $field, $post_id );
			if ( is_array( $value ) ) {
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$rows[] = array(
						'question' => isset( $row[ $q_key ] ) ? (string) $row[ $q_key ] : '',
						'answer'   => isset( $row[ $a_key ] ) ? (string) $row[ $a_key ] : '',
					);
				}
				return $rows;
			}
		}

		$count = (int) get_post_meta( $post_id, $field, true );
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = array(
				'question' => (string) get_post_meta( $post_id, $field . '_' . $i . '_' . $q_key, true ),
				'answer'   => (string) get_post_meta( $post_id, $field . '_' . $i . '_' . $a_key, true ),
			);
		}

		return $rows;
	}

	/**
	 * One FAQ row by 1-based index.
	 *
	 * @param int $post_id Post ID.
	 * @param int $index   1-based index.
	 * @return array{question:string,answer:string}|null
	 */
	public function faq_at( $post_id, $index ) {
		$index = (int) $index;
		if ( $index < 1 ) {
			return null;
		}
		$rows = $this->faqs( $post_id );
		if ( ! isset( $rows[ $index - 1 ] ) ) {
			return null;
		}
		$row = $rows[ $index - 1 ];
		if ( '' === trim( $row['question'] ) && '' === trim( wp_strip_all_tags( $row['answer'] ) ) ) {
			return null;
		}
		return $row;
	}

	/**
	 * Raw meta value for a logical field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $logical Logical field name.
	 * @return string
	 */
	public function meta( $post_id, $logical ) {
		$name = $this->config->field( $logical );
		if ( '' === $name ) {
			return '';
		}

		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $name, (int) $post_id );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
			if ( is_numeric( $value ) ) {
				return (string) $value;
			}
		}

		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$value = get_post_meta( (int) $post_id, $name, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Raw meta by the stored ACF field name.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name.
	 * @return string
	 */
	private function meta_named( $post_id, $name ) {
		if ( '' === $name ) {
			return '';
		}
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $name, (int) $post_id );
			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
			if ( is_numeric( $value ) ) {
				return (string) $value;
			}
		}
		if ( ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$value = get_post_meta( (int) $post_id, $name, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Slug of a parent segment already known on this post.
	 *
	 * @param int                  $post_id      Post ID.
	 * @param string               $parent_key   Parent segment.
	 * @param string               $city_slug    City slug.
	 * @param string               $service_slug Service slug.
	 * @param string               $state        State code.
	 * @param array<string,string> $extra        Dimension slugs already read.
	 * @return string
	 */
	private function parent_slug( $post_id, $parent_key, $city_slug, $service_slug, $state, array $extra ) {
		unset( $state );
		if ( isset( $extra[ $parent_key ] ) ) {
			return (string) $extra[ $parent_key ];
		}
		if ( 'city' === $parent_key ) {
			return $city_slug;
		}
		if ( 'service' === $parent_key ) {
			return $service_slug;
		}
		$dimension = $this->catalog->dimension( $parent_key );
		if ( ! is_array( $dimension ) ) {
			return '';
		}
		return Slug::normalize( $this->meta_named( $post_id, isset( $dimension['field'] ) ? (string) $dimension['field'] : '' ) );
	}

	/**
	 * Resolves taxonomy and the ACF select.
	 *
	 * @param int $post_id Post ID.
	 * @return array{0:array<string,mixed>|null,1:bool}
	 */
	private function resolve_level( $post_id ) {
		$levels    = $this->config->get( 'levels' );
		$levels    = is_array( $levels ) ? $levels : array();
		$taxonomy  = (string) $this->config->get( 'taxonomy' );
		$tax_level = null;

		if ( function_exists( 'wp_get_post_terms' ) ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
			$hits  = array();
			if ( is_array( $terms ) ) {
				foreach ( $levels as $level ) {
					if ( is_array( $level ) && in_array( (string) $level['taxonomy_slug'], $terms, true ) ) {
						$hits[] = $level;
					}
				}
			}
			if ( 1 === count( $hits ) ) {
				$tax_level = $hits[0];
			} elseif ( count( $hits ) > 1 ) {
				$this->log( 'Multiple SEO level terms on post ' . $post_id );
			}
		}

		$acf_value = (string) $this->meta( $post_id, 'page_level' );
		$acf_level = null;
		foreach ( $levels as $level ) {
			if ( is_array( $level ) && (string) $level['acf_value'] === $acf_value ) {
				$acf_level = $level;
				break;
			}
		}

		$diverged = ( is_array( $tax_level ) && is_array( $acf_level ) && (string) $tax_level['key'] !== (string) $acf_level['key'] );
		if ( $diverged ) {
			$this->log( 'Level divergence on post ' . $post_id );
			if ( $this->config->get( 'strict_levels' ) ) {
				return array( null, true );
			}
		}

		$chosen = is_array( $tax_level ) ? $tax_level : $acf_level;
		return array( $chosen, $diverged );
	}

	/**
	 * Uses the field state when it agrees with the catalog.
	 *
	 * @param int                      $post_id Post ID.
	 * @param array<string,string>     $city    Catalog city.
	 * @return string
	 */
	private function state_value( $post_id, array $city ) {
		$catalog_state = strtoupper( (string) $city['state'] );
		$field_state   = strtoupper( $this->text( $this->meta( $post_id, 'city_state' ) ) );
		if ( '' === $field_state ) {
			return $catalog_state;
		}
		if ( $field_state !== $catalog_state ) {
			$this->log( 'City state does not match the catalog on post ' . $post_id );
			return '';
		}
		return $field_state;
	}

	/**
	 * Single-line text.
	 *
	 * @param string $value Raw text.
	 * @return string
	 */
	private function text( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
		return $value;
	}

	/**
	 * Empty record.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function empty_record( $post_id ) {
		return array(
			'post_id'      => $post_id,
			'level_key'    => '',
			'level'        => null,
			'city_slug'    => '',
			'city_name'    => '',
			'state'        => '',
			'service_slug' => '',
			'service_name' => '',
			'extra'        => array(),
			'labels'       => array(),
			'diverged'     => false,
			'valid'        => false,
			'published'    => false,
		);
	}

	/**
	 * Stores a lookup.
	 *
	 * @param string $key Cache key.
	 * @param int    $id  Post ID.
	 * @return int
	 */
	private function remember( $key, $id ) {
		$this->found[ $key ] = (int) $id;
		return $this->found[ $key ];
	}

	/**
	 * Logs a diagnostic when WP_DEBUG is on.
	 *
	 * @param string $message Message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[LRPS] ' . $message );
		}
	}
}
