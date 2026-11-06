<?php
/**
 * Canonical paths, rewrites and request resolution.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Catalog;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;
use LocalRank\ProgrammaticSeo\Support\Path_Builder;
use LocalRank\ProgrammaticSeo\Support\Rewrite_Compiler;
use LocalRank\ProgrammaticSeo\Support\Slug;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns programmatic permalinks without capturing institutional routes.
 */
final class Urls_Module {

	const REWRITE_OPTION = 'lrps_rewrite_hash';

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
	 * Context stack.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * @param Repository    $config  Configuration.
	 * @param Catalog       $catalog Catalog.
	 * @param Record_Reader $reader  Reader.
	 * @param Store         $store   Context.
	 */
	public function __construct( Repository $config, Catalog $catalog, Record_Reader $reader, Store $store ) {
		$this->config  = $config;
		$this->catalog = $catalog;
		$this->reader  = $reader;
		$this->store   = $store;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_rewrites' ), 20 );
		add_action( 'init', array( $this, 'maybe_flush' ), 30 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'post_type_link', array( $this, 'permalink' ), 10, 2 );
		add_filter( 'request', array( $this, 'resolve_request' ) );
		add_action( 'template_redirect', array( $this, 'guard_404' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'cancel_redirect' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( $this, 'yoast_canonical' ) );
		add_filter( 'get_canonical_url', array( $this, 'core_canonical' ), 10, 2 );
	}

	/**
	 * Adds catalog-limited rules.
	 */
	public function register_rewrites() {
		$rules = $this->rules();
		foreach ( $rules as $rule ) {
			add_rewrite_rule( $rule['regex'], $rule['query'], 'top' );
		}
	}

	/**
	 * Compiled rules for the current catalogs.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function rules() {
		$levels = $this->config->get( 'levels' );
		return Rewrite_Compiler::compile(
			(string) $this->config->get( 'cpt' ),
			is_array( $levels ) ? $levels : array(),
			$this->catalog->indexes(),
			$this->catalog->reserved()
		);
	}

	/**
	 * Flushes permalinks once when the catalogs or levels change.
	 */
	public function maybe_flush() {
		$hash   = $this->hash();
		$stored = (string) get_option( self::REWRITE_OPTION, '' );
		if ( $stored === $hash ) {
			return;
		}
		if ( get_transient( 'lrps_flushing' ) ) {
			return;
		}

		set_transient( 'lrps_flushing', '1', MINUTE_IN_SECONDS );
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $hash, false );
		delete_transient( 'lrps_flushing' );
	}

	/**
	 * Public query variables.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		foreach ( $this->signal_keys() as $var ) {
			if ( ! in_array( $var, $vars, true ) ) {
				$vars[] = $var;
			}
		}
		return $vars;
	}

	/**
	 * Replaces the native permalink with the programmatic path.
	 *
	 * @param string   $link Existing link.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function permalink( $link, $post ) {
		if ( ! $post instanceof \WP_Post || $post->post_type !== (string) $this->config->get( 'cpt' ) ) {
			return $link;
		}

		$path = $this->path_for( (int) $post->ID );
		if ( '' === $path ) {
			return $link;
		}

		return trailingslashit( home_url( $path ) );
	}

	/**
	 * Turns a catalog request into a single post before the main query runs.
	 *
	 * Setting `p` here lets WordPress treat the URL as singular instead of
	 * as a post type archive.
	 *
	 * @param array<string,mixed> $query_vars Request vars.
	 * @return array<string,mixed>
	 */
	public function resolve_request( $query_vars ) {
		if ( is_admin() || ! is_array( $query_vars ) || ! $this->has_programmatic_signal( $query_vars ) ) {
			return $query_vars;
		}

		$vars   = $this->segment_values( $query_vars );
		$levels = $this->config->get( 'levels' );
		$levels = is_array( $levels ) ? $levels : array();
		$level  = $this->level_from_request( $levels, $query_vars, $vars );
		$id     = 0;
		if ( is_array( $level ) ) {
			$state = isset( $vars['state'] ) && '' !== $vars['state'] ? strtoupper( $vars['state'] ) : '';
			$extra = $vars;
			unset( $extra['city'], $extra['service'], $extra['state'] );
			$id = $this->reader->find_id(
				$level,
				isset( $vars['city'] ) ? $vars['city'] : '',
				isset( $vars['service'] ) ? $vars['service'] : '',
				$state,
				$extra
			);
		}

		$query_vars['post_type'] = (string) $this->config->get( 'cpt' );
		$query_vars['p']         = $id > 0 ? $id : -1;
		unset( $query_vars['name'], $query_vars['pagename'] );
		return $query_vars;
	}

