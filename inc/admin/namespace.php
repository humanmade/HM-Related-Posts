<?php
/**
 * Related posts admin features.
 *
 * @package hm-related-posts
 */

namespace HM\Related_Posts\Admin;

use WP_Meta_Query;
use WP_Post;
use WP_Query;

/**
 * Add the related posts metabox.
 *
 * @return void
 */
function add_meta_boxes() {
	$post_types = get_post_types_by_support( 'hm-related-posts' );

	/**
	 * Control the post types that should get the related posts metabox.
	 *
	 * @param array $post_types Affected post types.
	 */
	$post_types = apply_filters( 'hm_rp_post_types', $post_types );

	add_meta_box(
		'hm_rp_mb',
		'Related Posts',
		__NAMESPACE__ . '\\override_metabox',
		$post_types
	);
}

add_action( 'add_meta_boxes', __NAMESPACE__ . '\\add_meta_boxes' );

/**
 * Enqueue admin scripts.
 *
 * @return void
 */
function scripts() {
	wp_enqueue_script( 'select2', HMRP_URL . 'inc/admin/assets/select2/select2.js', [ 'jquery' ] );
	wp_enqueue_script( 'hm-rp-scripts', HMRP_URL . 'inc/admin/assets/hm-rp.js' );
	wp_enqueue_script( 'field-select', HMRP_URL . 'inc/admin/assets/field.select.js', [ 'jquery' ] );

	wp_enqueue_style( 'select2', HMRP_URL . 'inc/admin/assets/select2/select2.css' );
	wp_enqueue_style( 'hm-rp-styles', HMRP_URL . 'inc/admin/assets/style.css' );

}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\scripts' );

/**
 * Output main related posts manual override metabox.
 *
 * @param WP_Post $post
 * @return void
 */
