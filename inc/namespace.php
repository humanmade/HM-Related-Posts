<?php
/**
 * Related posts functions.
 *
 * @package hm-related-posts
 */

namespace HM\Related_Posts;

use WP_Query;

// Load admin UI.
require_once( HMRP_PATH . 'inc/admin/namespace.php' );

// Load CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( HMRP_PATH . 'inc/class-cli.php' );
}

function setup() {
	add_action( 'init', __NAMESPACE__ . '\\init' );
}

function init() {
	add_filter( 'ep_formatted_args', __NAMESPACE__ . '\\ep_formatted_args', 20, 2 );

	// Don't clobber the post_filter query if present.
	remove_filter( 'ep_formatted_args', 'ep_related_posts_formatted_args', 10, 2 );
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
		'ep_integrate' => defined( 'EP_VERSION' ),
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
				$term_objects = wp_get_object_terms( $post_id, $args['taxonomies'] );
			} else {
				$term_objects = $args['terms'];
			}

			if ( is_wp_error( $term_objects ) ) {
				$term_objects = [];
			}

			$query_args = [
				'post_type'      => $args['post_types'],
				'post_status'    => 'publish',
				'posts_per_page' => $query_limit,
				'order'          => 'DESC',
				'tax_query'      => [], // phpcs:ignore
				'fields'         => 'ids',
				'post__not_in'   => array_merge( [ $post_id ], $manual_related_posts ),
				'ep_integrate'   => $args['ep_integrate'],
			];

			foreach ( $term_objects as $term ) {
				if ( isset( $query_args['tax_query'][ $term->taxonomy ] ) ) {
					continue;
				}

				$query_args['tax_query'][ $term->taxonomy ] = [
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => [],
				];

				array_push( $query_args['tax_query'][ $term->taxonomy ]['terms'], $term->term_id );
			}

			foreach ( $args['terms_not_in'] as $term ) {
				if ( isset( $query_args['tax_query'][ $term->taxonomy ] ) ) {
					continue;
				}

				$query_args['tax_query'][ 'not_' . $term->taxonomy ] = [
					'taxonomy' => $term->taxonomy,
					'field'    => 'term_id',
					'terms'    => [],
					'operator' => 'NOT IN',
				];
			}

			$query_args['tax_query'] = array_values( $query_args['tax_query'] ); // phpcs:ignore
			$query_args['tax_query']['relation'] = 'OR';

			// Use ElasticPress if available.
			if ( $args['ep_integrate'] ) {
				$related_taxonomies = array_map( function ( $taxonomy ) {
					return "terms.{$taxonomy}.name";
				}, $args['taxonomies'] );

				$related_fields = apply_filters( 'hm_related_posts_fields', array_merge( [
					'post_title',
					'post_content',
				], $related_taxonomies ) );

				$query_args['more_like']        = $post_id;
				$query_args['more_like_fields'] = $related_fields;
			}

			$query = new WP_Query( $query_args );
			wp_reset_postdata();

			$related_posts = array_merge( $manual_related_posts, $query->posts );
			$related_posts = array_map( 'intval', $related_posts );
			$related_posts = array_unique( $related_posts );
		}

		$related_posts = array_slice( $related_posts, 0, $args['limit'] );

		set_transient( $transient, $related_posts, 600 );

	endif;

	return $related_posts;
}

/**
 * Patch ElasticPress's overzealous rewrite of the entire query on a more_like_this query.
 *
 * @param array $formatted_args
 * @param array $args
 * @return array
 */
function ep_formatted_args( $formatted_args, $args ) {
	if ( empty( $args['more_like'] ) ) {
		return $formatted_args;
	}

	$more_like = is_array( $args['more_like'] ) ? $args['more_like'] : [ $args['more_like'] ];

	// Remove this as it disables score based sorting.
	unset( $formatted_args['sort'] );

	// Set and override the default more like fields.
	$more_like_fields = [ 'post_title', 'post_content', 'terms.post_tag.name' ];

	if ( isset( $args['more_like_fields'] ) ) {
		$more_like_fields = $args['more_like_fields'];
	}

	$formatted_args['query'] = [
		'more_like_this' => [
			'like'            => array_map( function ( $id ) {
				return [
					'_index' => ep_get_index_name(),
					'_type'  => 'post',
					'_id'    => $id,
				];
			}, $more_like ),
			'fields'          => apply_filters( 'ep_related_posts_fields', $more_like_fields ),
			'min_term_freq'   => 1,
			'max_query_terms' => 12,
			'min_doc_freq'    => 1,
		],
	];

	return $formatted_args;
}
