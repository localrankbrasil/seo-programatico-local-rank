<?php
/**
 * Numbers and handoff text the team uses before opening ACF or WP All Import.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure view of a project: coverage, example URLs, unique keys and the nine steps.
 */
final class Playbook {

	const YOAST_TITLE = '%%title%% %%page%% %%sep%% %%sitename%%';

	/**
	 * Coverage and one example URL per level.
	 *
	 * @param array<string,mixed> $config Project.
	 * @return array{cities:int,services:int,pages:int,levels:array<int,array<string,mixed>>}
	 */
	public static function coverage( array $config ) {
		$cities   = isset( $config['cities'] ) && is_array( $config['cities'] ) ? $config['cities'] : array();
		$services = isset( $config['services'] ) && is_array( $config['services'] ) ? $config['services'] : array();
		$levels   = isset( $config['levels'] ) && is_array( $config['levels'] ) ? $config['levels'] : array();
		$sample   = self::sample( $cities, $services );
		$catalogs = array(
			'city'    => '' !== $sample['city_slug'] ? array( $sample['city_slug'] ) : array(),
			'service' => '' !== $sample['service_slug'] ? array( $sample['service_slug'] ) : array(),
			'state'   => '' !== $sample['state'] ? array( strtolower( $sample['state'] ) ) : array(),
		);

		$rows  = array();
		$pages = 0;
		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
			$count    = self::page_count( $segments, $cities, $services );
			$pages   += $count;
			$path     = Path_Builder::path(
				$level,
				array(
					'city'    => $sample['city_slug'],
					'service' => $sample['service_slug'],
					'state'   => strtolower( $sample['state'] ),
				),
				$catalogs
			);
			$rows[] = array(
				'key'           => isset( $level['key'] ) ? (string) $level['key'] : '',
				'label'         => isset( $level['label'] ) ? (string) $level['label'] : '',
				'example'       => '' === $path ? '' : '/' . $path . '/',
				'count'         => $count,
				'unique_key'    => self::unique_key_formula( $level ),
				'acf_value'     => isset( $level['acf_value'] ) ? (string) $level['acf_value'] : '',
				'taxonomy_slug' => isset( $level['taxonomy_slug'] ) ? (string) $level['taxonomy_slug'] : '',
			);
		}

