<?php
/*
Plugin Name: HM Related Posts
Author: Human Made Limited
Version: 1.2.2
Author URI: https://humanmade.com
*/

define( 'HMRP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HMRP_URL', plugin_dir_url( __FILE__ ) );

require_once( HMRP_PATH . '/inc/namespace.php' );

// Plugin bootstrap.
HM\Related_Posts\setup();

/**
 * Generates an array of related posts.
 *
 * @deprecated 1.2.0
 *
 * @param int $limit. (default: 10)
 * @param array $post_types. (default: 'post')
 * @param array $taxonomies. (default: 'post_tag') This is not the taxonomies which are used to compare, it is the taxonomies for the post
 * @param array $terms_not_in. (default: empty array) This should be an array of term objects.
 * @return array - post IDs
 */
function hm_rp_get_related_posts( $post_id, $args = [] ) { // phpcs:ignore
	_deprecated_function( __FUNCTION__, '1.2.0', 'HM\\Related_Posts\\get' );
	return HM\Related_Posts\get( $post_id, $args );
}
