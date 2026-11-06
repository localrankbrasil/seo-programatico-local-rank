<?php
/**
 * Pure tests for the stage 3 rules.
 */

namespace LocalRank\ProgrammaticSeo\Tests;

use LocalRank\ProgrammaticSeo\Config\Defaults;
use LocalRank\ProgrammaticSeo\Config\Sanitizer;
use LocalRank\ProgrammaticSeo\Support\Company_Builder;
use LocalRank\ProgrammaticSeo\Support\Ids;
use LocalRank\ProgrammaticSeo\Support\Json_Ld;
use LocalRank\ProgrammaticSeo\Support\Link_Policy;
use LocalRank\ProgrammaticSeo\Support\Map_Url;
use LocalRank\ProgrammaticSeo\Support\Path_Builder;
use LocalRank\ProgrammaticSeo\Support\Playbook;
use LocalRank\ProgrammaticSeo\Support\Presets;
use LocalRank\ProgrammaticSeo\Support\Reference;
use LocalRank\ProgrammaticSeo\Support\Rewrite_Compiler;
use LocalRank\ProgrammaticSeo\Support\Schema_Builder;
use LocalRank\ProgrammaticSeo\Support\Slug;
use LocalRank\ProgrammaticSeo\Support\Token_Expander;
use PHPUnit\Framework\TestCase;

class LogicTest extends TestCase {

	public function test_slug_strips_accents_and_spaces() {
		$this->assertSame( 'sao-paulo', Slug::normalize( 'São Paulo' ) );
		$this->assertSame( 'hardwood-floor-repair-infill', Slug::normalize( 'Hardwood Floor Repair & Infill' ) );
	}

	public function test_path_requires_catalog_membership() {
		$levels = Defaults::levels();
		$catalogs = array(
			'city'    => array( 'greenwich' ),
			'service' => array( 'tile-installation' ),
		);

		$this->assertSame(
			'greenwich',
			Path_Builder::path(
				$levels[0],
				array( 'city' => 'greenwich' ),
				$catalogs
			)
		);
		$this->assertSame(
			'greenwich/tile-installation',
			Path_Builder::path(
				$levels[1],
				array(
					'city'    => 'greenwich',
					'service' => 'tile-installation',
				),
				$catalogs
			)
		);
		$this->assertSame(
			'',
			Path_Builder::path(
				$levels[1],
				array(
					'city'    => 'greenwich',
					'service' => 'not-a-service',
				),
				$catalogs
			)
		);
	}

	public function test_city_path_does_not_include_the_service() {
		$path = Path_Builder::path(
			Defaults::levels()[0],
			array(
				'city'    => 'greenwich',
				'service' => 'tile-installation',
			),
			array(
				'city'    => array( 'greenwich' ),
				'service' => array( 'tile-installation' ),
			)
		);
		$this->assertSame( 'greenwich', $path );
	}

	public function test_rewrite_orders_service_before_city_and_drops_reserved_slugs() {
		$rules = Rewrite_Compiler::compile(
			'location_page',
			Defaults::levels(),
			array(
				'city'    => array( 'greenwich', 'blog' ),
				'service' => array( 'tile-installation' ),
				'state'   => array( 'ct' ),
			),
			array( 'blog', 'services' )
		);

		$this->assertCount( 2, $rules );
		$this->assertSame( 1, preg_match( '#' . $rules[0]['regex'] . '#', 'greenwich/tile-installation' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[0]['regex'] . '#', 'blog/tile-installation' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[1]['regex'] . '#', 'greenwich' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'blog' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'greenwich/tile-installation' ) );
		$this->assertStringContainsString( 'lrps_service=$matches[2]', $rules[0]['query'] );
		$this->assertStringNotContainsString( 'lrps_service', $rules[1]['query'] );
	}

	public function test_literal_prefix_builds_a_child_of_a_static_page() {
		$levels   = Presets::levels( 'static_service' );
		$catalogs = array(
			'city'    => array( 'greenwich' ),
			'service' => array( 'tile-installation' ),
			'state'   => array( 'ct' ),
		);

		$this->assertSame(
			'services/tile-installation',
			Path_Builder::path(
				$levels[0],
				array( 'service' => 'tile-installation' ),
				$catalogs
			)
		);
		$this->assertSame(
			'services/tile-installation/greenwich',
			Path_Builder::path(
				$levels[1],
				array(
					'service' => 'tile-installation',
					'city'    => 'greenwich',
				),
				$catalogs
			)
		);

		$rules = Rewrite_Compiler::compile( 'location_page', $levels, $catalogs, array( 'services', 'blog' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[0]['regex'] . '#', 'services/tile-installation/greenwich' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[0]['regex'] . '#', 'services/tile-installation' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'services' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[1]['regex'] . '#', 'services/tile-installation' ) );
		$this->assertStringContainsString( 'lrps_level=service_city', $rules[0]['query'] );
		$this->assertStringContainsString( 'lrps_service=$matches[1]', $rules[0]['query'] );
		$this->assertStringContainsString( 'lrps_city=$matches[2]', $rules[0]['query'] );
	}

