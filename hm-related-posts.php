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
 * @param array $terms_not_in. (default: empty array)
 * @return array - post IDs
 */
function hm_rp_get_related_posts( $limit = 10, $post_types = array( 'post' ), $taxonomies = array( 'post_tag', 'category' ), $terms_not_in = array(), $args = array() ) {

	global $wpdb;

	$default_args = array(
		'post_id' 	=> get_the_id(),
		'terms'		=> array(),
		'related_post_taxonomies' => $taxonomies
	);

	$args = wp_parse_args( $args, $default_args );

	extract( $args );

	foreach ( $related_post_taxonomies as &$related_post_taxonomy )
		$related_post_taxonomy = $wpdb->prepare( "'%s'", $related_post_taxonomy );

	if ( empty( $post_id ) )
		return;

	$hash = hash( 'md5', json_encode( func_get_args() ) );

	if ( ! $related_posts = wp_cache_get( $post_id . $hash, 'hm_related_posts' ) ) :

		$manual_related_posts = array_filter( get_post_meta( $post_id, 'hm_rp_post' ) );
		$limit = $limit - count( $manual_related_posts );

		if ( empty( $terms ) )
			$term_objects = wp_get_object_terms( $post_id, $taxonomies );
		else
			$term_objects = $terms;

		$terms = array();

		foreach ( $term_objects as $term )
			$terms[] = $term->term_id;

		$terms = array_unique( array_diff( $terms, $terms_not_in ) );

		sort( $terms );

		$post_type_sql = '';

		foreach ( $post_types as $post_type )
			$post_type_sql .= $wpdb->prepare( " OR p.post_type = '%s'", $post_type );

		$post_type_sql = substr( $post_type_sql, 4 );

		$query[] = "SELECT p.ID";
		$query[] = "FROM $wpdb->term_taxonomy t_t, $wpdb->term_relationships t_r, $wpdb->posts p";
		$query[] = "WHERE t_t.term_taxonomy_id = t_r.term_taxonomy_id";
		$query[] = "AND t_r.object_id  = p.ID";

		if ( ! empty( $terms ) ) {
			// Note $related_post_taxonomies has been 'prepared' already.
			$query[] = $wpdb->prepare( "AND t_t.term_id IN ( %s )", implode( ', ', $terms ) );
			$query[] = "AND t_t.taxonomy IN ( " . implode( ', ', $related_post_taxonomies ) . " )";
		}

		if ( ! empty( $terms_not_in ) ) {
			$query[] = $wpdb->prepare(
				"AND p.ID NOT IN ( SELECT tr.object_id FROM wp_term_relationships AS tr INNER JOIN wp_term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.term_id IN ( %s ) )",
				implode( ', ', $terms_not_in )
			);
		}

		$query[] = "AND ( $post_type_sql )";
		$query[] = $wpdb->prepare( "AND p.ID != %d", $post_id );
		$query[] = "AND p.post_status = 'publish'";
		$query[] = "GROUP BY t_r.object_id";
		$query[] = $wpdb->prepare( "ORDER BY count(t_r.object_id) DESC, p.post_date_gmt DESC LIMIT 0, %d", $limit );

		$query = implode( ' ', $query );

		$related_posts = array_merge( $manual_related_posts, $wpdb->get_col( $query ) );
		$related_posts = array_map( 'intval', $related_posts );

		wp_cache_add( $post_id . $hash, $related_posts, 'hm_related_posts' );

	endif;

	return $related_posts;

}
