<?php
/**
 * Post type, taxonomy, terms and level sync.
 *
 * ACF field groups stay in ACF Pro. This module never registers them.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the level taxonomy aligned with the ACF select.
 */
final class Structure_Module {

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * Reader used by the repair action.
	 *
	 * @var Record_Reader
	 */
	private $reader;

	/**
	 * @param Repository    $config Configuration.
	 * @param Record_Reader $reader Reader.
	 */
	public function __construct( Repository $config, Record_Reader $reader ) {
		$this->config = $config;
		$this->reader = $reader;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_types' ), 5 );
		add_action( 'init', array( $this, 'ensure_terms' ), 15 );
		add_action( 'save_post', array( $this, 'sync_level' ), 20 );
		add_action( 'acf/save_post', array( $this, 'sync_level' ), 20 );
		add_filter( 'wpseo_sitemap_exclude_taxonomy', array( $this, 'hide_taxonomy_sitemap' ), 10, 2 );
		add_action( 'admin_post_lrps_sync_levels', array( $this, 'sync_all' ) );

		$city_field = $this->config->field( 'city_slug' );
		if ( '' !== $city_field ) {
			add_filter( 'acf/load_field/name=' . $city_field, array( $this, 'fill_city_choices' ) );
		}

		$dimensions = $this->config->get( 'dimensions' );
		if ( is_array( $dimensions ) ) {
			foreach ( $dimensions as $dimension ) {
				if ( ! is_array( $dimension ) || empty( $dimension['field'] ) ) {
					continue;
				}
				add_filter( 'acf/load_field/name=' . (string) $dimension['field'], array( $this, 'fill_dimension_choices' ) );
			}
		}
	}

	/**
	 * Registers the post type and taxonomy when the site does not already have them.
	 */
	public function register_types() {
		$cpt = (string) $this->config->get( 'cpt' );
		if ( ! post_type_exists( $cpt ) ) {
			register_post_type(
				$cpt,
				array(
					'labels'              => array(
						'name'          => __( 'Páginas locais', 'local-rank-programmatic-seo' ),
						'singular_name' => __( 'Página local', 'local-rank-programmatic-seo' ),
						'add_new_item'  => __( 'Adicionar página local', 'local-rank-programmatic-seo' ),
						'edit_item'     => __( 'Editar página local', 'local-rank-programmatic-seo' ),
						'search_items'  => __( 'Buscar páginas locais', 'local-rank-programmatic-seo' ),
					),
					'public'              => true,
					'publicly_queryable'  => true,
					'show_ui'             => true,
					'show_in_rest'        => true,
					'show_in_menu'        => true,
					'has_archive'         => false,
					'hierarchical'        => false,
					'rewrite'             => false,
					'exclude_from_search' => false,
					'menu_icon'           => 'dashicons-location-alt',
					'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
				)
			);
		}

		$taxonomy = (string) $this->config->get( 'taxonomy' );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				$cpt,
				array(
					'labels'            => array(
						'name'          => __( 'Níveis de SEO', 'local-rank-programmatic-seo' ),
						'singular_name' => __( 'Nível de SEO', 'local-rank-programmatic-seo' ),
					),
					'public'            => false,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'hierarchical'      => true,
					'rewrite'           => false,
				)
			);
		}
	}

	/**
	 * Fills the city select from the closed catalog.
	 *
	 * The field group itself stays in ACF. This only replaces the choices so a
	 * new city in the project appears in the editor without a second snippet.
	 *
	 * @param mixed $field ACF field array.
	 * @return mixed
	 */
	public function fill_city_choices( $field ) {
		if ( ! is_array( $field ) ) {
			return $field;
		}

		$cities = $this->config->get( 'cities' );
		if ( ! is_array( $cities ) || empty( $cities ) ) {
			return $field;
		}

		$choices = array();
		foreach ( $cities as $city ) {
			if ( ! is_array( $city ) || empty( $city['slug'] ) ) {
				continue;
			}
			$slug  = (string) $city['slug'];
			$name  = isset( $city['name'] ) ? (string) $city['name'] : $slug;
			$state = isset( $city['state'] ) ? (string) $city['state'] : '';
			$choices[ $slug ] = $name . ( '' !== $state ? ', ' . $state : '' ) . ' (' . $slug . ')';
		}

		if ( ! empty( $choices ) ) {
			$field['choices'] = $choices;
		}

		return $field;
	}

	/**
	 * Creates missing level terms.
	 */
	public function ensure_terms() {
		$taxonomy = (string) $this->config->get( 'taxonomy' );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$levels = $this->config->get( 'levels' );
		if ( ! is_array( $levels ) ) {
			return;
		}

		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			$slug = (string) $level['taxonomy_slug'];
			if ( '' === $slug || term_exists( $slug, $taxonomy ) ) {
				continue;
			}
			wp_insert_term(
				(string) $level['label'],
				$taxonomy,
				array( 'slug' => $slug )
			);
		}
	}

	/**
	 * Copies the ACF select onto the taxonomy term.
	 *
	 * @param int|string $post_id Post ID. ACF may pass a string for option pages.
	 */
	public function sync_level( $post_id ) {
		if ( ! is_numeric( $post_id ) ) {
			return;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( get_post_type( $post_id ) !== (string) $this->config->get( 'cpt' ) ) {
			return;
		}

		$value = (string) $this->reader->meta( $post_id, 'page_level' );
		if ( '' === $value ) {
			return;
		}

		$levels = $this->config->get( 'levels' );
		if ( ! is_array( $levels ) ) {
			return;
		}

		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) || (string) $level['acf_value'] !== $value ) {
				continue;
			}
			wp_set_object_terms( $post_id, array( (string) $level['taxonomy_slug'] ), (string) $this->config->get( 'taxonomy' ), false );
			return;
		}
	}

	/**
	 * Repairs every programmatic post from the diagnostics screen.
	 */
	public function sync_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Você não pode sincronizar os níveis.', 'local-rank-programmatic-seo' ) );
		}
		check_admin_referer( 'lrps_sync_levels' );

		$cpt   = (string) $this->config->get( 'cpt' );
		$paged = 1;
		$total = 0;

		do {
			$ids = get_posts(
				array(
					'post_type'      => $cpt,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 200,
					'paged'          => $paged,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			if ( ! is_array( $ids ) || empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $id ) {
				$this->sync_level( (int) $id );
				++$total;
			}
			++$paged;
		} while ( $paged <= 40 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'lrps',
					'tab'     => 'diagnostics',
					'synced'  => $total,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Keeps the level taxonomy out of the Yoast sitemap.
	 *
	 * @param bool   $excluded Current decision.
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	public function hide_taxonomy_sitemap( $excluded, $taxonomy ) {
		if ( (string) $taxonomy === (string) $this->config->get( 'taxonomy' ) ) {
			return true;
		}
		return $excluded;
	}
}
