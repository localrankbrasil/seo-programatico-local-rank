<?php
/**
 * Settings, catalog, reference and diagnostics screens.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Admin;

use LocalRank\ProgrammaticSeo\Config\Defaults;
use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Config\Sanitizer;
use LocalRank\ProgrammaticSeo\Modules\Urls_Module;
use LocalRank\ProgrammaticSeo\Support\Company_Builder;
use LocalRank\ProgrammaticSeo\Support\Presets;
use LocalRank\ProgrammaticSeo\Support\Reference;
use LocalRank\ProgrammaticSeo\Support\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The agency-facing screen for one programmatic project.
 */
final class Page {

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
	 * Registers menu, assets and form handlers.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_head', array( $this, 'menu_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'handle_post' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LRPS_FILE ), array( $this, 'links' ) );
	}

	/**
	 * Adds the top-level menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Programático', 'local-rank-programmatic-seo' ),
			__( 'Programático', 'local-rank-programmatic-seo' ),
			'manage_options',
			'lrps',
			array( $this, 'render' ),
			$this->menu_icon(),
			58
		);
	}

	/**
	 * Square brand mark for the admin menu.
	 *
	 * A file URL collapses in the 20px menu slot. The data URI is in the HTML.
	 *
	 * @return string
	 */
	private function menu_icon() {
		$path = LRPS_PATH . 'assets/brand/menu-mark.svg';
		$svg  = is_readable( $path ) ? file_get_contents( $path ) : '';
		if ( ! is_string( $svg ) || false === strpos( $svg, '<svg' ) ) {
			return 'dashicons-chart-bar';
		}
		$svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );
		return 'data:image/svg+xml;base64,' . base64_encode( trim( (string) $svg ) );
	}

	/**
	 * Forces the menu mark to a visible 20px tile on every admin screen.
	 */
	public function menu_style() {
		$icon = $this->menu_icon();
		if ( 0 !== strpos( $icon, 'data:image/svg+xml;base64,' ) ) {
			return;
		}
		echo '<style id="lrps-menu-icon">';
		echo '#toplevel_page_lrps .wp-menu-image{background:transparent url("' . esc_attr( $icon ) . '") center/20px 20px no-repeat!important}';
		echo '#toplevel_page_lrps .wp-menu-image img,#toplevel_page_lrps .wp-menu-image:before{opacity:0!important}';
		echo '</style>';
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function links( $links ) {
		$url = admin_url( 'admin.php?page=lrps' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configurar', 'local-rank-programmatic-seo' ) . '</a>' );
		return $links;
	}

	/**
	 * Loads assets only on this screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function assets( $hook ) {
		if ( 'toplevel_page_lrps' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'lrps-admin', LRPS_URL . 'assets/css/admin.css', array(), LRPS_VERSION );
		wp_enqueue_script( 'lrps-admin', LRPS_URL . 'assets/js/admin.js', array(), LRPS_VERSION, true );
	}

	/**
	 * Saves, imports or resets the project.
	 */
	public function handle_post() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['lrps_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['lrps_action'] ) );
		check_admin_referer( 'lrps_save' );

		if ( 'reset' === $action ) {
			$this->config->save( Defaults::all() );
			delete_option( Urls_Module::REWRITE_OPTION );
			$this->redirect( 'reset' );
		}

		if ( 'import' === $action ) {
			$this->import_file();
			return;
		}

		if ( 'checklist' === $action ) {
			( new Guide( $this->config ) )->save_checklist();
			$this->redirect( 'checklist', 'roteiro' );
		}

		if ( 'wizard' === $action ) {
			$this->apply_wizard();
			return;
		}

		if ( 'preset' === $action ) {
			$this->apply_preset();
			return;
		}

		if ( 'save' !== $action ) {
			return;
		}

		$input  = wp_unslash( $_POST );
		$result = Sanitizer::clean( is_array( $input ) ? $input : array(), $this->config->all(), 'form' );
		if ( ! empty( $result['errors'] ) ) {
			set_transient( 'lrps_form_errors_' . get_current_user_id(), $result['errors'], MINUTE_IN_SECONDS );
			set_transient( 'lrps_form_old_' . get_current_user_id(), $this->remember( $input ), MINUTE_IN_SECONDS );
			$this->redirect( 'error' );
		}

		$this->config->save( $result['config'] );
		delete_option( Urls_Module::REWRITE_OPTION );
		$this->redirect( 'saved' );
	}

	/**
	 * Renders the current tab.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = $this->config->all();
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( '' === $tab ) {
			$tab = Wizard::is_ready( $config ) ? 'roteiro' : 'comecar';
		}
		$errors = get_transient( 'lrps_form_errors_' . get_current_user_id() );
		$old    = get_transient( 'lrps_form_old_' . get_current_user_id() );
		delete_transient( 'lrps_form_errors_' . get_current_user_id() );
		delete_transient( 'lrps_form_old_' . get_current_user_id() );
		if ( ! is_array( $errors ) ) {
			$errors = array();
		}
		if ( ! is_array( $old ) ) {
			$old = array();
		}

		echo '<div class="wrap lrps-wrap">';
		echo '<header class="lrps-hero">';
		$this->logo();
		echo '<h1>' . esc_html__( 'Programático', 'local-rank-programmatic-seo' ) . '</h1>';
		echo '<p class="lrps-kicker">' . esc_html( $this->kicker( $tab ) ) . '</p>';
		echo '</header>';
		$this->tabs( $tab );
		$this->flash();

		if ( ! empty( $errors ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( implode( ' ', $errors ) ) . '</p></div>';
		}

		if ( 'comecar' === $tab ) {
			Setup::render( $this->config, $old );
		} elseif ( 'project' === $tab ) {
			$this->render_project( $config, $old );
		} elseif ( 'reference' === $tab ) {
			$this->render_reference( $config );
		} elseif ( 'diagnostics' === $tab ) {
			$this->render_diagnostics();
		} else {
			( new Guide( $this->config ) )->render();
		}

		echo '</div>';
	}

	/**
	 * Project form.
	 *
	 * @param array<string,mixed> $config Stored config.
	 * @param array<string,mixed> $old    Posted values after an error.
	 */
	private function render_project( array $config, array $old ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=lrps' ) ) . '" class="lrps-form">';
		wp_nonce_field( 'lrps_save' );
		echo '<input type="hidden" name="lrps_action" value="save" />';

		echo '<div class="lrps-grid">';
		$this->card_open( __( 'Identidade do projeto', 'local-rank-programmatic-seo' ), __( 'O prefixo é deste cliente e vira o nome dos shortcodes. lr vira [lr_city_state]. Cada site ganha o próprio prefixo.', 'local-rank-programmatic-seo' ) );
		$this->text_field( 'prefix', __( 'Prefixo', 'local-rank-programmatic-seo' ), $this->old_or( $old, 'prefix', $config['prefix'] ), 'lrps-prefix' );
		$this->text_field( 'cpt', __( 'CPT', 'local-rank-programmatic-seo' ), $this->old_or( $old, 'cpt', $config['cpt'] ) );
		$this->text_field( 'taxonomy', __( 'Taxonomia de nível', 'local-rank-programmatic-seo' ), $this->old_or( $old, 'taxonomy', $config['taxonomy'] ) );
		$this->text_field( 'trade_label', __( 'Rótulo do ofício', 'local-rank-programmatic-seo' ), $this->old_or( $old, 'trade_label', $config['trade_label'] ) );
		echo '<p class="description">' . esc_html__( 'Exemplo: serviço local. Entra no schema e no título das páginas de cidade.', 'local-rank-programmatic-seo' ) . '</p>';
		$this->checkbox( 'strict_levels', __( 'Bloquear contexto quando taxonomia e select divergirem', 'local-rank-programmatic-seo' ), ! empty( $config['strict_levels'] ) );
		$this->checkbox( 'skip_self_links', __( 'Omitir link quando o destino é a própria página', 'local-rank-programmatic-seo' ), ! empty( $config['skip_self_links'] ) );
		$this->card_close();

		$this->card_open( __( 'Campos ACF', 'local-rank-programmatic-seo' ), __( 'Estes são os field names já criados no ACF Pro. O plugin não registra o grupo.', 'local-rank-programmatic-seo' ) );
		$labels = array(
			'page_level'   => __( 'Nível (select)', 'local-rank-programmatic-seo' ),
			'city_slug'    => __( 'Slug da cidade', 'local-rank-programmatic-seo' ),
			'city_name'    => __( 'Nome da cidade', 'local-rank-programmatic-seo' ),
			'city_state'   => __( 'Estado', 'local-rank-programmatic-seo' ),
			'service_slug' => __( 'Slug do serviço', 'local-rank-programmatic-seo' ),
			'service_name' => __( 'Nome do serviço', 'local-rank-programmatic-seo' ),
			'map_embed'    => __( 'URL do mapa', 'local-rank-programmatic-seo' ),
			'city_content' => __( 'Conteúdo localizado', 'local-rank-programmatic-seo' ),
			'faqs'         => __( 'Repeater de FAQ', 'local-rank-programmatic-seo' ),
			'faq_question' => __( 'Subcampo pergunta', 'local-rank-programmatic-seo' ),
			'faq_answer'   => __( 'Subcampo resposta', 'local-rank-programmatic-seo' ),
		);
		$suffixes = array(
			'page_level'   => 'page_level',
			'city_slug'    => 'city_slug',
			'city_name'    => 'city_name',
			'city_state'   => 'city_state',
			'service_slug' => 'service_slug',
			'service_name' => 'service_name',
			'map_embed'    => 'map_embed_url',
			'city_content' => 'city_content',
			'faqs'         => 'faqs',
		);
		echo '<div class="lrps-fields">';
		foreach ( $labels as $key => $label ) {
			$value = isset( $old['fields'][ $key ] ) ? (string) $old['fields'][ $key ] : (string) $config['fields'][ $key ];
			$extra = isset( $suffixes[ $key ] ) ? ' data-suffix="' . esc_attr( $suffixes[ $key ] ) . '" data-autofield="1"' : '';
			echo '<label><span>' . esc_html( $label ) . '</span><input type="text" name="fields[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . $extra . ' /></label>';
		}
		echo '</div>';
		$this->card_close();

		$this->card_open( __( 'Níveis e URLs', 'local-rank-programmatic-seo' ), __( 'Segmentos: state, city, service. prefix é a página estática pai, por exemplo ["services"]. trail define o breadcrumb: home, static, ancestor e current. Sem trail, o breadcrumb segue Home, a página de áreas e a cidade.', 'local-rank-programmatic-seo' ) );
		$levels_json = isset( $old['levels_json'] ) ? (string) $old['levels_json'] : wp_json_encode( $config['levels'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '<textarea name="levels_json" rows="16" class="large-text code">' . esc_textarea( (string) $levels_json ) . '</textarea>';
		$reserved = isset( $old['reserved_slugs'] ) ? (string) $old['reserved_slugs'] : implode( "\n", $config['reserved_slugs'] );
		echo '<label class="lrps-stack"><span>' . esc_html__( 'Slugs reservados', 'local-rank-programmatic-seo' ) . '</span><textarea name="reserved_slugs" rows="6" class="large-text code">' . esc_textarea( $reserved ) . '</textarea></label>';
		$this->card_close();

		$this->card_open( __( 'Cidades', 'local-rank-programmatic-seo' ), __( 'Uma cidade por linha: slug, nome, estado. O catálogo é fechado; slug fora dele não gera URL.', 'local-rank-programmatic-seo' ) );
		$csv = isset( $old['cities_csv'] ) ? (string) $old['cities_csv'] : Sanitizer::cities_to_csv( $config['cities'] );
		echo '<textarea name="cities_csv" rows="10" class="large-text code">' . esc_textarea( $csv ) . '</textarea>';
		$this->card_close();

		$this->card_open( __( 'Serviços', 'local-rank-programmatic-seo' ), __( 'JSON. gallery_template_id 0 esconde a galeria sem reaproveitar outro serviço. Os IDs são desta instalação.', 'local-rank-programmatic-seo' ) );
		$services_json = isset( $old['services_json'] ) ? (string) $old['services_json'] : wp_json_encode( $config['services'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '<textarea name="services_json" rows="18" class="large-text code">' . esc_textarea( (string) $services_json ) . '</textarea>';
		$this->card_close();

		$this->card_open( __( 'Breadcrumb', 'local-rank-programmatic-seo' ), __( 'A trilha visual e o BreadcrumbList são saídas diferentes. O JSON-LD só substitui o do Yoast quando ligado abaixo.', 'local-rank-programmatic-seo' ) );
		$this->group_text( 'breadcrumbs', 'home_label', __( 'Home', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'breadcrumbs', 'areas_label', __( 'Rótulo da página de áreas', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'breadcrumbs', 'areas_path', __( 'Caminho da página de áreas', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'breadcrumbs', 'color', __( 'Cor', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'breadcrumbs', 'hover', __( 'Cor do hover', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'breadcrumbs', 'font_size', __( 'Tamanho', 'local-rank-programmatic-seo' ), $config, $old );
		$this->card_close();

		$this->card_open( __( 'Schema', 'local-rank-programmatic-seo' ), __( 'Páginas locais descrevem o serviço na região. O endereço físico fica só na entidade da empresa, fora dessas páginas.', 'local-rank-programmatic-seo' ) );
		$this->group_text( 'schema', 'city_name', __( 'Nome da cidade', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'schema', 'city_type', __( 'serviceType da cidade', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'schema', 'service_name', __( 'Nome do serviço', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'schema', 'service_type', __( 'serviceType do serviço', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'schema', 'provider_id', __( '@id do provider', 'local-rank-programmatic-seo' ), $config, $old );
		$this->group_text( 'schema', 'faq_expected', __( 'FAQs esperadas (0 = livre)', 'local-rank-programmatic-seo' ), $config, $old );
		echo '<p class="description">' . esc_html__( 'Deixe o @id vazio para usar a Organization do domínio atual (/#organization). Tokens: {trade_label}, {service_name}, {city_state}.', 'local-rank-programmatic-seo' ) . '</p>';
		$this->checkbox( 'schema[breadcrumb_jsonld]', __( 'Alinhar o BreadcrumbList do Yoast à trilha visual', 'local-rank-programmatic-seo' ), ! empty( $config['schema']['breadcrumb_jsonld'] ), 'schema', 'breadcrumb_jsonld' );
		$this->checkbox( 'schema[faq_jsonld]', __( 'Emitir FAQPage a partir do repeater', 'local-rank-programmatic-seo' ), ! empty( $config['schema']['faq_jsonld'] ), 'schema', 'faq_jsonld' );
		$this->checkbox( 'schema[embed_provider]', __( 'Incluir a empresa no grafo local quando o Yoast não for a fonte', 'local-rank-programmatic-seo' ), ! empty( $config['schema']['embed_provider'] ), 'schema', 'embed_provider' );
		$this->card_close();

		$this->card_open( __( 'Empresa', 'local-rank-programmatic-seo' ), __( 'Use o endereço real da sede. Uma cidade atendida não entra aqui.', 'local-rank-programmatic-seo' ) );
		$this->checkbox( 'company[enabled]', __( 'Emitir a entidade fora das páginas locais', 'local-rank-programmatic-seo' ), ! empty( $config['company']['enabled'] ), 'company', 'enabled' );
		echo '<label class="lrps-stack"><span>' . esc_html__( 'Tipo', 'local-rank-programmatic-seo' ) . '</span><select name="company[type]">';
		foreach ( Company_Builder::types() as $type ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $config['company']['type'], $type, false ) . '>' . esc_html( $type ) . '</option>';
		}
		echo '</select></label>';
		foreach ( array(
			'name'      => __( 'Nome', 'local-rank-programmatic-seo' ),
			'url'       => __( 'URL', 'local-rank-programmatic-seo' ),
			'telephone' => __( 'Telefone', 'local-rank-programmatic-seo' ),
			'image'     => __( 'Imagem', 'local-rank-programmatic-seo' ),
			'id'        => __( '@id', 'local-rank-programmatic-seo' ),
			'street'    => __( 'Rua', 'local-rank-programmatic-seo' ),
			'locality'  => __( 'Cidade da sede', 'local-rank-programmatic-seo' ),
			'region'    => __( 'Estado da sede', 'local-rank-programmatic-seo' ),
			'postal'    => __( 'CEP', 'local-rank-programmatic-seo' ),
			'country'   => __( 'País', 'local-rank-programmatic-seo' ),
			'latitude'  => __( 'Latitude', 'local-rank-programmatic-seo' ),
			'longitude' => __( 'Longitude', 'local-rank-programmatic-seo' ),
		) as $key => $label ) {
			$this->group_text( 'company', $key, $label, $config, $old );
		}
		$this->card_close();

		$this->card_open( __( 'Módulos', 'local-rank-programmatic-seo' ), __( 'Cada módulo pode ser desligado sem apagar o catálogo. Desligue o que a etapa 02 ou o tema já resolvem.', 'local-rank-programmatic-seo' ) );
		$module_labels = array(
			'structure'       => __( 'Estrutura: CPT, taxonomia, termos e sincronização do nível', 'local-rank-programmatic-seo' ),
			'urls'            => __( 'URLs: permalink, rewrite e resolução', 'local-rank-programmatic-seo' ),
			'shortcodes'      => __( 'Shortcodes de contexto, FAQ, mapa e breadcrumb', 'local-rank-programmatic-seo' ),
			'templates'       => __( 'Problems e Gallery por serviço', 'local-rank-programmatic-seo' ),
			'elementor'       => __( 'Dynamic Tags e expansão de tokens no Elementor', 'local-rank-programmatic-seo' ),
			'schema'          => __( 'Schema localizado por shortcode', 'local-rank-programmatic-seo' ),
			'company_schema'  => __( 'Schema da empresa nas demais páginas', 'local-rank-programmatic-seo' ),
			'breadcrumbs_css' => __( 'CSS do breadcrumb', 'local-rank-programmatic-seo' ),
			'section_label'   => __( 'Shortcode section_label, se ainda não existir', 'local-rank-programmatic-seo' ),
			'html_sitemap'    => __( 'Sitemap HTML: [prefixo_html_sitemap]', 'local-rank-programmatic-seo' ),
			'kadence'         => __( 'Header transparente e layout full-width do Kadence', 'local-rank-programmatic-seo' ),
		);
		foreach ( $module_labels as $key => $label ) {
			$this->checkbox( 'modules[' . $key . ']', $label, ! empty( $config['modules'][ $key ] ), 'modules', $key );
		}
		$this->card_close();

		$this->card_open( __( 'Templates do Elementor', 'local-rank-programmatic-seo' ), __( 'A condição de exibição do Theme Builder continua mandando. Priorizar só reordena um template que já seja candidato.', 'local-rank-programmatic-seo' ) );
		$this->checkbox( 'templates[force]', __( 'Priorizar o template do nível quando ele já for candidato', 'local-rank-programmatic-seo' ), ! empty( $config['templates']['force'] ), 'templates', 'force' );
		if ( is_array( $config['levels'] ) ) {
			foreach ( $config['levels'] as $level ) {
				$key = (string) $level['key'];
				$value = isset( $config['templates']['map'][ $key ] ) ? (int) $config['templates']['map'][ $key ] : 0;
				echo '<label class="lrps-stack"><span>' . esc_html( (string) $level['label'] ) . '</span><input type="number" min="0" name="templates[map][' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" /></label>';
			}
		}
		echo '<label class="lrps-stack"><span>' . esc_html__( 'Classe do mapa', 'local-rank-programmatic-seo' ) . '</span><input type="text" name="map[class]" value="' . esc_attr( (string) $config['map']['class'] ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Opcional. Informe a classe CSS que o template já usa no mapa, para aplicar o contrato de altura e iframe.', 'local-rank-programmatic-seo' ) . '</p>';
		$this->card_close();
		echo '</div>';

		submit_button( __( 'Salvar projeto', 'local-rank-programmatic-seo' ) );
		echo '</form>';

		echo '<div class="lrps-split">';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin.php?page=lrps' ) ) . '">';
		wp_nonce_field( 'lrps_save' );
		echo '<input type="hidden" name="lrps_action" value="import" />';
		echo '<h2>' . esc_html__( 'Importar JSON', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'O JSON traz o projeto inteiro. Troque prefixo, catálogos e IDs para este cliente. O arquivo examples/projeto.json mostra o formato, sem dados de um cliente real.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<input type="file" name="lrps_import" accept="application/json,.json" required />';
		submit_button( __( 'Importar', 'local-rank-programmatic-seo' ), 'secondary' );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=lrps' ) ) . '" data-confirm="' . esc_attr__( 'Isso substitui o projeto salvo pelos padrões vazios.', 'local-rank-programmatic-seo' ) . '">';
		wp_nonce_field( 'lrps_save' );
		echo '<input type="hidden" name="lrps_action" value="reset" />';
		echo '<h2>' . esc_html__( 'Recomeçar', 'local-rank-programmatic-seo' ) . '</h2>';
		submit_button( __( 'Restaurar padrão vazio', 'local-rank-programmatic-seo' ), 'delete' );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Shortcode and Dynamic Tag handoff for stage 5.
	 *
	 * @param array<string,mixed> $config Configuration.
	 */
	private function render_reference( array $config ) {
		echo '<div class="lrps-card">';
		echo '<h2>' . esc_html__( 'Shortcodes para a etapa 05', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Shortcode', 'local-rank-programmatic-seo' ) . '</th><th>' . esc_html__( 'Uso', 'local-rank-programmatic-seo' ) . '</th></tr></thead><tbody>';
		foreach ( Reference::shortcodes( $config ) as $row ) {
			echo '<tr><td><code>' . esc_html( $row['code'] ) . '</code></td><td>' . esc_html( $row['use'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		echo '<div class="lrps-card">';
		echo '<h2>' . esc_html__( 'Dynamic Tags', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Nome técnico', 'local-rank-programmatic-seo' ) . '</th><th>' . esc_html__( 'Uso', 'local-rank-programmatic-seo' ) . '</th></tr></thead><tbody>';
		foreach ( Reference::dynamic_tags( $config ) as $row ) {
			echo '<tr><td><code>' . esc_html( $row['code'] ) . '</code></td><td>' . esc_html( $row['use'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		echo '<div class="lrps-card">';
		echo '<h2>' . esc_html__( 'Tokens', 'local-rank-programmatic-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Use estes tokens dentro de textos e do section_label. Não aninhe um shortcode no atributo text, porque o WordPress fecha no primeiro ].', 'local-rank-programmatic-seo' ) . '</p>';
		echo '<p><code>' . esc_html( implode( ' ', Reference::tokens() ) ) . '</code></p>';
		echo '</div>';
	}

	/**
	 * Diagnostics tab.
	 */
	private function render_diagnostics() {
		if ( isset( $_GET['synced'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( /* translators: %d: number of posts */ __( '%d registros tiveram o nível sincronizado.', 'local-rank-programmatic-seo' ), (int) $_GET['synced'] ) ) . '</p></div>';
		}

		echo '<div class="lrps-card"><table class="widefat lrps-checks"><tbody>';
		foreach ( Diagnostics::run() as $check ) {
			echo '<tr class="lrps-check is-' . esc_attr( $check['status'] ) . '">';
			echo '<td class="lrps-check__status">' . esc_html( $this->status_label( $check['status'] ) ) . '</td>';
			echo '<td><strong>' . esc_html( $check['gate'] . ' — ' . $check['label'] ) . '</strong><br />' . esc_html( $check['detail'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lrps-inline">';
		echo '<input type="hidden" name="action" value="lrps_sync_levels" />';
		wp_nonce_field( 'lrps_sync_levels' );
		submit_button( __( 'Sincronizar níveis agora', 'local-rank-programmatic-seo' ), 'secondary', 'submit', false );
		echo '<p class="description">' . esc_html__( 'Copia o select de nível para o termo da taxonomia em todos os registros do CPT. Não altera conteúdo.', 'local-rank-programmatic-seo' ) . '</p>';
		echo '</form>';
	}

	/**
	 * Tab navigation.
	 *
	 * @param string $current Current tab.
	 */
	private function tabs( $current ) {
		$tabs = array(
			'comecar'     => __( 'Começar', 'local-rank-programmatic-seo' ),
			'roteiro'     => __( 'Próximos passos', 'local-rank-programmatic-seo' ),
			'project'     => __( 'Projeto', 'local-rank-programmatic-seo' ),
			'reference'   => __( 'Referência', 'local-rank-programmatic-seo' ),
			'diagnostics' => __( 'Diagnóstico', 'local-rank-programmatic-seo' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $tab => $label ) {
			$url = add_query_arg(
				array(
					'page' => 'lrps',
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			);
			$class = $current === $tab ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Success and reset notices from the query string.
	 */
	private function flash() {
		if ( empty( $_GET['lrps_notice'] ) ) {
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['lrps_notice'] ) );
		$messages = array(
			'saved'     => __( 'Projeto salvo. As rotas serão regeneradas neste carregamento.', 'local-rank-programmatic-seo' ),
			'reset'     => __( 'Projeto restaurado para o padrão vazio.', 'local-rank-programmatic-seo' ),
			'import'    => __( 'JSON importado.', 'local-rank-programmatic-seo' ),
			'preset'    => __( 'Modelo aplicado. Confira as URLs de exemplo no roteiro.', 'local-rank-programmatic-seo' ),
			'checklist' => __( 'Roteiro salvo.', 'local-rank-programmatic-seo' ),
			'wizard'    => __( 'Estrutura criada. As rotas e os shortcodes já existem. Siga os passos abaixo.', 'local-rank-programmatic-seo' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	/**
	 * Imports a JSON project file.
	 */
	private function import_file() {
		if ( empty( $_FILES['lrps_import'] ) || ! is_array( $_FILES['lrps_import'] ) ) {
			$this->fail_import( __( 'Nenhum arquivo foi enviado.', 'local-rank-programmatic-seo' ) );
		}

		$file = $_FILES['lrps_import'];
		if ( ! isset( $file['error'], $file['size'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			$this->fail_import( __( 'O upload do JSON falhou.', 'local-rank-programmatic-seo' ) );
		}
		if ( (int) $file['size'] > 1000000 ) {
			$this->fail_import( __( 'O JSON passa de 1 MB.', 'local-rank-programmatic-seo' ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->fail_import( __( 'O arquivo enviado não é um upload válido.', 'local-rank-programmatic-seo' ) );
		}

		$raw = file_get_contents( $file['tmp_name'] );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$this->fail_import( __( 'O arquivo não é um JSON de projeto.', 'local-rank-programmatic-seo' ) );
		}

		$result = Sanitizer::clean( $decoded, Defaults::all(), 'stored' );
		if ( ! empty( $result['errors'] ) ) {
			$this->fail_import( implode( ' ', $result['errors'] ) );
		}

		$this->config->save( $result['config'] );
		delete_option( Urls_Module::REWRITE_OPTION );
		$this->redirect( 'import' );
	}

	/**
	 * Stores an import error and redirects.
	 *
	 * @param string $message Error message.
	 */
	private function fail_import( $message ) {
		set_transient( 'lrps_form_errors_' . get_current_user_id(), array( $message ), MINUTE_IN_SECONDS );
		$this->redirect( 'error' );
	}

	/**
	 * Redirects back to the project screen.
	 *
	 * @param string $notice Notice key.
	 */
	private function redirect( $notice, $tab = '' ) {
		$args = array(
			'page'        => 'lrps',
			'lrps_notice' => $notice,
		);
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Replaces the levels with a named architecture.
	 */
	private function apply_preset() {
		$id     = isset( $_POST['preset'] ) ? sanitize_key( wp_unslash( $_POST['preset'] ) ) : '';
		$levels = Presets::levels( $id );
		if ( empty( $levels ) ) {
			set_transient( 'lrps_form_errors_' . get_current_user_id(), array( 'Modelo desconhecido.' ), MINUTE_IN_SECONDS );
			$this->redirect( 'error', 'roteiro' );
		}

		$config           = $this->config->all();
		$config['levels'] = $levels;
		$result           = Sanitizer::clean( $config, Defaults::all(), 'stored' );
		if ( ! empty( $result['errors'] ) ) {
			set_transient( 'lrps_form_errors_' . get_current_user_id(), $result['errors'], MINUTE_IN_SECONDS );
			$this->redirect( 'error', 'roteiro' );
		}

		$this->config->save( $result['config'] );
		delete_option( Urls_Module::REWRITE_OPTION );
		$this->redirect( 'preset', 'roteiro' );
	}

	/**
	 * Keeps the text the person just submitted.
	 *
	 * @param array<string,mixed> $input Posted input.
	 * @return array<string,mixed>
	 */
	private function remember( array $input ) {
		$keep = array( 'prefix', 'cpt', 'taxonomy', 'trade_label', 'levels_json', 'cities_csv', 'services_json', 'reserved_slugs', 'fields', 'client_name', 'model', 'parent_slug', 'parent_label', 'places', 'offers' );
		$old  = array();
		foreach ( $keep as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$old[ $key ] = $input[ $key ];
			}
		}
		return $old;
	}

	/**
	 * Posted scalar or stored value.
	 *
	 * @param array<string,mixed> $old Stored old input.
	 * @param string              $key Key.
	 * @param mixed               $fallback Stored value.
	 * @return string
	 */
	private function old_or( array $old, $key, $fallback ) {
		if ( isset( $old[ $key ] ) && ! is_array( $old[ $key ] ) ) {
			return (string) $old[ $key ];
		}
		return (string) $fallback;
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 * @param string $intro Intro.
	 */
	private function card_open( $title, $intro ) {
		echo '<section class="lrps-card"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $intro ) . '</p>';
	}

	/**
	 * Closes a card.
	 */
	private function card_close() {
		echo '</section>';
	}

	/**
	 * Single-line field.
	 *
	 * @param string $name  Field name.
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $id    Optional id.
	 */
	private function text_field( $name, $label, $value, $id = '' ) {
		echo '<label class="lrps-stack"><span>' . esc_html( $label ) . '</span><input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' /></label>';
	}

	/**
	 * Grouped text field.
	 *
	 * @param string              $group Group.
	 * @param string              $key Key.
	 * @param string              $label Label.
	 * @param array<string,mixed> $config Config.
	 * @param array<string,mixed> $old Old input.
	 */
	private function group_text( $group, $key, $label, array $config, array $old ) {
		$value = isset( $old[ $group ][ $key ] ) ? (string) $old[ $group ][ $key ] : (string) ( isset( $config[ $group ][ $key ] ) ? $config[ $group ][ $key ] : '' );
		echo '<label class="lrps-stack"><span>' . esc_html( $label ) . '</span><input type="text" name="' . esc_attr( $group . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" /></label>';
	}

	/**
	 * Checkbox with a hidden false value.
	 *
	 * @param string $name Name.
	 * @param string $label Label.
	 * @param bool   $checked Checked.
	 * @param string $group Optional group, unused when the name is complete.
	 * @param string $key Optional key.
	 */
	private function checkbox( $name, $label, $checked, $group = '', $key = '' ) {
		unset( $group, $key );
		echo '<label class="lrps-checkline"><input type="hidden" name="' . esc_attr( $name ) . '" value="0" /><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> <span>' . esc_html( $label ) . '</span></label>';
	}

	/**
	 * Prints the brand mark from the plugin file, so a blocked image URL still shows it.
	 */
	private function logo() {
		$path = LRPS_PATH . 'assets/brand/local-rank-white-green.svg';
		$svg  = is_readable( $path ) ? file_get_contents( $path ) : '';
		if ( ! is_string( $svg ) || false === strpos( $svg, '<svg' ) ) {
			echo '<p class="lrps-wordmark">Local Rank</p>';
			return;
		}
		$svg = preg_replace( '/<\?xml[^>]*\?>/', '', $svg );
		$svg = preg_replace( '/<style\b[^>]*>.*?<\/style>/s', '', (string) $svg );
		$svg = str_replace(
			array( 'class="cls-1"', 'class="cls-2"' ),
			array( 'fill="#ffffff"', 'fill="#a3d115"' ),
			(string) $svg
		);
		$svg = preg_replace( '/<svg\b/', '<svg class="lrps-logo" role="img" aria-label="Local Rank" width="220" height="47"', (string) $svg, 1 );
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- local brand file, not user input.
	}

	/**
	 * One sentence under the title, for the screen that is open.
	 *
	 * @param string $tab Current tab.
	 * @return string
	 */
	private function kicker( $tab ) {
		if ( 'comecar' === $tab ) {
			return __( 'Preencha o formulário. O plugin monta as URLs, os shortcodes e o schema. Depois, a próxima aba mostra o que falta fazer no ACF, no Elementor, no WP All Import e no Yoast.', 'local-rank-programmatic-seo' );
		}
		if ( 'roteiro' === $tab ) {
			return __( 'A parte técnica deste cliente já está no plugin. Siga a ordem. ACF, Elementor, WP All Import e Yoast continuam nas etapas deles.', 'local-rank-programmatic-seo' );
		}
		return __( 'Ajustes do projeto. O formulário da aba Começar cobre o caminho normal.', 'local-rank-programmatic-seo' );
	}

	/**
	 * Saves the setup form and opens the handoff.
	 */
	private function apply_wizard() {
		$input  = wp_unslash( $_POST );
		$input  = is_array( $input ) ? $input : array();
		$result = Wizard::build( $this->config->all(), $input );
		if ( ! empty( $result['errors'] ) ) {
			set_transient( 'lrps_form_errors_' . get_current_user_id(), $result['errors'], MINUTE_IN_SECONDS );
			set_transient( 'lrps_form_old_' . get_current_user_id(), $this->remember( $input ), MINUTE_IN_SECONDS );
			$this->redirect( 'error', 'comecar' );
		}

		$this->config->save( $result['config'] );
		delete_option( Urls_Module::REWRITE_OPTION );
		$this->redirect( 'wizard', 'roteiro' );
	}

	/**
	 * Human status label.
	 *
	 * @param string $status ok|warn|fail.
	 * @return string
	 */
	private function status_label( $status ) {
		if ( 'ok' === $status ) {
			return __( 'Ok', 'local-rank-programmatic-seo' );
		}
		if ( 'warn' === $status ) {
			return __( 'Atenção', 'local-rank-programmatic-seo' );
		}
		return __( 'Bloqueio', 'local-rank-programmatic-seo' );
	}
}
