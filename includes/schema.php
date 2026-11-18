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
		'post_types'         => $post_type_schema,
		'taxonomies'         => $taxonomy_schema,
		'observed_post_meta' => wp_rebuild_mapper_observed_post_meta( array_keys( $post_types ) ),
	);
}

function wp_rebuild_mapper_observed_post_meta( $post_types ) {
	global $wpdb;

	if ( ! $post_types ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$previous_suppression = $wpdb->suppress_errors();
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT posts.post_type, meta.meta_key, COUNT(DISTINCT meta.post_id) AS post_count
			 FROM {$wpdb->posts} AS posts
			 INNER JOIN {$wpdb->postmeta} AS meta ON meta.post_id = posts.ID
			 WHERE posts.post_type IN ($placeholders) AND meta.meta_key IS NOT NULL
			 GROUP BY posts.post_type, meta.meta_key, CAST(meta.meta_key AS BINARY)
			 ORDER BY posts.post_type, CAST(meta.meta_key AS BINARY)",
			$post_types
		),
		ARRAY_A
	);
	$query_failed = '' !== $wpdb->last_error;
	$wpdb->suppress_errors( $previous_suppression );

	if ( $query_failed || ! is_array( $rows ) ) {
		return null;
	}

	foreach ( $rows as &$row ) {
		$row['post_count'] = (int) $row['post_count'];
	}
	unset( $row );

	return $rows;
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