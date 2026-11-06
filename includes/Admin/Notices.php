<?php
/**
 * Admin notices for conflicts and missing companions.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Admin;

use LocalRank\ProgrammaticSeo\Config\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warns when WPCode snippets from an older install are still active.
 */
final class Notices {

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
	 * Registers the notice hook.
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Whether an older PHP snippet package is still loaded.
	 *
	 * @return bool
	 */
	private function legacy_snippets_active() {
		$functions = array(
			'phf_seo_local_path',
			'phf_seo_register_rewrites',
			'phf_seo_register_shortcodes',
			'phf_shortcode_city_state',
			'phf_problems_register_shortcode',
			'phf_gallery_register_shortcode',
			'phf_shortcode_html_sitemap',
			'phf_local_schema_output',
			'phf_kadence_location_header_options',
			'ph_section_label_shortcode',
		);
		foreach ( $functions as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Prints the operational notices.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->legacy_snippets_active() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Os snippets PHP de SEO programático ainda estão ativos no WPCode. Desative URLs, core, shortcodes, problems, gallery, sitemap, schema da empresa, Kadence das location pages e o section_label antes de publicar com este plugin. Os snippets de CSS do design system podem continuar.', 'local-rank-programmatic-seo' );
			echo '</p></div>';
		}

		if ( $this->config->module( 'elementor' ) && ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'O módulo de Elementor está ligado, mas o Elementor não está ativo. URLs e shortcodes continuam funcionando; Dynamic Tags e Saved Templates não.', 'local-rank-programmatic-seo' );
			echo '</p></div>';
		}
	}
}
