<?php
/**
 * Elementor Dynamic Tags and token expansion on location pages.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Modules;

use LocalRank\ProgrammaticSeo\Context\Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the core to Elementor without choosing the layout.
 */
final class Elementor_Module {

	/**
	 * Context.
	 *
	 * @var Store
	 */
	private $store;

	/**
	 * Shortcodes.
	 *
	 * @var Shortcode_Module
	 */
	private $shortcodes;

	/**
	 * @param Store            $store      Context.
	 * @param Shortcode_Module $shortcodes Shortcodes.
	 */
	public function __construct( Store $store, Shortcode_Module $shortcodes ) {
		$this->store      = $store;
		$this->shortcodes = $shortcodes;
	}

	/**
	 * Registers hooks.
	 */
	public function register() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
		add_filter( 'elementor/widget/render_content', array( $this, 'render_content' ), 20, 2 );
		add_filter( 'elementor/theme/get_location_templates/single', array( $this, 'prefer_level_template' ), 20, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'disable_element_cache' ) );
	}

	/**
	 * Registers Dynamic Tags after Elementor is available.
	 *
	 * @param object $manager Elementor dynamic tag manager.
	 */
	public function register_tags( $manager ) {
		require_once LRPS_PATH . 'includes/Elementor/Tag_Set.php';
		\LocalRank\ProgrammaticSeo\Elementor\Tag_Set::register( $manager );
	}

	/**
	 * Expands tokens and owned shortcodes in widgets on a location page.
	 *
	 * @param string $content Widget HTML.
	 * @param object $widget  Widget instance.
	 * @return string
	 */
	public function render_content( $content, $widget ) {
		if ( ! is_string( $content ) || '' === $content || ! $this->store->is_location() ) {
			return $content;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}

		$raw = $this->shortcode_widget_source( $widget );
		if ( null !== $raw ) {
			$html = $this->shortcodes->expand_html( $raw );
			if ( function_exists( 'do_shortcode' ) ) {
				$html = do_shortcode( $html );
			}
			return '<div class="elementor-shortcode">' . $html . '</div>';
		}

		return $this->shortcodes->expand_html( $content );
	}

	/**
	 * Raw shortcode widget text when it still contains a shortcode or a token.
	 *
	 * Elementor sometimes prefixes a URL shortcode with https:// or renders the
	 * widget before the location context can expand tokens inside attributes.
	 *
	 * @param object $widget Widget instance.
	 * @return string|null
	 */
	private function shortcode_widget_source( $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) || 'shortcode' !== $widget->get_name() ) {
			return null;
		}
		if ( ! method_exists( $widget, 'get_settings' ) ) {
			return null;
		}
		$raw = $widget->get_settings( 'shortcode' );
		if ( ! is_string( $raw ) ) {
			return null;
		}
		if ( false === strpos( $raw, '[' ) && false === strpos( $raw, '{' ) ) {
			return null;
		}
		return $raw;
	}

	/**
	 * Moves the configured template to the front when Elementor already listed it.
	 *
	 * Display conditions remain the source of truth. This only breaks a tie.
	 *
	 * @param mixed  $templates Templates Elementor is considering.
	 * @param string $location  Theme location.
	 * @return mixed
	 */
	public function prefer_level_template( $templates, $location ) {
		unset( $location );
		$plugin = \LocalRank\ProgrammaticSeo\Plugin::instance();
		$settings = $plugin->config()->get( 'templates' );
		if ( ! is_array( $settings ) || empty( $settings['force'] ) || ! is_array( $templates ) ) {
			return $templates;
		}
		if ( ! $this->store->is_location() ) {
			return $templates;
		}

		$record = $plugin->reader()->read( $this->store->effective_id() );
		if ( empty( $record['valid'] ) ) {
			return $templates;
		}

		$map    = isset( $settings['map'] ) && is_array( $settings['map'] ) ? $settings['map'] : array();
		$wanted = isset( $map[ $record['level_key'] ] ) ? (int) $map[ $record['level_key'] ] : 0;
		if ( $wanted <= 0 ) {
			return $templates;
		}

		$picked = null;
		$rest   = array();
		foreach ( $templates as $template ) {
			$id = 0;
			if ( $template instanceof \WP_Post ) {
				$id = (int) $template->ID;
			} elseif ( is_object( $template ) && isset( $template->ID ) ) {
				$id = (int) $template->ID;
			} elseif ( is_numeric( $template ) ) {
				$id = (int) $template;
			}
			if ( $id === $wanted && null === $picked ) {
				$picked = $template;
			} else {
				$rest[] = $template;
			}
		}

		if ( null === $picked ) {
			return $templates;
		}

		array_unshift( $rest, $picked );
		return $rest;
	}

	/**
	 * Asks Elementor not to cache widgets whose content depends on the record.
	 *
	 * @param object $element Element being rendered.
	 */
	public function disable_element_cache( $element ) {
		if ( ! $this->store->is_location() || ! is_object( $element ) || ! method_exists( $element, 'set_settings' ) ) {
			return;
		}
		$element->set_settings( '_element_cache', 'no' );
	}
}
