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

	wp_enqueue_script( 'select2', HMRP_URL . '/js/select/select2.js', array( 'jquery' ) );
	wp_enqueue_script( 'hm-rp-scripts', HMRP_URL . '/js/hm-rp.js' );
	wp_enqueue_script( 'field-select', HMRP_URL . '/js/field.select.js', array( 'jquery' ) );

	wp_enqueue_style( 'select2', HMRP_URL . '/js/select/select2.css' );
	wp_enqueue_style( 'hm-rp-styles', HMRP_URL . '/style.css' );

}
add_action( 'admin_enqueue_scripts', 'hm_rp_scripts' );

function hm_rp_override_metabox( $post ) {

	$data = (array) get_post_meta( $post->ID, 'hm_rp_post' );
	wp_nonce_field( 'hm_rp','hm_rp_nonce' );

	?>
	<table class="form-table hm_rp_metabox">
		<tr>
			<td style="width: 100%" colspan="12">
				<div class="hm-rp-field repeatable HMRP_Post_Select" data-rep-min="2" data-rep-max="5">

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
						<?php hm_rp_override_metabox_field( 'x' ); ?>
					</div>

					<button class="button hm-rp-repeat-field">Add New</button>

				</div>
			</td>
		</tr>
	</table>
	<?php
}

function hm_rp_override_metabox_field( $id, $value = null ) {

	$ajax_args = array( 'posts_per_page' => 100 );

	?>

	<a class="hm-rp-delete-field">&times;</a>

	<input id="hm_rp_post-hm-rp-field-<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"  name="hm_rp_post[hm-rp-field-<?php echo esc_attr( $id ); ?>]"  class="hm_rp_select" data-field-id="hm_rp_post_<?php echo esc_attr( $id ); ?>" style="width: 100%" />

	<?php hm_rp_override_metabox_script( $id, $value, $ajax_args ); ?>

	<?php
}

function hm_rp_override_metabox_script( $id, $value = false, $ajax_args = false ) {

	?>

	<script type="text/javascript">

		jQuery( document ).ready( function() {

			var options = {
				placeholder: "Type to search" ,
				allowClear: true
			};

			var query = JSON.parse( '<?php echo json_encode( $ajax_args ? wp_parse_args( $ajax_args ) : (object) array() ); ?>' );

			options.ajax = {
				url: '<?php echo add_query_arg( 'action', 'hm_rp_ajax_post_select', admin_url( 'admin-ajax.php' ) ); ?>',
				dataType: 'json',
				data: function( term, page ) {
					query.s = term;
					query.paged = page;
					return query;
				},
				results : function( data, page ) {
					return { results: data }
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

// TODO this should be in inside the class
function hm_rp_ajax_post_select() {

	$query = new WP_Query( $_GET );

	$posts = $query->posts;

	$json = array();

	foreach ( $posts as $post )
		$json[] = array( 'id' => $post->ID, 'text' => get_the_title( $post->ID ) );

	echo json_encode( $json );

	exit;

}
add_action( 'wp_ajax_hm_rp_ajax_post_select', 'hm_rp_ajax_post_select' );