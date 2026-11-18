<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_discover_content() {
	$post_types = get_post_types( array(), 'objects' );
	ksort( $post_types );
	$inventory = array();

	foreach ( $post_types as $name => $post_type ) {
		$counts = array_map( 'intval', (array) wp_count_posts( $name ) );
		ksort( $counts );
		$inventory[] = array(
			'name'             => $name,
			'label'            => $post_type->label,
			'counts_by_status' => (object) $counts,
		);
	}

	return array(
		'show_on_front' => get_option( 'show_on_front', 'posts' ),
		'front_page_id' => (int) get_option( 'page_on_front' ),
		'posts_page_id' => (int) get_option( 'page_for_posts' ),
		'post_types'    => $inventory,
		'pages'         => wp_rebuild_mapper_discover_pages(),
	);
}

function wp_rebuild_mapper_discover_pages() {
	$pages      = array();
	$batch_size = 200;
	$batch      = 1;
	$statuses   = array_values( array_diff( get_post_stati(), array( 'auto-draft', 'inherit' ) ) );

	do {
		$posts = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => $statuses,
				'posts_per_page'         => $batch_size,
				'paged'                  => $batch,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'cache_results'          => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$pages[] = array(
				'id'                => (int) $post->ID,
				'title'             => $post->post_title,
				'slug'              => $post->post_name,
				'url'               => wp_rebuild_mapper_page_url( $post ),
				'status'            => $post->post_status,
				'parent_id'         => (int) $post->post_parent,
				'assigned_template' => get_page_template_slug( $post ) ?: null,
			);
		}
		++$batch;
	} while ( count( $posts ) === $batch_size );

	return $pages;
}

function wp_rebuild_mapper_page_url( $post ) {
	$url = wp_parse_url( get_permalink( $post ) ?: '' );
	if ( empty( $url['host'] ) || ! in_array( $url['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
		return null;
	}

	$clean_url = $url['scheme'] . '://' . $url['host'];
	if ( isset( $url['port'] ) ) {
		$clean_url .= ':' . $url['port'];
	}
	$clean_url .= $url['path'] ?? '/';

	if ( isset( $url['query'] ) ) {
		parse_str( $url['query'], $query );
		if ( isset( $query['page_id'] ) && (string) $post->ID === $query['page_id'] ) {
			$clean_url .= '?page_id=' . (int) $post->ID;
		}
	}

	return esc_url_raw( $clean_url ) ?: null;
}