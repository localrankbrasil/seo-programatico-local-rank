<?php
/**
 * The first screen: a form that builds the project, then hands the rest back.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Admin;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Support\Presets;
use LocalRank\ProgrammaticSeo\Support\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and reads the setup form.
 */
final class Setup {

	/**
	 * Form for a new client, or the same form filled with the saved answers.
	 *
	 * @param Repository          $config Configuration.
	 * @param array<string,mixed> $old    Values posted before an error.
	 */
	public static function render( Repository $config, array $old ) {
		$project = $config->all();
		$ready   = Wizard::is_ready( $project );
		$levels  = isset( $project['levels'] ) && is_array( $project['levels'] ) ? $project['levels'] : array();
		$model   = isset( $old['model'] ) ? (string) $old['model'] : Wizard::model_of( $levels );
		$parent  = Wizard::parent_of( $levels );
		$company = isset( $project['company']['name'] ) ? (string) $project['company']['name'] : '';

		$client = self::old( $old, 'client_name', $company );
		$trade  = self::old( $old, 'trade_label', isset( $project['trade_label'] ) ? (string) $project['trade_label'] : '' );
		$prefix = self::old( $old, 'prefix', isset( $project['prefix'] ) ? (string) $project['prefix'] : 'lrps' );
		$parent_slug  = self::old( $old, 'parent_slug', $parent['slug'] );
		$parent_label = self::old( $old, 'parent_label', $parent['label'] );
		$places = isset( $old['places'] ) ? (string) $old['places'] : Wizard::places_text( isset( $project['cities'] ) && is_array( $project['cities'] ) ? $project['cities'] : array() );
		$offers = isset( $old['offers'] ) ? (string) $old['offers'] : Wizard::offers_text( isset( $project['services'] ) && is_array( $project['services'] ) ? $project['services'] : array() );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=lrps&tab=comecar' ) ) . '" class="lrps-steps-form" id="lrps-setup">';
		wp_nonce_field( 'lrps_save' );
		echo '<input type="hidden" name="lrps_action" value="wizard" />';

		echo '<section class="lrps-card">';
		echo '<h2><span class="lrps-step-no">1</span>' . esc_html__( 'O cliente', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Estas respostas viram o nome dos shortcodes e o texto do schema. O plugin não publica página nenhuma sozinho.', 'local-rank-programmatic-seo' ) . '</p>';
		self::field( 'client_name', __( 'Nome do cliente', 'local-rank-programmatic-seo' ), $client, 'Prime House Flooring' );
		self::field( 'trade_label', __( 'O que ele faz', 'local-rank-programmatic-seo' ), $trade, 'Flooring Contractor' );
		self::field( 'prefix', __( 'Prefixo curto', 'local-rank-programmatic-seo' ), $prefix, 'phf', 'lrps-prefix' );
		echo '<p class="description">' . esc_html__( 'Duas a doze letras, sem espaço. phf vira [phf_city_state]. Use um prefixo novo para cada cliente.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</section>';

		echo '<section class="lrps-card">';
		echo '<h2><span class="lrps-step-no">2</span>' . esc_html__( 'Como as páginas ficam na URL', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Escolha um formato. O plugin cria os níveis, o breadcrumb e as rotas a partir dele.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<div class="lrps-choices">';
		foreach ( self::models() as $id => $choice ) {
			echo '<label class="lrps-choice">';
			echo '<input type="radio" name="model" value="' . esc_attr( $id ) . '"' . checked( $model, $id, false ) . ' />';
			echo '<span><strong>' . esc_html( $choice['label'] ) . '</strong>';
			echo '<span class="lrps-choice__text">' . esc_html( $choice['text'] ) . '</span>';
			echo '<code>' . esc_html( $choice['url'] ) . '</code></span>';
			echo '</label>';
		}
		echo '</div>';
		echo '<div class="lrps-parent" id="lrps-parent"' . ( 'static_service' === $model ? '' : ' hidden' ) . '>';
		self::field( 'parent_label', __( 'Nome da página que já existe', 'local-rank-programmatic-seo' ), $parent_label, 'Services' );
		self::field( 'parent_slug', __( 'Endereço dessa página', 'local-rank-programmatic-seo' ), $parent_slug, 'services' );
		echo '<p class="description">' . esc_html__( 'A página continua comum no WordPress. O programático nasce debaixo dela e ela entra no breadcrumb.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</div>';
		echo '</section>';

		echo '<section class="lrps-card">';
		echo '<h2><span class="lrps-step-no">3</span>' . esc_html__( 'Onde atende', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Uma cidade por linha: nome e estado. O slug nasce do nome. Só entram cidades que o cliente atende de verdade.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<textarea name="places" rows="8" class="large-text" placeholder="Greenwich, CT&#10;Stamford, CT">' . esc_textarea( $places ) . '</textarea>';
		echo '</section>';

		echo '<section class="lrps-card">';
		echo '<h2><span class="lrps-step-no">4</span>' . esc_html__( 'O que oferece', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Um serviço por linha. Cada serviço combina com cada cidade e vira uma página.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<textarea name="offers" rows="8" class="large-text" placeholder="Tile Installation&#10;Hardwood Flooring">' . esc_textarea( $offers ) . '</textarea>';
		echo '</section>';

		$button = $ready
			? __( 'Atualizar a estrutura', 'local-rank-programmatic-seo' )
			: __( 'Criar a estrutura', 'local-rank-programmatic-seo' );
		submit_button( $button );
		echo '</form>';
	}

	/**
	 * The three architectures, in plain language.
	 *
	 * @return array<string,array{label:string,text:string,url:string}>
	 */
	private static function models() {
		$catalog = Presets::catalog();
		return array(
			'city_service'       => array(
				'label' => $catalog['city_service']['label'],
				'text'  => 'A cidade é a primeira página. O serviço mora dentro dela.',
				'url'   => '/greenwich/  e  /greenwich/tile-installation/',
			),
			'static_service'     => array(
				'label' => $catalog['static_service']['label'],
				'text'  => 'Já existe uma página, como Services. O programático nasce debaixo dela.',
				'url'   => '/services/tile-installation/  e  /services/tile-installation/greenwich/',
			),
			'state_city_service' => array(
				'label' => $catalog['state_city_service']['label'],
				'text'  => 'Três níveis: o estado, a cidade dentro dele e o serviço na cidade.',
				'url'   => '/ct/  /ct/greenwich/  /ct/greenwich/tile-installation/',
			),
		);
	}

	/**
	 * One labeled text input.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Label.
	 * @param string $value       Value.
	 * @param string $placeholder Placeholder.
	 * @param string $id          Optional id.
	 */
	private static function field( $name, $label, $value, $placeholder, $id = '' ) {
		echo '<label class="lrps-stack"><span>' . esc_html( $label ) . '</span>';
		echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' />';
		echo '</label>';
	}

	/**
	 * Posted value, or the stored one.
	 *
	 * @param array<string,mixed> $old      Posted values.
	 * @param string              $key      Key.
	 * @param string              $fallback Stored value.
	 * @return string
	 */
	private static function old( array $old, $key, $fallback ) {
		if ( isset( $old[ $key ] ) && ! is_array( $old[ $key ] ) ) {
			return (string) $old[ $key ];
		}
		return $fallback;
	}
}