function override_metabox( WP_Post $post ) {

	$data = (array) get_post_meta( $post->ID, 'hm_rp_post' );
	wp_nonce_field( 'hm_rp', 'hm_rp_nonce' );

	?>
	<table class="form-table hm_rp_metabox">
		<tr>
			<td style="width: 100%" colspan="12">
				<div class="hm-rp-field repeatable HMRP_Post_Select" data-rep-max="5">

					<div class="field-title">
						<label for="hm_rp_post-hm-rp-field-0"><?php esc_html_e( 'Manually select related post', 'hm-related-posts' ); ?></label>
					</div>

					<div class="hm_rp_metabox_description"><?php esc_html_e( 'By default, 5 related posts are dynamically generated. Manually override these here', 'hm-related-posts' ); ?></div>

					<?php

					foreach ( $data as $key => $value ) :
						if ( ! empty( $value ) ) :
							echo '<div class="hm-rp-field-item" data-class="HMRP_Post_Select" style="position: relative">';
							override_metabox_field( $key, intval( $value ) );
							echo '</div>';
						endif;
					endforeach;

					?>

					<div class="hm-rp-field-item hidden" data-class="HMRP_Post_Select" style="position: relative">
						<?php override_metabox_field( 'x' ); // x used to identify hidden placeholder field. ?>
					</div>

					<button class="button hm-rp-repeat-field"><?php esc_html_e( 'Add New', 'hm-related-posts' ); ?></button>

				</div>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Output metabox field input.
 *
 * @param int $id
 * @param int $value
 * @return void
 */
function override_metabox_field( $id, $value = null ) {
	?>

	<a class="hm-rp-delete-field">&times;</a>

	<input id="hm_rp_post-hm-rp-field-<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"  name="hm_rp_post[hm-rp-field-<?php echo esc_attr( $id ); ?>]"  class="hm_rp_select" data-field-id="hm_rp_post_<?php echo esc_attr( $id ); ?>" style="width: 100%" />

	<?php override_metabox_script( $id, $value, [] ); ?>

	<?php
}

/**
 * Output inline metabox JS.
 *
 * @param int $id
 * @param boolean $value
 * @param boolean $ajax_args
 * @return void
 */
function override_metabox_script( $id, $value = false, $ajax_args = false ) {

	$query = wp_json_encode( $ajax_args ? wp_parse_args( $ajax_args ) : (object) [] );

	$url = add_query_arg( [
		'action' => 'hm_rp_ajax_post_select',
		'hm_rp_ajax_post_select_nonce' => wp_create_nonce( 'hm_rp_ajax_post_select' ),
		'post_id' => get_the_id(),
	], admin_url( 'admin-ajax.php' ) );

	?>

	<script type="text/javascript">

		jQuery( document ).ready( function() {

			var options = {
				placeholder: "<?php echo esc_js( __( 'Type to search', 'hm-related-posts' ) ); ?>" ,
				allowClear: true
			};

			var query = JSON.parse( '<?php echo $query; ?>' );

			options.ajax = {
				url: '<?php echo esc_url_raw( $url ); ?>',
				dataType: 'json',
				data: function( term, page ) {
					query.s = term;
					query.paged = page;
					return { query: query };
				},
				results : function( data, page ) {
					var postsPerPage = query.posts_per_page = ( 'posts_per_page' in query ) ? query.posts_per_page : ( 'showposts' in query ) ? query.showposts : 10;
					var isMore = ( page * postsPerPage ) < data.total;
					return { results: data.posts, more: isMore };
				}
			}

			<?php if ( ! empty( $value ) ) : ?>

				options.initSelection = function( element, callback ) {

					var data = <?php echo sprintf( '{ id: %d, text: "%s" }', $value, html_entity_decode( get_the_title( $value ) ) ); ?>;
					callback( data );

				};

			<?php endif; ?>

			if ( 'undefined' === typeof( window.hm_rp_select_fields ) ) {
				window.hm_rp_select_fields = {};
			}

			window.hm_rp_select_fields.hm_rp_post_<?php echo esc_attr( $id ); ?> = options;

		} );

	</script>

	<?php
}

/**
 * Save the manually selected related posts.
 *
 * @param int $post_id
 * @return void
 */
function save( $post_id ) {
	if (
		empty( $_POST['hm_rp_post'] ) ||
		! isset( $_POST['hm_rp_nonce'] ) ||
		! wp_verify_nonce( $_POST['hm_rp_nonce'], 'hm_rp' )
	) {
		return;
	}

	unset( $_POST['hm_rp_post']['hm-rp-field-x'] );

	delete_post_meta( $post_id, 'hm_rp_post' );

	foreach ( $_POST['hm_rp_post'] as $value ) {
		if ( ! empty( $value ) ) {
			add_post_meta( $post_id, 'hm_rp_post', intval( $value ) );
		}
	}
}
add_action( 'save_post', __NAMESPACE__ . '\\save' );

/**
 * Handle Select2 data requests.
 *
 * @return void
 */
function ajax_post_select() {
	$post_id = filter_input( INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT );
	$nonce   = filter_input( INPUT_GET, 'hm_rp_ajax_post_select_nonce', FILTER_SANITIZE_STRING );

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hm_rp_ajax_post_select' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$args = ! empty( $_GET['query'] ) ? $_GET['query'] : [];
	$args = sanitize_query( $args );

	$args['fields'] = 'ids'; // Only need to retrieve post IDs.

	$query = new WP_Query( $args );

	$json = [
		'total' => $query->found_posts,
		'posts' => [],
	];

	foreach ( $query->posts as $post_id ) {
		array_push( $json['posts'], [
			'id' => $post_id,
			'text' => html_entity_decode( get_the_title( $post_id ) ),
		] );
	}

	echo wp_json_encode( $json );

	exit;
}

add_action( 'wp_ajax_hm_rp_ajax_post_select', __NAMESPACE__ . '\\ajax_post_select' );

/**
 * Sanitize WP Query args
 *
 * Public QVs are allowed through...
 * Private query vars are sanitized
 * Other query vars are also sanitized
 * Anything else is removed from the query
 *
 * @param  array $query args
 * @return  array $query args
 */
function sanitize_query( $query ) {

	// Public Query Vars
	// These can just be added to any url on the front end of the site so they don't need sanitizing.
	$public_qv = [ 'm', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'static', 'pagename', 'page_id', 'error', 'comments_popup', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'taxonomy', 'term', 'cpage', 'post_type' ];

	// Private Query Vars
	// array key is the query var, array values are appropriate santization functions.
	$private_qv = [
		'offset' => 'absint',
		'posts_per_page' => 'intval',
		'posts_per_archive_page' => 'intval',
		'showposts' => 'intval',
		'nopaging' => 'intval',
		'post_type' => __NAMESPACE__ . '\\sanitize_string_array_or_text_field',
		'post_status' => __NAMESPACE__ . '\\sanitize_string_array_or_text_field',
		'category__in' => __NAMESPACE__ . '\\sanitize_int_array',
		'category__not_in' => __NAMESPACE__ . '\\sanitize_int_array',
		'category__and' => __NAMESPACE__ . '\\sanitize_int_array',
		'tag__in' => __NAMESPACE__ . '\\sanitize_int_array',
		'tag__not_in' => __NAMESPACE__ . '\\sanitize_int_array',
		'tag__and' => __NAMESPACE__ . '\\sanitize_int_array',
		'tag_slug__in' => __NAMESPACE__ . '\\sanitize_string_array',
		'tag_slug__and' => __NAMESPACE__ . '\\sanitize_string_array',
		'tag_id' => 'intval',
		'post_mime_type' => __NAMESPACE__ . '\\sanitize_string_array_or_text_field',
		'perm' => 'sanitize_text_field',
		'comments_per_page' => 'intval',
		'post__in' => __NAMESPACE__ . '\\sanitize_int_array',
		'post__not_in' => __NAMESPACE__ . '\\sanitize_int_array',
		'post_parent' => 'intval',
		'post_parent__in' => __NAMESPACE__ . '\\sanitize_int_array',
		'post_parent__not_in' => __NAMESPACE__ . '\\sanitize_int_array',
	];

	$other_qvs = [
		'ignore_sticky_posts' => 'intval',
		'meta_query' => __NAMESPACE__ . '\\sanitize_meta_query', // phpcs:ignore
		'tax_query' => __NAMESPACE__ . '\\sanitize_tax_query', // phpcs:ignore
	];

	foreach ( $query as $arg => &$val ) {
		if ( in_array( $arg, $public_qv, true ) ) {

			continue;

		} elseif ( array_key_exists( $arg, $private_qv ) ) {

			// Call the specified sanitization function for this arg.
			$val = $private_qv[ $arg ]( $val );

		} elseif ( array_key_exists( $arg, $other_qvs ) ) {

			// Call the specified sanitization function for this arg.
			$val = $other_qvs[ $arg ]( $val );

		} else {

			unset( $query[ $arg ] );
			continue;

		}
	}

	return $query;
}

/**
 * Sanitize an array of integers.
 *
 * @param  array $array array of what should be integers.
 * @return array $array array of integers
 */
function sanitize_int_array( array $array ) {
	return array_map( 'intval', $array );
}

/**
 * Sanitize an array of strings.
 *
 * @param  array $array array of strings.
 * @return array $array array of sanitized strings
 */
function sanitize_string_array( array $array ) {
	return array_map( 'sanitize_text_field', $array );
}

/**
 * Sanitize a string or array of strings.
 *
 * @param  string/array $value
 * @return string/array $value
 */
function sanitize_string_array_or_text_field( $value ) {
	if ( is_array( $value ) ) {
		return hm_sanitize_string_array( $value );
	}
	return sanitize_text_field( $value );
}

/**
 * Sanitize meta query.
 *
 * @param array $q
 * @return array
 */
function sanitize_meta_query( $q ) {
	$meta_query = new WP_Meta_Query();
	return $meta_query->parse_query_vars( $q );
}

/**
 * Sanitize tax query.
 *
 * @param array $q
 * @return array
 */
function sanitize_tax_query( $q ) {
	$tax_query = new WP_Query();
	return $tax_query->parse_tax_query( $q );
}
