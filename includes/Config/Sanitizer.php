<?php
/**
 * Normalizes project configuration from the admin or an import file.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Config;

use LocalRank\ProgrammaticSeo\Support\Company_Builder;
use LocalRank\ProgrammaticSeo\Support\Slug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces a config array the rest of the plugin can trust.
 */
final class Sanitizer {

	/**
	 * Cleans an input array.
	 *
	 * @param array<string,mixed> $input   Raw input.
	 * @param array<string,mixed> $defaults Defaults used for missing stored keys.
	 * @param string              $source  form|stored. Form checkboxes that are absent become false.
	 * @return array{config:array<string,mixed>,errors:string[]}
	 */
	public static function clean( array $input, array $defaults, $source = 'stored' ) {
		$errors = array();
		$config = $defaults;

		$config['prefix'] = self::prefix( isset( $input['prefix'] ) ? $input['prefix'] : $defaults['prefix'], $errors );
		$config['cpt']    = self::key( isset( $input['cpt'] ) ? $input['cpt'] : $defaults['cpt'], 'location_page' );
		$config['taxonomy'] = self::key( isset( $input['taxonomy'] ) ? $input['taxonomy'] : $defaults['taxonomy'], 'seo_level' );
		$config['trade_label'] = self::plain( isset( $input['trade_label'] ) ? $input['trade_label'] : '', 80 );

		$config['strict_levels']   = self::boolean( isset( $input['strict_levels'] ) ? $input['strict_levels'] : null, (bool) $defaults['strict_levels'], 'form' === $source );
		$config['skip_self_links'] = self::boolean( isset( $input['skip_self_links'] ) ? $input['skip_self_links'] : null, (bool) $defaults['skip_self_links'], 'form' === $source );

		$config['reserved_slugs'] = self::reserved( isset( $input['reserved_slugs'] ) ? $input['reserved_slugs'] : $defaults['reserved_slugs'] );
		$config['fields']         = self::fields( isset( $input['fields'] ) ? $input['fields'] : array(), $config['prefix'], $defaults['fields'] );

		$dimensions = self::dimensions( $input, $config['prefix'], $errors );
		if ( is_array( $dimensions ) ) {
			$config['dimensions'] = $dimensions;
		}

		$dimension_rows = isset( $config['dimensions'] ) && is_array( $config['dimensions'] ) ? $config['dimensions'] : array();
		$levels         = self::levels( $input, $errors, self::dimension_keys( $dimension_rows ) );
		if ( empty( $errors ) || ! empty( $levels ) ) {
			$config['levels'] = ! empty( $levels ) ? $levels : $defaults['levels'];
		}

		$cities = self::cities( $input, $config['reserved_slugs'], $errors );
		if ( is_array( $cities ) ) {
			$config['cities'] = $cities;
		}

		$services = self::services( $input, $errors );
		if ( is_array( $services ) ) {
			$config['services'] = $services;
		}

		$config['breadcrumbs'] = self::breadcrumbs( isset( $input['breadcrumbs'] ) ? $input['breadcrumbs'] : array(), $defaults['breadcrumbs'] );
		$config['schema']      = self::schema( isset( $input['schema'] ) ? $input['schema'] : array(), $defaults['schema'], 'form' === $source );
		$config['company']     = self::company( isset( $input['company'] ) ? $input['company'] : array(), $defaults['company'], 'form' === $source );
		$config['modules']     = self::modules( isset( $input['modules'] ) ? $input['modules'] : array(), $defaults['modules'], 'form' === $source );
		$config['templates']   = self::templates( isset( $input['templates'] ) ? $input['templates'] : array(), $config['levels'], $defaults['templates'], 'form' === $source );
		if ( isset( $input['design'] ) && is_array( $input['design'] ) ) {
			$config['design'] = self::design( $input['design'], isset( $defaults['design'] ) && is_array( $defaults['design'] ) ? $defaults['design'] : array() );
		}
		if ( isset( $input['sitemap'] ) && is_array( $input['sitemap'] ) ) {
			$config['sitemap'] = self::sitemap( $input['sitemap'], isset( $defaults['sitemap'] ) && is_array( $defaults['sitemap'] ) ? $defaults['sitemap'] : array() );
		}
		$config['map']         = array(
			'class' => self::css_class( isset( $input['map']['class'] ) ? $input['map']['class'] : ( isset( $defaults['map']['class'] ) ? $defaults['map']['class'] : '' ) ),
		);

		if ( self::duplicate_level_keys( $config['levels'] ) ) {
			$errors[] = 'Cada nível precisa de uma chave exclusiva.';
		}

		return array(
			'config' => $config,
			'errors' => array_values( array_unique( $errors ) ),
		);
	}

