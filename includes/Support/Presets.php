<?php
/**
 * Starting architectures a teammate can apply without editing JSON.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

use LocalRank\ProgrammaticSeo\Config\Defaults;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Level presets. Catalogs, prefix and field names stay with the project.
 */
final class Presets {

	/**
	 * Preset id, label and the decision it encodes.
	 *
	 * @return array<string,array{label:string,summary:string}>
	 */
	public static function catalog() {
		return array(
			'city_service'       => array(
				'label'   => 'Cidade e serviço',
				'summary' => 'Dois níveis na raiz: /cidade/ e /cidade/servico/. A página de áreas atendidas entra no breadcrumb padrão e não faz parte da URL.',
			),
			'static_service'     => array(
				'label'   => 'Filha de uma página estática',
				'summary' => 'A página /services/ continua comum. O programático nasce em /services/servico/ e /services/servico/cidade/. O breadcrumb passa por Services.',
			),
			'state_city_service' => array(
				'label'   => 'Estado, cidade e serviço',
				'summary' => 'Três níveis: /estado/, /estado/cidade/ e /estado/cidade/servico/. O breadcrumb sobe um nível de cada vez.',
			),
		);
	}

	/**
	 * Levels for a preset, or an empty list when the id is unknown.
	 *
	 * @param string $id Preset id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function levels( $id ) {
		if ( 'city_service' === $id ) {
			return Defaults::levels();
		}
		if ( 'static_service' === $id ) {
			return self::static_service();
		}
		if ( 'state_city_service' === $id ) {
			return self::state_city_service();
		}
		return array();
	}

	/**
	 * Programmatic pages nested under a static Services page.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function static_service() {
		return array(
			array(
				'key'              => 'service',
				'label'            => 'Serviço',
				'taxonomy_slug'    => 'level-1-service',
				'acf_value'        => 'level_1_service',
				'segments'         => array( 'service' ),
				'prefix'           => array( 'services' ),
				'trail'            => array(
					array( 'type' => 'home' ),
					array(
						'type'  => 'static',
						'label' => 'Services',
						'path'  => '/services/',
					),
					array( 'type' => 'current' ),
				),
				'schema_shortcode' => 'schema_service',
			),
			array(
				'key'              => 'service_city',
				'label'            => 'Serviço na cidade',
				'taxonomy_slug'    => 'level-2-service-city',
				'acf_value'        => 'level_2_service_city',
				'segments'         => array( 'service', 'city' ),
				'prefix'           => array( 'services' ),
				'trail'            => array(
					array( 'type' => 'home' ),
					array(
						'type'  => 'static',
						'label' => 'Services',
						'path'  => '/services/',
					),
					array(
						'type'  => 'ancestor',
						'level' => 'service',
					),
					array( 'type' => 'current' ),
				),
				'schema_shortcode' => 'schema_service_city',
			),
		);
	}

	/**
	 * Three public levels: state, city, then service.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function state_city_service() {
		return array(
			array(
				'key'              => 'state',
				'label'            => 'Estado',
				'taxonomy_slug'    => 'level-1-state',
				'acf_value'        => 'level_1_state',
				'segments'         => array( 'state' ),
				'prefix'           => array(),
				'trail'            => array(
					array( 'type' => 'home' ),
					array( 'type' => 'current' ),
				),
				'schema_shortcode' => 'schema_state',
			),
			array(
				'key'              => 'city',
				'label'            => 'Cidade',
				'taxonomy_slug'    => 'level-2-city',
				'acf_value'        => 'level_2_city',
				'segments'         => array( 'state', 'city' ),
				'prefix'           => array(),
				'trail'            => array(
					array( 'type' => 'home' ),
					array(
						'type'  => 'ancestor',
						'level' => 'state',
					),
					array( 'type' => 'current' ),
				),
				'schema_shortcode' => 'schema_city',
			),
			array(
				'key'              => 'service',
				'label'            => 'Serviço',
				'taxonomy_slug'    => 'level-3-service',
				'acf_value'        => 'level_3_service',
				'segments'         => array( 'state', 'city', 'service' ),
				'prefix'           => array(),
				'trail'            => array(
					array( 'type' => 'home' ),
					array(
						'type'  => 'ancestor',
						'level' => 'state',
					),
					array(
						'type'  => 'ancestor',
						'level' => 'city',
					),
					array( 'type' => 'current' ),
				),
				'schema_shortcode' => 'schema_service',
			),
		);
	}
}
