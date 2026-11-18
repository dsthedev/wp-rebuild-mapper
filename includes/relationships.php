<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/acf-usage.php';

function wp_rebuild_mapper_discover_relationships( $pages ) {
	$page_parents = array();
	foreach ( $pages as $page ) {
		if ( $page['parent_id'] > 0 ) {
			$page_parents[] = array(
				'post_id'   => $page['id'],
				'parent_id' => $page['parent_id'],
			);
		}
	}

	return array(
		'page_parents'              => $page_parents,
		'other_post_parents'        => wp_rebuild_mapper_other_post_parents(),
		'term_assignment_counts'   => wp_rebuild_mapper_term_assignment_counts(),
		'acf_post_field_usage'      => wp_rebuild_mapper_acf_post_field_usage(),
	);
}

function wp_rebuild_mapper_other_post_parents() {
	global $wpdb;

	$post_types = array_values( array_diff( get_post_types( array( 'hierarchical' => true ) ), array( 'page' ) ) );
	if ( ! $post_types ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
	$rows = wp_rebuild_mapper_relationship_rows(
		"SELECT post_type, ID AS post_id, post_parent AS parent_id
		 FROM {$wpdb->posts}
		 WHERE post_parent > 0 AND post_type IN ($placeholders)
		 ORDER BY post_type, ID",
		$post_types
	);
	if ( null === $rows ) {
		return null;
	}

	foreach ( $rows as &$row ) {
		$row['post_id']   = (int) $row['post_id'];
		$row['parent_id'] = (int) $row['parent_id'];
	}
	unset( $row );
	return $rows;
}

function wp_rebuild_mapper_term_assignment_counts() {
	global $wpdb;

	$post_types = get_post_types();
	$taxonomy_post_types = array();
	foreach ( get_taxonomies( array(), 'objects' ) as $name => $taxonomy ) {
		$attached_types = array_intersect( $taxonomy->object_type, $post_types );
		if ( $attached_types ) {
			$taxonomy_post_types[ $name ] = $attached_types;
		}
	}
	if ( ! $taxonomy_post_types ) {
		return array();
	}

	$placeholders = implode( ', ', array_fill( 0, count( $taxonomy_post_types ), '%s' ) );
	$rows = wp_rebuild_mapper_relationship_rows(
		"SELECT term_taxonomy.taxonomy, term_taxonomy.term_id, posts.post_type,
		        COUNT(DISTINCT posts.ID) AS post_count
		 FROM {$wpdb->term_relationships} AS relationships
		 INNER JOIN {$wpdb->term_taxonomy} AS term_taxonomy ON term_taxonomy.term_taxonomy_id = relationships.term_taxonomy_id
		 INNER JOIN {$wpdb->terms} AS terms ON terms.term_id = term_taxonomy.term_id
		 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = relationships.object_id
		 WHERE term_taxonomy.taxonomy IN ($placeholders)
		 GROUP BY term_taxonomy.taxonomy, term_taxonomy.term_id, posts.post_type
		 ORDER BY term_taxonomy.taxonomy, term_taxonomy.term_id, posts.post_type",
		array_keys( $taxonomy_post_types )
	);
	if ( null === $rows ) {
		return null;
	}

	$counts = array();
	foreach ( $rows as $row ) {
		if ( ! in_array( $row['post_type'], $taxonomy_post_types[ $row['taxonomy'] ] ?? array(), true ) ) {
			continue;
		}
		$row['term_id']    = (int) $row['term_id'];
		$row['post_count'] = (int) $row['post_count'];
		$counts[] = $row;
	}
	return $counts;
}

function wp_rebuild_mapper_relationship_rows( $sql, $args ) {
	global $wpdb;

	$previous_suppression = $wpdb->suppress_errors();
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$query_failed = '' !== $wpdb->last_error;
	$wpdb->suppress_errors( $previous_suppression );

	return $query_failed || ! is_array( $rows ) ? null : $rows;
}