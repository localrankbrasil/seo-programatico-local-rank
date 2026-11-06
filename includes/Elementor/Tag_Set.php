<?php
/**
 * Elementor Dynamic Tags for programmatic pages.
 *
 * This file is loaded only after Elementor registers dynamic tags.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Elementor;

use LocalRank\ProgrammaticSeo\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the tag group and every tag instance.
 */
final class Tag_Set {

	/**
	 * Registers tags on the Elementor manager.
	 *
	 * @param object $manager Dynamic tag manager.
	 */
	public static function register( $manager ) {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register_group' ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}

		$manager->register_group(
			'lrps',
			array(
				'title' => __( 'SEO programático', 'local-rank-programmatic-seo' ),
			)
		);

		$classes = array(
			City_Tag::class,
			State_Tag::class,
			City_State_Tag::class,
			Service_Name_Tag::class,
			Hero_Suffix_Tag::class,
			Hero_Description_Tag::class,
			Page_Title_Tag::class,
			Cover_Alt_Tag::class,
			Location_Url_Tag::class,
			Map_Url_Tag::class,
			Hero_Image_Tag::class,
			Cover_Image_Tag::class,
			Faq_Question_Tag::class,
			Faq_Answer_Tag::class,
		);

		foreach ( $classes as $class ) {
			$manager->register( new $class() );
		}
	}
}

/**
 * Shared text tag.
 */
abstract class Text_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Tag group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'lrps';
	}

	/**
	 * Text category.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
	}

	/**
	 * Shortcode module for the current request.
	 *
	 * @return \LocalRank\ProgrammaticSeo\Modules\Shortcode_Module
	 */
	protected function shortcodes() {
		return Plugin::instance()->shortcodes();
	}
}

/**
 * City name.
 */
final class City_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-city';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'City', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the city.
	 */
	public function render() {
		echo $this->shortcodes()->city_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the shortcode.
	}
}

/**
 * State.
 */
