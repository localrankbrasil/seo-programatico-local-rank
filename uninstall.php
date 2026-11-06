<?php
/**
 * Removes plugin options when the plugin is deleted.
 *
 * @package LocalRank\ProgrammaticSeo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lrps_config' );
delete_option( 'lrps_rewrite_hash' );
delete_option( 'rewrite_rules' );
