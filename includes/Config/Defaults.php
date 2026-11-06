<?php
/**
 * Default project configuration.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Neutral defaults for a new client. Prime House lives in examples/.
 */
final class Defaults {

	/**
	 * Full configuration before a client is imported.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		return array(
			'prefix'           => 'lrps',
			'cpt'              => 'location_page',
			'taxonomy'         => 'seo_level',
			'trade_label'      => '',
			'strict_levels'    => true,
			'skip_self_links'  => true,
			'reserved_slugs'   => self::reserved_slugs(),
			'fields'           => self::fields( 'lrps' ),
			'levels'           => self::levels(),
			'cities'           => array(),
			'services'         => array(),
			'dimensions'       => array(),
			'breadcrumbs'      => array(
				'home_label'  => 'Home',
				'areas_label' => 'Service Areas',
				'areas_path'  => '/service-areas/',
				'color'       => '#8E92A5',
				'hover'       => '#BF9A33',
				'font_size'   => '11px',
			),
			'schema'           => array(
				'city_name'         => '{trade_label} in {city_state}',
				'city_type'         => '{trade_label}',
				'service_name'      => '{service_name} in {city_state}',
				'service_type'      => '{service_name}',
				'provider_id'       => '',
				'breadcrumb_jsonld' => false,
				'faq_jsonld'        => false,
				'embed_provider'    => false,
				'faq_expected'      => 0,
			),
			'company'          => array(
				'enabled'   => false,
				'type'      => 'LocalBusiness',
				'name'      => '',
				'url'       => '',
				'telephone' => '',
				'image'     => '',
				'id'        => '',
				'street'    => '',
				'locality'  => '',
				'region'    => '',
				'postal'    => '',
				'country'   => 'US',
				'latitude'  => '',
				'longitude' => '',
			),
			'design'           => array(
				'section_class' => '',
			),
			'sitemap'          => array(
				'skip_ids'   => array(),
				'page_slugs' => array(),
				'states'     => array(),
				'accent'     => '#bf9a33',
				'text'       => '#1a1a1e',
				'font'       => '',
			),
			'modules'          => array(
				'structure'       => true,
				'urls'            => true,
				'shortcodes'      => true,
				'templates'       => true,
				'elementor'       => true,
				'schema'          => true,
				'company_schema'  => false,
				'breadcrumbs_css' => true,
				'section_label'   => true,
				'html_sitemap'    => false,
				'kadence'         => false,
			),
			'templates'        => array(
				'force' => false,
				'map'   => array(
					'city'    => 0,
					'service' => 0,
				),
			),
			'map'              => array(
				'class' => '',
			),
		);
	}

	/**
	 * Field names derived from a prefix.
	 *
	 * @param string $prefix Project prefix.
	 * @return array<string,string>
	 */
	public static function fields( $prefix ) {
		$prefix = (string) $prefix;
		return array(
			'page_level'    => $prefix . '_page_level',
			'city_slug'     => $prefix . '_city_slug',
			'city_name'     => $prefix . '_city_name',
			'city_state'    => $prefix . '_city_state',
			'service_slug'  => $prefix . '_service_slug',
			'service_name'  => $prefix . '_service_name',
			'map_embed'     => $prefix . '_map_embed_url',
			'city_content'  => $prefix . '_city_content',
			'faqs'          => $prefix . '_faqs',
			'faq_question'  => 'question',
			'faq_answer'    => 'answer',
		);
	}

	/**
	 * Two-level city then service architecture.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function levels() {
		return array(
			array(
				'key'              => 'city',
				'label'            => 'City',
				'taxonomy_slug'    => 'level-1-city',
				'acf_value'        => 'level_1_city',
				'segments'         => array( 'city' ),
				'prefix'           => array(),
				'trail'            => array(),
				'schema_shortcode' => 'schema_city',
			),
			array(
				'key'              => 'service',
				'label'            => 'Service',
				'taxonomy_slug'    => 'level-2-service',
				'acf_value'        => 'level_2_service',
				'segments'         => array( 'city', 'service' ),
				'prefix'           => array(),
				'trail'            => array(),
				'schema_shortcode' => 'schema_service',
			),
		);
	}

	/**
	 * Paths that must not be captured by a city slug.
	 *
	 * @return string[]
	 */
	public static function reserved_slugs() {
		return array(
			'about',
			'about-us',
			'author',
			'blog',
			'cart',
			'category',
			'checkout',
			'comments',
			'contact',
			'contact-us',
			'embed',
			'feed',
			'gallery',
			'home',
			'my-account',
			'page',
			'privacy-policy',
			'reviews',
			'search',
			'service-areas',
			'services',
			'shop',
			'tag',
			'thank-you',
			'wp-json',
		);
	}
}
