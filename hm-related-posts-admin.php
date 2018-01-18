<?php

function hm_rp_add_meta_boxes() {

 	add_meta_box(
		'hm_rp_mb',
		'Related Posts',
		'hm_rp_override_metabox',
		'post'
	);

}
add_action( 'add_meta_boxes', 'hm_rp_add_meta_boxes' );


function hm_rp_scripts() {
	wp_enqueue_script( 'select2', HMRP_URL . 'js/select2/select2.js', array( 'jquery' ) );
	wp_enqueue_script( 'hm-rp-scripts', HMRP_URL . 'js/hm-rp.js' );
	wp_enqueue_script( 'field-select', HMRP_URL . 'js/field.select.js', array( 'jquery' ) );

	wp_enqueue_style( 'select2', HMRP_URL . 'js/select2/select2.css' );
	wp_enqueue_style( 'hm-rp-styles', HMRP_URL . 'style.css' );

}
add_action( 'admin_enqueue_scripts', 'hm_rp_scripts' );

function hm_rp_override_metabox( $post ) {

	$data = (array) get_post_meta( $post->ID, 'hm_rp_post' );
	wp_nonce_field( 'hm_rp','hm_rp_nonce' );

	?>
	<table class="form-table hm_rp_metabox">
		<tr>
			<td style="width: 100%" colspan="12">
				<div class="hm-rp-field repeatable HMRP_Post_Select" data-rep-max="5">

					<div class="field-title">
						<label for="hm_rp_post-hm-rp-field-0">Manually select related post</label>
					</div>

					<div class="hm_rp_metabox_description">By default, 5 related posts are dynamically generated. Manually override these here</div>

					<?php

					foreach ( $data as $key => $value ) :
						if ( ! empty( $value ) ) :
							echo '<div class="hm-rp-field-item" data-class="HMRP_Post_Select" style="position: relative">';
							hm_rp_override_metabox_field( $key, intval( $value ) );
							echo '</div>';
						endif;
					endforeach;

					?>

					<div class="hm-rp-field-item hidden" data-class="HMRP_Post_Select" style="position: relative">
						<?php hm_rp_override_metabox_field( 'x' ); // x used to identify hidden placeholder field. ?>
					</div>

					<button class="button hm-rp-repeat-field">Add New</button>

				</div>
			</td>
		</tr>
	</table>
	<?php
}

function hm_rp_override_metabox_field( $id, $value = null ) {

	?>

	<a class="hm-rp-delete-field">&times;</a>

	<input id="hm_rp_post-hm-rp-field-<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"  name="hm_rp_post[hm-rp-field-<?php echo esc_attr( $id ); ?>]"  class="hm_rp_select" data-field-id="hm_rp_post_<?php echo esc_attr( $id ); ?>" style="width: 100%" />

	<?php hm_rp_override_metabox_script( $id, $value, array() ); ?>

	<?php
}

function hm_rp_override_metabox_script( $id, $value = false, $ajax_args = false ) {

	$query = json_encode( $ajax_args ? wp_parse_args( $ajax_args ) : (object) array() );

	$url = add_query_arg( array( 
		'action' => 'hm_rp_ajax_post_select', 
		'hm_rp_ajax_post_select_nonce' => wp_create_nonce( 'hm_rp_ajax_post_select' ),
		'post_id' => get_the_id()
	), admin_url( 'admin-ajax.php' ) );

	?>

	<script type="text/javascript">

		jQuery( document ).ready( function() {

			var options = {
				placeholder: "Type to search" ,
				allowClear: true
			};

			var query = JSON.parse( '<?php echo $query; ?>' );

			options.ajax = {
				url: '<?php echo $url; ?>',
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

					var data = <?php echo sprintf( '{ id: %d, text: "%s" }', $value, get_the_title( $value ) ); ?>;
					callback( data );

				};

			<?php endif; ?>

			if ( 'undefined' === typeof( window.hm_rp_select_fields ) )
				window.hm_rp_select_fields = {};

			window.hm_rp_select_fields.hm_rp_post_<?php echo esc_attr( $id ); ?> = options;

		} );

	</script>

	<?php

}

function hm_rp_save( $post_id ) {

	if (
		empty( $_POST['hm_rp_post'] ) ||
		! isset( $_POST['hm_rp_nonce'] ) ||
		! wp_verify_nonce($_POST['hm_rp_nonce'], 'hm_rp' )
	)
		return;

 	unset( $_POST['hm_rp_post']['hm-rp-field-x'] );

 	delete_post_meta( $post_id, 'hm_rp_post' );

 	foreach ( $_POST['hm_rp_post'] as $value )
 		if ( ! empty( $value ) )
 			add_post_meta( $post_id, 'hm_rp_post', intval( $value ) );

}
add_action( 'save_post', 'hm_rp_save' );