	public function test_playbook_counts_coverage_and_unique_keys() {
		$config = array(
			'trade_label' => 'Flooring Contractor',
			'cities'      => array(
				array(
					'slug'  => 'greenwich',
					'name'  => 'Greenwich',
					'state' => 'CT',
				),
				array(
					'slug'  => 'yonkers',
					'name'  => 'Yonkers',
					'state' => 'NY',
				),
			),
			'services'    => array(
				array(
					'slug'         => 'tile-installation',
					'display_name' => 'Tile Installation',
				),
			),
			'levels'      => Defaults::levels(),
		);

		$coverage = Playbook::coverage( $config );
		$this->assertSame( 4, $coverage['pages'] );
		$this->assertSame( '/greenwich/', $coverage['levels'][0]['example'] );
		$this->assertSame( '/greenwich/tile-installation/', $coverage['levels'][1]['example'] );
		$this->assertSame( '{city_slug}__city', $coverage['levels'][0]['unique_key'] );
		$this->assertSame( '{city_slug}__service__{service_slug}', $coverage['levels'][1]['unique_key'] );

		$csv = Playbook::csv_text( $config );
		$this->assertStringContainsString( 'greenwich__city', $csv );
		$this->assertStringContainsString( 'greenwich__service__tile-installation', $csv );
		$this->assertStringContainsString( 'draft', $csv );

		$three = Playbook::coverage(
			array(
				'cities'   => $config['cities'],
				'services' => $config['services'],
				'levels'   => Presets::levels( 'state_city_service' ),
			)
		);
		$this->assertSame( '/ct/', $three['levels'][0]['example'] );
		$this->assertSame( '/ct/greenwich/', $three['levels'][1]['example'] );
		$this->assertSame( '/ct/greenwich/tile-installation/', $three['levels'][2]['example'] );
		$this->assertSame( 2, $three['levels'][0]['count'] );
		$this->assertSame( 2, $three['levels'][1]['count'] );
		$this->assertSame( 2, $three['levels'][2]['count'] );
	}

