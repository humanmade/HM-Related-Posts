<?php

add_filter( 'cmb_meta_boxes', function( array $meta_boxes ) {

	$fields = array(
		array(
			'id'          => 'hm_rp_post',
			'name'        => 'Manually select related post',
			'desc'        => 'By default, 5 related posts are dynamically generated. Manually override these here',
			'type'        => 'post_select',
			'use_ajax'    => true,
			'query'       => array(
				'cat' => 8
			),
			'repeatable'  => true,
			'repeatable_max'  => 5,
		),
	);

	$meta_boxes[] = array(
		'title'    => 'Related Posts',
		'pages'    => 'post',
		'context'  => 'normal',
		'priority' => 'high',
		'fields'   => apply_filters( 'hm_rp_fields', $fields ),
	);

	return $meta_boxes;

} );