final class State_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-state';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'State', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the state.
	 */
	public function render() {
		echo $this->shortcodes()->state(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * City, state.
 */
final class City_State_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-city-state';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'City, State', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the combined label.
	 */
	public function render() {
		echo $this->shortcodes()->city_state(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Service name.
 */
final class Service_Name_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-name';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Name', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the service name.
	 */
	public function render() {
		echo $this->shortcodes()->service_name(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Hero suffix.
 */
final class Hero_Suffix_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-hero-suffix';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Hero Suffix', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the suffix.
	 */
	public function render() {
		echo $this->shortcodes()->hero_suffix(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Hero description.
 */
final class Hero_Description_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-hero-description';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Hero Description', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the description.
	 */
	public function render() {
		echo $this->shortcodes()->hero_description(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Calculated page title.
 */
final class Page_Title_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-page-title';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Page Title', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints the page title.
	 */
	public function render() {
		echo $this->shortcodes()->page_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Cover alt text.
 */
final class Cover_Alt_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-cover-alt';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Cover Alt', 'local-rank-programmatic-seo' );
	}

	/**
	 * Prints escaped alt text.
	 */
	public function render() {
		echo esc_html( $this->shortcodes()->cover_alt() );
	}
}

/**
 * Shared URL tag.
 */
abstract class Url_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

	/**
	 * @return string
	 */
	public function get_group() {
		return 'lrps';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
	}
}

/**
 * Location permalink.
 */
final class Location_Url_Tag extends Url_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-location-url';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Location URL', 'local-rank-programmatic-seo' );
	}

	/**
	 * Service slug and self-link controls.
	 */
	protected function register_controls() {
		$this->add_control(
			'service',
			array(
				'label'       => __( 'Slug do serviço', 'local-rank-programmatic-seo' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Vazio ou city aponta para a página da cidade.', 'local-rank-programmatic-seo' ),
			)
		);
		$this->add_control(
			'skip_current',
			array(
				'label'   => __( 'Omitir a página atual', 'local-rank-programmatic-seo' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);
	}

		/**
		 * Resolved URL.
		 *
		 * Existing templates store the destination in `service`. The value `city`
		 * points at the city page. Skipping the current URL is opt-in.
		 *
		 * @param array<string,mixed> $options Elementor options.
		 * @return string
		 */
		public function get_value( array $options = array() ) {
			unset( $options );
			$service = strtolower( trim( (string) $this->get_settings( 'service' ) ) );
			$skip    = ( 'yes' === (string) $this->get_settings( 'skip_current' ) ) ? '1' : '0';
		if ( '' === $service || 'city' === $service ) {
			return Plugin::instance()->shortcodes()->location_url(
				array(
					'target'       => 'city',
					'skip_current' => $skip,
				)
			);
		}
		return Plugin::instance()->shortcodes()->location_url(
			array(
				'service'      => $service,
				'skip_current' => $skip,
			)
		);
	}
}

/**
 * Map embed URL.
 */
final class Map_Url_Tag extends Url_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-map-embed-url';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Map Embed URL', 'local-rank-programmatic-seo' );
	}

	/**
	 * @param array<string,mixed> $options Elementor options.
	 * @return string
	 */
	public function get_value( array $options = array() ) {
		unset( $options );
		return Plugin::instance()->shortcodes()->map_url();
	}
}

/**
 * Shared image tag.
 */
abstract class Image_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

	/**
	 * @return string
	 */
	public function get_group() {
		return 'lrps';
	}

	/**
	 * @return string[]
	 */
	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY );
	}

	/**
	 * Which catalog key this tag reads.
	 *
	 * @return string
	 */
	abstract protected function image_key();

	/**
	 * @param array<string,mixed> $options Elementor options.
	 * @return array{id:int|string,url:string}
	 */
	public function get_value( array $options = array() ) {
		unset( $options );
		$media = Plugin::instance()->shortcodes()->service_image( $this->image_key() );
		if ( empty( $media['id'] ) || empty( $media['url'] ) ) {
			return array(
				'id'  => '',
				'url' => '',
			);
		}
		return array(
			'id'  => (int) $media['id'],
			'url' => esc_url( $media['url'] ),
		);
	}
}

/**
 * Hero background.
 */
final class Hero_Image_Tag extends Image_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-hero-image';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Hero Image', 'local-rank-programmatic-seo' );
	}

	/**
	 * @return string
	 */
	protected function image_key() {
		return 'hero_image';
	}
}

/**
 * Inner cover.
 */
final class Cover_Image_Tag extends Image_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-service-cover-image';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'Service Cover Image', 'local-rank-programmatic-seo' );
	}

	/**
	 * @return string
	 */
	protected function image_key() {
		return 'cover_image';
	}
}

/**
 * FAQ question by index.
 */
final class Faq_Question_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-faq-question';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'FAQ Question', 'local-rank-programmatic-seo' );
	}

	/**
	 * Index control.
	 */
	protected function register_controls() {
		$this->add_control(
			'index',
			array(
				'label'   => __( 'Índice', 'local-rank-programmatic-seo' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
			)
		);
	}

	/**
	 * Prints the question.
	 */
	public function render() {
		echo Plugin::instance()->shortcodes()->faq_question( array( 'index' => (string) $this->get_settings( 'index' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * FAQ answer by index.
 */
final class Faq_Answer_Tag extends Text_Tag {

	/**
	 * @return string
	 */
	public function get_name() {
		return Plugin::instance()->config()->prefix() . '-faq-answer';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return strtoupper( Plugin::instance()->config()->prefix() ) . ' ' . __( 'FAQ Answer', 'local-rank-programmatic-seo' );
	}

	/**
	 * Index control.
	 */
	protected function register_controls() {
		$this->add_control(
			'index',
			array(
				'label'   => __( 'Índice', 'local-rank-programmatic-seo' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 1,
				'min'     => 1,
			)
		);
	}

	/**
	 * Prints the answer.
	 */
	public function render() {
		echo Plugin::instance()->shortcodes()->faq_answer( array( 'index' => (string) $this->get_settings( 'index' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
