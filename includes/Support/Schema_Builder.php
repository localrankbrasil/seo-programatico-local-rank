<?php
/**
 * Localized Service graph. Coverage is areaServed, never a street address.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assembles the Service node described by the stage 3 contract.
 */
final class Schema_Builder {

	/**
	 * Builds a schema.org graph or returns null when the context is incomplete.
	 *
	 * @param array<string,mixed> $args name, type, url, provider_id, city, state, provider.
	 * @return array<string,mixed>|null
	 */
	public static function service_graph( array $args ) {
		$name        = self::plain( isset( $args['name'] ) ? $args['name'] : '' );
		$type        = self::plain( isset( $args['type'] ) ? $args['type'] : '' );
		$url         = trim( (string) ( isset( $args['url'] ) ? $args['url'] : '' ) );
		$provider_id = trim( (string) ( isset( $args['provider_id'] ) ? $args['provider_id'] : '' ) );
		$city        = self::plain( isset( $args['city'] ) ? $args['city'] : '' );
		$state       = self::plain( isset( $args['state'] ) ? $args['state'] : '' );

		if ( '' === $name || '' === $type || '' === $url || '' === $provider_id || '' === $city || '' === $state ) {
			return null;
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return null;
		}

		$service = array(
			'@type'       => 'Service',
			'@id'         => $url . '#service',
			'url'         => $url,
			'name'        => $name,
			'serviceType' => $type,
			'provider'    => array(
				'@id' => $provider_id,
			),
			'areaServed'  => array(
				'@type'            => 'City',
				'name'             => $city,
				'containedInPlace' => array(
					'@type' => 'State',
					'name'  => $state,
				),
			),
		);

		$graph = array();
		if ( isset( $args['provider'] ) && is_array( $args['provider'] ) ) {
			$graph[] = $args['provider'];
		}
		$graph[] = $service;

		if ( isset( $args['breadcrumb'] ) && is_array( $args['breadcrumb'] ) ) {
			$graph[] = $args['breadcrumb'];
		}

		if ( isset( $args['faq'] ) && is_array( $args['faq'] ) ) {
			$graph[] = $args['faq'];
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}

	/**
	 * Builds a FAQPage node from aligned question and answer pairs.
	 *
	 * @param array<int,array{question:string,answer:string}> $pairs Visible pairs.
	 * @return array<string,mixed>|null
	 */
	public static function faq_page( array $pairs ) {
		$entities = array();
		foreach ( $pairs as $pair ) {
			$question = self::plain( isset( $pair['question'] ) ? $pair['question'] : '' );
			$answer   = self::plain( isset( $pair['answer'] ) ? $pair['answer'] : '' );
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $entities ) ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	/**
	 * Builds a BreadcrumbList from already ordered items.
	 *
	 * @param array<int,array{name:string,url:string}> $items Crumbs.
	 * @return array<string,mixed>|null
	 */
	public static function breadcrumb_list( array $items ) {
		$list = array();
		$position = 1;
		foreach ( $items as $item ) {
			$name = self::plain( isset( $item['name'] ) ? $item['name'] : '' );
			$url  = trim( (string) ( isset( $item['url'] ) ? $item['url'] : '' ) );
			if ( '' === $name || '' === $url ) {
				return null;
			}
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $name,
				'item'     => $url,
			);
			++$position;
		}

		if ( count( $list ) < 2 ) {
			return null;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		);
	}

	/**
	 * Fills a template and rejects names that lost their place tokens.
	 *
	 * @param string               $template Template with {tokens}.
	 * @param array<string,string> $vars     Token values.
	 * @return string
	 */
	public static function render_template( $template, array $vars ) {
		$template = (string) $template;
		if ( preg_match_all( '/\{([a-z0-9_]+)\}/', $template, $matches ) ) {
			foreach ( $matches[1] as $token ) {
				if ( ! isset( $vars[ $token ] ) || '' === trim( (string) $vars[ $token ] ) ) {
					return '';
				}
			}
		}

		$rendered = trim( Token_Expander::expand( $template, $vars ) );
		if ( '' === $rendered ) {
			return '';
		}

		$city = isset( $vars['city_name'] ) ? trim( (string) $vars['city_name'] ) : '';
		if ( '' !== $city && false === strpos( $rendered, $city ) ) {
			return '';
		}

		return $rendered;
	}

	/**
	 * Removes tags and collapses whitespace.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function plain( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
		return $value;
	}
}
