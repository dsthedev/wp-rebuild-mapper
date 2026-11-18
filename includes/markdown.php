<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/markdown-schema.php';

function wp_rebuild_mapper_render_markdown( $report ) {
	$content = $report['content'];
	$lines   = array(
		'# WP Rebuild Mapper',
		'',
		'Report version: ' . (int) $report['report_version'],
		'',
		'## Content Summary',
		'',
		'- Registered post types: ' . count( $content['post_types'] ),
		'- Pages in the sitemap: ' . count( $content['pages'] ),
		'- Front page ID: ' . ( $content['front_page_id'] ?: 'not set' ),
		'- Posts page ID: ' . ( $content['posts_page_id'] ?: 'not set' ),
		'',
		'## Page Hierarchy',
		'',
	);
	$lines = array_merge( $lines, wp_rebuild_mapper_markdown_page_hierarchy( $content['pages'] ) );
	if ( isset( $report['schema'] ) ) {
		$schema  = $report['schema'];
		$lines[] = '';
		$lines[] = '## Schema Summary';
		$lines[] = '';
		$lines[] = '- Registered taxonomies: ' . count( $schema['taxonomies'] );
		$lines[] = '- Observed metadata key entries: ' . wp_rebuild_mapper_markdown_count( $schema['observed_post_meta'] );
		$lines[] = '- ACF field groups: ' . wp_rebuild_mapper_markdown_count( $schema['acf']['field_groups'] ?? null );
		$lines   = array_merge( $lines, wp_rebuild_mapper_markdown_schema( $schema ) );
	}
	if ( isset( $report['relationships'] ) ) {
		$relationships = $report['relationships'];
		$lines[]       = '';
		$lines[]       = '## Relationship Summary';
		$lines[]       = '';
		$lines[]       = '- Page parent links: ' . count( $relationships['page_parents'] );
		$lines[]       = '- Other post parent links: ' . wp_rebuild_mapper_markdown_count( $relationships['other_post_parents'] );
		$lines[]       = '- Term assignment count entries: ' . wp_rebuild_mapper_markdown_count( $relationships['term_assignment_counts'] );
		$lines[]       = '- ACF field usage entries: ' . wp_rebuild_mapper_markdown_count( $relationships['acf_post_field_usage'] );
		$lines         = array_merge( $lines, wp_rebuild_mapper_markdown_acf_usage( $relationships['acf_post_field_usage'] ) );
	}
	return implode( "\n", $lines );
}

function wp_rebuild_mapper_markdown_count( $records ) {
	return is_array( $records ) ? count( $records ) : 'unavailable';
}

function wp_rebuild_mapper_markdown_page_hierarchy( $pages ) {
	if ( ! $pages ) {
		return array( 'No pages in the export.' );
	}

	$parents = array();
	$labels  = array();
	foreach ( $pages as $page ) {
		$page_id             = (int) $page['id'];
		$parents[ $page_id ] = (int) $page['parent_id'];
		$title               = trim( preg_replace( '/[\x00-\x20]+/', ' ', $page['title'] ) );
		$labels[ $page_id ]  = ( '' === $title ? '(Untitled)' : $title ) . ' [#' . $page_id . ', ' . sanitize_key( $page['status'] ) . ']';
	}
	ksort( $parents, SORT_NUMERIC );

	foreach ( $parents as $page_id => $parent_id ) {
		if ( 0 !== $parent_id && ! isset( $parents[ $parent_id ] ) ) {
			$parents[ $page_id ] = 0;
			$labels[ $page_id ] .= ' (parent #' . $parent_id . ' not in export)';
		}
	}

	$visited = array();
	foreach ( $parents as $page_id => $parent_id ) {
		$path = array();
		$current_id = $page_id;
		while ( 0 !== $current_id && ! isset( $visited[ $current_id ] ) ) {
			if ( isset( $path[ $current_id ] ) ) {
				$labels[ $current_id ] .= ' (parent cycle via #' . $parents[ $current_id ] . ')';
				$parents[ $current_id ] = 0;
				break;
			}
			$path[ $current_id ] = true;
			$current_id = $parents[ $current_id ];
		}
		$visited += $path;
	}

	$children = array();
	foreach ( $parents as $page_id => $parent_id ) {
		$children[ $parent_id ][] = $page_id;
	}
	$stack = array();
	foreach ( array_reverse( $children[0] ) as $page_id ) {
		$stack[] = array( $page_id, 0 );
	}
	$lines = array( '```text' );
	while ( $stack ) {
		list( $page_id, $depth ) = array_pop( $stack );
		$lines[] = str_repeat( '  ', $depth ) . '- ' . $labels[ $page_id ];
		foreach ( array_reverse( $children[ $page_id ] ?? array() ) as $child_id ) {
			$stack[] = array( $child_id, $depth + 1 );
		}
	}
	$lines[] = '```';
	$lines[] = '';
	if ( count( $parents ) > 100 ) {
		$lines[] = 'Mermaid diagram omitted for exports with more than 100 pages. The text tree above is complete.';
		return $lines;
	}

	$lines[] = '```mermaid';
	$lines[] = 'flowchart TD';
	foreach ( $parents as $page_id => $parent_id ) {
		$label = preg_replace_callback(
			'/[!"#$%&\x27()*+,\/:;<=>?@\[\]\\\\^`{|}~]/',
			static function ( $match ) {
				return '#' . ord( $match[0] ) . ';';
			},
			$labels[ $page_id ]
		);
		$lines[] = '  page_' . $page_id . '["' . $label . '"]';
		if ( 0 !== $parent_id ) {
			$lines[] = '  page_' . $parent_id . ' --> page_' . $page_id;
		}
	}
	$lines[] = '```';
	return $lines;
}