function hm_rp_ajax_post_select() {

	$post_id = ! empty( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : false;
	$nonce = ! empty( $_GET['hm_rp_ajax_post_select_nonce'] ) ? $_GET['hm_rp_ajax_post_select_nonce'] : false;
	$args = ! empty( $_GET['query'] ) ? $_GET['query'] : array();

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'hm_rp_ajax_post_select' ) || ! current_user_can( 'edit_post', $post_id ) )
		return;
	
	$args = hm_rp_sanitize_query( $args );
	
	$args['fields'] = 'ids'; // Only need to retrieve post IDs.

	$query = new WP_Query( $args );
	
	$json = array( 'total' => $query->found_posts, 'posts' => array() );

	foreach ( $query->posts as $post_id )
		array_push( $json['posts'], array( 'id' => $post_id, 'text' => get_the_title( $post_id ) ) );

	echo json_encode( $json );

	exit;

}
add_action( 'wp_ajax_hm_rp_ajax_post_select', 'hm_rp_ajax_post_select' );

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
function hm_rp_sanitize_query( $query ) {

	// Public Query Vars
	// These can just be added to any url on the front end of the site so they don't need sanitizing.
	$public_qv = array( 'm', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'static', 'pagename', 'page_id', 'error', 'comments_popup', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'taxonomy', 'term', 'cpage', 'post_type' );
	
	// Private Query Vars
	// array key is the query var, array values are appropriate santization functions.
	$private_qv = array( 
		'offset' => 'absint',
		'posts_per_page' => 'intval',
		'posts_per_archive_page' => 'intval',
		'showposts' => 'intval',
		'nopaging' => 'intval', 
		'post_type' => 'hm_sanitize_string_array_or_text_field',
		'post_status' => 'hm_sanitize_string_array_or_text_field',
		'category__in' => 'hm_sanitize_int_array',
		'category__not_in' => 'hm_sanitize_int_array',
		'category__and' => 'hm_sanitize_int_array',
		'tag__in' => 'hm_sanitize_int_array',
		'tag__not_in' => 'hm_sanitize_int_array',
		'tag__and' => 'hm_sanitize_int_array',
		'tag_slug__in' => 'hm_sanitize_string_array',
		'tag_slug__and' => 'hm_sanitize_string_array',
		'tag_id' => 'intval', 
		'post_mime_type' => 'hm_sanitize_string_array_or_text_field',
		'perm' => 'sanitize_text_field',
		'comments_per_page' => 'intval',
		'post__in' => 'hm_sanitize_int_array',
		'post__not_in' => 'hm_sanitize_int_array',
		'post_parent' => 'intval',
		'post_parent__in' => 'hm_sanitize_int_array',
		'post_parent__not_in' => 'hm_sanitize_int_array'
	);

	$other_qvs = array(
		'ignore_sticky_posts' => 'intval',
		'meta_query' => 'hm_sanitize_meta_query',
		'tax_query' => 'hm_sanitize_tax_query'
	);

	foreach ( $query as $arg => &$val ) {
			
		if ( in_array( $arg, $public_qv ) ) {
			
			continue;
		
		} elseif ( array_key_exists( $arg, $private_qv ) ) {
			
			// Call the specified sanitization function for this arg.
			$val = $private_qv[$arg]($val);
		
		} elseif ( array_key_exists( $arg, $other_qvs ) ) {
			
			// Call the specified sanitization function for this arg.
			$val = $other_qvs[$arg]($val);
		
		} else {
			
			unset( $query[$arg] );
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
function hm_sanitize_int_array( array $array ) {
	return array_map( 'intval', $array );
}

/**
 * Sanitize an array of strings.
 * 
 * @param  array $array array of strings.
 * @return array $array array of sanitized strings
 */
function hm_sanitize_string_array( array $array ) {
	return array_map( 'sanitize_text_field', $array );
}

/**
 * Sanitize a string or array of strings.
 * 
 * @param  string/array $value
 * @return string/array $value
 */
function hm_sanitize_string_array_or_text_field( $value ) {
	if ( is_array( $value ) )
		return hm_sanitize_string_array( $value );
	return sanitize_text_field( $value );
}

// Sanitize meta query
function hm_sanitize_meta_query( $q ) {
	$meta_query = new WP_Meta_Query();
	return $meta_query->parse_query_vars( $q );
}

// Sanitize tax query
function hm_sanitize_tax_query( $q ) {
	$tax_query = new WP_Query();
	return $tax_query->parse_tax_query( $q );
}