		return array(
			'cities'   => count( $cities ),
			'services' => count( $services ),
			'pages'    => $pages,
			'levels'   => $rows,
		);
	}

	/**
	 * Unique key WP All Import must keep so a reimport updates the same post.
	 *
	 * City pages use {city_slug}__city. Service pages use
	 * {city_slug}__service__{service_slug}. The level key always sits between
	 * the place and the service.
	 *
	 * @param array<string,mixed> $level Level.
	 * @return string
	 */
	public static function unique_key_formula( array $level ) {
		$segments = isset( $level['segments'] ) && is_array( $level['segments'] ) ? $level['segments'] : array();
		$parts    = array();
		if ( in_array( 'city', $segments, true ) ) {
			$parts[] = '{city_slug}';
		} elseif ( in_array( 'state', $segments, true ) ) {
			$parts[] = '{state}';
		}
		$parts[] = isset( $level['key'] ) ? (string) $level['key'] : 'level';
		if ( in_array( 'service', $segments, true ) ) {
			$parts[] = '{service_slug}';
		}
		return implode( '__', $parts );
	}

	/**
	 * Header plus one draft row per level, using the first catalog entries.
	 *
	 * @param array<string,mixed> $config Project.
	 * @return string
	 */
	public static function csv_text( array $config ) {
		$cities   = isset( $config['cities'] ) && is_array( $config['cities'] ) ? $config['cities'] : array();
		$services = isset( $config['services'] ) && is_array( $config['services'] ) ? $config['services'] : array();
		$sample   = self::sample( $cities, $services );
		$trade    = isset( $config['trade_label'] ) ? trim( (string) $config['trade_label'] ) : '';
		$handle   = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return '';
		}

		fputcsv(
			$handle,
			array(
				'unique_key',
				'post_title',
				'post_status',
				'page_level',
				'level_term',
				'city_slug',
				'city_name',
				'city_state',
				'service_slug',
				'service_name',
				'city_content',
				'map_embed_url',
			)
		);

		foreach ( self::coverage( $config )['levels'] as $level ) {
			$unique = strtr(
				(string) $level['unique_key'],
				array(
					'{city_slug}'    => $sample['city_slug'],
					'{service_slug}' => $sample['service_slug'],
					'{state}'        => strtolower( $sample['state'] ),
				)
			);
			fputcsv(
				$handle,
				array(
					$unique,
					self::sample_title( $level, $sample, $trade ),
					'draft',
					(string) $level['acf_value'],
					(string) $level['taxonomy_slug'],
					false !== strpos( (string) $level['unique_key'], '{city_slug}' ) ? $sample['city_slug'] : '',
					false !== strpos( (string) $level['unique_key'], '{city_slug}' ) ? $sample['city_name'] : '',
					'' !== $sample['state'] && ( false !== strpos( (string) $level['unique_key'], '{city_slug}' ) || false !== strpos( (string) $level['unique_key'], '{state}' ) ) ? $sample['state'] : '',
					false !== strpos( (string) $level['unique_key'], '{service_slug}' ) ? $sample['service_slug'] : '',
					false !== strpos( (string) $level['unique_key'], '{service_slug}' ) ? $sample['service_name'] : '',
					'',
					'',
				)
			);
		}

		rewind( $handle );
		$text = stream_get_contents( $handle );
		fclose( $handle );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Fields to create by hand in ACF Pro.
	 *
	 * @param array<string,mixed> $config Project.
	 * @return array<int,array{name:string,type:string,note:string}>
	 */
	public static function acf_fields( array $config ) {
		$fields = isset( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : array();
		$levels = isset( $config['levels'] ) && is_array( $config['levels'] ) ? $config['levels'] : array();
		$values = array();
		foreach ( $levels as $level ) {
			if ( is_array( $level ) && ! empty( $level['acf_value'] ) ) {
				$values[] = (string) $level['acf_value'];
			}
		}

		$name = static function ( $key, $fallback ) use ( $fields ) {
			return isset( $fields[ $key ] ) && '' !== (string) $fields[ $key ] ? (string) $fields[ $key ] : $fallback;
		};

		return array(
			array(
				'name' => $name( 'page_level', 'page_level' ),
				'type' => 'Select',
				'note' => 'Um valor por nível: ' . implode( ', ', $values ) . '. O plugin copia a escolha para a taxonomia.',
			),
			array(
				'name' => $name( 'city_slug', 'city_slug' ),
				'type' => 'Select',
				'note' => 'As opções saem do catálogo de cidades. Não mantenha essa lista na mão.',
			),
			array(
				'name' => $name( 'city_name', 'city_name' ),
				'type' => 'Texto',
				'note' => 'Nome visível da cidade.',
			),
			array(
				'name' => $name( 'city_state', 'city_state' ),
				'type' => 'Texto',
				'note' => 'Sigla do estado, igual à do catálogo.',
			),
			array(
				'name' => $name( 'service_slug', 'service_slug' ),
				'type' => 'Texto',
				'note' => 'Slug do serviço, igual ao catálogo.',
			),
			array(
				'name' => $name( 'service_name', 'service_name' ),
				'type' => 'Texto',
				'note' => 'Nome visível. Se ficar vazio, o plugin usa o nome do catálogo.',
			),
			array(
				'name' => $name( 'map_embed', 'map_embed_url' ),
				'type' => 'URL',
				'note' => 'Somente https://www.google.com/maps/embed ou https://google.com/maps/embed.',
			),
			array(
				'name' => $name( 'city_content', 'city_content' ),
				'type' => 'Editor',
				'note' => 'Texto localizado desta combinação. Sem endereço de filial inventado.',
			),
			array(
				'name' => $name( 'faqs', 'faqs' ),
				'type' => 'Repeater',
				'note' => 'Subcampos ' . $name( 'faq_question', 'faq_question' ) . ' e ' . $name( 'faq_answer', 'faq_answer' ) . '.',
			),
		);
	}

	/**
	 * The nine stages, and which tool still owns each one.
	 *
	 * @return array<int,array{id:string,etapa:string,title:string,where:string,text:string}>
	 */
	public static function steps() {
		return array(
			array(
				'id'    => 'estrategia',
				'etapa' => '01',
				'title' => 'Definir a arquitetura',
				'where' => 'Este plugin',
				'text'  => 'Escolha o modelo abaixo ou descreva os níveis no JSON. Confira a URL de exemplo e a conta de páginas antes de criar campo ou planilha. Dois níveis e três níveis são projetos diferentes; o prefixo phf é só da Prime House.',
			),
			array(
				'id'    => 'estrutura',
				'etapa' => '02',
				'title' => 'CPT, taxonomia e campos',
				'where' => 'ACF Pro',
				'text'  => 'Crie o grupo no ACF com os field names desta tela. O plugin não registra o grupo. Ele cria o CPT e a taxonomia se ainda não existirem, garante os termos e preenche o select de cidade.',
			),
			array(
				'id'    => 'logica',
				'etapa' => '03',
				'title' => 'Ligar a lógica dinâmica',
				'where' => 'Este plugin',
				'text'  => 'Salve o projeto. As rotas, os shortcodes e o schema passam a existir. Desative snippets PHP antigos que registrem as mesmas URLs ou os mesmos shortcodes. CSS de layout pode ficar.',
			),
			array(
				'id'    => 'conteudo',
				'etapa' => '04',
				'title' => 'Diretriz do texto localizado',
				'where' => 'Planilha',
				'text'  => 'Cada linha cobre uma combinação que o cliente atende de verdade. Não invente endereço, filial, equipe ou prova. Página localizada é um Service com areaServed, não um LocalBusiness por cidade.',
			),
			array(
				'id'    => 'templates',
				'etapa' => '05',
				'title' => 'Templates',
				'where' => 'Elementor',
				'text'  => 'O layout continua no Elementor. A aba Referência lista os shortcodes e as Dynamic Tags deste prefixo. O schema entra no fim do template, não no wp_head.',
			),
			array(
				'id'    => 'planilha',
				'etapa' => '06',
				'title' => 'Planilha de importação',
				'where' => 'WP All Import',
				'text'  => 'Use o CSV de exemplo. A coluna unique_key é o identificador único. A mesma chave na reimportação atualiza o post. Uma chave nova cria outro post.',
			),
			array(
				'id'    => 'producao',
				'etapa' => '07',
				'title' => 'Produzir as linhas',
				'where' => 'Planilha',
				'text'  => 'Uma linha por página da conta de cobertura. post_status pode nascer como draft no piloto. page_level e level_term precisam ser os valores do nível, não o nome bonito.',
			),
			array(
				'id'    => 'piloto',
				'etapa' => '08',
				'title' => 'Importar um piloto',
				'where' => 'WP All Import',
				'text'  => 'Importe poucas linhas. Abra cada URL, o breadcrumb, o canonical e o schema. Corrija o modelo antes do lote. A aba Diagnóstico precisa estar sem bloqueio.',
			),
			array(
				'id'    => 'importacao',
				'etapa' => '09',
				'title' => 'Importar o lote',
				'where' => 'WP All Import',
				'text'  => 'Rode a importação completa com a mesma unique_key. Combinação duplicada de nível, cidade e serviço não escolhe um vencedor: a URL responde 404. O Yoast continua dono do título, com o padrão desta tela.',
			),
		);
	}

	/**
	 * First city and service, used only as an example.
	 *
	 * @param array<int,mixed> $cities   Cities.
	 * @param array<int,mixed> $services Services.
	 * @return array{city_slug:string,city_name:string,state:string,service_slug:string,service_name:string}
	 */
	private static function sample( array $cities, array $services ) {
		$city    = isset( $cities[0] ) && is_array( $cities[0] ) ? $cities[0] : array();
		$service = isset( $services[0] ) && is_array( $services[0] ) ? $services[0] : array();
		return array(
			'city_slug'    => isset( $city['slug'] ) ? (string) $city['slug'] : '',
			'city_name'    => isset( $city['name'] ) ? (string) $city['name'] : '',
			'state'        => isset( $city['state'] ) ? (string) $city['state'] : '',
			'service_slug' => isset( $service['slug'] ) ? (string) $service['slug'] : '',
			'service_name' => isset( $service['display_name'] ) ? (string) $service['display_name'] : '',
		);
	}

	/**
	 * How many posts this level needs.
	 *
	 * @param string[]         $segments Segments.
	 * @param array<int,mixed> $cities   Cities.
	 * @param array<int,mixed> $services Services.
	 * @return int
	 */
	private static function page_count( array $segments, array $cities, array $services ) {
		if ( empty( $segments ) ) {
			return 0;
		}

		$count = 1;
		if ( in_array( 'city', $segments, true ) ) {
			$count *= count( $cities );
		}
		if ( in_array( 'service', $segments, true ) ) {
			$count *= count( $services );
		}
		if ( in_array( 'state', $segments, true ) && ! in_array( 'city', $segments, true ) ) {
			$states = array();
			foreach ( $cities as $city ) {
				if ( ! is_array( $city ) ) {
					continue;
				}
				$code = strtoupper( trim( (string) ( isset( $city['state'] ) ? $city['state'] : '' ) ) );
				if ( '' !== $code ) {
					$states[ $code ] = true;
				}
			}
			$count *= count( $states );
		}

		return $count;
	}

	/**
	 * Draft title for the sample row.
	 *
	 * @param array<string,mixed>                                               $level  Level row.
	 * @param array{city_slug:string,city_name:string,state:string,service_slug:string,service_name:string} $sample Sample.
	 * @param string                                                            $trade  Trade label.
	 * @return string
	 */
	private static function sample_title( array $level, array $sample, $trade ) {
		$formula = (string) $level['unique_key'];
		$place   = $sample['city_name'];
		if ( '' !== $sample['state'] && '' !== $place ) {
			$place .= ', ' . $sample['state'];
		}
		if ( false !== strpos( $formula, '{service_slug}' ) && '' !== $sample['service_name'] && '' !== $place ) {
			return $sample['service_name'] . ' in ' . $place;
		}
		if ( false !== strpos( $formula, '{service_slug}' ) && '' !== $sample['service_name'] ) {
			return $sample['service_name'];
		}
		if ( '' !== $place ) {
			return '' !== $trade ? $trade . ' in ' . $place : $place;
		}
		if ( '' !== $sample['state'] ) {
			return $sample['state'];
		}
		return (string) $level['label'];
	}
}
