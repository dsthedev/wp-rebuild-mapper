<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_discover_schema() {
	$post_types = get_post_types( array(), 'objects' );
	$taxonomies = get_taxonomies( array(), 'objects' );
	ksort( $post_types );
	ksort( $taxonomies );
	$post_type_schema = array();
	$taxonomy_schema  = array();

	foreach ( $post_types as $name => $post_type ) {
		$supports           = array_keys( get_all_post_type_supports( $name ) );
		$attached          = get_object_taxonomies( $name );
		sort( $supports, SORT_STRING );
		sort( $attached, SORT_STRING );

		$post_type_schema[] = array(
			'name'               => $name,
			'label'              => $post_type->label,
			'public'             => (bool) $post_type->public,
			'publicly_queryable' => (bool) $post_type->publicly_queryable,
			'hierarchical'       => (bool) $post_type->hierarchical,
			'has_archive'        => $post_type->has_archive,
			'supports'           => $supports,
			'rewrite'            => wp_rebuild_mapper_schema_rewrite( $post_type->rewrite, array( 'slug', 'with_front', 'feeds', 'pages', 'ep_mask' ) ),
			'taxonomies'         => $attached,
		);
	}

	foreach ( $taxonomies as $name => $taxonomy ) {
		$object_types = array_values( $taxonomy->object_type );
		sort( $object_types, SORT_STRING );

		$taxonomy_schema[] = array(
			'name'               => $name,
			'label'              => $taxonomy->label,
			'post_types'         => $object_types,
			'public'             => (bool) $taxonomy->public,
			'publicly_queryable' => (bool) $taxonomy->publicly_queryable,
			'hierarchical'       => (bool) $taxonomy->hierarchical,
			'rewrite'            => wp_rebuild_mapper_schema_rewrite( $taxonomy->rewrite, array( 'slug', 'with_front', 'hierarchical', 'ep_mask' ) ),
			'terms'              => wp_rebuild_mapper_schema_terms( $name ),
		);
	}

	return array(
		'post_types' => $post_type_schema,
		'taxonomies' => $taxonomy_schema,
	);
}

function wp_rebuild_mapper_schema_rewrite( $rewrite, $keys ) {
	if ( ! is_array( $rewrite ) ) {
		return false;
	}

	$settings = array_intersect_key( $rewrite, array_flip( $keys ) );
	ksort( $settings );
	return (object) $settings;
}

function wp_rebuild_mapper_schema_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'               => $taxonomy,
			'hide_empty'             => false,
			'orderby'                => 'term_id',
			'order'                  => 'ASC',
			'update_term_meta_cache' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return null;
	}

	$records = array();
	foreach ( $terms as $term ) {
		$records[] = array(
			'id'        => (int) $term->term_id,
			'name'      => $term->name,
			'slug'      => $term->slug,
			'parent_id' => (int) $term->parent,
		);
	}
	return $records;
}