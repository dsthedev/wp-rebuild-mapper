<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_acf_post_field_usage() {
	$acf = wp_rebuild_mapper_discover_acf();
	if ( null === $acf ) {
		return null;
	}

	$post_types = array_diff( get_post_types(), array( 'revision' ) );
	$usage      = array();
	foreach ( $acf['field_groups'] as $group ) {
		foreach ( $group['fields'] as $field ) {
			if ( '' === $field['name'] || ! isset( $field['target_filters'] ) ) {
				continue;
			}
			$counts = wp_rebuild_mapper_acf_count_post_field( $field, $post_types );
			$usage[] = array(
				'group_key'      => $group['key'],
				'field_key'      => $field['key'],
				'field_name'     => $field['name'],
				'field_type'     => $field['type'],
				'by_post_type'   => $counts,
			);
		}
	}
	return $usage;
}

function wp_rebuild_mapper_acf_count_post_field( $field, $post_types ) {
	global $wpdb;

	$counts     = array();
	$last_id    = 0;
	$batch_size = 200;
	do {
		$rows = wp_rebuild_mapper_relationship_rows(
			"SELECT posts.ID, posts.post_type, stored.meta_value
			 FROM {$wpdb->posts} AS posts
			 INNER JOIN {$wpdb->postmeta} AS stored ON stored.meta_id = (
			     SELECT candidate.meta_id FROM {$wpdb->postmeta} AS candidate
			     WHERE candidate.post_id = posts.ID AND candidate.meta_key = %s AND BINARY candidate.meta_key = %s
			     ORDER BY candidate.meta_id LIMIT 1
			 )
			 WHERE posts.ID > %d AND EXISTS (
			     SELECT 1 FROM {$wpdb->postmeta} AS reference
			     WHERE reference.post_id = posts.ID AND reference.meta_key = %s AND BINARY reference.meta_key = %s
			     AND BINARY reference.meta_value = %s
			 )
			 ORDER BY posts.ID LIMIT %d",
			array( $field['name'], $field['name'], $last_id, '_' . $field['name'], '_' . $field['name'], $field['key'], $batch_size )
		);
		if ( null === $rows ) {
			return null;
		}

		foreach ( $rows as $row ) {
			$last_id   = (int) $row['ID'];
			$post_type = $row['post_type'];
			if ( ! isset( $post_types[ $post_type ] ) ) {
				continue;
			}

			if ( ! isset( $counts[ $post_type ] ) ) {
				$counts[ $post_type ] = array(
					'post_type'              => $post_type,
					'posts_with_saved_value' => 0,
					'posts_with_valid'       => 0,
					'posts_with_missing'     => 0,
					'posts_with_mismatched'  => 0,
				);
			}
			++$counts[ $post_type ]['posts_with_saved_value'];

			$outcomes = array();
			foreach ( wp_rebuild_mapper_acf_saved_ids( $row['meta_value'] ) as $target_id ) {
				$outcomes[ wp_rebuild_mapper_acf_target_outcome( $target_id, $field ) ] = true;
			}
			foreach ( $outcomes as $outcome => $present ) {
				++$counts[ $post_type ][ 'posts_with_' . $outcome ];
			}
		}
	} while ( count( $rows ) === $batch_size );

	ksort( $counts, SORT_STRING );
	return array_values( $counts );
}

function wp_rebuild_mapper_acf_saved_ids( $stored ) {
	$values = array( $stored );
	if ( is_string( $stored ) && 0 === strpos( $stored, 'a:' ) && is_serialized( $stored ) ) {
		$values = @unserialize( $stored, array( 'allowed_classes' => false, 'max_depth' => 2 ) );
		if ( ! is_array( $values ) ) {
			return array();
		}
	}

	$ids = array();
	foreach ( $values as $value ) {
		if ( ! is_int( $value ) && ( ! is_string( $value ) || ! ctype_digit( $value ) ) ) {
			continue;
		}
		$target_id = (int) $value;
		if ( $target_id > 0 && (string) $target_id === ltrim( (string) $value, '0' ) ) {
			$ids[ $target_id ] = $target_id;
		}
	}
	return array_values( $ids );
}

function wp_rebuild_mapper_acf_target_outcome( $target_id, $field ) {
	$filters = $field['target_filters'];
	if ( 'taxonomy' === $field['type'] ) {
		$taxonomy = $filters['taxonomy'];
		if ( $taxonomy && get_term( $target_id, $taxonomy ) instanceof WP_Term ) {
			return 'valid';
		}
		$term = get_term( $target_id );
		return $term instanceof WP_Term || is_wp_error( $term ) ? 'mismatched' : 'missing';
	}

	$post = get_post( $target_id );
	if ( ! $post ) {
		return 'missing';
	}
	if ( ! post_type_exists( $post->post_type ) ||
		 ( $filters['post_types'] && ! in_array( $post->post_type, $filters['post_types'], true ) ) ||
		 ( $filters['post_statuses'] && ! in_array( $post->post_status, $filters['post_statuses'], true ) ) ) {
		return 'mismatched';
	}
	return 'valid';
}