<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_markdown_cell( $value ) {
	$text = htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	$text = str_replace( array( '\\', '|', '`', '*', '_', '[', ']', '~' ), array( '&#92;', '&#124;', '&#96;', '&#42;', '&#95;', '&#91;', '&#93;', '&#126;' ), $text );
	return preg_replace( '/[\r\n\t]+/', ' ', $text );
}

function wp_rebuild_mapper_markdown_row( $cells ) {
	return '| ' . implode( ' | ', array_map( 'wp_rebuild_mapper_markdown_cell', $cells ) ) . ' |';
}

function wp_rebuild_mapper_markdown_location( $location ) {
	$alternatives = array();
	foreach ( $location as $rules ) {
		$conditions = array();
		foreach ( $rules as $rule ) {
			$conditions[] = $rule['param'] . ' ' . $rule['operator'] . ' ' . ( $rule['value'] ?? '(unavailable)' );
		}
		$alternatives[] = '(' . ( $conditions ? implode( ' AND ', $conditions ) : 'empty rule set' ) . ')';
	}
	return $alternatives ? implode( ' OR ', $alternatives ) : 'No location rules exposed';
}

function wp_rebuild_mapper_markdown_group_references( $groups, $param, $name ) {
	$references = array();
	foreach ( $groups as $group ) {
		foreach ( $group['location'] as $rules ) {
			foreach ( $rules as $rule ) {
				if ( $param === $rule['param'] && '==' === $rule['operator'] && $name === $rule['value'] ) {
					$references[ $group['key'] ] = $group['title'] . ' [' . $group['key'] . ']' . ( $group['active'] ? '' : ' (inactive)' );
				}
			}
		}
	}
	return $references ? implode( '; ', $references ) : 'No explicit equality rule; see all field groups below';
}

function wp_rebuild_mapper_markdown_schema( $schema ) {
	$groups = $schema['acf']['field_groups'] ?? array();
	$lines  = array(
		'',
		'## Registered Post Types',
		'',
		'All registered types are listed, including WordPress and plugin internals. ACF group references below identify explicit equality rules only, not evaluated placement. Additional conditions, exclusions, templates, specific pages, and custom rules remain in the field group catalogue.',
		'',
		'| Label | Post type | Public | Hierarchical | Attached taxonomies | ACF group references |',
		'| --- | --- | --- | --- | --- | --- |',
	);
	foreach ( $schema['post_types'] as $post_type ) {
		$lines[] = wp_rebuild_mapper_markdown_row( array(
			$post_type['label'], $post_type['name'], $post_type['public'] ? 'Yes' : 'No',
			$post_type['hierarchical'] ? 'Yes' : 'No', implode( ', ', $post_type['taxonomies'] ) ?: 'None',
			null === $schema['acf'] ? 'ACF unavailable' : wp_rebuild_mapper_markdown_group_references( $groups, 'post_type', $post_type['name'] ),
		) );
	}
	$lines = array_merge( $lines, array(
		'', '## Registered Taxonomies', '',
		'Categories and tags are taxonomies too. Attached post types are registered associations, not evidence of saved term assignments.', '',
		'| Label | Taxonomy | Attached post types | Hierarchical | Terms | ACF group references |',
		'| --- | --- | --- | --- | --- | --- |',
	) );
	foreach ( $schema['taxonomies'] as $taxonomy ) {
		$lines[] = wp_rebuild_mapper_markdown_row( array(
			$taxonomy['label'], $taxonomy['name'], implode( ', ', $taxonomy['post_types'] ) ?: 'None',
			$taxonomy['hierarchical'] ? 'Yes' : 'No', wp_rebuild_mapper_markdown_count( $taxonomy['terms'] ),
			null === $schema['acf'] ? 'ACF unavailable' : wp_rebuild_mapper_markdown_group_references( $groups, 'taxonomy', $taxonomy['name'] ),
		) );
	}
	foreach ( $schema['taxonomies'] as $taxonomy ) {
		$lines[] = '';
		$lines[] = '### Terms: ' . wp_rebuild_mapper_markdown_cell( $taxonomy['label'] . ' [' . $taxonomy['name'] . ']' );
		$lines[] = '';
		if ( ! $taxonomy['terms'] ) {
			$lines[] = null === $taxonomy['terms'] ? 'Term lookup unavailable.' : 'No registered terms.';
			continue;
		}
		$names = array();
		foreach ( $taxonomy['terms'] as $term ) {
			$names[ $term['id'] ] = $term['name'];
		}
		$lines[] = '| Term | ID | Slug | Parent |';
		$lines[] = '| --- | --- | --- | --- |';
		foreach ( $taxonomy['terms'] as $term ) {
			$parent = $term['parent_id'] ? ( $names[ $term['parent_id'] ] ?? 'Parent not in export' ) . ' [#' . $term['parent_id'] . ']' : 'Root';
			$lines[] = wp_rebuild_mapper_markdown_row( array( $term['name'], $term['id'], $term['slug'], $parent ) );
		}
	}
	$lines = array_merge( $lines, array( '', '## ACF Field Group Catalogue', '' ) );
	if ( null === $schema['acf'] ) {
		$lines[] = 'ACF discovery unavailable. This does not establish that no custom fields exist.';
		return $lines;
	}
	$lines[] = 'Placement rules are preserved as AND conditions inside OR alternatives; they are not evaluated. Relationship targets describe configuration, not saved links. Nested field paths retain their group, repeater, and layout containers. Clone expansion and settings not exposed by discovery are not inferred.';
	if ( ! $groups ) {
		$lines[] = 'No ACF field groups exposed.';
	}
	foreach ( $groups as $group ) {
		$lines = array_merge( $lines, array(
			'', '### ' . wp_rebuild_mapper_markdown_cell( $group['title'] ), '',
			'- Group key: ' . wp_rebuild_mapper_markdown_cell( $group['key'] ),
			'- Active: ' . ( $group['active'] ? 'Yes' : 'No' ),
			'- Placement: ' . wp_rebuild_mapper_markdown_cell( wp_rebuild_mapper_markdown_location( $group['location'] ) ), '',
		) );
		if ( ! $group['fields'] ) {
			$lines[] = 'No fields exposed.';
			continue;
		}
		$lines[] = '| Field path | Label | Type | Key | Configured relationship targets |';
		$lines[] = '| --- | --- | --- | --- | --- |';
		$lines = array_merge( $lines, wp_rebuild_mapper_markdown_fields( $group['fields'] ) );
	}
	$lines = array_merge( $lines, array( '', '### ACF Options Pages', '' ) );
	$options_pages = $schema['acf']['options_pages'];
	if ( ! $options_pages ) {
		$lines[] = null === $options_pages ? 'Options page discovery unavailable.' : 'No options pages exposed.';
	} else {
		$lines[] = '| Page | Menu slug | Parent slug |';
		$lines[] = '| --- | --- | --- |';
		foreach ( $options_pages as $page ) {
			$lines[] = wp_rebuild_mapper_markdown_row( array( $page['page_title'], $page['menu_slug'], $page['parent_slug'] ?: 'None' ) );
		}
	}
	return $lines;
}

