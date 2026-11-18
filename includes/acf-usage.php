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
			if ( '' === $field['name'] ) {
				continue;
			}
			if ( isset( $field['target_filters'] ) ) {
				$usage[] = wp_rebuild_mapper_acf_usage_record( $group['key'], $field, $post_types );
			} elseif ( 'repeater' === $field['type'] ) {
				foreach ( $field['sub_fields'] as $child ) {
					if ( '' !== $child['name'] && isset( $child['target_filters'] ) ) {
						$usage[] = wp_rebuild_mapper_acf_usage_record( $group['key'], $child, $post_types, $field );
					}
				}
			}
		}
	}
	return $usage;
}

function wp_rebuild_mapper_acf_usage_record( $group_key, $field, $post_types, $repeater = null ) {
	$record = array(
		'group_key'  => $group_key,
		'field_key'  => $field['key'],
		'field_name' => $field['name'],
		'field_type' => $field['type'],
	);
	if ( null !== $repeater ) {
		$record['repeater_key']  = $repeater['key'];
		$record['repeater_name'] = $repeater['name'];
	}
	$record['by_post_type'] = wp_rebuild_mapper_acf_count_post_field( $field, $post_types, $repeater );
	return $record;
}

function wp_rebuild_mapper_acf_count_post_field( $field, $post_types, $repeater = null ) {
	global $wpdb;

	$lookup     = $repeater ?? $field;
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
			array( $lookup['name'], $lookup['name'], $last_id, '_' . $lookup['name'], '_' . $lookup['name'], $lookup['key'], $batch_size )
		);
		if ( null === $rows ) {
			return null;
		}

		$repeater_values = array();
		if ( null !== $repeater ) {
			$repeater_values = wp_rebuild_mapper_acf_repeater_values( $rows, $repeater['name'], $field );
			if ( null === $repeater_values ) {
				return null;
			}
		}

		foreach ( $rows as $row ) {
			$last_id   = (int) $row['ID'];
			$post_type = $row['post_type'];
			if ( ! isset( $post_types[ $post_type ] ) ) {
				continue;
			}
			$values = null === $repeater ? array( $row['meta_value'] ) : ( $repeater_values[ $last_id ] ?? array() );
			if ( ! $values ) {
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
			foreach ( $values as $value ) {
				foreach ( wp_rebuild_mapper_acf_saved_ids( $value ) as $target_id ) {
					$outcomes[ wp_rebuild_mapper_acf_target_outcome( $target_id, $field ) ] = true;
				}
			}
			foreach ( $outcomes as $outcome => $present ) {
				++$counts[ $post_type ][ 'posts_with_' . $outcome ];
			}
		}
	} while ( count( $rows ) === $batch_size );

	ksort( $counts, SORT_STRING );
	return array_values( $counts );
}

function wp_rebuild_mapper_acf_repeater_values( $rows, $repeater_name, $field ) {
	global $wpdb;

	$row_counts = array();
	foreach ( $rows as $row ) {
		$stored = $row['meta_value'];
		$count  = (int) $stored;
		if ( $count > 0 && (string) $count === $stored ) {
			$row_counts[ (int) $row['ID'] ] = $count;
		}
	}
	if ( ! $row_counts ) {
		return array();
	}

	$prefix       = $repeater_name . '_';
	$suffix       = '_' . $field['name'];
	$like         = $wpdb->esc_like( $prefix ) . '%' . $wpdb->esc_like( $suffix );
	$pattern      = '/\A' . preg_quote( $prefix, '/' ) . '(0|[1-9][0-9]*)' . preg_quote( $suffix, '/' ) . '\z/';
	$placeholders = implode( ', ', array_fill( 0, count( $row_counts ), '%d' ) );
	$children     = wp_rebuild_mapper_relationship_rows(
		"SELECT stored.post_id, stored.meta_key, stored.meta_value
		 FROM {$wpdb->postmeta} AS stored
		 WHERE stored.post_id IN ($placeholders) AND stored.meta_key LIKE %s AND EXISTS (
		     SELECT 1 FROM {$wpdb->postmeta} AS reference
		     WHERE reference.post_id = stored.post_id
		     AND reference.meta_key = CONCAT('_', stored.meta_key)
		     AND BINARY reference.meta_key = BINARY CONCAT('_', stored.meta_key)
		     AND BINARY reference.meta_value = %s
		 )
		 ORDER BY stored.meta_id",
		array_merge( array_keys( $row_counts ), array( $like, $field['key'] ) )
	);
	if ( null === $children ) {
		return null;
	}

	$values = array();
	foreach ( $children as $child ) {
		$post_id = (int) $child['post_id'];
		if ( ! preg_match( $pattern, $child['meta_key'], $matches ) || (int) $matches[1] >= $row_counts[ $post_id ] ) {
			continue;
		}
		$row_index = (int) $matches[1];
		if ( ! array_key_exists( $row_index, $values[ $post_id ] ?? array() ) ) {
			$values[ $post_id ][ $row_index ] = $child['meta_value'];
		}
	}
	return $values;
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