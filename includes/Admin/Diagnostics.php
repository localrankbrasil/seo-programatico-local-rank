<?php
/**
 * Operational checks for the stage 3 gates.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Admin;

use LocalRank\ProgrammaticSeo\Modules\Urls_Module;
use LocalRank\ProgrammaticSeo\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the checks a producer needs before templates and import.
 */
final class Diagnostics {

	/**
	 * Runs every gate.
	 *
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	public static function run() {
		$plugin = Plugin::instance();
		$config = $plugin->config()->all();
		$checks = array();

		$checks[] = post_type_exists( $config['cpt'] )
			? self::ok( 'Estrutura', 'CPT disponível', $config['cpt'] )
			: self::fail( 'Estrutura', 'CPT ausente', 'Ative o módulo de estrutura ou registre ' . $config['cpt'] . ' na etapa 02.' );

		$checks[] = taxonomy_exists( $config['taxonomy'] )
			? self::ok( 'Estrutura', 'Taxonomia disponível', $config['taxonomy'] )
			: self::fail( 'Estrutura', 'Taxonomia ausente', $config['taxonomy'] );

		$checks = array_merge( $checks, self::terms( $config ) );
		$checks[] = function_exists( 'get_field' )
			? self::ok( 'Estrutura', 'ACF disponível', 'Os campos continuam sendo editados no ACF Pro. O plugin só lê.' )
			: self::warn( 'Estrutura', 'ACF não encontrado', 'A leitura cai para post meta. Confirme os nomes dos campos antes de importar.' );

		$cities = is_array( $config['cities'] ) ? count( $config['cities'] ) : 0;
		$services = is_array( $config['services'] ) ? count( $config['services'] ) : 0;
		if ( $cities > 0 && $services > 0 ) {
			$checks[] = self::ok( 'Catálogo', 'Cidades e serviços carregados', $cities . ' cidades e ' . $services . ' serviços.' );
		} else {
			$checks[] = self::warn( 'Catálogo', 'Catálogo incompleto', 'Sem cidades ou serviços, as regras de URL não são registradas.' );
		}

		$trade = (string) $config['trade_label'];
		$city_template = isset( $config['schema']['city_name'] ) ? (string) $config['schema']['city_name'] : '';
		if ( '' === $trade && false !== strpos( $city_template, '{trade_label}' ) ) {
			$checks[] = self::warn( 'Schema', 'Rótulo do ofício vazio', 'O nome das páginas de cidade usa {trade_label}. Preencha o rótulo ou troque o modelo.' );
		} else {
			$checks[] = self::ok( 'Schema', 'Modelo de nome configurável', 'Cidade: ' . $city_template );
		}

		$checks = array_merge( $checks, self::rewrites( $config, $cities, $services ) );
		$checks = array_merge( $checks, self::collisions( $config ) );
		$checks = array_merge( $checks, self::duplicates( $config ) );
		$checks = array_merge( $checks, self::templates( $config ) );
		$checks = array_merge( $checks, self::company( $config ) );
		$checks = array_merge( $checks, self::sample( $plugin, $config ) );

		if ( $plugin->config()->module( 'elementor' ) ) {
			$checks[] = class_exists( '\Elementor\Plugin' )
				? self::ok( 'Elementor', 'Elementor ativo', 'Dynamic Tags e Saved Templates podem ser ligados nos templates.' )
				: self::warn( 'Elementor', 'Elementor inativo', 'O módulo está ligado, mas o plugin Elementor não está.' );
		}

		$checks[] = defined( 'WPSEO_VERSION' )
			? self::ok( 'SEO', 'Yoast ativo', 'O canonical programático entra pelo filtro do Yoast. Confira o @id da Organization no grafo.' )
			: self::warn( 'SEO', 'Yoast não encontrado', 'O canonical do núcleo do WordPress ainda é filtrado. O provider @id usa /#organization.' );

		$published = 0;
		if ( post_type_exists( $config['cpt'] ) ) {
			$counts = wp_count_posts( $config['cpt'] );
			$published = ( $counts && isset( $counts->publish ) ) ? (int) $counts->publish : 0;
		}
		$checks[] = self::ok( 'Operação', 'Registros publicados', (string) $published );

		return $checks;
	}

	/**
	 * Confirms each level term exists.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function terms( array $config ) {
		$checks = array();
		if ( ! taxonomy_exists( $config['taxonomy'] ) || ! is_array( $config['levels'] ) ) {
			return $checks;
		}
		foreach ( $config['levels'] as $level ) {
			$slug = (string) $level['taxonomy_slug'];
			$checks[] = term_exists( $slug, $config['taxonomy'] )
				? self::ok( 'Estrutura', 'Termo ' . $slug, (string) $level['label'] . ' → ' . (string) $level['acf_value'] )
				: self::fail( 'Estrutura', 'Termo ausente: ' . $slug, 'Recarregue o admin com o módulo de estrutura ativo ou sincronize os níveis.' );
		}
		return $checks;
	}

	/**
	 * Checks that rewrite rules exist when catalogs can build them.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @param int                 $cities City count.
	 * @param int                 $services Service count.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function rewrites( array $config, $cities, $services ) {
		if ( empty( $config['modules']['urls'] ) ) {
			return array( self::warn( 'URLs', 'Módulo de URLs desligado', 'As rotas públicas não serão registradas.' ) );
		}
		if ( $cities < 1 ) {
			return array( self::warn( 'URLs', 'Regras aguardando catálogo', 'Nenhuma cidade aprovada, então nenhuma rota foi criada.' ) );
		}

		global $wp_rewrite;
		$rules = ( $wp_rewrite instanceof \WP_Rewrite ) ? $wp_rewrite->wp_rewrite_rules() : array();
		$found = false;
		if ( is_array( $rules ) ) {
			foreach ( $rules as $query ) {
				if ( is_string( $query ) && false !== strpos( $query, 'lrps_city=' ) ) {
					$found = true;
					break;
				}
			}
		}

		$hash = (string) get_option( Urls_Module::REWRITE_OPTION, '' );
		if ( $found ) {
			return array( self::ok( 'URLs', 'Regras presentes', 'Hash ' . ( '' !== $hash ? $hash : 'ainda não gravado' ) . '.' ) );
		}

		$detail = $services < 1
			? 'Há cidades, mas o nível de serviço fica sem regra enquanto o catálogo de serviços estiver vazio. A regra de cidade deveria existir: salve o projeto e abra esta tela de novo.'
			: 'Salve o projeto para regenerar os permalinks. O hash atual é ' . ( '' !== $hash ? $hash : 'vazio' ) . '.';
		return array( self::warn( 'URLs', 'Regras ainda não aparecem', $detail ) );
	}

	/**
	 * Finds pages and posts that use a city slug.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function collisions( array $config ) {
		$slugs = array();
		foreach ( $config['cities'] as $city ) {
			$slugs[] = (string) $city['slug'];
		}
		$slugs = array_slice( array_values( array_unique( $slugs ) ), 0, 300 );
		if ( empty( $slugs ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $slugs ), '%s' ) );
		$args = array_merge( array( (string) $config['cpt'] ), $slugs );
		$sql  = $wpdb->prepare(
			"SELECT post_type, post_name, post_status FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','pending') AND post_type <> %s AND post_name IN ($placeholders) LIMIT 30",
			...$args
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array( self::ok( 'URLs', 'Sem colisão de slug', 'Nenhuma página ou post publicado usa o slug de uma cidade do catálogo.' ) );
		}

		$bits = array();
		foreach ( $rows as $row ) {
			$bits[] = $row['post_type'] . '/' . $row['post_name'] . ' (' . $row['post_status'] . ')';
		}
		return array( self::fail( 'URLs', 'Slug de cidade já usado', implode( ', ', $bits ) ) );
	}

	/**
	 * Finds duplicated city, level and service combinations.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function duplicates( array $config ) {
		if ( ! post_type_exists( $config['cpt'] ) ) {
			return array();
		}

		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT city.meta_value AS city_slug, lvl.meta_value AS page_level, COALESCE(svc.meta_value, '') AS service_slug, COUNT(*) AS total
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} city ON city.post_id = p.ID AND city.meta_key = %s
			INNER JOIN {$wpdb->postmeta} lvl ON lvl.post_id = p.ID AND lvl.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} svc ON svc.post_id = p.ID AND svc.meta_key = %s
			WHERE p.post_type = %s AND p.post_status = 'publish'
			GROUP BY city.meta_value, lvl.meta_value, COALESCE(svc.meta_value, '')
			HAVING COUNT(*) > 1
			LIMIT 20",
			$config['fields']['city_slug'],
			$config['fields']['page_level'],
			$config['fields']['service_slug'],
			$config['cpt']
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array( self::ok( 'Core', 'Sem combinação duplicada', 'Cada cidade, nível e serviço publicado aparece uma vez.' ) );
		}

		$bits = array();
		foreach ( $rows as $row ) {
			$bits[] = $row['city_slug'] . ' / ' . $row['page_level'] . ' / ' . ( '' !== $row['service_slug'] ? $row['service_slug'] : '—' ) . ' (' . $row['total'] . ')';
		}
		return array( self::fail( 'Core', 'Combinações duplicadas', implode( '; ', $bits ) ) );
	}

	/**
	 * Checks Saved Template IDs declared in the service catalog.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function templates( array $config ) {
		$checks = array();
		$missing = array();
		$empty_galleries = array();
		foreach ( $config['services'] as $service ) {
			if ( empty( $service['gallery_template_id'] ) ) {
				$empty_galleries[] = (string) $service['slug'];
			}
			foreach ( array( 'problems_template_id' => 'Problems', 'gallery_template_id' => 'Gallery' ) as $key => $label ) {
				$id = isset( $service[ $key ] ) ? (int) $service[ $key ] : 0;
				if ( $id <= 0 ) {
					continue;
				}
				$post = get_post( $id );
				if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || 'elementor_library' !== $post->post_type ) {
					$missing[] = $service['slug'] . ' ' . $label . ' #' . $id;
				}
			}
		}

		if ( empty( $config['services'] ) ) {
			return $checks;
		}
		$checks[] = empty( $missing )
			? self::ok( 'Componentes', 'Templates informados existem', 'IDs 0 continuam vazios de propósito e não usam outro serviço.' )
			: self::fail( 'Componentes', 'Saved Template ausente', implode( ', ', $missing ) );

		if ( ! empty( $empty_galleries ) ) {
			$checks[] = self::ok( 'Componentes', 'Galerias vazias', implode( ', ', $empty_galleries ) . ' retornam vazio, sem fallback.' );
		}
		return $checks;
	}

	/**
	 * Checks the business entity when that module is enabled.
	 *
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function company( array $config ) {
		if ( empty( $config['modules']['company_schema'] ) && empty( $config['company']['enabled'] ) ) {
			return array( self::ok( 'Schema', 'Entidade da empresa desligada', 'Nenhuma cidade de atendimento será publicada como filial.' ) );
		}

		$company = $config['company'];
		$ready = '' !== $company['name'] && '' !== $company['street'] && '' !== $company['locality'] && '' !== $company['region'] && '' !== $company['postal'] && '' !== $company['country'];
		if ( ! $ready ) {
			return array( self::fail( 'Schema', 'Endereço da empresa incompleto', 'LocalBusiness só é emitido com rua, cidade, estado, CEP e país reais. Páginas locais não recebem esse endereço.' ) );
		}
		return array( self::ok( 'Schema', 'Endereço principal completo', $company['name'] . ', ' . $company['locality'] . ' ' . $company['region'] ) );
	}

	/**
	 * Looks at a small published sample for level divergence.
	 *
	 * @param Plugin              $plugin Plugin.
	 * @param array<string,mixed> $config Configuration.
	 * @return array<int,array{status:string,gate:string,label:string,detail:string}>
	 */
	private static function sample( Plugin $plugin, array $config ) {
		if ( ! post_type_exists( $config['cpt'] ) ) {
			return array();
		}

		$ids = get_posts(
			array(
				'post_type'              => $config['cpt'],
				'post_status'            => 'publish',
				'posts_per_page'         => 40,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array( self::warn( 'Core', 'Nenhuma página publicada', 'O contexto só pode ser conferido depois dos registros de teste.' ) );
		}

		$diverged = 0;
		$invalid  = 0;
		foreach ( $ids as $id ) {
			$record = $plugin->reader()->read( (int) $id );
			if ( ! empty( $record['diverged'] ) ) {
				++$diverged;
			}
			if ( empty( $record['valid'] ) ) {
				++$invalid;
			}
		}

		$checks = array();
		$checks[] = 0 === $diverged
			? self::ok( 'Core', 'Amostra sem divergência de nível', count( $ids ) . ' registros comparando taxonomia e select.' )
			: self::fail( 'Core', 'Nível divergente na amostra', $diverged . ' de ' . count( $ids ) . '. Use sincronizar níveis ou corrija o select.' );
		if ( $invalid > 0 ) {
			$checks[] = self::warn( 'Core', 'Registros fora do catálogo', $invalid . ' de ' . count( $ids ) . ' não formam um contexto válido. Confira slug, estado e serviço.' );
		}
		return $checks;
	}

	/**
	 * Ok row.
	 *
	 * @param string $gate Gate.
	 * @param string $label Label.
	 * @param string $detail Detail.
	 * @return array{status:string,gate:string,label:string,detail:string}
	 */
	private static function ok( $gate, $label, $detail ) {
		return array(
			'status' => 'ok',
			'gate'   => $gate,
			'label'  => $label,
			'detail' => $detail,
		);
	}

	/**
	 * Warning row.
	 *
	 * @param string $gate Gate.
	 * @param string $label Label.
	 * @param string $detail Detail.
	 * @return array{status:string,gate:string,label:string,detail:string}
	 */
	private static function warn( $gate, $label, $detail ) {
		return array(
			'status' => 'warn',
			'gate'   => $gate,
			'label'  => $label,
			'detail' => $detail,
		);
	}

	/**
	 * Failure row.
	 *
	 * @param string $gate Gate.
	 * @param string $label Label.
	 * @param string $detail Detail.
	 * @return array{status:string,gate:string,label:string,detail:string}
	 */
	private static function fail( $gate, $label, $detail ) {
		return array(
			'status' => 'fail',
			'gate'   => $gate,
			'label'  => $label,
			'detail' => $detail,
		);
	}
}
