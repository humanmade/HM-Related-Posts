<?php
/*
Plugin Name: HM Related Posts
Author: Human Made Limited
Version: 1.0.1
Author URI: http://www.hmn.md/
*/

define( 'HMRP_PATH', plugin_dir_path( __FILE__ ) );
define( 'HMRP_URL', plugin_dir_url( __FILE__ ) );

require_once( HMRP_PATH . '/hm-related-posts-admin.php' );

if ( defined( 'WP_CLI' ) && WP_CLI )
	require_once( HMRP_PATH . '/hm-related-posts-cli.php' );

/**
 * Generates an array of related posts
 *
 * @param int $limit. (default: 10)
 * @param array $post_types. (default: 'post')
 * @param array $taxonomies. (default: 'post_tag') This is not the taxonomies which are used to compare, it is the taxonomies for the post
 * @param array $terms_not_in. (default: empty array) This should be an array of term objects.
 * @return array - post IDs
 */
function hm_rp_get_related_posts( $post_id, $args = array() ) {

	$default_args = array(
		'limit'        => 10,
		'post_types'   => array( 'post' ),
		'taxonomies'   => array( 'category' ),
		'terms'		   => array(),
		'terms_not_in' => array(),
	);

	$args = wp_parse_args( $args, $default_args );

	extract( $args );

	$transient = sprintf( 'hmrp_%s_%s', $post_id, hash( 'md5', json_encode( $args ) ) );

	if ( ! $related_posts = get_transient( $transient ) ) :

		// Get manually specified related posts.
		$manual_related_posts = $related_posts = array_filter( get_post_meta( $post_id, 'hm_rp_post' ) );
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
			wp_reset_postdata();

			$related_posts = array_merge( $manual_related_posts, $query->posts );
			$related_posts = array_map( 'intval', $related_posts );
			$related_posts = array_unique( $related_posts );

		}

		$related_posts = array_slice( $related_posts, 0, $limit );

		set_transient( $transient, $related_posts, DAY_IN_SECONDS );

	endif;

	return $related_posts;

}
