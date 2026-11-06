<?php
/**
 * Localized Service JSON-LD, printed only by shortcode.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;
use LocalRank\ProgrammaticSeo\Data\Tokens;
use LocalRank\ProgrammaticSeo\Support\Company_Builder;
use LocalRank\ProgrammaticSeo\Support\Json_Ld;
use LocalRank\ProgrammaticSeo\Support\Schema_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One Service graph per post and level. Cities are areaServed.
 */
final class Schema_Module {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

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
	 * Prints already emitted, keyed by post and level.
	 *
	 * @var array<string,bool>
	 */
	private $printed = array();

	/**
	 * @param Repository    $config Configuration.
	 * @param Record_Reader $reader Reader.
	 * @param Store         $store  Context.
	 * @param Tokens        $tokens Tokens.
	 */
	public function __construct( Repository $config, Record_Reader $reader, Store $store, Tokens $tokens ) {
		$this->config = $config;
		$this->reader = $reader;
		$this->store  = $store;
		$this->tokens = $tokens;
	}

	/**
	 * Registers the Yoast breadcrumb replacement.
	 */
	public function register() {
		add_filter( 'wpseo_schema_breadcrumb', array( $this, 'filter_yoast_breadcrumb' ), 10, 2 );
	}

	/**
	 * JSON-LD for one configured level, or an empty string.
	 *
	 * @param string $level_key Level key.
	 * @return string
	 */
	public function render_level( $level_key ) {
		if ( ! $this->store->is_location() ) {
			return '';
		}

		$record = $this->reader->read( $this->store->effective_id() );
		if ( empty( $record['valid'] ) || (string) $record['level_key'] !== (string) $level_key ) {
			return '';
		}

		$token = (int) $record['post_id'] . ':' . (string) $level_key;
		if ( isset( $this->printed[ $token ] ) ) {
			return '';
		}

		$graph = $this->graph_for( $record );
		if ( null === $graph ) {
			return '';
		}

		$this->printed[ $token ] = true;
		$script = Json_Ld::script( $graph );

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'lrps_schema_script', $script, $graph, $record );
			if ( is_string( $filtered ) ) {
				return $filtered;
			}
		}

		return $script;
	}

	/**
	 * Builds the graph for a valid record.
	 *
	 * @param array<string,mixed> $record Record.
	 * @return array<string,mixed>|null
	 */
	public function graph_for( array $record ) {
		$schema = $this->config->get( 'schema' );
		if ( ! is_array( $schema ) ) {
			return null;
		}

		$vars      = $this->tokens->for_record( $record );
		$is_service = '' !== (string) $record['service_slug'];
		$name_key  = $is_service ? 'service_name' : 'city_name';
		$type_key  = $is_service ? 'service_type' : 'city_type';
		$name      = Schema_Builder::render_template( isset( $schema[ $name_key ] ) ? (string) $schema[ $name_key ] : '', $vars );
		$type      = Schema_Builder::render_template( isset( $schema[ $type_key ] ) ? (string) $schema[ $type_key ] : '', $vars );
		$url       = get_permalink( (int) $record['post_id'] );
		$url       = is_string( $url ) ? $url : '';

		$provider_id = $this->provider_id( $schema );

		$provider = null;
		if ( ! empty( $schema['embed_provider'] ) ) {
			$company = $this->config->get( 'company' );
			$company = is_array( $company ) ? $company : array();
			$id      = ! empty( $company['id'] ) ? (string) $company['id'] : home_url( '/#business' );
			$provider = Company_Builder::entity( $company, $id, false );
			if ( is_array( $provider ) ) {
				unset( $provider['@context'] );
				$provider_id = $id;
			}
		}

		$breadcrumb = null;
		if ( ! empty( $schema['breadcrumb_jsonld'] ) && ! $this->yoast_active() ) {
			$breadcrumb = Schema_Builder::breadcrumb_list( $this->breadcrumb_items( $record, $url ) );
		}

		$faq = null;
		if ( ! empty( $schema['faq_jsonld'] ) ) {
			$faq = Schema_Builder::faq_page( $this->faq_pairs( (int) $record['post_id'] ) );
		}

		$graph = Schema_Builder::service_graph(
			array(
				'name'        => $name,
				'type'        => $type,
				'url'         => $url,
				'provider_id' => $provider_id,
				'city'        => (string) $record['city_name'],
				'state'       => (string) $record['state'],
				'provider'    => $provider,
				'breadcrumb'  => $breadcrumb,
				'faq'         => $faq,
			)
		);

		if ( null !== $graph && function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'lrps_schema_graph', $graph, $record );
			if ( is_array( $filtered ) ) {
				$graph = $filtered;
			}
		}

		return $graph;
	}

	/**
	 * Replaces Yoast's breadcrumb piece when the project asked for one list.
	 *
	 * @param array<string,mixed> $piece   Yoast breadcrumb.
	 * @param mixed               $context Yoast context.
	 * @return array<string,mixed>|false
	 */
	public function filter_yoast_breadcrumb( $piece, $context ) {
		unset( $context );
		$schema = $this->config->get( 'schema' );
		if ( ! is_array( $schema ) || empty( $schema['breadcrumb_jsonld'] ) || ! $this->store->is_location() ) {
			return $piece;
		}

		$record = $this->reader->read( $this->store->effective_id() );
		if ( empty( $record['valid'] ) ) {
			return $piece;
		}

		$url   = get_permalink( (int) $record['post_id'] );
		$built = Schema_Builder::breadcrumb_list( $this->breadcrumb_items( $record, is_string( $url ) ? $url : '' ) );
		if ( null === $built ) {
			return $piece;
		}

		if ( is_array( $piece ) && isset( $piece['@id'] ) ) {
			$built['@id'] = $piece['@id'];
		}
		return $built;
	}

	/**
	 * Visual breadcrumb items, including a URL for the current page.
	 *
	 * @param array<string,mixed> $record Record.
	 * @param string              $current_url Current permalink.
	 * @return array<int,array{name:string,url:string}>
	 */
	public function breadcrumb_items( array $record, $current_url ) {
		$level = isset( $record['level'] ) && is_array( $record['level'] ) ? $record['level'] : array();
		$trail = isset( $level['trail'] ) && is_array( $level['trail'] ) ? $level['trail'] : array();
		if ( ! empty( $trail ) ) {
			return $this->trail_items( $record, $current_url, $trail );
		}

		$crumbs = $this->config->get( 'breadcrumbs' );
		$crumbs = is_array( $crumbs ) ? $crumbs : array();
		$items  = array(
			array(
				'name' => isset( $crumbs['home_label'] ) ? (string) $crumbs['home_label'] : 'Home',
				'url'  => home_url( '/' ),
			),
		);

		if ( ! empty( $crumbs['areas_path'] ) ) {
			$items[] = array(
				'name' => (string) $crumbs['areas_label'],
				'url'  => home_url( (string) $crumbs['areas_path'] ),
			);
		}

		$place = (string) $record['city_name'] . ', ' . (string) $record['state'];
		if ( '' !== (string) $record['service_slug'] ) {
			$city_level = $this->reader->city_level();
			$city_url   = '';
			if ( is_array( $city_level ) ) {
				$city_id  = $this->reader->find_id( $city_level, (string) $record['city_slug'], '', (string) $record['state'] );
				$city_url = $city_id > 0 ? (string) get_permalink( $city_id ) : '';
			}
			if ( '' === $city_url ) {
				return array();
			}
			$items[] = array(
				'name' => $place,
				'url'  => $city_url,
			);
			$items[] = array(
				'name' => (string) $record['service_name'],
				'url'  => $current_url,
			);
		} else {
			$items[] = array(
				'name' => $place,
				'url'  => $current_url,
			);
		}

		return $items;
	}

	/**
	 * Builds the breadcrumb declared on the level.
	 *
	 * A missing ancestor page clears the trail, the same way a service page
	 * without its city page does in the default architecture.
	 *
	 * @param array<string,mixed>              $record      Record.
	 * @param string                           $current_url Current permalink.
	 * @param array<int,array<string,string>>  $trail       Trail steps.
	 * @return array<int,array{name:string,url:string}>
	 */
	private function trail_items( array $record, $current_url, array $trail ) {
		$crumbs = $this->config->get( 'breadcrumbs' );
		$crumbs = is_array( $crumbs ) ? $crumbs : array();
		$items  = array();

		foreach ( $trail as $step ) {
			if ( ! is_array( $step ) ) {
				return array();
			}
			$type = isset( $step['type'] ) ? (string) $step['type'] : '';
			if ( 'home' === $type ) {
				$items[] = array(
					'name' => isset( $crumbs['home_label'] ) ? (string) $crumbs['home_label'] : 'Home',
					'url'  => home_url( '/' ),
				);
				continue;
			}
			if ( 'static' === $type ) {
				$items[] = array(
					'name' => (string) $step['label'],
					'url'  => home_url( (string) $step['path'] ),
				);
				continue;
			}
			if ( 'current' === $type ) {
				$level = isset( $record['level'] ) && is_array( $record['level'] ) ? $record['level'] : array();
				$items[] = array(
					'name' => $this->crumb_name( $level, $record ),
					'url'  => $current_url,
				);
				continue;
			}
			if ( 'ancestor' !== $type ) {
				return array();
			}

			$ancestor = $this->reader->level( isset( $step['level'] ) ? (string) $step['level'] : '' );
			if ( ! is_array( $ancestor ) ) {
				return array();
			}
			$segments = isset( $ancestor['segments'] ) && is_array( $ancestor['segments'] ) ? $ancestor['segments'] : array();
			$extra    = isset( $record['extra'] ) && is_array( $record['extra'] ) ? $record['extra'] : array();
			$id       = $this->reader->find_id(
				$ancestor,
				in_array( 'city', $segments, true ) ? (string) $record['city_slug'] : '',
				in_array( 'service', $segments, true ) ? (string) $record['service_slug'] : '',
				in_array( 'state', $segments, true ) ? (string) $record['state'] : '',
				$extra
			);
			$url = $id > 0 ? (string) get_permalink( $id ) : '';
			if ( '' === $url ) {
				return array();
			}
			$items[] = array(
				'name' => $this->crumb_name( $ancestor, $record ),
				'url'  => $url,
			);
		}

		return $items;
	}

	/**
	 * Visible name of a crumb for a level.
	 *
	 * @param array<string,mixed> $level  Level.
	 * @param array<string,mixed> $record Record.
	 * @return string
	 */
	private function crumb_name( array $level, array $record ) {
		$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		$labels   = isset( $record['labels'] ) && is_array( $record['labels'] ) ? $record['labels'] : array();
		$ordered  = array_reverse( $segments );
		foreach ( $ordered as $segment ) {
			if ( isset( $labels[ $segment ] ) && '' !== (string) $labels[ $segment ] ) {
				return (string) $labels[ $segment ];
			}
		}
		if ( in_array( 'service', $segments, true ) && '' !== (string) $record['service_name'] ) {
			return (string) $record['service_name'];
		}
		if ( '' !== (string) $record['city_name'] ) {
			$state = (string) $record['state'];
			return '' !== $state ? (string) $record['city_name'] . ', ' . $state : (string) $record['city_name'];
		}
		if ( '' !== (string) $record['state'] ) {
			return (string) $record['state'];
		}
		return isset( $level['label'] ) ? (string) $level['label'] : '';
	}

	/**
	 * FAQ pairs with tags removed, in repeater order.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{question:string,answer:string}>
	 */
	private function faq_pairs( $post_id ) {
		$pairs = array();
		foreach ( $this->reader->faqs( $post_id ) as $row ) {
			$pairs[] = array(
				'question' => trim( wp_strip_all_tags( $row['question'] ) ),
				'answer'   => trim( wp_strip_all_tags( $row['answer'] ) ),
			);
		}
		return $pairs;
	}

	/**
	 * Provider @id. An explicit project value wins. Otherwise use Yoast's
	 * organization when that graph already exists, so the Service node points
	 * at the same entity the rest of the site publishes.
	 *
	 * @param array<string,mixed> $schema Schema settings.
	 * @return string
	 */
	private function provider_id( array $schema ) {
		$configured = isset( $schema['provider_id'] ) ? trim( (string) $schema['provider_id'] ) : '';
		if ( '' !== $configured ) {
			return $configured;
		}

		$yoast = $this->yoast_organization_id();
		if ( '' !== $yoast ) {
			return $yoast;
		}

		return home_url( '/#organization' );
	}

	/**
	 * Organization @id already emitted by Yoast for the current page.
	 *
	 * @return string
	 */
	private function yoast_organization_id() {
		if ( ! function_exists( 'YoastSEO' ) ) {
			return '';
		}

		try {
			$container = YoastSEO();
			if ( ! isset( $container->meta ) || ! is_object( $container->meta ) || ! method_exists( $container->meta, 'for_current_page' ) ) {
				return '';
			}
			$page = $container->meta->for_current_page();
			if ( ! $page || ! isset( $page->schema ) || ! is_array( $page->schema ) ) {
				return '';
			}
			$schema = $page->schema;
		} catch ( \Throwable $error ) {
			unset( $error );
			return '';
		}

		$graph = array();
		if ( isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ) {
			$graph = $schema['@graph'];
		} elseif ( isset( $schema['@type'] ) ) {
			$graph = array( $schema );
		}

		$wanted = array( 'Organization', 'LocalBusiness', 'HomeAndConstructionBusiness', 'ProfessionalService', 'Corporation' );
		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || empty( $node['@id'] ) ) {
				continue;
			}
			$type  = isset( $node['@type'] ) ? $node['@type'] : '';
			$types = is_array( $type ) ? $type : array( $type );
			foreach ( $types as $item ) {
				if ( in_array( $item, $wanted, true ) ) {
					return (string) $node['@id'];
				}
			}
		}

		return '';
	}

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	private function yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}
}