	public function test_sanitizer_keeps_prefix_and_rejects_external_crumb() {
		$result = Sanitizer::clean(
			array(
				'levels' => array(
					array(
						'key'      => 'service',
						'segments' => array( 'service' ),
						'prefix'   => array( 'services', 'Services' ),
						'trail'    => array(
							array( 'type' => 'home' ),
							array(
								'type'  => 'static',
								'label' => 'Services',
								'path'  => '/services/',
							),
							array(
								'type'  => 'static',
								'label' => 'Fora',
								'path'  => 'https://evil.example/services/',
							),
							array( 'type' => 'current' ),
						),
					),
				),
			),
			Defaults::all(),
			'stored'
		);

		$level = $result['config']['levels'][0];
		$this->assertSame( array( 'services' ), $level['prefix'] );
		$this->assertSame( 'home', $level['trail'][0]['type'] );
		$this->assertSame( '/services/', $level['trail'][1]['path'] );
		$this->assertSame( 'current', $level['trail'][2]['type'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	public function test_match_level_prefers_the_longer_complete_level() {
		$levels = Defaults::levels();
		$service = Rewrite_Compiler::match_level(
			$levels,
			array(
				'city'    => 'greenwich',
				'service' => 'tile-installation',
				'state'   => '',
			)
		);
		$city = Rewrite_Compiler::match_level(
			$levels,
			array(
				'city'    => 'greenwich',
				'service' => '',
				'state'   => '',
			)
		);

		$this->assertSame( 'service', $service['key'] );
		$this->assertSame( 'city', $city['key'] );
	}

	public function test_map_url_allow_list() {
		$valid = 'https://www.google.com/maps/embed?pb=!1m18';
		$this->assertSame( $valid, Map_Url::sanitize( $valid ) );
		$this->assertSame( 'https://google.com/maps/embed?pb=abc', Map_Url::sanitize( 'https://google.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'http://www.google.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://maps.google.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://evil.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/place/Greenwich' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://user:pass@www.google.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'javascript:alert(1)' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/embed?pb=abc#frag' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/embed?pb="abc"' ) );
	}

	public function test_tokens_leave_unknown_braces_alone() {
		$text = Token_Expander::expand(
			'FLOORING CONTRACTOR IN {city_state} {unknown}',
			array( 'city_state' => 'Greenwich, CT' )
		);
		$this->assertSame( 'FLOORING CONTRACTOR IN Greenwich, CT {unknown}', $text );
	}

	public function test_schema_uses_area_served_and_not_an_address() {
		$graph = Schema_Builder::service_graph(
			array(
				'name'        => 'Tile Installation in Greenwich, CT',
				'type'        => 'Tile Installation',
				'url'         => 'https://example.com/greenwich/tile-installation/',
				'provider_id' => 'https://example.com/#organization',
				'city'        => 'Greenwich',
				'state'       => 'CT',
			)
		);

		$this->assertNotNull( $graph );
		$service = $graph['@graph'][0];
		$this->assertSame( 'Service', $service['@type'] );
		$this->assertArrayNotHasKey( 'address', $service );
		$this->assertArrayNotHasKey( 'geo', $service );
		$this->assertSame( 'Greenwich', $service['areaServed']['name'] );
		$this->assertSame( 'CT', $service['areaServed']['containedInPlace']['name'] );
		$this->assertSame( 'https://example.com/#organization', $service['provider']['@id'] );
	}

	public function test_schema_name_requires_every_token() {
		$vars = array(
			'trade_label'  => '',
			'city_name'    => 'Greenwich',
			'city_state'   => 'Greenwich, CT',
			'service_name' => 'Tile Installation',
		);
		$this->assertSame( '', Schema_Builder::render_template( '{trade_label} in {city_state}', $vars ) );
		$this->assertSame(
			'Tile Installation in Greenwich, CT',
			Schema_Builder::render_template( '{service_name} in {city_state}', $vars )
		);
	}

	public function test_json_ld_escapes_script_close() {
		$script = Json_Ld::script(
			array(
				'name' => 'Greenwich</script><script>alert(1)',
			)
		);
		$this->assertStringNotContainsString( '</script><script>', $script );
		$this->assertStringContainsString( '<\/script>', $script );
	}

	public function test_company_entity_requires_a_full_address() {
		$this->assertNull(
			Company_Builder::entity(
				array(
					'name' => 'Prime House Flooring',
					'type' => 'HomeAndConstructionBusiness',
				),
				'https://example.com/#business',
				true
			)
		);

		$entity = Company_Builder::entity(
			array(
				'type'     => 'HomeAndConstructionBusiness',
				'name'     => 'Prime House Flooring',
				'url'      => 'https://primehouseflooring.com/',
				'street'   => '89 Pemberwick Rd 2nd Floor',
				'locality' => 'Greenwich',
				'region'   => 'CT',
				'postal'   => '06831',
				'country'  => 'US',
			),
			'https://primehouseflooring.com/#business',
			true
		);
		$this->assertSame( 'PostalAddress', $entity['address']['@type'] );
		$this->assertSame( 'Greenwich', $entity['address']['addressLocality'] );
	}

	public function test_company_without_address_omits_the_postal_node() {
		$entity = Company_Builder::entity(
			array(
				'type' => 'HomeAndConstructionBusiness',
				'name' => 'Prime House Flooring',
			),
			'https://example.com/#business',
			false
		);
		$this->assertIsArray( $entity );
		$this->assertArrayNotHasKey( 'address', $entity );
	}

	public function test_link_policy_skips_the_current_post() {
		$this->assertSame( '', Link_Policy::select( 10, 10, 'https://example.com/greenwich/', true ) );
		$this->assertSame( 'https://example.com/greenwich/', Link_Policy::select( 10, 11, 'https://example.com/greenwich/', true ) );
		$this->assertSame( '', Link_Policy::select( 0, 11, 'https://example.com/greenwich/', true ) );
	}

	public function test_only_one_id_rejects_duplicates() {
		$this->assertSame( 15, Ids::only_one( array( 15 ) ) );
		$this->assertSame( 0, Ids::only_one( array( 15, 16 ) ) );
		$this->assertSame( 0, Ids::only_one( array() ) );
	}

	public function test_faq_pairs_keep_question_and_answer_together() {
		$faq = Schema_Builder::faq_page(
			array(
				array(
					'question' => 'First question',
					'answer'   => 'First answer',
				),
				array(
					'question' => 'Second question',
					'answer'   => 'Second answer',
				),
			)
		);
		$this->assertSame( 'First question', $faq['mainEntity'][0]['name'] );
		$this->assertSame( 'Second answer', $faq['mainEntity'][1]['acceptedAnswer']['text'] );
	}

	public function test_prime_house_example_imports() {
		$raw = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/examples/prime-house-flooring.json' ), true );
		$this->assertIsArray( $raw );
		$result = Sanitizer::clean( $raw, Defaults::all(), 'stored' );

		$this->assertSame( array(), $result['errors'] );
		$config = $result['config'];
		$this->assertSame( 'phf', $config['prefix'] );
		$this->assertGreaterThan( 80, count( $config['cities'] ) );
		$this->assertSame( 'greenwich', $config['cities'][0]['slug'] );
		$slugs = array_column( $config['cities'], 'slug' );
		$this->assertContains( 'yonkers', $slugs );
		$this->assertContains( 'jersey-city', $slugs );
		$this->assertCount( 6, $config['services'] );
		$this->assertSame( 'built to last decades.', $config['services'][0]['hero_suffix'] );
		$this->assertSame( 1766, $config['services'][0]['problems_template_id'] );
		$this->assertSame( 0, $config['services'][5]['gallery_template_id'] );
		$this->assertSame( '2026/08/hero-hardwood-floor-repair.jpg', $config['services'][2]['hero_image'] );
		$this->assertSame( 'Hardwood-Repair.jpg', basename( $config['services'][2]['cover_image'] ) );
		$this->assertSame( 'phf_faq_question', $config['fields']['faq_question'] );
		$this->assertSame( 'phf_faq_answer', $config['fields']['faq_answer'] );
		$this->assertSame( 'ph-section-label', $config['design']['section_class'] );
		$this->assertFalse( $config['skip_self_links'] );
		$this->assertTrue( $config['company']['enabled'] );
		$this->assertSame( '41.0198907', $config['company']['latitude'] );
		$this->assertTrue( $config['modules']['kadence'] );
		$this->assertTrue( $config['modules']['html_sitemap'] );
		$this->assertContains( 633, $config['sitemap']['skip_ids'] );

		$codes = array_column( Reference::shortcodes( $config ), 'code' );
		$this->assertContains( '[phf_city]', $codes );
		$this->assertContains( '[phf_city_state]', $codes );
		$this->assertContains( '[phf_schema_city]', $codes );
		$this->assertContains( '[phf_schema_service]', $codes );
		$this->assertContains( '[phf_problems_section]', $codes );
		$this->assertContains( '[phf_html_sitemap]', $codes );

		$tags = array_column( Reference::dynamic_tags( $config ), 'code' );
		$this->assertContains( 'phf-location-url', $tags );
		$this->assertContains( 'phf-service-hero-image', $tags );
		$this->assertContains( 'phf-service-cover-image', $tags );
	}

	public function test_duplicate_city_and_reserved_slug_are_rejected() {
		$result = Sanitizer::clean(
			array(
				'cities_csv' => "slug,name,state\ngreenwich,Greenwich,CT\ngreenwich,Greenwich Again,CT\nblog,Blog,CT\n",
			),
			Defaults::all(),
			'form'
		);

		$this->assertNotEmpty( $result['errors'] );
		$slugs = array_column( $result['config']['cities'], 'slug' );
		$this->assertContains( 'greenwich', $slugs );
		$this->assertNotContains( 'blog', $slugs );
		$this->assertCount( 1, $slugs );
	}

	public function test_relative_media_path_rejects_traversal() {
		$result = Sanitizer::clean(
			array(
				'services' => array(
					array(
						'slug'         => 'tile-installation',
						'display_name' => 'Tile Installation',
						'hero_image'   => 'https://staging.example.com/wp-content/uploads/2026/08/hero.jpg',
						'cover_image'  => '../secret.jpg',
					),
				),
			),
			Defaults::all(),
			'stored'
		);

		$this->assertSame( '2026/08/hero.jpg', $result['config']['services'][0]['hero_image'] );
		$this->assertSame( '', $result['config']['services'][0]['cover_image'] );
	}
}
