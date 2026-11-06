<?php
/**
 * Shortcode and Dynamic Tag inventory for stage 5.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists the bindings a template author needs.
 */
final class Reference {

	/**
	 * Shortcodes produced by the active configuration.
	 *
	 * @param array<string,mixed> $config Sanitized configuration.
	 * @return array<int,array{code:string,use:string}>
	 */
	public static function shortcodes( array $config ) {
		$prefix = isset( $config['prefix'] ) ? (string) $config['prefix'] : 'lrps';
		$items  = array(
			self::item( $prefix, 'city', 'Nome da cidade. Alias de city_name, usado nos snippets antigos.' ),
			self::item( $prefix, 'city_name', 'Nome da cidade do registro atual.' ),
			self::item( $prefix, 'state', 'Sigla do estado do registro atual.' ),
			self::item( $prefix, 'city_state', 'Cidade e estado, no formato usado nos textos.' ),
			self::item( $prefix, 'service_name', 'Nome de exibição do serviço. Vazio fora do nível de serviço.' ),
			self::item( $prefix, 'service_hero_suffix', 'Complemento da hero vindo do catálogo do serviço.' ),
			self::item( $prefix, 'service_hero_description', 'Descrição da hero do serviço, com tokens já expandidos.' ),
			self::item( $prefix, 'page_title', 'Nome da página usado no schema e em H1 dinâmicos.' ),
			self::item( $prefix, 'current_url', 'Permalink canônico do registro atual.' ),
			self::item( $prefix, 'city_content', 'Conteúdo editorial localizado do registro.' ),
			self::item( $prefix, 'location_url', 'Permalink publicado. Use service="slug" ou target="city".' ),
			self::item( $prefix, 'map_embed_url', 'URL HTTPS de embed do Google Maps, ou vazio.' ),
			self::item( $prefix, 'faq_question', 'Pergunta do repeater. Use index="1" em diante.' ),
			self::item( $prefix, 'faq_answer', 'Resposta do mesmo índice do repeater.' ),
			self::item( $prefix, 'breadcrumbs', 'Trilha visual Home, áreas e página atual.' ),
			self::item( $prefix, 'problems_section', 'Saved Template de problemas do serviço atual.' ),
			self::item( $prefix, 'service_problems', 'Alias de problems_section.' ),
			self::item( $prefix, 'service_gallery', 'Saved Template de galeria do serviço. Vazio quando o ID é 0.' ),
		);

		$levels = isset( $config['levels'] ) && is_array( $config['levels'] ) ? $config['levels'] : array();
		foreach ( $levels as $level ) {
			$code = isset( $level['schema_shortcode'] ) ? (string) $level['schema_shortcode'] : '';
			$label = isset( $level['label'] ) ? (string) $level['label'] : $code;
			if ( '' === $code ) {
				continue;
			}
			$items[] = self::item( $prefix, $code, 'JSON-LD de Service do nível ' . $label . '. Inserir uma vez no final do template.' );
		}

		$modules = isset( $config['modules'] ) && is_array( $config['modules'] ) ? $config['modules'] : array();
		if ( ! empty( $modules['section_label'] ) ) {
			$items[] = array(
				'code' => '[section_label tag="h2" text="TEXTO EM {city_state}"]',
				'use'  => 'Rótulo de seção. A classe fica no próprio título. Use tokens entre chaves; não aninhe outro shortcode no atributo.',
			);
		}
		if ( ! empty( $modules['html_sitemap'] ) ) {
			$items[] = self::item( $prefix, 'html_sitemap', 'Lista páginas, posts e a árvore cidade → serviço. group="none" tira os títulos de estado.' );
		}

		return $items;
	}

	/**
	 * Dynamic Tags registered in Elementor.
	 *
	 * @param array<string,mixed> $config Sanitized configuration.
	 * @return array<int,array{code:string,use:string}>
	 */
	public static function dynamic_tags( array $config ) {
		$prefix = isset( $config['prefix'] ) ? (string) $config['prefix'] : 'lrps';
		$rows   = array(
			array( 'city', 'Texto', 'Nome da cidade.' ),
			array( 'state', 'Texto', 'Estado.' ),
			array( 'city-state', 'Texto', 'Cidade e estado.' ),
			array( 'service-name', 'Texto', 'Nome do serviço.' ),
			array( 'service-hero-suffix', 'Texto', 'Sufixo da hero.' ),
			array( 'service-hero-description', 'Texto', 'Descrição da hero.' ),
			array( 'service-cover-alt', 'Texto', 'Alt da imagem interna: serviço em cidade, estado.' ),
			array( 'page-title', 'Texto', 'Nome calculado da página.' ),
			array( 'location-url', 'URL', 'Link do serviço configurado na tag, ou da cidade quando o serviço fica vazio.' ),
			array( 'map-embed-url', 'URL', 'Src do iframe. Preferir esta tag a um shortcode dentro do HTML.' ),
			array( 'service-hero-image', 'Imagem', 'Background da hero do nível de serviço.' ),
			array( 'service-cover-image', 'Imagem', 'Imagem interna do serviço. Mapa separado da hero.' ),
			array( 'faq-question', 'Texto', 'Pergunta pelo índice.' ),
			array( 'faq-answer', 'Texto', 'Resposta pelo mesmo índice.' ),
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'code' => $prefix . '-' . $row[0],
				'use'  => $row[1] . ' — ' . $row[2],
			);
		}

		return $items;
	}

	/**
	 * Tokens expanded inside Elementor and section labels.
	 *
	 * @return string[]
	 */
	public static function tokens() {
		return array(
			'{city_name}',
			'{city}',
			'{state}',
			'{city_state}',
			'{service_name}',
			'{service}',
			'{service_slug}',
			'{trade_label}',
		);
	}

	/**
	 * Builds one shortcode row.
	 *
	 * @param string $prefix Prefix.
	 * @param string $name   Shortcode suffix.
	 * @param string $use    What it is for.
	 * @return array{code:string,use:string}
	 */
	private static function item( $prefix, $name, $use ) {
		return array(
			'code' => '[' . $prefix . '_' . $name . ']',
			'use'  => $use,
		);
	}
}
