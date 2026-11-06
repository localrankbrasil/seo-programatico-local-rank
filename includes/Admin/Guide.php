<?php
/**
 * The operating checklist for one programmatic project.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Admin;

use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Support\Playbook;
use LocalRank\ProgrammaticSeo\Support\Presets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the architecture, the handoff to ACF, Elementor, WP All Import and Yoast,
 * and a checklist the team can tick.
 */
final class Guide {

	const CHECKLIST_OPTION = 'lrps_checklist';

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * @param Repository $config Configuration.
	 */
	public function __construct( Repository $config ) {
		$this->config = $config;
	}

	/**
	 * Renders the roteiro tab.
	 */
	public function render() {
		$config   = $this->config->all();
		$coverage = Playbook::coverage( $config );
		$prefix   = isset( $config['prefix'] ) ? (string) $config['prefix'] : 'lrps';
		$done     = get_option( self::CHECKLIST_OPTION, array() );
		$done     = is_array( $done ) ? $done : array();

		echo '<div class="lrps-grid">';
		$this->handoff_card( $config, $prefix );
		$this->coverage_card( $coverage, $prefix );
		$this->preset_card( $config );
		$this->acf_card( $config );
		$this->import_card( $config );
		$this->yoast_card();
		$this->steps_card( $done );
		echo '</div>';
	}

	/**
	 * Saves the checklist from the roteiro form.
	 */
	public function save_checklist() {
		$posted = isset( $_POST['done'] ) && is_array( $_POST['done'] ) ? wp_unslash( $_POST['done'] ) : array();
		$done   = array();
		foreach ( Playbook::steps() as $step ) {
			$id          = (string) $step['id'];
			$done[ $id ] = ! empty( $posted[ $id ] );
		}
		update_option( self::CHECKLIST_OPTION, $done, false );
	}

	/**
	 * What the person still does after the form creates the structure.
	 *
	 * @param array<string,mixed> $config Project.
	 * @param string              $prefix Prefix.
	 */
	private function handoff_card( array $config, $prefix ) {
		$client = isset( $config['company']['name'] ) ? trim( (string) $config['company']['name'] ) : '';
		$trade  = isset( $config['trade_label'] ) ? trim( (string) $config['trade_label'] ) : '';
		$who    = '' !== $client ? $client : __( 'este cliente', 'local-rank-programmatic-seo' );
		$url    = add_query_arg(
			array(
				'page' => 'lrps',
				'tab'  => 'comecar',
			),
			admin_url( 'admin.php' )
		);

		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'O que você faz agora', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html( sprintf(
			/* translators: 1: client name, 2: prefix */
			__( 'A estrutura de %1$s já está criada. As rotas, os shortcodes [%2$s_…] e o schema saem deste plugin. Falta só o que continua em cada ferramenta:', 'local-rank-programmatic-seo' ),
			$who,
			$prefix
		) ) . '</p>';
		if ( '' !== $trade ) {
			echo '<p class="description">' . esc_html( sprintf(
				/* translators: %s: trade label */
				__( 'Ofício usado no schema: %s.', 'local-rank-programmatic-seo' ),
				$trade
			) ) . '</p>';
		}
		echo '<ol class="lrps-now">';
		echo '<li><strong>' . esc_html__( 'ACF Pro', 'local-rank-programmatic-seo' ) . '</strong> ' . esc_html__( 'Crie o grupo com os field names da tabela abaixo. O plugin não cria o grupo. O select de cidade já vem do catálogo.', 'local-rank-programmatic-seo' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Elementor', 'local-rank-programmatic-seo' ) . '</strong> ' . esc_html__( 'Monte o template e cole os shortcodes da aba Referência. O schema entra uma vez, no fim do template.', 'local-rank-programmatic-seo' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'WP All Import', 'local-rank-programmatic-seo' ) . '</strong> ' . esc_html__( 'Use a planilha abaixo. A coluna unique_key é o identificador: a mesma chave atualiza a página, uma chave nova cria outra.', 'local-rank-programmatic-seo' ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Yoast', 'local-rank-programmatic-seo' ) . '</strong> ' . esc_html__( 'Cole o título padrão no fim desta tela. O Yoast continua dono do título e da meta description.', 'local-rank-programmatic-seo' ) . '</li>';
		echo '</ol>';
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Alterar as respostas', 'local-rank-programmatic-seo' ) . '</a></p>';
		echo '</section>';
	}

