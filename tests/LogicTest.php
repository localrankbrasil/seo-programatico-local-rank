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
use LocalRank\ProgrammaticSeo\Support\Wizard;
use PHPUnit\Framework\TestCase;

class LogicTest extends TestCase {

	public function test_slug_strips_accents_and_spaces() {
		$this->assertSame( 'sao-paulo', Slug::normalize( 'São Paulo' ) );
		$this->assertSame( 'manutencao-reparo', Slug::normalize( 'Manutenção & Reparo' ) );
	}

	public function test_path_requires_catalog_membership() {
		$levels = Defaults::levels();
		$catalogs = array(
			'city'    => array( 'austin' ),
			'service' => array( 'manutencao' ),
		);

		$this->assertSame(
			'austin',
			Path_Builder::path(
				$levels[0],
				array( 'city' => 'austin' ),
				$catalogs
			)
		);
		$this->assertSame(
			'austin/manutencao',
			Path_Builder::path(
				$levels[1],
				array(
					'city'    => 'austin',
					'service' => 'manutencao',
				),
				$catalogs
			)
		);
		$this->assertSame(
			'',
			Path_Builder::path(
				$levels[1],
				array(
					'city'    => 'austin',
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
				'city'    => 'austin',
				'service' => 'manutencao',
			),
			array(
				'city'    => array( 'austin' ),
				'service' => array( 'manutencao' ),
			)
		);
		$this->assertSame( 'austin', $path );
	}

	public function test_rewrite_orders_service_before_city_and_drops_reserved_slugs() {
		$rules = Rewrite_Compiler::compile(
			'location_page',
			Defaults::levels(),
			array(
				'city'    => array( 'austin', 'blog' ),
				'service' => array( 'manutencao' ),
				'state'   => array( 'tx' ),
			),
			array( 'blog', 'services' )
		);

		$this->assertCount( 2, $rules );
		$this->assertSame( 1, preg_match( '#' . $rules[0]['regex'] . '#', 'austin/manutencao' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[0]['regex'] . '#', 'blog/manutencao' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[1]['regex'] . '#', 'austin' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'blog' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'austin/manutencao' ) );
		$this->assertStringContainsString( 'lrps_service=$matches[2]', $rules[0]['query'] );
		$this->assertStringNotContainsString( 'lrps_service', $rules[1]['query'] );
	}

	public function test_literal_prefix_builds_a_child_of_a_static_page() {
		$levels   = Presets::levels( 'static_service' );
		$catalogs = array(
			'city'    => array( 'austin' ),
			'service' => array( 'manutencao' ),
			'state'   => array( 'tx' ),
		);

		$this->assertSame(
			'services/manutencao',
			Path_Builder::path(
				$levels[0],
				array( 'service' => 'manutencao' ),
				$catalogs
			)
		);
		$this->assertSame(
			'services/manutencao/austin',
			Path_Builder::path(
				$levels[1],
				array(
					'service' => 'manutencao',
					'city'    => 'austin',
				),
				$catalogs
			)
		);

		$rules = Rewrite_Compiler::compile( 'location_page', $levels, $catalogs, array( 'services', 'blog' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[0]['regex'] . '#', 'services/manutencao/austin' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[0]['regex'] . '#', 'services/manutencao' ) );
		$this->assertSame( 0, preg_match( '#' . $rules[1]['regex'] . '#', 'services' ) );
		$this->assertSame( 1, preg_match( '#' . $rules[1]['regex'] . '#', 'services/manutencao' ) );
		$this->assertStringContainsString( 'lrps_level=service_city', $rules[0]['query'] );
		$this->assertStringContainsString( 'lrps_service=$matches[1]', $rules[0]['query'] );
		$this->assertStringContainsString( 'lrps_city=$matches[2]', $rules[0]['query'] );
	}

	public function test_playbook_counts_coverage_and_unique_keys() {
		$config = array(
			'trade_label' => 'Serviço local',
			'cities'      => array(
				array(
					'slug'  => 'austin',
					'name'  => 'Austin',
					'state' => 'TX',
				),
				array(
					'slug'  => 'miami',
					'name'  => 'Miami',
					'state' => 'FL',
				),
			),
			'services'    => array(
				array(
					'slug'         => 'manutencao',
					'display_name' => 'Manutenção',
				),
			),
			'levels'      => Defaults::levels(),
		);

		$coverage = Playbook::coverage( $config );
		$this->assertSame( 4, $coverage['pages'] );
		$this->assertSame( '/austin/', $coverage['levels'][0]['example'] );
		$this->assertSame( '/austin/manutencao/', $coverage['levels'][1]['example'] );
		$this->assertSame( '{city_slug}__city', $coverage['levels'][0]['unique_key'] );
		$this->assertSame( '{city_slug}__service__{service_slug}', $coverage['levels'][1]['unique_key'] );

		$csv = Playbook::csv_text( $config );
		$this->assertStringContainsString( 'austin__city', $csv );
		$this->assertStringContainsString( 'austin__service__manutencao', $csv );
		$this->assertStringContainsString( 'draft', $csv );

		$three = Playbook::coverage(
			array(
				'cities'   => $config['cities'],
				'services' => $config['services'],
				'levels'   => Presets::levels( 'state_city_service' ),
			)
		);
		$this->assertSame( '/tx/', $three['levels'][0]['example'] );
		$this->assertSame( '/tx/austin/', $three['levels'][1]['example'] );
		$this->assertSame( '/tx/austin/manutencao/', $three['levels'][2]['example'] );
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
				'city'    => 'austin',
				'service' => 'manutencao',
				'state'   => '',
			)
		);
		$city = Rewrite_Compiler::match_level(
			$levels,
			array(
				'city'    => 'austin',
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
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/place/Austin' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://user:pass@www.google.com/maps/embed?pb=abc' ) );
		$this->assertSame( '', Map_Url::sanitize( 'javascript:alert(1)' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/embed?pb=abc#frag' ) );
		$this->assertSame( '', Map_Url::sanitize( 'https://www.google.com/maps/embed?pb="abc"' ) );
	}

	public function test_tokens_leave_unknown_braces_alone() {
		$text = Token_Expander::expand(
			'SERVICO LOCAL IN {city_state} {unknown}',
			array( 'city_state' => 'Austin, TX' )
		);
		$this->assertSame( 'SERVICO LOCAL IN Austin, TX {unknown}', $text );
	}

	public function test_schema_uses_area_served_and_not_an_address() {
		$graph = Schema_Builder::service_graph(
			array(
				'name'        => 'Manutenção in Austin, TX',
				'type'        => 'Manutenção',
				'url'         => 'https://example.com/austin/manutencao/',
				'provider_id' => 'https://example.com/#organization',
				'city'        => 'Austin',
				'state'       => 'TX',
			)
		);

		$this->assertNotNull( $graph );
		$service = $graph['@graph'][0];
		$this->assertSame( 'Service', $service['@type'] );
		$this->assertArrayNotHasKey( 'address', $service );
		$this->assertArrayNotHasKey( 'geo', $service );
		$this->assertSame( 'Austin', $service['areaServed']['name'] );
		$this->assertSame( 'TX', $service['areaServed']['containedInPlace']['name'] );
		$this->assertSame( 'https://example.com/#organization', $service['provider']['@id'] );
	}

	public function test_schema_name_requires_every_token() {
		$vars = array(
			'trade_label'  => '',
			'city_name'    => 'Austin',
			'city_state'   => 'Austin, TX',
			'service_name' => 'Manutenção',
		);
		$this->assertSame( '', Schema_Builder::render_template( '{trade_label} in {city_state}', $vars ) );
		$this->assertSame(
			'Manutenção in Austin, TX',
			Schema_Builder::render_template( '{service_name} in {city_state}', $vars )
		);
	}

	public function test_json_ld_escapes_script_close() {
		$script = Json_Ld::script(
			array(
				'name' => 'Austin</script><script>alert(1)',
			)
		);
		$this->assertStringNotContainsString( '</script><script>', $script );
		$this->assertStringContainsString( '<\/script>', $script );
	}

	public function test_company_entity_requires_a_full_address() {
		$this->assertNull(
			Company_Builder::entity(
				array(
					'name' => 'Empresa exemplo',
					'type' => 'HomeAndConstructionBusiness',
				),
				'https://example.com/#business',
				true
			)
		);

		$entity = Company_Builder::entity(
			array(
				'type'     => 'HomeAndConstructionBusiness',
				'name'     => 'Empresa exemplo',
				'url'      => 'https://example.com/',
				'street'   => '100 Main St',
				'locality' => 'Austin',
				'region'   => 'TX',
				'postal'   => '78701',
				'country'  => 'US',
			),
			'https://example.com/#business',
			true
		);
		$this->assertSame( 'PostalAddress', $entity['address']['@type'] );
		$this->assertSame( 'Austin', $entity['address']['addressLocality'] );
	}

	public function test_company_without_address_omits_the_postal_node() {
		$entity = Company_Builder::entity(
			array(
				'type' => 'HomeAndConstructionBusiness',
				'name' => 'Empresa exemplo',
			),
			'https://example.com/#business',
			false
		);
		$this->assertIsArray( $entity );
		$this->assertArrayNotHasKey( 'address', $entity );
	}

	public function test_link_policy_skips_the_current_post() {
		$this->assertSame( '', Link_Policy::select( 10, 10, 'https://example.com/austin/', true ) );
		$this->assertSame( 'https://example.com/austin/', Link_Policy::select( 10, 11, 'https://example.com/austin/', true ) );
		$this->assertSame( '', Link_Policy::select( 0, 11, 'https://example.com/austin/', true ) );
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

	public function test_example_project_imports_without_a_real_client() {
		$raw = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/examples/projeto.json' ), true );
		$this->assertIsArray( $raw );
		$result = Sanitizer::clean( $raw, Defaults::all(), 'stored' );

		$this->assertSame( array(), $result['errors'] );
		$config = $result['config'];
		$this->assertSame( 'lr', $config['prefix'] );
		$this->assertSame( 'austin', $config['cities'][0]['slug'] );
		$this->assertSame( 'manutencao', $config['services'][0]['slug'] );
		$this->assertSame( 0, $config['services'][0]['gallery_template_id'] );

		$codes = array_column( Reference::shortcodes( $config ), 'code' );
		$this->assertContains( '[lr_city]', $codes );
		$this->assertContains( '[lr_city_state]', $codes );

		$tags = array_column( Reference::dynamic_tags( $config ), 'code' );
		$this->assertContains( 'lr-location-url', $tags );
	}

	public function test_duplicate_city_and_reserved_slug_are_rejected() {
		$result = Sanitizer::clean(
			array(
				'cities_csv' => "slug,name,state\naustin,Austin,TX\naustin,Austin Again,TX\nblog,Blog,TX\n",
			),
			Defaults::all(),
			'form'
		);

		$this->assertNotEmpty( $result['errors'] );
		$slugs = array_column( $result['config']['cities'], 'slug' );
		$this->assertContains( 'austin', $slugs );
		$this->assertNotContains( 'blog', $slugs );
		$this->assertCount( 1, $slugs );
	}

	public function test_relative_media_path_rejects_traversal() {
		$result = Sanitizer::clean(
			array(
				'services' => array(
					array(
						'slug'         => 'manutencao',
						'display_name' => 'Manutenção',
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

	public function test_wizard_builds_a_project_from_plain_answers() {
		$result = Wizard::build(
			Defaults::all(),
			array(
				'client_name'  => 'Cliente exemplo',
				'trade_label'  => 'Serviço local',
				'prefix'       => 'lr',
				'model'        => 'static_service',
				'parent_slug'  => 'pisos',
				'parent_label' => 'Pisos',
				'places'       => "Austin, TX\nDallas, TX",
				'offers'       => "Manutenção\nInstalação",
			)
		);

		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( 'lr', $result['config']['prefix'] );
		$this->assertSame( 'lr_city_slug', $result['config']['fields']['city_slug'] );
		$this->assertSame( 'Cliente exemplo', $result['config']['company']['name'] );
		$this->assertSame( array( 'austin', 'dallas' ), array_column( $result['config']['cities'], 'slug' ) );
		$this->assertSame( 'manutencao', $result['config']['services'][0]['slug'] );
		$this->assertSame( array( 'pisos' ), $result['config']['levels'][0]['prefix'] );
		$this->assertSame( '/pisos/', $result['config']['levels'][0]['trail'][1]['path'] );
		$this->assertTrue( Wizard::is_ready( $result['config'] ) );
		$this->assertSame( 'static_service', Wizard::model_of( $result['config']['levels'] ) );
	}

	public function test_wizard_asks_for_a_city_and_a_service() {
		$result = Wizard::build(
			Defaults::all(),
			array(
				'client_name' => 'Casa',
				'trade_label' => 'Limpeza',
				'prefix'      => 'lr',
				'model'       => 'city_service',
				'places'      => '',
				'offers'      => '',
			)
		);

		$this->assertNotEmpty( $result['errors'] );
		$this->assertFalse( Wizard::is_ready( $result['config'] ) );
	}
}