	/**
	 * Parses a city CSV into rows.
	 *
	 * @param string $csv CSV text.
	 * @return array{rows:array<int,array<string,string>>,errors:string[]}
	 */
	public static function parse_city_csv( $csv ) {
		$csv = trim( (string) $csv );
		if ( '' === $csv ) {
			return array(
				'rows'   => array(),
				'errors' => array(),
			);
		}

		$lines  = preg_split( '/\r\n|\n|\r/', $csv );
		$rows   = array();
		$errors = array();
		$start  = 0;
		if ( isset( $lines[0] ) && false !== stripos( $lines[0], 'slug' ) && false !== stripos( $lines[0], 'name' ) ) {
			$start = 1;
		}

		$count = count( $lines );
		for ( $i = $start; $i < $count; $i++ ) {
			if ( '' === trim( $lines[ $i ] ) ) {
				continue;
			}
			$cols = str_getcsv( $lines[ $i ] );
			if ( count( $cols ) < 3 ) {
				$errors[] = 'Linha ' . ( $i + 1 ) . ' das cidades precisa de slug, nome e estado.';
				continue;
			}
			$rows[] = array(
				'slug'  => (string) $cols[0],
				'name'  => (string) $cols[1],
				'state' => (string) $cols[2],
			);
		}

		return array(
			'rows'   => $rows,
			'errors' => $errors,
		);
	}

