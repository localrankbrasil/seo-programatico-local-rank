<?php
/**
 * Turns the setup form into a project the rest of the plugin already understands.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

use LocalRank\ProgrammaticSeo\Config\Defaults;
use LocalRank\ProgrammaticSeo\Config\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure translation from plain answers to a stored project.
 */
final class Wizard {

	/**
	 * A project is ready when the team has named at least one place and one offer.
	 *
	 * @param array<string,mixed> $config Project.
	 * @return bool
	 */
	public static function is_ready( array $config ) {
		$cities   = isset( $config['cities'] ) && is_array( $config['cities'] ) ? $config['cities'] : array();
		$services = isset( $config['services'] ) && is_array( $config['services'] ) ? $config['services'] : array();
		return count( $cities ) > 0 && count( $services ) > 0;
	}

	/**
	 * Which starting model the stored levels match.
	 *
	 * @param array<int,mixed> $levels Levels.
	 * @return string
	 */
	public static function model_of( array $levels ) {
		$signature = self::shape( $levels );
		foreach ( array_keys( Presets::catalog() ) as $id ) {
			$preset = self::shape( Presets::levels( $id ) );
			if ( $signature === $preset ) {
				return (string) $id;
			}
		}
		foreach ( $levels as $level ) {
			if ( is_array( $level ) && ! empty( $level['prefix'] ) ) {
				return 'static_service';
			}
		}
		return 'city_service';
	}

