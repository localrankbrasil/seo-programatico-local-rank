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
 * Warns when a companion plugin the screen expects is missing.
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
	 * Prints the operational notices.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->config->module( 'elementor' ) && ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'O módulo de Elementor está ligado, mas o Elementor não está ativo. URLs e shortcodes continuam funcionando; Dynamic Tags e Saved Templates não.', 'local-rank-programmatic-seo' );
			echo '</p></div>';
		}
	}
}
