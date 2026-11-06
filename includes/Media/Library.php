<?php
/**
 * Resolves catalog image paths inside the current uploads directory.
 *
 * @package LocalRank\ProgrammaticSeo
 */

namespace LocalRank\ProgrammaticSeo\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Never hardcodes a staging domain.
 */
final class Library {

	/**
	 * Request cache keyed by relative path.
	 *
	 * @var array<string,array{id:int,url:string}>
	 */
	private $cache = array();

	/**
	 * Resolves a relative uploads path to an image attachment.
	 *
	 * @param string $relative Path below the uploads directory.
	 * @return array{id:int,url:string}
	 */
	public function resolve( $relative ) {
		$relative = ltrim( (string) $relative, '/' );
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return $this->empty_media();
		}
		if ( isset( $this->cache[ $relative ] ) ) {
			return $this->cache[ $relative ];
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return $this->remember( $relative, $this->empty_media() );
		}

		$file = trailingslashit( $uploads['basedir'] ) . $relative;
		if ( ! is_readable( $file ) ) {
			$this->log( 'Missing media file ' . $relative );
			return $this->remember( $relative, $this->empty_media() );
		}

		$url = trailingslashit( $uploads['baseurl'] ) . $relative;
		$id  = (int) attachment_url_to_postid( $url );
		if ( $id <= 0 ) {
			global $wpdb;
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
					$relative
				)
			);
			$id = (int) $found;
		}

		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			$this->log( 'Media file is not an image attachment: ' . $relative );
			return $this->remember( $relative, $this->empty_media() );
		}

		return $this->remember(
			$relative,
			array(
				'id'  => $id,
				'url' => (string) $url,
			)
		);
	}

	/**
	 * Empty Elementor image value.
	 *
	 * @return array{id:int,url:string}
	 */
	private function empty_media() {
		return array(
			'id'  => 0,
			'url' => '',
		);
	}

	/**
	 * Stores a resolution.
	 *
	 * @param string                    $relative Relative path.
	 * @param array{id:int,url:string}  $media    Result.
	 * @return array{id:int,url:string}
	 */
	private function remember( $relative, array $media ) {
		$this->cache[ $relative ] = $media;
		return $media;
	}

	/**
	 * Debug log.
	 *
	 * @param string $message Message.
	 */
	private function log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[LRPS] ' . $message );
		}
	}
}
