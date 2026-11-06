<?php
/**
 * Plugin wiring.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo;

use LocalRank\ProgrammaticSeo\Admin\Notices;
use LocalRank\ProgrammaticSeo\Admin\Page;
use LocalRank\ProgrammaticSeo\Config\Defaults;
use LocalRank\ProgrammaticSeo\Config\Repository;
use LocalRank\ProgrammaticSeo\Context\Store;
use LocalRank\ProgrammaticSeo\Data\Catalog;
use LocalRank\ProgrammaticSeo\Data\Record_Reader;
use LocalRank\ProgrammaticSeo\Data\Tokens;
use LocalRank\ProgrammaticSeo\Media\Library;
use LocalRank\ProgrammaticSeo\Modules\Company_Schema_Module;
use LocalRank\ProgrammaticSeo\Modules\Elementor_Module;
use LocalRank\ProgrammaticSeo\Modules\Front_Module;
use LocalRank\ProgrammaticSeo\Modules\Kadence_Module;
use LocalRank\ProgrammaticSeo\Modules\Schema_Module;
use LocalRank\ProgrammaticSeo\Modules\Shortcode_Module;
use LocalRank\ProgrammaticSeo\Modules\Sitemap_Module;
use LocalRank\ProgrammaticSeo\Modules\Structure_Module;
use LocalRank\ProgrammaticSeo\Modules\Template_Module;
use LocalRank\ProgrammaticSeo\Modules\Urls_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the modules selected for the current client.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Configuration.
	 *
	 * @var Repository
	 */
	private $config;

	/**
	 * Catalog.
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Context.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Reader.
	 *
	 * @var Record_Reader
	 */
	private $reader;

	/**
	 * Shortcodes.
	 *
	 * @var Shortcode_Module
	 */
	private $shortcodes;

	/**
	 * Schema.
	 *
	 * @var Schema_Module
	 */
	private $schema;

	/**
	 * URLs, when that module is enabled.
	 *
	 * @var Urls_Module|null
	 */
	private $urls = null;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Creates the first option and flushes routes.
	 */
	public static function activate() {
		if ( false === get_option( Repository::OPTION, false ) ) {
			add_option( Repository::OPTION, Defaults::all(), '', true );
		}

		$plugin = self::instance();
		$plugin->boot();
		if ( $plugin->urls() instanceof Urls_Module ) {
			$plugin->urls()->register_rewrites();
			flush_rewrite_rules( false );
			update_option( Urls_Module::REWRITE_OPTION, $plugin->urls()->hash(), false );
		}
	}

	/**
	 * Asks WordPress to rebuild routes without this plugin.
	 */
	public static function deactivate() {
		delete_option( 'rewrite_rules' );
		delete_option( Urls_Module::REWRITE_OPTION );
	}

	/**
	 * Registers hooks once.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->config     = new Repository();
		$this->catalog    = new Catalog( $this->config );
		$this->store      = new Store( $this->config );
		$this->reader     = new Record_Reader( $this->config, $this->catalog );
		$tokens           = new Tokens( $this->config );
		$media            = new Library();
		$templates        = new Template_Module( $this->catalog, $this->reader, $this->store );
		$this->schema     = new Schema_Module( $this->config, $this->reader, $this->store, $tokens );
		$this->shortcodes = new Shortcode_Module( $this->config, $this->catalog, $this->reader, $this->store, $tokens, $media, $templates, $this->schema );

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'template_redirect', array( $this->store, 'lock_queried' ), 1 );

		if ( $this->config->module( 'structure' ) ) {
			( new Structure_Module( $this->config, $this->reader ) )->register();
		}
		if ( $this->config->module( 'urls' ) ) {
			$this->urls = new Urls_Module( $this->config, $this->catalog, $this->reader, $this->store );
			$this->urls->register();
		}
		if ( $this->config->module( 'shortcodes' ) ) {
			add_action( 'init', array( $this->shortcodes, 'register' ) );
		}
		if ( $this->config->module( 'schema' ) ) {
			$this->schema->register();
		}
		if ( $this->config->module( 'company_schema' ) ) {
			( new Company_Schema_Module( $this->config, $this->store ) )->register();
		}
		if ( $this->config->module( 'elementor' ) ) {
			( new Elementor_Module( $this->store, $this->shortcodes ) )->register();
		}
		if ( $this->config->module( 'kadence' ) ) {
			( new Kadence_Module( $this->config ) )->register();
		}
		if ( $this->config->module( 'html_sitemap' ) ) {
			( new Sitemap_Module( $this->config, $this->catalog, $this->reader ) )->register();
		}

		( new Front_Module( $this->config, $this->store, $this->reader ) )->register();
		( new Page( $this->config ) )->register();
		( new Notices( $this->config ) )->register();
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'local-rank-programmatic-seo', false, dirname( plugin_basename( LRPS_FILE ) ) . '/languages' );
	}

	/**
	 * Configuration repository.
	 *
	 * @return Repository
	 */
	public function config() {
		return $this->config;
	}

	/**
	 * Record reader.
	 *
	 * @return Record_Reader
	 */
	public function reader() {
		return $this->reader;
	}

	/**
	 * Shortcodes.
	 *
	 * @return Shortcode_Module
	 */
	public function shortcodes() {
		return $this->shortcodes;
	}

	/**
	 * Schema.
	 *
	 * @return Schema_Module
	 */
	public function schema() {
		return $this->schema;
	}

	/**
	 * URL module, or null when it is disabled.
	 *
	 * @return Urls_Module|null
	 */
	public function urls() {
		return $this->urls;
	}
}
