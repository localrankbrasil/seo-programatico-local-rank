<?php
/**
 * Plugin Name: Local Rank — Programático
 * Description: Roteiro e lógica de SEO programático: arquitetura, URLs, shortcodes, Dynamic Tags e schema. Não substitui ACF, Elementor, WP All Import nem Yoast.
 * Version: 1.3.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Local Rank Brasil
 * Author URI: https://localrankbrasil.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: local-rank-programmatic-seo
 * Domain Path: /languages
 *
 * @package LocalRank\ProgrammaticSeo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LRPS_VERSION', '1.3.2' );
define( 'LRPS_FILE', __FILE__ );
define( 'LRPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'LRPS_URL', plugin_dir_url( __FILE__ ) );

require LRPS_PATH . 'includes/Autoloader.php';

LocalRank\ProgrammaticSeo\Autoloader::register();

register_activation_hook( LRPS_FILE, array( LocalRank\ProgrammaticSeo\Plugin::class, 'activate' ) );
register_deactivation_hook( LRPS_FILE, array( LocalRank\ProgrammaticSeo\Plugin::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		LocalRank\ProgrammaticSeo\Plugin::instance()->boot();
	},
	20
);