	/**
	 * Example URLs and the page count.
	 *
	 * @param array{cities:int,services:int,pages:int,levels:array<int,array<string,mixed>>} $coverage Coverage.
	 * @param string                                                                         $prefix   Project prefix.
	 */
	private function coverage_card( array $coverage, $prefix ) {
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Arquitetura deste projeto', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html( sprintf(
			/* translators: 1: prefix, 2: city count, 3: service count, 4: page count */
			__( 'Prefixo %1$s. %2$d cidades e %3$d serviços no catálogo geram %4$d páginas. A conta é a cobertura real: cada nível multiplica só os catálogos que entram na URL.', 'local-rank-programmatic-seo' ),
			$prefix,
			(int) $coverage['cities'],
			(int) $coverage['services'],
			(int) $coverage['pages']
		) ) . '</p>';

		if ( empty( $coverage['levels'] ) ) {
			echo '<p>' . esc_html__( 'Este projeto ainda não tem níveis. Escolha um modelo abaixo.', 'local-rank-programmatic-seo' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Nível', 'local-rank-programmatic-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'URL de exemplo', 'local-rank-programmatic-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Páginas', 'local-rank-programmatic-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Chave única', 'local-rank-programmatic-seo' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $coverage['levels'] as $level ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $level['label'] ) . '</td>';
			echo '<td><code>' . esc_html( '' !== (string) $level['example'] ? (string) $level['example'] : __( 'adicione cidade e serviço ao catálogo', 'local-rank-programmatic-seo' ) ) . '</code></td>';
			echo '<td>' . esc_html( (string) $level['count'] ) . '</td>';
			echo '<td><code>' . esc_html( (string) $level['unique_key'] ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'O CPT continua plano. Uma página estática entra como prefixo da URL e como item do breadcrumb. Ela não vira post pai.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Applies one of the three starting architectures.
	 *
	 * @param array<string,mixed> $config Project.
	 */
	private function preset_card( array $config ) {
		$current = isset( $config['levels'] ) && is_array( $config['levels'] ) ? $config['levels'] : array();
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Modelo de URL', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'O modelo troca só os níveis e o breadcrumb de cada nível. Catálogo, prefixo do projeto e campos continuam. Se a página estática não se chama services, aplique o modelo e edite o prefixo e o caminho no JSON da aba Projeto. Esse slug precisa estar na lista de slugs reservados.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<div class="lrps-presets">';
		foreach ( Presets::catalog() as $id => $preset ) {
			$active = $this->same_levels( $current, Presets::levels( $id ) );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=lrps&tab=roteiro' ) ) . '" class="lrps-preset">';
			wp_nonce_field( 'lrps_save' );
			echo '<input type="hidden" name="lrps_action" value="preset" />';
			echo '<input type="hidden" name="preset" value="' . esc_attr( $id ) . '" />';
			echo '<h3>' . esc_html( $preset['label'] ) . '</h3>';
			echo '<p>' . esc_html( $preset['summary'] ) . '</p>';
			if ( $active ) {
				echo '<p class="lrps-preset__state">' . esc_html__( 'Modelo em uso', 'local-rank-programmatic-seo' ) . '</p>';
			}
			echo '<button type="submit" class="button">' . esc_html__( 'Usar este modelo', 'local-rank-programmatic-seo' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Field names the person creates in ACF.
	 *
	 * @param array<string,mixed> $config Project.
	 */
	private function acf_card( array $config ) {
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Campos para criar no ACF', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Crie estes field names no ACF Pro. O plugin não cria o grupo. O select de cidade é preenchido pelo catálogo.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Field name', 'local-rank-programmatic-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Tipo', 'local-rank-programmatic-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'O que colocar', 'local-rank-programmatic-seo' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( Playbook::acf_fields( $config ) as $field ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $field['name'] ) . '</code></td>';
			echo '<td>' . esc_html( $field['type'] ) . '</td>';
			echo '<td>' . esc_html( $field['note'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</section>';
	}

	/**
	 * CSV sample and the reimport rule.
	 *
	 * @param array<string,mixed> $config Project.
	 */
	private function import_card( array $config ) {
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Planilha do WP All Import', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'A importação continua no WP All Import. Marque unique_key como identificador único. A mesma chave atualiza o post. Uma chave diferente cria outro post e deixa a URL antiga no ar.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<textarea readonly rows="8" class="large-text code">' . esc_textarea( Playbook::csv_text( $config ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'A linha de exemplo fica como rascunho de propósito. Apague-a ou troque o status quando a página estiver pronta para o piloto.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Yoast decisions the team pastes, without replacing Yoast.
	 */
	private function yoast_card() {
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Yoast', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'O Yoast continua dono do título e da meta description. O padrão de título da operação é este:', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<p><code>' . esc_html( Playbook::YOAST_TITLE ) . '</code></p>';
		echo '<p>' . esc_html__( 'O canonical da location page aponta para a URL programática. O schema Service, com areaServed, entra pelo shortcode no fim do template. Não publique um LocalBusiness por cidade. O schema Service só sai quando a página tem cidade e estado; uma página só de serviço não inventa área atendida.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</section>';
	}

	/**
	 * Nine steps with a saved checkbox.
	 *
	 * @param array<string,mixed> $done Saved checks.
	 */
	private function steps_card( array $done ) {
		echo '<section class="lrps-card">';
		echo '<h2>' . esc_html__( 'Roteiro', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Quem abre o projeto marca o que já foi feito. ACF, Elementor, WP All Import e Yoast continuam nas etapas em que o nome deles aparece.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=lrps&tab=roteiro' ) ) . '">';
		wp_nonce_field( 'lrps_save' );
		echo '<input type="hidden" name="lrps_action" value="checklist" />';
		echo '<ol class="lrps-steps">';
		foreach ( Playbook::steps() as $step ) {
			$id = (string) $step['id'];
			echo '<li>';
			echo '<label><input type="checkbox" name="done[' . esc_attr( $id ) . ']" value="1"' . checked( ! empty( $done[ $id ] ), true, false ) . ' /> ';
			echo '<strong>' . esc_html( $step['etapa'] . ' · ' . $step['title'] ) . '</strong>';
			echo ' <span class="lrps-where">' . esc_html( $step['where'] ) . '</span></label>';
			echo '<p>' . esc_html( $step['text'] ) . '</p>';
			echo '</li>';
		}
		echo '</ol>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Salvar roteiro', 'local-rank-programmatic-seo' ) . '</button>';
		echo '</form>';
		echo '</section>';
	}

	/**
	 * Compares the public shape of two level lists.
	 *
	 * @param array<int,mixed> $left  Stored levels.
	 * @param array<int,mixed> $right Preset levels.
	 * @return bool
	 */
	private function same_levels( array $left, array $right ) {
		return $this->signature( $left ) === $this->signature( $right );
	}

	/**
	 * Stable signature of keys, segments, prefix and trail.
	 *
	 * @param array<int,mixed> $levels Levels.
	 * @return string
	 */
	private function signature( array $levels ) {
		$rows = array();
		foreach ( $levels as $level ) {
			if ( ! is_array( $level ) ) {
				continue;
			}
			$rows[] = array(
				'key'      => isset( $level['key'] ) ? (string) $level['key'] : '',
				'segments' => isset( $level['segments'] ) && is_array( $level['segments'] ) ? array_values( $level['segments'] ) : array(),
				'prefix'   => isset( $level['prefix'] ) && is_array( $level['prefix'] ) ? array_values( $level['prefix'] ) : array(),
				'trail'    => isset( $level['trail'] ) && is_array( $level['trail'] ) ? $level['trail'] : array(),
			);
		}
		$encoded = wp_json_encode( $rows );
		return is_string( $encoded ) ? $encoded : '';
	}
}
