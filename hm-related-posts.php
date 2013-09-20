<?php
/*
Plugin Name: HM Related Posts
Author: Human Made Limited
Version: 0.1
Author URI: http://www.hmn.md/
*/

define( 'HMRP_PATH', str_replace( '\\', '/', dirname( __FILE__ ) ) );
define( 'HMRP_URL', str_replace( str_replace( '\\', '/', WP_CONTENT_DIR ), str_replace( '\\', '/', WP_CONTENT_URL ), HMRP_PATH ) );

require_once( HMRP_PATH . '/hm-related-posts-admin.php' );

/**
 * Generates an array of related posts
 *
 * @param int $limit. (default: 10)
 * @param array $post_types. (default: 'post')
 * @param array $taxonomies. (default: 'post_tag') This is not the taxonomies which are used to compare, it is the taxonomies for the post
 * @param array $terms_not_in. (default: empty array) This should be an array of term objects.
 * @return array - post IDs
 */
function hm_rp_get_related_posts( $limit = 10, $post_types = array( 'post' ), $taxonomies = array( 'post_tag', 'category' ), $terms_not_in = array(), $args = array() ) {

	$default_args = array(
		'post_id' 	=> get_the_id(),
		'terms'		=> array(),
		'related_post_taxonomies' => $taxonomies
	);

	$args = wp_parse_args( $args, $default_args );

	extract( $args );

	if ( empty( $post_id ) )
		return;

	$hash = hash( 'md5', json_encode( func_get_args() ) );

	if ( ! $related_posts = get_transient( $post_id . $hash, 'hm_related_posts' ) ) :

		// Get manually specified related posts.
		$manual_related_posts = array_filter( get_post_meta( $post_id, 'hm_rp_post' ) );
		$query_limit = $limit - count( $manual_related_posts );
						
		if ( $query_limit > 0 ) {

			if ( empty( $terms ) )
				$term_objects = wp_get_object_terms( $post_id, $taxonomies );
			else
				$term_objects = $terms;

			$query_args = array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $query_limit,
				'order'          => 'DESC',
				'tax_query'      => array(),
				'fields'         => 'ids',
				'post__not_in'   => array_merge( array( $post_id ), $manual_related_posts )
			);

			foreach ( $term_objects as $term ) {

				if ( ! isset( $query_args['tax_query'][$term->taxonomy] ) )
					$query_args['tax_query'][$term->taxonomy] = array( 
						'taxonomy' => $term->taxonomy, 
						'field' => 'id', 
						'terms' => array() 
					);

				array_push( $query_args['tax_query'][$term->taxonomy]['terms'], $term->term_id );

			}

			foreach ( $terms_not_in as $term ) {

				if ( ! isset( $query_args['tax_query'][$term->taxonomy] ) )
					$query_args['tax_query']['not_' . $term->taxonomy] = array( 
						'taxonomy' => $term->taxonomy, 
						'field' => 'id', 
						'terms' => array(),
						'operator' => 'NOT IN'
					);

			}

			$query_args['tax_query'] = array_values( $query_args['tax_query'] );
			$query_args['tax_query']['relation'] = 'OR';
			
			$query = new WP_QUERY( $query_args );
			
			$related_posts = array_merge( $manual_related_posts, $query->posts );
			$related_posts = array_map( 'intval', $related_posts );

		}

		$related_posts = array_slice( $related_posts, 0, $limit );

		set_transient( $post_id . $hash, $related_posts, 'hm_related_posts', HOUR_IN_SECONDS );

	endif;

	return $related_posts;

}