	/**
	 * Confirms a catalog URL did not fall through to another object.
	 */
	public function guard_404() {
		if ( ! $this->has_programmatic_signal( $this->public_query_signal() ) ) {
			return;
		}
		if ( is_singular( (string) $this->config->get( 'cpt' ) ) ) {
			return;
		}

		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Stops WordPress from redirecting a resolved programmatic URL.
	 *
	 * @param string|false $redirect  Proposed redirect.
	 * @param string       $requested Requested URL.
	 * @return string|false
	 */
	public function cancel_redirect( $redirect, $requested ) {
		unset( $requested );
		if ( $this->has_programmatic_signal( $this->public_query_signal() ) ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * Points Yoast canonical at the programmatic permalink.
	 *
	 * @param string $canonical Current canonical.
	 * @return string
	 */
	public function yoast_canonical( $canonical ) {
		$url = $this->canonical_url();
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * Points the core canonical at the programmatic permalink.
	 *
	 * @param string   $url  Current canonical.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function core_canonical( $url, $post ) {
		if ( $post instanceof \WP_Post && $post->post_type === (string) $this->config->get( 'cpt' ) ) {
			$programmatic = $this->permalink( $url, $post );
			return is_string( $programmatic ) && '' !== $programmatic ? $programmatic : $url;
		}
		return $url;
	}

	/**
	 * Public path for a post, without slashes around it.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function path_for( $post_id ) {
		$record = $this->reader->read( (int) $post_id );
		if ( empty( $record['level'] ) || ! is_array( $record['level'] ) ) {
			return '';
		}
		if ( ! empty( $record['diverged'] ) && $this->config->get( 'strict_levels' ) ) {
			return '';
		}

		$values = array(
			'city'    => (string) $record['city_slug'],
			'service' => (string) $record['service_slug'],
			'state'   => strtolower( (string) $record['state'] ),
		);
		if ( isset( $record['extra'] ) && is_array( $record['extra'] ) ) {
			foreach ( $record['extra'] as $segment => $slug ) {
				$values[ (string) $segment ] = (string) $slug;
			}
		}

		return Path_Builder::path( $record['level'], $values, $this->catalog->indexes(), $this->catalog->relations() );
	}

	/**
	 * Hash of everything that changes public routes.
	 *
	 * @return string
	 */
	public function hash() {
		$payload = array(
			'cpt'      => $this->config->get( 'cpt' ),
			'levels'   => $this->config->get( 'levels' ),
			'indexes'  => $this->catalog->indexes(),
			'reserved' => $this->catalog->reserved(),
			'version'  => LRPS_VERSION,
		);
		return md5( (string) wp_json_encode( $payload ) );
	}

	/**
	 * True when the request carries a programmatic rewrite variable.
	 *
	 * @param array<string,mixed> $query_vars Request vars.
	 * @return bool
	 */
	private function has_programmatic_signal( array $query_vars ) {
		foreach ( $this->signal_keys() as $key ) {
			if ( ! empty( $query_vars[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Query vars already registered on the main request.
	 *
	 * @return array<string,string>
	 */
	private function public_query_signal() {
		$signal = array();
		foreach ( $this->signal_keys() as $key ) {
			$value          = get_query_var( $key );
			$signal[ $key ] = is_string( $value ) ? $value : '';
		}
		return $signal;
	}

	/**
	 * Query var names that mark a programmatic request.
	 *
	 * @return string[]
	 */
	private function signal_keys() {
		$keys = array( 'lrps_level' );
		foreach ( $this->segment_names() as $name ) {
			$keys[] = 'lrps_' . $name;
		}
		return $keys;
	}

	/**
	 * Segment names used by the current levels and dimensions.
	 *
	 * @return string[]
	 */
	private function segment_names() {
		$names = array( 'city', 'service', 'state' );
		$levels = $this->config->get( 'levels' );
		if ( is_array( $levels ) ) {
			foreach ( $levels as $level ) {
				if ( ! is_array( $level ) || empty( $level['segments'] ) || ! is_array( $level['segments'] ) ) {
					continue;
				}
				foreach ( $level['segments'] as $segment ) {
					$names[] = (string) $segment;
				}
			}
		}
		$dimensions = $this->config->get( 'dimensions' );
		if ( is_array( $dimensions ) ) {
			foreach ( $dimensions as $dimension ) {
				if ( is_array( $dimension ) && ! empty( $dimension['key'] ) ) {
					$names[] = (string) $dimension['key'];
				}
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Normalized segment values from the request.
	 *
	 * @param array<string,mixed> $query_vars Request vars.
	 * @return array<string,string>
	 */
	private function segment_values( array $query_vars ) {
		$values = array();
		foreach ( $this->segment_names() as $name ) {
			$raw = isset( $query_vars[ 'lrps_' . $name ] ) ? (string) $query_vars[ 'lrps_' . $name ] : '';
			if ( 'state' === $name ) {
				$values[ $name ] = strtolower( sanitize_title( $raw ) );
				continue;
			}
			$values[ $name ] = Slug::normalize( $raw );
		}
		return $values;
	}

	/**
	 * Picks the level named by the rewrite rule, then checks its segments.
	 *
	 * @param array<int,array<string,mixed>> $levels     Levels.
	 * @param array<string,mixed>            $query_vars Raw request.
	 * @param array<string,string>           $vars       Normalized segments.
	 * @return array<string,mixed>|null
	 */
	private function level_from_request( array $levels, array $query_vars, array $vars ) {
		$level = null;
		if ( ! empty( $query_vars['lrps_level'] ) ) {
			$wanted = sanitize_key( (string) $query_vars['lrps_level'] );
			foreach ( $levels as $candidate ) {
				if ( is_array( $candidate ) && isset( $candidate['key'] ) && sanitize_key( (string) $candidate['key'] ) === $wanted ) {
					$level = $candidate;
					break;
				}
			}
		}
		if ( ! is_array( $level ) ) {
			$level = Rewrite_Compiler::match_level( $levels, $vars );
		}
		if ( ! is_array( $level ) ) {
			return null;
		}

		$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		foreach ( $segments as $segment ) {
			$value = isset( $vars[ $segment ] ) ? (string) $vars[ $segment ] : '';
			if ( '' === $value ) {
				return null;
			}
		}

		return $level;
	}

	/**
	 * Canonical URL of the location being viewed.
	 *
	 * @return string
	 */
	private function canonical_url() {
		if ( ! $this->store->is_location() ) {
			return '';
		}
		$url = get_permalink( $this->store->effective_id() );
		return is_string( $url ) ? $url : '';
	}

}