function wp_rebuild_mapper_markdown_fields( $fields, $prefix = '' ) {
	$lines = array();
	foreach ( $fields as $field ) {
		$path    = $prefix . ( '' !== $field['name'] ? $field['name'] : '[' . $field['key'] . ']' );
		$targets = 'Not a mapped relationship field';
		if ( isset( $field['target_filters'] ) ) {
			$filters = $field['target_filters'];
			if ( 'taxonomy' === $field['type'] ) {
				$targets = 'Taxonomy: ' . ( $filters['taxonomy'] ?? 'not exposed' );
			} else {
				$targets = 'Post types: ' . ( implode( ', ', $filters['post_types'] ) ?: 'no explicit restriction' );
				$targets .= '; statuses: ' . ( implode( ', ', $filters['post_statuses'] ) ?: 'no explicit restriction' );
				$targets .= '; term filters: ' . ( implode( ', ', $filters['terms'] ) ?: 'none configured' );
			}
		}
		$lines[] = wp_rebuild_mapper_markdown_row( array( $path, $field['label'], $field['type'], $field['key'], $targets ) );
		$lines   = array_merge( $lines, wp_rebuild_mapper_markdown_fields( $field['sub_fields'] ?? array(), $path . ' / ' ) );
		foreach ( $field['layouts'] ?? array() as $layout ) {
			$layout_path = $path . ' / layout:' . $layout['name'];
			$lines[]     = wp_rebuild_mapper_markdown_row( array( $layout_path, $layout['label'], 'layout', $layout['key'], 'Container' ) );
			$lines       = array_merge( $lines, wp_rebuild_mapper_markdown_fields( $layout['sub_fields'], $layout_path . ' / ' ) );
		}
	}
	return $lines;
}

function wp_rebuild_mapper_markdown_acf_usage( $usage ) {
	$lines = array( '', '### Observed ACF Relationship Usage', '' );
	if ( ! $usage ) {
		$lines[] = null === $usage ? 'ACF relationship usage unavailable.' : 'No supported relationship fields found for the usage scan.';
		return $lines;
	}
	$lines = array_merge( $lines, array(
		'Counts are source posts, not individual links. Saved values may be empty; valid, missing, and mismatched outcomes can overlap. The scan covers top-level post fields and direct subfields of top-level repeaters only, not deeper nesting, flexible layouts, clones, options, or term metadata. Runtime filters are not evaluated.', '',
		'| Group key | Field path | Field key | Type | Source post type | With saved value | With valid target | With missing target | With mismatched target |',
		'| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
	) );
	foreach ( $usage as $field ) {
		$path = isset( $field['repeater_name'] ) ? $field['repeater_name'] . ' / ' . $field['field_name'] : $field['field_name'];
		$base = array( $field['group_key'], $path, $field['field_key'], $field['field_type'] );
		if ( ! $field['by_post_type'] ) {
			$lines[] = wp_rebuild_mapper_markdown_row( array_merge( $base, array( null === $field['by_post_type'] ? 'Lookup unavailable' : 'No observed saved values', '-', '-', '-', '-' ) ) );
			continue;
		}
		foreach ( $field['by_post_type'] as $counts ) {
			$lines[] = wp_rebuild_mapper_markdown_row( array_merge( $base, array(
				$counts['post_type'], $counts['posts_with_saved_value'], $counts['posts_with_valid'],
				$counts['posts_with_missing'], $counts['posts_with_mismatched'],
			) ) );
		}
	}
	return $lines;
}