<?php
/**
 * Related posts functions.
 *
 * @package hm-related-posts
 */

namespace HM\Related_Posts;

// Load admin UI.
require_once( HMRP_PATH . 'inc/admin/namespace.php' );

// Load CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( HMRP_PATH . 'inc/class-cli.php' );
}

/**
 * Generates an array of related posts
 *
 * @param int $limit. (default: 10)
 * @param array $post_types. (default: 'post')
 * @param array $taxonomies. (default: 'post_tag') This is not the taxonomies which are used to compare, it is the taxonomies for the post
 * @param array $terms_not_in. (default: empty array) This should be an array of term objects.
 * @return array - post IDs
 */
function get( $post_id, $args = [] ) {

	$default_args = [
		'limit'        => 10,
		'post_types'   => [ 'post' ],
		'taxonomies'   => [ 'category' ],
		'terms'        => [],
		'terms_not_in' => [],
	];

	$args = wp_parse_args( $args, $default_args );

	$transient = sprintf( 'hmrp_%s_%s', $post_id, hash( 'md5', wp_json_encode( $args ) ) );

	$related_posts = get_transient( $transient );

	if ( ! $related_posts ) :

		// Get manually specified related posts.
		$manual_related_posts = array_filter( get_post_meta( $post_id, 'hm_rp_post' ) );
		$related_posts        = $manual_related_posts;
		$query_limit          = $args['limit'] - count( $manual_related_posts );

		if ( $query_limit > 0 ) {

			if ( empty( $args['terms'] ) ) {
				$term_objects = wp_get_object_terms( $post_id, $taxonomies );
			} else {
				$term_objects = $args['terms'];
			}

			$query_args = [
				'post_type'      => $args['post_types'],
				'post_status'    => 'publish',
				'posts_per_page' => $query_limit,
				'order'          => 'DESC',
				'tax_query'      => [], // phpcs:ignore
				'fields'         => 'ids',
				'post__not_in'   => array_merge( [ $post_id ], $manual_related_posts ),
			];

			foreach ( $term_objects as $term ) {
				if ( ! isset( $query_args['tax_query'][ $term->taxonomy ] ) ) {
					$query_args['tax_query'][ $term->taxonomy ] = [
						'taxonomy' => $term->taxonomy,
						'field'    => 'id',
						'terms'    => [],
					];
				}

				array_push( $query_args['tax_query'][ $term->taxonomy ]['terms'], $term->term_id );
			}

			foreach ( $args['terms_not_in'] as $term ) {

				if ( isset( $query_args['tax_query'][ $term->taxonomy ] ) ) {
					continue;
				}

				$query_args['tax_query'][ 'not_' . $term->taxonomy ] = [
					'taxonomy' => $term->taxonomy,
					'field'    => 'id',
					'terms'    => [],
					'operator' => 'NOT IN',
				];
			}

			$query_args['tax_query'] = array_values( $query_args['tax_query'] ); // phpcs:ignore
			$query_args['tax_query']['relation'] = 'OR';

			$query = new WP_Query( $query_args );
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