	/**
	 * Parent page used by the static model, when one exists.
	 *
	 * @param array<int,mixed> $levels Levels.
	 * @return array{slug:string,label:string}
	 */
	public static function parent_of( array $levels ) {
		$slug  = 'services';
		$label = 'Services';
		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			if ( isset( $level['prefix'][0] ) && '' !== (string) $level['prefix'][0] ) {
				$slug = (string) $level['prefix'][0];
			}
			$trail = isset( $level['trail'] ) && is_array( $level['trail'] ) ? $level['trail'] : array();
			foreach ( $trail as $crumb ) {
				if ( is_array( $crumb ) && isset( $crumb['type'] ) && 'static' === $crumb['type'] && ! empty( $crumb['label'] ) ) {
					$label = (string) $crumb['label'];
				}
			}
		}
		return array(
			'slug'  => $slug,
			'label' => $label,
		);
	}

	/**
	 * Cities as "Nome, UF" lines for the form.
	 *
	 * @param array<int,mixed> $cities Cities.
	 * @return string
	 */
	public static function places_text( array $cities ) {
		$lines = array();
		foreach ( $cities as $city ) {
			if ( ! is_array( $city ) || empty( $city['name'] ) ) {
				continue;
			}
			$lines[] = (string) $city['name'] . ', ' . (string) ( isset( $city['state'] ) ? $city['state'] : '' );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Service names, one per line.
	 *
	 * @param array<int,mixed> $services Services.
	 * @return string
	 */
	public static function offers_text( array $services ) {
		$lines = array();
		foreach ( $services as $service ) {
			if ( is_array( $service ) && ! empty( $service['display_name'] ) ) {
				$lines[] = (string) $service['display_name'];
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Builds a full project from the current one plus the form answers.
	 *
	 * @param array<string,mixed> $current Current project.
	 * @param array<string,mixed> $answers Form answers.
	 * @return array{config:array<string,mixed>,errors:string[]}
	 */
	public static function build( array $current, array $answers ) {
		$errors = array();
		$model  = isset( $answers['model'] ) ? (string) $answers['model'] : '';
		if ( ! isset( Presets::catalog()[ $model ] ) ) {
			$errors[] = 'Escolha como as páginas ficam na URL.';
			$model    = 'city_service';
		}

		$trade = trim( (string) ( isset( $answers['trade_label'] ) ? $answers['trade_label'] : '' ) );
		if ( '' === $trade ) {
			$errors[] = 'Diga o que o cliente faz. Exemplo: instalação de piso.';
		}

		$client = trim( (string) ( isset( $answers['client_name'] ) ? $answers['client_name'] : '' ) );
		if ( '' === $client ) {
			$errors[] = 'Informe o nome do cliente.';
		}

		$levels = self::levels_for( $model, $answers, $errors );
		$csv    = self::places_to_csv( isset( $answers['places'] ) ? (string) $answers['places'] : '', $errors );
		$offers = self::offers( isset( $answers['offers'] ) ? (string) $answers['offers'] : '', $errors );

		$input             = $current;
		$input['prefix']   = isset( $answers['prefix'] ) ? $answers['prefix'] : '';
		$input['trade_label'] = $trade;
		$input['levels']   = $levels;
		$input['cities_csv'] = $csv;
		$input['services'] = $offers;
		$input['fields']   = self::fields_for( $current, (string) $input['prefix'] );

		if ( isset( $input['company'] ) && is_array( $input['company'] ) ) {
			$input['company']['name'] = $client;
		}

		if ( 'static_service' === $model ) {
			$parent = self::parent_slug( isset( $answers['parent_slug'] ) ? (string) $answers['parent_slug'] : '' );
			$reserved = isset( $input['reserved_slugs'] ) && is_array( $input['reserved_slugs'] ) ? $input['reserved_slugs'] : Defaults::reserved_slugs();
			if ( ! in_array( $parent, $reserved, true ) ) {
				$reserved[] = $parent;
			}
			$input['reserved_slugs'] = $reserved;
		}

		$result = Sanitizer::clean( $input, Defaults::all(), 'stored' );
		$errors = array_merge( $errors, $result['errors'] );

		if ( empty( $result['config']['cities'] ) ) {
			$errors[] = 'Informe ao menos uma cidade, no formato Nome, UF.';
		}
		if ( empty( $result['config']['services'] ) ) {
			$errors[] = 'Informe ao menos um serviço, um por linha.';
		}

		return array(
			'config' => $result['config'],
			'errors' => array_values( array_unique( $errors ) ),
		);
	}

	/**
	 * Levels for the chosen model, with the static parent rewritten.
	 *
	 * @param string              $model   Preset id.
	 * @param array<string,mixed> $answers Answers.
	 * @param string[]            $errors  Errors.
	 * @return array<int,array<string,mixed>>
	 */
	private static function levels_for( $model, array $answers, array &$errors ) {
		$levels = Presets::levels( $model );
		if ( 'static_service' !== $model ) {
			return $levels;
		}

		$slug  = self::parent_slug( isset( $answers['parent_slug'] ) ? (string) $answers['parent_slug'] : '' );
		$label = trim( (string) ( isset( $answers['parent_label'] ) ? $answers['parent_label'] : '' ) );
		if ( '' === $label ) {
			$errors[] = 'Dê um nome para a página que já existe, por exemplo Services.';
			$label    = 'Services';
		}
		if ( '' === $slug ) {
			$errors[] = 'O endereço da página pai precisa ser um slug, por exemplo services.';
			$slug     = 'services';
		}

		foreach ( $levels as $index => $level ) {
			$levels[ $index ]['prefix'] = array( $slug );
			if ( empty( $level['trail'] ) || ! is_array( $level['trail'] ) ) {
				continue;
			}
			foreach ( $level['trail'] as $crumb_index => $crumb ) {
				if ( is_array( $crumb ) && isset( $crumb['type'] ) && 'static' === $crumb['type'] ) {
					$levels[ $index ]['trail'][ $crumb_index ]['label'] = $label;
					$levels[ $index ]['trail'][ $crumb_index ]['path']  = '/' . $slug . '/';
				}
			}
		}

		return $levels;
	}

	/**
	 * Keeps custom field names. A still-default map follows the new prefix.
	 *
	 * @param array<string,mixed> $current Current project.
	 * @param string              $prefix  Requested prefix, not yet sanitized.
	 * @return array<string,string>
	 */
	private static function fields_for( array $current, $prefix ) {
		$old_prefix = isset( $current['prefix'] ) ? (string) $current['prefix'] : 'lrps';
		$current_fields = isset( $current['fields'] ) && is_array( $current['fields'] ) ? $current['fields'] : array();
		$clean_prefix = strtolower( preg_replace( '/[^a-z0-9_]/', '', $prefix ) );
		if ( ! is_string( $clean_prefix ) || '' === $clean_prefix ) {
			$clean_prefix = $old_prefix;
		}
		if ( $current_fields === Defaults::fields( $old_prefix ) || $current_fields === Defaults::fields( 'lrps' ) ) {
			return Defaults::fields( $clean_prefix );
		}
		return $current_fields;
	}

	/**
	 * Accepts "Nome, UF" or "slug, Nome, UF".
	 *
	 * @param string   $text   Raw textarea.
	 * @param string[] $errors Errors.
	 * @return string
	 */
	private static function places_to_csv( $text, array &$errors ) {
		$lines = preg_split( '/\r\n|\n|\r/', trim( $text ) );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		$rows = array();
		foreach ( $lines as $index => $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$cols = str_getcsv( $line );
			$cols = is_array( $cols ) ? $cols : array();
			if ( count( $cols ) >= 3 ) {
				$rows[] = array( trim( (string) $cols[0] ), trim( (string) $cols[1] ), trim( (string) $cols[2] ) );
				continue;
			}
			if ( 2 === count( $cols ) ) {
				$name = trim( (string) $cols[0] );
				$rows[] = array( Slug::normalize( $name ), $name, trim( (string) $cols[1] ) );
				continue;
			}
			$errors[] = 'Linha ' . ( $index + 1 ) . ' das cidades precisa de nome e estado. Exemplo: Austin, TX.';
		}

		$csv = '';
		foreach ( $rows as $row ) {
			$csv .= $row[0] . ',' . self::csv_cell( $row[1] ) . ',' . $row[2] . "\n";
		}
		return $csv;
	}

	/**
	 * One service name per line.
	 *
	 * @param string   $text   Raw textarea.
	 * @param string[] $errors Errors.
	 * @return array<int,array<string,mixed>>
	 */
	private static function offers( $text, array &$errors ) {
		unset( $errors );
		$lines = preg_split( '/\r\n|\n|\r/', trim( $text ) );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$services = array();
		$seen     = array();
		foreach ( $lines as $line ) {
			$name = trim( $line );
			if ( '' === $name ) {
				continue;
			}
			$slug = Slug::normalize( $name );
			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$services[] = array(
				'slug'                 => $slug,
				'display_name'         => $name,
				'hero_suffix'          => '',
				'hero_description'     => '',
				'hero_image'           => '',
				'cover_image'          => '',
				'problems_template_id' => 0,
				'gallery_template_id'  => 0,
			);
		}
		return $services;
	}

	/**
	 * Keys and segments, ignoring labels so a renamed parent still matches.
	 *
	 * @param array<int,mixed> $levels Levels.
	 * @return string
	 */
	private static function shape( array $levels ) {
		$rows = array();
		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			$rows[] = array(
				'key'      => isset( $level['key'] ) ? (string) $level['key'] : '',
				'segments' => isset( $level['segments'] ) && is_array( $level['segments'] ) ? array_values( $level['segments'] ) : array(),
				'prefix'   => ! empty( $level['prefix'] ) ? array( 'parent' ) : array(),
			);
		}
		$encoded = json_encode( $rows );
		return is_string( $encoded ) ? $encoded : '';
	}

	/**
	 * Slug of the existing parent page.
	 *
	 * @param string $value Raw slug.
	 * @return string
	 */
	private static function parent_slug( $value ) {
		$slug = Slug::normalize( trim( $value, "/ \t" ) );
		return $slug;
	}

	/**
	 * Quotes a CSV cell that contains a comma.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	private static function csv_cell( $value ) {
		if ( false !== strpos( $value, ',' ) || false !== strpos( $value, '"' ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}
}