	/**
	 * Renders cities as CSV.
	 *
	 * @param array<int,array<string,string>> $cities Cities.
	 * @return string
	 */
	public static function cities_to_csv( array $cities ) {
		$lines = array( 'slug,name,state' );
		foreach ( $cities as $city ) {
			$lines[] = self::csv_cell( $city['slug'] ) . ',' . self::csv_cell( $city['name'] ) . ',' . self::csv_cell( $city['state'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Validates the project prefix.
	 *
	 * @param mixed    $value  Raw prefix.
	 * @param string[] $errors Error list, appended by reference.
	 * @return string
	 */
	private static function prefix( $value, array &$errors ) {
		$prefix = strtolower( trim( (string) $value ) );
		$prefix = preg_replace( '/[^a-z0-9_]/', '', $prefix );
		if ( ! is_string( $prefix ) || ! preg_match( '/^[a-z][a-z0-9_]{1,20}$/', $prefix ) ) {
			$errors[] = 'O prefixo precisa ter de 2 a 21 caracteres e começar com letra.';
			return 'lrps';
		}
		return $prefix;
	}

	/**
	 * Sanitizes a WordPress key.
	 *
	 * @param mixed  $value   Raw value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function key( $value, $fallback ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9_]/', '', (string) $value );
		if ( ! is_string( $value ) || '' === $value ) {
			return $fallback;
		}
		return substr( $value, 0, 40 );
	}

	/**
	 * Reads a boolean.
	 *
	 * @param mixed $value    Raw value.
	 * @param bool  $default  Default when the value was omitted on a stored config.
	 * @param bool  $absent_is_false Whether a missing form field means false.
	 * @return bool
	 */
	private static function boolean( $value, $default, $absent_is_false ) {
		if ( null === $value ) {
			return $absent_is_false ? false : $default;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Cleans reserved slugs from a textarea or a list.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private static function reserved( $value ) {
		if ( is_string( $value ) ) {
			$parts = preg_split( '/[\s,]+/', $value );
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			$parts = array();
		}

		$slugs = array();
		foreach ( $parts as $part ) {
			$slug = Slug::normalize( $part );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}
		$slugs = array_values( array_unique( $slugs ) );
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Cleans ACF field names.
	 *
	 * @param mixed                $input    Raw fields.
	 * @param string               $prefix   Prefix.
	 * @param array<string,string> $defaults Default field map.
	 * @return array<string,string>
	 */
	private static function fields( $input, $prefix, array $defaults ) {
		$input  = is_array( $input ) ? $input : array();
		$fields = array();
		foreach ( $defaults as $logical => $fallback ) {
			$raw = isset( $input[ $logical ] ) ? (string) $input[ $logical ] : (string) $fallback;
			$name = strtolower( trim( $raw ) );
			$name = preg_replace( '/[^a-z0-9_]/', '', (string) $name );
			if ( ! is_string( $name ) || '' === $name ) {
				$name = str_replace( 'lrps_', $prefix . '_', (string) $fallback );
			}
			$fields[ $logical ] = substr( $name, 0, 80 );
		}
		return $fields;
	}

	/**
	 * Cleans levels from JSON text or an array.
	 *
	 * @param array<string,mixed> $input           Raw input.
	 * @param string[]            $errors          Errors.
	 * @param string[]            $dimension_keys  Extra catalog keys.
	 * @return array<int,array<string,mixed>>
	 */
	private static function levels( array $input, array &$errors, array $dimension_keys = array() ) {
		$raw = null;
		if ( isset( $input['levels_json'] ) && is_string( $input['levels_json'] ) && '' !== trim( $input['levels_json'] ) ) {
			$decoded = json_decode( $input['levels_json'], true );
			if ( ! is_array( $decoded ) ) {
				$errors[] = 'O JSON dos níveis é inválido.';
				return array();
			}
			$raw = $decoded;
		} elseif ( isset( $input['levels'] ) && is_array( $input['levels'] ) ) {
			$raw = $input['levels'];
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$levels   = array();
		$allowed  = array_merge( array( 'city', 'service', 'state' ), $dimension_keys );
		$position = 0;
		foreach ( $raw as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			++$position;
			$key = Slug::normalize( isset( $level['key'] ) ? $level['key'] : '' );
			$key = str_replace( '-', '_', $key );
			if ( '' === $key ) {
				$errors[] = 'O nível ' . $position . ' está sem chave.';
				continue;
			}

			$segments = array();
			$source_segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
			foreach ( $source_segments as $segment ) {
				$segment = str_replace( '-', '_', Slug::normalize( $segment ) );
				if ( '' === $segment || in_array( $segment, $segments, true ) ) {
					continue;
				}
				if ( ! in_array( $segment, $allowed, true ) ) {
					$errors[] = 'O segmento "' . $segment . '" do nível ' . $key . ' precisa ser city, service, state ou uma dimensão deste projeto.';
					continue;
				}
				$segments[] = $segment;
			}
			if ( empty( $segments ) ) {
				$errors[] = 'O nível ' . $key . ' precisa de ao menos um segmento.';
				continue;
			}

			$taxonomy = Slug::normalize( isset( $level['taxonomy_slug'] ) ? $level['taxonomy_slug'] : $key );
			$acf      = strtolower( trim( (string) ( isset( $level['acf_value'] ) ? $level['acf_value'] : '' ) ) );
			$acf      = preg_replace( '/[^a-z0-9_]/', '', (string) $acf );
			$schema   = strtolower( trim( (string) ( isset( $level['schema_shortcode'] ) ? $level['schema_shortcode'] : '' ) ) );
			$schema   = preg_replace( '/[^a-z0-9_]/', '', (string) $schema );

			$levels[] = array(
				'key'              => substr( $key, 0, 40 ),
				'label'            => self::plain( isset( $level['label'] ) ? $level['label'] : $key, 80 ),
				'taxonomy_slug'    => '' !== $taxonomy ? $taxonomy : $key,
				'acf_value'        => is_string( $acf ) && '' !== $acf ? $acf : $key,
				'segments'         => $segments,
				'prefix'           => self::level_prefix( isset( $level['prefix'] ) ? $level['prefix'] : array() ),
				'trail'            => self::trail( isset( $level['trail'] ) ? $level['trail'] : array(), $errors, $key ),
				'schema_shortcode' => is_string( $schema ) && '' !== $schema ? substr( $schema, 0, 40 ) : 'schema_' . $key,
			);
		}

		return $levels;
	}

	/**
	 * Extra catalogs. Null means the input did not include them.
	 *
	 * @param array<string,mixed> $input  Raw input.
	 * @param string              $prefix Project prefix.
	 * @param string[]            $errors Errors.
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function dimensions( array $input, $prefix, array &$errors ) {
		$raw = null;
		if ( isset( $input['dimensions_json'] ) && is_string( $input['dimensions_json'] ) ) {
			$trimmed = trim( $input['dimensions_json'] );
			if ( '' === $trimmed ) {
				return array();
			}
			$decoded = json_decode( $trimmed, true );
			if ( ! is_array( $decoded ) ) {
				$errors[] = 'O JSON das dimensões é inválido.';
				return null;
			}
			$raw = $decoded;
		} elseif ( isset( $input['dimensions'] ) && is_array( $input['dimensions'] ) ) {
			$raw = $input['dimensions'];
		}

		if ( ! is_array( $raw ) ) {
			return null;
		}

		$reserved = array( 'city', 'service', 'state' );
		$dimensions = array();
		$seen       = array();
		foreach ( $raw as $dimension ) {
			if ( ! is_array( $dimension ) ) {
				continue;
			}
			$key = str_replace( '-', '_', Slug::normalize( isset( $dimension['key'] ) ? $dimension['key'] : '' ) );
			if ( '' === $key ) {
				$errors[] = 'Uma dimensão está sem chave.';
				continue;
			}
			if ( in_array( $key, $reserved, true ) ) {
				$errors[] = 'A dimensão "' . $key . '" usa um nome reservado. Cidade, serviço e estado já têm catálogo próprio.';
				continue;
			}
			if ( isset( $seen[ $key ] ) ) {
				$errors[] = 'A dimensão "' . $key . '" está duplicada.';
				continue;
			}
			$seen[ $key ] = true;

			$field = strtolower( trim( (string) ( isset( $dimension['field'] ) ? $dimension['field'] : '' ) ) );
			$field = preg_replace( '/[^a-z0-9_]/', '', (string) $field );
			if ( ! is_string( $field ) || '' === $field ) {
				$field = $prefix . '_' . $key . '_slug';
			}

			$parent = str_replace( '-', '_', Slug::normalize( isset( $dimension['parent'] ) ? $dimension['parent'] : '' ) );
			if ( $parent === $key ) {
				$errors[] = 'A dimensão "' . $key . '" não pode ser pai dela mesma.';
				$parent  = '';
			}

			$items = array();
			$slugs = array();
			$source_items = isset( $dimension['items'] ) && is_array( $dimension['items'] ) ? $dimension['items'] : array();
			foreach ( $source_items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$slug = Slug::normalize( isset( $item['slug'] ) ? $item['slug'] : '' );
				$name = self::plain( isset( $item['name'] ) ? $item['name'] : '', 120 );
				$item_parent = Slug::normalize( isset( $item['parent'] ) ? $item['parent'] : '' );
				if ( '' === $slug || '' === $name ) {
					continue;
				}
				if ( isset( $slugs[ $slug ] ) ) {
					$errors[] = 'O item "' . $slug . '" está duplicado na dimensão ' . $key . '.';
					continue;
				}
				$slugs[ $slug ] = true;
				$items[] = array(
					'slug'   => $slug,
					'name'   => $name,
					'parent' => $item_parent,
				);
				if ( count( $items ) >= 500 ) {
					break;
				}
			}

			$dimensions[] = array(
				'key'    => substr( $key, 0, 40 ),
				'label'  => self::plain( isset( $dimension['label'] ) ? $dimension['label'] : $key, 80 ),
				'field'  => substr( $field, 0, 64 ),
				'parent' => substr( $parent, 0, 40 ),
				'items'  => $items,
			);
			if ( count( $dimensions ) >= 6 ) {
				break;
			}
		}

		foreach ( $dimensions as $dimension ) {
			if ( '' !== $dimension['parent'] && ! isset( $seen[ $dimension['parent'] ] ) ) {
				$errors[] = 'A dimensão "' . $dimension['key'] . '" aponta para o pai "' . $dimension['parent'] . '", que não existe.';
			}
		}

		return $dimensions;
	}

	/**
	 * Keys of the extra catalogs.
	 *
	 * @param mixed $dimensions Dimensions.
	 * @return string[]
	 */
	private static function dimension_keys( $dimensions ) {
		$keys = array();
		if ( ! is_array( $dimensions ) ) {
			return $keys;
		}
		foreach ( $dimensions as $dimension ) {
			if ( is_array( $dimension ) && ! empty( $dimension['key'] ) ) {
				$keys[] = (string) $dimension['key'];
			}
		}
		return $keys;
	}

	/**
	 * Literal URL pieces that belong to a static parent page.
	 *
	 * @param mixed $raw Prefix list.
	 * @return string[]
	 */
	private static function level_prefix( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$prefix = array();
		foreach ( $raw as $piece ) {
			$piece = Slug::normalize( $piece );
			if ( '' === $piece || in_array( $piece, $prefix, true ) ) {
				continue;
			}
			$prefix[] = $piece;
			if ( count( $prefix ) >= 3 ) {
				break;
			}
		}

		return $prefix;
	}

	/**
	 * Breadcrumb steps for one level. An empty trail keeps the legacy city trail.
	 *
	 * @param mixed  $raw    Trail list.
	 * @param string[] $errors Errors.
	 * @param string $key    Level key.
	 * @return array<int,array<string,string>>
	 */
	private static function trail( $raw, array &$errors, $key ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$items = array();
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = strtolower( trim( (string) ( isset( $item['type'] ) ? $item['type'] : '' ) ) );
			if ( 'home' === $type || 'current' === $type ) {
				$items[] = array( 'type' => $type );
			} elseif ( 'static' === $type ) {
				$label = self::plain( isset( $item['label'] ) ? $item['label'] : '', 80 );
				$path  = self::static_path( isset( $item['path'] ) ? $item['path'] : '' );
				if ( '' === $label || '' === $path ) {
					$errors[] = 'O breadcrumb estático do nível ' . $key . ' precisa de rótulo e de um caminho interno.';
					continue;
				}
				$items[] = array(
					'type'  => 'static',
					'label' => $label,
					'path'  => $path,
				);
			} elseif ( 'ancestor' === $type ) {
				$ancestor = Slug::normalize( isset( $item['level'] ) ? $item['level'] : '' );
				$ancestor = str_replace( '-', '_', $ancestor );
				if ( '' === $ancestor ) {
					$errors[] = 'O breadcrumb do nível ' . $key . ' aponta para um nível anterior sem chave.';
					continue;
				}
				$items[] = array(
					'type'  => 'ancestor',
					'level' => substr( $ancestor, 0, 40 ),
				);
			}
			if ( count( $items ) >= 8 ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Internal path used as a static breadcrumb crumb.
	 *
	 * @param mixed $path Raw path.
	 * @return string
	 */
	private static function static_path( $path ) {
		$path = trim( str_replace( '\\', '/', (string) $path ) );
		if ( '' === $path || false !== strpos( $path, '..' ) || false !== strpos( $path, '://' ) ) {
			return '';
		}

		$path = '/' . trim( $path, '/' ) . '/';
		if ( ! preg_match( '#^/(?:[a-z0-9]+(?:-[a-z0-9]+)*/){1,4}$#', $path ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Cleans the city catalog.
	 *
	 * @param array<string,mixed> $input    Raw input.
	 * @param string[]            $reserved Reserved slugs.
	 * @param string[]            $errors   Errors.
	 * @return array<int,array<string,string>>|null Null keeps the previous value when the form section is absent.
	 */
	private static function cities( array $input, array $reserved, array &$errors ) {
		$rows = null;
		if ( isset( $input['cities_csv'] ) && is_string( $input['cities_csv'] ) ) {
			$parsed = self::parse_city_csv( $input['cities_csv'] );
			$errors = array_merge( $errors, $parsed['errors'] );
			$rows   = $parsed['rows'];
		} elseif ( isset( $input['cities'] ) && is_array( $input['cities'] ) ) {
			$rows = $input['cities'];
		}

		if ( null === $rows ) {
			return null;
		}

		$cities = array();
		$seen   = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = Slug::normalize( isset( $row['slug'] ) ? $row['slug'] : '' );
			$name = self::plain( isset( $row['name'] ) ? $row['name'] : '', 120 );
			$state = self::state( isset( $row['state'] ) ? $row['state'] : '' );
			if ( '' === $slug || '' === $name || '' === $state ) {
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				$errors[] = 'A cidade "' . $slug . '" está duplicada no catálogo.';
				continue;
			}
			if ( in_array( $slug, $reserved, true ) ) {
				$errors[] = 'A cidade "' . $slug . '" usa um slug reservado e não entra no catálogo.';
				continue;
			}
			$seen[ $slug ] = true;
			$cities[] = array(
				'slug'  => $slug,
				'name'  => $name,
				'state' => $state,
			);
		}

		if ( count( $cities ) > 5000 ) {
			$errors[] = 'O catálogo de cidades passa de 5000 linhas.';
			$cities   = array_slice( $cities, 0, 5000 );
		}

		return $cities;
	}

	/**
	 * Cleans the service catalog.
	 *
	 * @param array<string,mixed> $input  Raw input.
	 * @param string[]            $errors Errors.
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function services( array $input, array &$errors ) {
		$rows = null;
		if ( isset( $input['services_json'] ) && is_string( $input['services_json'] ) ) {
			$trimmed = trim( $input['services_json'] );
			if ( '' === $trimmed ) {
				$rows = array();
			} else {
				$decoded = json_decode( $trimmed, true );
				if ( ! is_array( $decoded ) ) {
					$errors[] = 'O JSON de serviços é inválido.';
					return null;
				}
				$rows = $decoded;
			}
		} elseif ( isset( $input['services'] ) && is_array( $input['services'] ) ) {
			$rows = $input['services'];
		}

		if ( null === $rows ) {
			return null;
		}

		$services = array();
		$seen     = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = Slug::normalize( isset( $row['slug'] ) ? $row['slug'] : '' );
			$name = self::plain( isset( $row['display_name'] ) ? $row['display_name'] : '', 140 );
			if ( '' === $slug || '' === $name ) {
				continue;
			}
			if ( isset( $seen[ $slug ] ) ) {
				$errors[] = 'O serviço "' . $slug . '" está duplicado no catálogo.';
				continue;
			}
			$seen[ $slug ] = true;
			$services[] = array(
				'slug'                 => $slug,
				'display_name'         => $name,
				'hero_suffix'          => self::plain( isset( $row['hero_suffix'] ) ? $row['hero_suffix'] : '', 180 ),
				'hero_description'     => self::plain( isset( $row['hero_description'] ) ? $row['hero_description'] : '', 400 ),
				'hero_image'           => self::relative_path( isset( $row['hero_image'] ) ? $row['hero_image'] : '' ),
				'cover_image'          => self::relative_path( isset( $row['cover_image'] ) ? $row['cover_image'] : '' ),
				'problems_template_id' => self::positive_int( isset( $row['problems_template_id'] ) ? $row['problems_template_id'] : 0 ),
				'gallery_template_id'  => self::positive_int( isset( $row['gallery_template_id'] ) ? $row['gallery_template_id'] : 0 ),
			);
		}

		if ( count( $services ) > 200 ) {
			$errors[] = 'O catálogo de serviços passa de 200 itens.';
			$services = array_slice( $services, 0, 200 );
		}

		return $services;
	}

	/**
	 * Cleans breadcrumb presentation.
	 *
	 * @param mixed                $input    Raw group.
	 * @param array<string,string> $defaults Defaults.
	 * @return array<string,string>
	 */
	private static function breadcrumbs( $input, array $defaults ) {
		$input = is_array( $input ) ? $input : array();
		$path  = isset( $input['areas_path'] ) ? trim( (string) $input['areas_path'] ) : $defaults['areas_path'];
		if ( '' !== $path ) {
			$path = '/' . trim( $path, '/' ) . '/';
			if ( ! preg_match( '#^/[a-z0-9_/-]+/$#', $path ) ) {
				$path = $defaults['areas_path'];
			}
		}

		return array(
			'home_label'  => self::plain( isset( $input['home_label'] ) ? $input['home_label'] : $defaults['home_label'], 40 ),
			'areas_label' => self::plain( isset( $input['areas_label'] ) ? $input['areas_label'] : $defaults['areas_label'], 60 ),
			'areas_path'  => $path,
			'color'       => self::color( isset( $input['color'] ) ? $input['color'] : $defaults['color'], $defaults['color'] ),
			'hover'       => self::color( isset( $input['hover'] ) ? $input['hover'] : $defaults['hover'], $defaults['hover'] ),
			'font_size'   => self::font_size( isset( $input['font_size'] ) ? $input['font_size'] : $defaults['font_size'], $defaults['font_size'] ),
		);
	}

	/**
	 * Cleans schema templates.
	 *
	 * @param mixed               $input    Raw group.
	 * @param array<string,mixed> $defaults Defaults.
	 * @param bool                $from_form Whether unchecked boxes mean false.
	 * @return array<string,mixed>
	 */
	private static function schema( $input, array $defaults, $from_form ) {
		$input = is_array( $input ) ? $input : array();
		return array(
			'city_name'         => self::template( isset( $input['city_name'] ) ? $input['city_name'] : $defaults['city_name'] ),
			'city_type'         => self::template( isset( $input['city_type'] ) ? $input['city_type'] : $defaults['city_type'] ),
			'service_name'      => self::template( isset( $input['service_name'] ) ? $input['service_name'] : $defaults['service_name'] ),
			'service_type'      => self::template( isset( $input['service_type'] ) ? $input['service_type'] : $defaults['service_type'] ),
			'provider_id'       => self::identifier( isset( $input['provider_id'] ) ? $input['provider_id'] : '' ),
			'breadcrumb_jsonld' => self::boolean( isset( $input['breadcrumb_jsonld'] ) ? $input['breadcrumb_jsonld'] : null, (bool) $defaults['breadcrumb_jsonld'], $from_form ),
			'faq_jsonld'        => self::boolean( isset( $input['faq_jsonld'] ) ? $input['faq_jsonld'] : null, (bool) $defaults['faq_jsonld'], $from_form ),
			'embed_provider'    => self::boolean( isset( $input['embed_provider'] ) ? $input['embed_provider'] : null, (bool) $defaults['embed_provider'], $from_form ),
			'faq_expected'      => max( 0, min( 20, (int) ( isset( $input['faq_expected'] ) ? $input['faq_expected'] : $defaults['faq_expected'] ) ) ),
		);
	}

	/**
	 * Cleans the company identity.
	 *
	 * @param mixed               $input     Raw group.
	 * @param array<string,mixed> $defaults  Defaults.
	 * @param bool                $from_form Form mode.
	 * @return array<string,mixed>
	 */
	private static function company( $input, array $defaults, $from_form ) {
		$input = is_array( $input ) ? $input : array();
		$type  = isset( $input['type'] ) ? (string) $input['type'] : (string) $defaults['type'];
		if ( ! in_array( $type, Company_Builder::types(), true ) ) {
			$type = 'LocalBusiness';
		}

		return array(
			'enabled'   => self::boolean( isset( $input['enabled'] ) ? $input['enabled'] : null, (bool) $defaults['enabled'], $from_form ),
			'type'      => $type,
			'name'      => self::plain( isset( $input['name'] ) ? $input['name'] : '', 140 ),
			'url'       => self::absolute_url( isset( $input['url'] ) ? $input['url'] : '' ),
			'telephone' => self::plain( isset( $input['telephone'] ) ? $input['telephone'] : '', 40 ),
			'image'     => self::absolute_url( isset( $input['image'] ) ? $input['image'] : '' ),
			'id'        => self::identifier( isset( $input['id'] ) ? $input['id'] : '' ),
			'street'    => self::plain( isset( $input['street'] ) ? $input['street'] : '', 160 ),
			'locality'  => self::plain( isset( $input['locality'] ) ? $input['locality'] : '', 80 ),
			'region'    => self::plain( isset( $input['region'] ) ? $input['region'] : '', 40 ),
			'postal'    => self::plain( isset( $input['postal'] ) ? $input['postal'] : '', 20 ),
			'country'   => self::plain( isset( $input['country'] ) ? $input['country'] : $defaults['country'], 2 ),
			'latitude'  => self::coordinate( isset( $input['latitude'] ) ? $input['latitude'] : '' ),
			'longitude' => self::coordinate( isset( $input['longitude'] ) ? $input['longitude'] : '' ),
		);
	}

	/**
	 * Cleans module toggles.
	 *
	 * @param mixed              $input     Raw modules.
	 * @param array<string,bool> $defaults  Defaults.
	 * @param bool               $from_form Form mode.
	 * @return array<string,bool>
	 */
	private static function modules( $input, array $defaults, $from_form ) {
		$input   = is_array( $input ) ? $input : array();
		$modules = array();
		foreach ( $defaults as $name => $default ) {
			if ( $from_form && empty( $input ) && ! isset( $input[ $name ] ) ) {
				$modules[ $name ] = false;
				continue;
			}
			$modules[ $name ] = self::boolean(
				isset( $input[ $name ] ) ? $input[ $name ] : null,
				(bool) $default,
				$from_form
			);
		}
		return $modules;
	}

	/**
	 * Cleans optional Elementor template IDs.
	 *
	 * @param mixed                           $input    Raw templates.
	 * @param array<int,array<string,mixed>>  $levels   Clean levels.
	 * @param array<string,mixed>             $defaults Defaults.
	 * @param bool                            $from_form Form mode.
	 * @return array<string,mixed>
	 */
	private static function templates( $input, array $levels, array $defaults, $from_form ) {
		$input = is_array( $input ) ? $input : array();
		$map   = array();
		$raw_map = isset( $input['map'] ) && is_array( $input['map'] ) ? $input['map'] : $input;
		foreach ( $levels as $level ) {
			$key         = $level['key'];
			$map[ $key ] = self::positive_int( isset( $raw_map[ $key ] ) ? $raw_map[ $key ] : 0 );
		}

		return array(
			'force' => self::boolean( isset( $input['force'] ) ? $input['force'] : null, ! empty( $defaults['force'] ), $from_form ),
			'map'   => $map,
		);
	}

	/**
	 * Detects duplicated level keys.
	 *
	 * @param array<int,array<string,mixed>> $levels Levels.
	 * @return bool
	 */
	private static function duplicate_level_keys( array $levels ) {
		$seen = array();
		foreach ( $levels as $level ) {
			$key = $level['key'];
			if ( isset( $seen[ $key ] ) ) {
				return true;
			}
			$seen[ $key ] = true;
		}
		return false;
	}

	/**
	 * Plain single-line text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $limit Maximum length.
	 * @return string
	 */
	private static function plain( $value, $limit ) {
		$value = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
		if ( strlen( $value ) > $limit ) {
			$value = substr( $value, 0, $limit );
		}
		return $value;
	}

	/**
	 * Normalizes a state label.
	 *
	 * @param mixed $value Raw state.
	 * @return string
	 */
	private static function state( $value ) {
		$value = self::plain( $value, 40 );
		if ( preg_match( '/^[A-Za-z]{2}$/', $value ) ) {
			return strtoupper( $value );
		}
		return $value;
	}

	/**
	 * Keeps a relative uploads path.
	 *
	 * @param mixed $value Raw path or URL.
	 * @return string
	 */
	private static function relative_path( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '#/uploads/([^?]+)$#', $value, $matches ) ) {
			$value = $matches[1];
		}
		$value = strtok( $value, '?' );
		$value = ltrim( (string) $value, '/' );
		if ( '' === $value || false !== strpos( $value, '..' ) || preg_match( '#^[a-z]+://#i', $value ) ) {
			return '';
		}
		if ( ! preg_match( '#^[A-Za-z0-9_./-]+$#', $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Casts a template ID.
	 *
	 * @param mixed $value Raw ID.
	 * @return int
	 */
	private static function positive_int( $value ) {
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}

	/**
	 * Validates a hex color.
	 *
	 * @param mixed  $value    Raw color.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ) {
			return strtolower( $value );
		}
		return (string) $fallback;
	}

	/**
	 * Validates a CSS font size.
	 *
	 * @param mixed  $value    Raw size.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function font_size( $value, $fallback ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^\d+(\.\d+)?(px|rem|em)$/', $value ) ) {
			return $value;
		}
		return (string) $fallback;
	}

	/**
	 * Keeps a token template on one line.
	 *
	 * @param mixed $value Raw template.
	 * @return string
	 */
	private static function template( $value ) {
		return self::plain( $value, 180 );
	}

	/**
	 * Keeps an @id or absolute URL fragment.
	 *
	 * @param mixed $value Raw identifier.
	 * @return string
	 */
	private static function identifier( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '#^https?://[^\s<>"\']+$#i', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Keeps an absolute HTTP URL.
	 *
	 * @param mixed $value Raw URL.
	 * @return string
	 */
	private static function absolute_url( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '#^https?://[^\s<>"\']+$#i', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Keeps a numeric coordinate as a string.
	 *
	 * @param mixed $value Raw coordinate.
	 * @return string
	 */
	private static function coordinate( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Cleans the section label class used by existing templates.
	 *
	 * @param array<string,mixed> $input    Raw design group.
	 * @param array<string,mixed> $defaults Defaults.
	 * @return array<string,string>
	 */
	private static function design( array $input, array $defaults ) {
		$fallback = isset( $defaults['section_class'] ) ? (string) $defaults['section_class'] : '';
		return array(
			'section_class' => self::css_class( isset( $input['section_class'] ) ? $input['section_class'] : $fallback ),
		);
	}

	/**
	 * Cleans the HTML sitemap settings.
	 *
	 * @param array<string,mixed> $input    Raw sitemap group.
	 * @param array<string,mixed> $defaults Defaults.
	 * @return array<string,mixed>
	 */
	private static function sitemap( array $input, array $defaults ) {
		$skip_source = isset( $input['skip_ids'] ) ? $input['skip_ids'] : ( isset( $defaults['skip_ids'] ) ? $defaults['skip_ids'] : array() );
		if ( is_string( $skip_source ) ) {
			$skip_source = preg_split( '/[\s,]+/', $skip_source );
		}
		$skip = array();
		if ( is_array( $skip_source ) ) {
			foreach ( $skip_source as $id ) {
				$id = (int) $id;
				if ( $id > 0 ) {
					$skip[] = $id;
				}
			}
		}
		$skip = array_slice( array_values( array_unique( $skip ) ), 0, 100 );

		$slug_source = isset( $input['page_slugs'] ) ? $input['page_slugs'] : ( isset( $defaults['page_slugs'] ) ? $defaults['page_slugs'] : array() );
		if ( is_string( $slug_source ) ) {
			$slug_source = preg_split( '/[\s,]+/', $slug_source );
		}
		$slugs = array();
		if ( is_array( $slug_source ) ) {
			foreach ( $slug_source as $slug ) {
				$slug = Slug::normalize( (string) $slug );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}
		}
		$slugs = array_slice( array_values( array_unique( $slugs ) ), 0, 40 );

		$state_source = isset( $input['states'] ) ? $input['states'] : ( isset( $defaults['states'] ) ? $defaults['states'] : array() );
		$states       = array();
		if ( is_array( $state_source ) ) {
			foreach ( $state_source as $code => $label ) {
				$code = strtoupper( self::plain( $code, 2 ) );
				if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
					continue;
				}
				$label = self::plain( $label, 80 );
				if ( '' === $label ) {
					continue;
				}
				$states[ $code ] = $label;
			}
		}

		$accent_fallback = isset( $defaults['accent'] ) ? (string) $defaults['accent'] : '#bf9a33';
		$text_fallback   = isset( $defaults['text'] ) ? (string) $defaults['text'] : '#1a1a1e';
		$font_fallback   = isset( $defaults['font'] ) ? (string) $defaults['font'] : '';

		return array(
			'skip_ids'   => $skip,
			'page_slugs' => $slugs,
			'states'     => $states,
			'accent'     => self::color( isset( $input['accent'] ) ? $input['accent'] : $accent_fallback, $accent_fallback ),
			'text'       => self::sitemap_color( isset( $input['text'] ) ? $input['text'] : $text_fallback, $text_fallback ),
			'font'       => self::font_family( isset( $input['font'] ) ? $input['font'] : $font_fallback ),
		);
	}

	/**
	 * Accepts #rgb, #rrggbb and #rrggbbaa.
	 *
	 * @param mixed  $value    Raw color.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function sitemap_color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
			return strtolower( $value );
		}
		return (string) $fallback;
	}

	/**
	 * Keeps a CSS font-family list.
	 *
	 * @param mixed $value Raw family.
	 * @return string
	 */
	private static function font_family( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9 ,"\'\-]+$/', $value ) ) {
			return '';
		}
		return substr( $value, 0, 80 );
	}

	/**
	 * Keeps a single CSS class.
	 *
	 * @param mixed $value Raw class.
	 * @return string
	 */
	private static function css_class( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^[a-z][a-z0-9_-]{0,40}$/', $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Escapes one CSV cell.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		$value = (string) $value;
		if ( false !== strpos( $value, ',' ) || false !== strpos( $value, '"' ) || false !== strpos( $value, "\n" ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
