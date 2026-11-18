<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_admin_section( $title ) {
	echo '<details class="mapper-section"><summary>' . esc_html( $title ) . '</summary><div class="mapper-section-body">';
}

function wp_rebuild_mapper_admin_end_section() {
	echo '</div></details>';
}

function wp_rebuild_mapper_admin_note( $text ) {
	echo '<p>' . esc_html( $text ) . '</p>';
}

function wp_rebuild_mapper_admin_table( $headers, $rows ) {
	if ( null === $rows ) {
		wp_rebuild_mapper_admin_note( __( 'Lookup unavailable.', 'wp-rebuild-mapper' ) );
		return;
	}
	if ( ! $rows ) {
		wp_rebuild_mapper_admin_note( __( 'No records found.', 'wp-rebuild-mapper' ) );
		return;
	}
	echo '<div class="mapper-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr( implode( ', ', $headers ) ) . '"><table class="widefat striped"><thead><tr>';
	foreach ( $headers as $header ) {
		echo '<th scope="col">' . esc_html( $header ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		echo '<tr>';
		foreach ( $row as $cell ) {
			echo '<td>' . esc_html( (string) $cell ) . '</td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

function wp_rebuild_mapper_admin_report( $report ) {
	echo '<div class="mapper-report">';
	wp_rebuild_mapper_admin_section( __( 'Content Summary', 'wp-rebuild-mapper' ) );
	$content = $report['content'];
	wp_rebuild_mapper_admin_table( array( 'Setting', 'Value' ), array(
		array( 'Registered post types', count( $content['post_types'] ) ),
		array( 'Pages in sitemap', count( $content['pages'] ) ),
		array( 'Front page ID', $content['front_page_id'] ?: 'Not set' ),
		array( 'Posts page ID', $content['posts_page_id'] ?: 'Not set' ),
	) );
	$rows = array();
	foreach ( $content['post_types'] as $post_type ) {
		$counts = array();
		foreach ( (array) $post_type['counts_by_status'] as $status_name => $count ) {
			$counts[] = $status_name . ': ' . $count;
		}
		$rows[] = array( $post_type['label'], $post_type['name'], implode( '; ', $counts ) );
	}
	wp_rebuild_mapper_admin_table( array( 'Label', 'Post type', 'Counts by status' ), $rows );
	wp_rebuild_mapper_admin_end_section();
	wp_rebuild_mapper_admin_section( __( 'Page Hierarchy', 'wp-rebuild-mapper' ) );
	wp_rebuild_mapper_admin_pages( $content['pages'] );
	wp_rebuild_mapper_admin_end_section();
	if ( isset( $report['schema'] ) ) {
		wp_rebuild_mapper_admin_schema( $report['schema'] );
	}
	if ( isset( $report['relationships'] ) ) {
		wp_rebuild_mapper_admin_relationships( $report['relationships'] );
	}
	echo '</div>';
}

function wp_rebuild_mapper_admin_pages( $pages ) {
	$records  = array();
	$children = array();
	foreach ( $pages as $page ) {
		$records[ $page['id'] ] = $page;
	}
	foreach ( $pages as $page ) {
		$parent = isset( $records[ $page['parent_id'] ] ) ? $page['parent_id'] : 0;
		$children[ $parent ][] = $page['id'];
	}
	$rows    = array();
	$visited = array();
	$stack   = array();
	foreach ( array_reverse( $children[0] ?? array() ) as $page_id ) {
		$stack[] = array( $page_id, 0 );
	}
	while ( count( $visited ) < count( $records ) ) {
		if ( ! $stack ) {
			foreach ( $records as $page_id => $page ) {
				if ( ! isset( $visited[ $page_id ] ) ) {
					$stack[] = array( $page_id, 0 );
					break;
				}
			}
		}
		list( $page_id, $depth ) = array_pop( $stack );
		if ( isset( $visited[ $page_id ] ) ) {
			continue;
		}
		$visited[ $page_id ] = true;
		$page                = $records[ $page_id ];
		$parent              = 'Root';
		if ( $page['parent_id'] ) {
			$parent = isset( $records[ $page['parent_id'] ] ) ? $records[ $page['parent_id'] ]['title'] : 'Parent not in export';
			$parent .= ' [#' . $page['parent_id'] . ']';
			if ( isset( $visited[ $page['parent_id'] ] ) && $page['parent_id'] === $page_id ) {
				$parent .= ' (self-parent cycle)';
			}
		}
		$rows[] = array( str_repeat( '- ', min( $depth, 20 ) ) . ( $page['title'] ?: '(Untitled)' ), $page_id, $page['status'], $parent, $page['assigned_template'] ?? 'Default', $page['url'] ?? 'Unavailable' );
		foreach ( array_reverse( $children[ $page_id ] ?? array() ) as $child_id ) {
			if ( ! isset( $visited[ $child_id ] ) ) {
				$stack[] = array( $child_id, $depth + 1 );
			}
		}
	}
	wp_rebuild_mapper_admin_table( array( 'Page', 'ID', 'Status', 'Parent', 'Template', 'URL' ), $rows );
}

function wp_rebuild_mapper_admin_schema( $schema ) {
	$groups = $schema['acf']['field_groups'] ?? array();
	wp_rebuild_mapper_admin_section( __( 'Registered Post Types', 'wp-rebuild-mapper' ) );
	wp_rebuild_mapper_admin_note( 'All registered types are included. ACF references identify explicit equality rules, not evaluated placement. Check the full field group conditions for exclusions, templates, specific pages, and custom rules.' );
	$rows = array();
	foreach ( $schema['post_types'] as $post_type ) {
		$rows[] = array( $post_type['label'], $post_type['name'], $post_type['public'] ? 'Yes' : 'No', $post_type['hierarchical'] ? 'Yes' : 'No', implode( ', ', $post_type['taxonomies'] ) ?: 'None', null === $schema['acf'] ? 'ACF unavailable' : wp_rebuild_mapper_markdown_group_references( $groups, 'post_type', $post_type['name'] ) );
	}
	wp_rebuild_mapper_admin_table( array( 'Label', 'Post type', 'Public', 'Hierarchical', 'Taxonomies', 'ACF group references' ), $rows );
	wp_rebuild_mapper_admin_end_section();
	wp_rebuild_mapper_admin_section( __( 'Taxonomies', 'wp-rebuild-mapper' ) );
	foreach ( $schema['taxonomies'] as $taxonomy ) {
		wp_rebuild_mapper_admin_section( $taxonomy['label'] . ' [' . $taxonomy['name'] . ']' );
		wp_rebuild_mapper_admin_table( array( 'Setting', 'Value' ), array(
			array( 'Attached post types', implode( ', ', $taxonomy['post_types'] ) ?: 'None' ),
			array( 'Hierarchical', $taxonomy['hierarchical'] ? 'Yes' : 'No' ),
			array( 'ACF group references', null === $schema['acf'] ? 'ACF unavailable' : wp_rebuild_mapper_markdown_group_references( $groups, 'taxonomy', $taxonomy['name'] ) ),
		) );
		$names = array();
		foreach ( $taxonomy['terms'] ?? array() as $term ) {
			$names[ $term['id'] ] = $term['name'];
		}
		$rows = null === $taxonomy['terms'] ? null : array();
		foreach ( $taxonomy['terms'] ?? array() as $term ) {
			$rows[] = array( $term['name'], $term['id'], $term['slug'], $term['parent_id'] ? ( $names[ $term['parent_id'] ] ?? 'Parent not in export' ) . ' [#' . $term['parent_id'] . ']' : 'Root' );
		}
		wp_rebuild_mapper_admin_table( array( 'Term', 'ID', 'Slug', 'Parent' ), $rows );
		wp_rebuild_mapper_admin_end_section();
	}
	if ( ! $schema['taxonomies'] ) {
		wp_rebuild_mapper_admin_note( 'No registered taxonomies.' );
	}
	wp_rebuild_mapper_admin_end_section();
	wp_rebuild_mapper_admin_section( __( 'ACF Field Groups', 'wp-rebuild-mapper' ) );
	if ( null === $schema['acf'] ) {
		wp_rebuild_mapper_admin_note( 'ACF discovery unavailable. This does not establish that no custom fields exist.' );
	} else {
		wp_rebuild_mapper_admin_note( 'Placement uses AND conditions inside OR alternatives and is not evaluated. Relationship targets describe configuration, not saved links. Clone expansion and settings absent from discovery are not inferred.' );
		foreach ( $groups as $group ) {
			wp_rebuild_mapper_admin_section( $group['title'] . ( $group['active'] ? '' : ' (inactive)' ) );
			wp_rebuild_mapper_admin_table( array( 'Setting', 'Value' ), array(
				array( 'Group key', $group['key'] ),
				array( 'Active', $group['active'] ? 'Yes' : 'No' ),
				array( 'Placement', wp_rebuild_mapper_markdown_location( $group['location'] ) ),
			) );
			wp_rebuild_mapper_admin_table( array( 'Field path', 'Label', 'Type', 'Key', 'Configured relationship targets' ), wp_rebuild_mapper_admin_fields( $group['fields'] ) );
			wp_rebuild_mapper_admin_end_section();
		}
		if ( ! $groups ) {
			wp_rebuild_mapper_admin_note( 'No ACF field groups exposed.' );
		}
	}
	wp_rebuild_mapper_admin_end_section();
	wp_rebuild_mapper_admin_section( __( 'ACF Options Pages', 'wp-rebuild-mapper' ) );
	$pages = $schema['acf']['options_pages'] ?? null;
	$rows  = null === $pages ? null : array();
	foreach ( $pages ?? array() as $page ) {
		$rows[] = array( $page['page_title'], $page['menu_slug'], $page['parent_slug'] ?: 'None' );
	}
	wp_rebuild_mapper_admin_table( array( 'Page', 'Menu slug', 'Parent slug' ), $rows );
	wp_rebuild_mapper_admin_end_section();
}

function wp_rebuild_mapper_admin_fields( $fields, $prefix = '' ) {
	$rows = array();
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
		$rows[] = array( $path, $field['label'], $field['type'], $field['key'], $targets );
		$rows   = array_merge( $rows, wp_rebuild_mapper_admin_fields( $field['sub_fields'] ?? array(), $path . ' / ' ) );
		foreach ( $field['layouts'] ?? array() as $layout ) {
			$layout_path = $path . ' / layout:' . $layout['name'];
			$rows[]      = array( $layout_path, $layout['label'], 'layout', $layout['key'], 'Container' );
			$rows        = array_merge( $rows, wp_rebuild_mapper_admin_fields( $layout['sub_fields'], $layout_path . ' / ' ) );
		}
	}
	return $rows;
}

function wp_rebuild_mapper_admin_relationships( $relationships ) {
	wp_rebuild_mapper_admin_section( __( 'Relationship Usage', 'wp-rebuild-mapper' ) );
	foreach ( array( 'page_parents' => 'Page parents', 'other_post_parents' => 'Other post parents', 'term_assignment_counts' => 'Term assignment counts' ) as $key => $title ) {
		wp_rebuild_mapper_admin_section( $title );
		$rows = null === $relationships[ $key ] ? null : array();
		foreach ( $relationships[ $key ] ?? array() as $record ) {
			$rows[] = 'term_assignment_counts' === $key ? array( $record['taxonomy'], $record['term_id'], $record['post_type'], $record['post_count'] ) : array( $record['post_id'], $record['parent_id'] );
		}
		wp_rebuild_mapper_admin_table( 'term_assignment_counts' === $key ? array( 'Taxonomy', 'Term ID', 'Post type', 'Post count' ) : array( 'Post ID', 'Parent ID' ), $rows );
		wp_rebuild_mapper_admin_end_section();
	}
	wp_rebuild_mapper_admin_section( __( 'Observed ACF Relationship Usage', 'wp-rebuild-mapper' ) );
	wp_rebuild_mapper_admin_note( 'Counts are source posts, not individual links. Saved values may be empty; outcomes can overlap. Only top-level post fields and direct subfields of top-level repeaters are scanned. Deeper nesting, flexible layouts, clones, options, term metadata, and runtime filters are not evaluated.' );
	$usage = $relationships['acf_post_field_usage'];
	$rows  = null === $usage ? null : array();
	foreach ( $usage ?? array() as $field ) {
		$path = isset( $field['repeater_name'] ) ? $field['repeater_name'] . ' / ' . $field['field_name'] : $field['field_name'];
		$base = array( $field['group_key'], $path, $field['field_key'], $field['field_type'] );
		if ( ! $field['by_post_type'] ) {
			$rows[] = array_merge( $base, array( null === $field['by_post_type'] ? 'Lookup unavailable' : 'No observed saved values', '-', '-', '-', '-' ) );
		}
		foreach ( $field['by_post_type'] ?? array() as $counts ) {
			$rows[] = array_merge( $base, array( $counts['post_type'], $counts['posts_with_saved_value'], $counts['posts_with_valid'], $counts['posts_with_missing'], $counts['posts_with_mismatched'] ) );
		}
	}
	wp_rebuild_mapper_admin_table( array( 'Group key', 'Field path', 'Field key', 'Type', 'Source post type', 'Saved value', 'Valid target', 'Missing target', 'Mismatched target' ), $rows );
	wp_rebuild_mapper_admin_end_section();
	wp_rebuild_mapper_admin_end_section();
}