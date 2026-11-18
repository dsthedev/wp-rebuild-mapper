<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wp_rebuild_mapper_discover_acf() {
	if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
		return null;
	}

	$groups = array();
	foreach ( acf_get_field_groups() as $group ) {
		$groups[ $group['key'] ] = array(
			'key'      => $group['key'],
			'title'    => $group['title'],
			'active'   => (bool) ( $group['active'] ?? true ),
			'location' => wp_rebuild_mapper_acf_location( $group['location'] ?? array() ),
			'fields'   => wp_rebuild_mapper_acf_fields( acf_get_fields( $group ) ?: array() ),
		);
	}
	ksort( $groups, SORT_STRING );

	$options_pages = null;
	if ( function_exists( 'acf_get_options_pages' ) ) {
		$options_pages = array();
		foreach ( (array) acf_get_options_pages() as $page ) {
			$options_pages[ $page['menu_slug'] ] = array(
				'menu_slug'   => $page['menu_slug'],
				'page_title'  => $page['page_title'],
				'menu_title'  => $page['menu_title'],
				'parent_slug' => $page['parent_slug'] ?? '',
			);
		}
		ksort( $options_pages, SORT_STRING );
		$options_pages = array_values( $options_pages );
	}

	return array(
		'version'       => defined( 'ACF_VERSION' ) ? ACF_VERSION : null,
		'field_groups'  => array_values( $groups ),
		'options_pages' => $options_pages,
	);
}

function wp_rebuild_mapper_acf_location( $location ) {
	$rule_groups = array();
	foreach ( $location as $rules ) {
		$rule_group = array();
		foreach ( $rules as $rule ) {
			$rule_group[] = array(
				'param'    => $rule['param'],
				'operator' => $rule['operator'],
				'value'    => is_scalar( $rule['value'] ?? null ) ? $rule['value'] : null,
			);
		}
		$rule_groups[] = $rule_group;
	}
	return $rule_groups;
}

function wp_rebuild_mapper_acf_fields( $fields ) {
	$records = array();
	foreach ( $fields as $field ) {
		$record = array(
			'key'   => $field['key'],
			'name'  => $field['name'],
			'label' => $field['label'],
			'type'  => $field['type'],
		);

		if ( in_array( $field['type'], array( 'relationship', 'post_object' ), true ) ) {
			$record['target_filters'] = array(
				'post_types'   => wp_rebuild_mapper_acf_filter_values( $field['post_type'] ?? null ),
				'post_statuses' => wp_rebuild_mapper_acf_filter_values( $field['post_status'] ?? null ),
				'terms'         => wp_rebuild_mapper_acf_filter_values( $field['taxonomy'] ?? null ),
			);
		} elseif ( 'taxonomy' === $field['type'] ) {
			$taxonomy = $field['taxonomy'] ?? null;
			$record['target_filters'] = array(
				'taxonomy' => is_string( $taxonomy ) && '' !== $taxonomy ? $taxonomy : null,
			);
		}

		if ( isset( $field['sub_fields'] ) || in_array( $field['type'], array( 'group', 'repeater' ), true ) ) {
			$record['sub_fields'] = wp_rebuild_mapper_acf_fields( $field['sub_fields'] ?? array() );
		}

		if ( 'flexible_content' === $field['type'] ) {
			$record['layouts'] = array();
			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$record['layouts'][] = array(
					'key'        => $layout['key'],
					'name'       => $layout['name'],
					'label'      => $layout['label'],
					'sub_fields' => wp_rebuild_mapper_acf_fields( $layout['sub_fields'] ?? array() ),
				);
			}
		}

		$records[] = $record;
	}
	return $records;
}

function wp_rebuild_mapper_acf_filter_values( $setting ) {
	$values = array();
	foreach ( (array) $setting as $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			$values[] = $value;
		}
	}
	sort( $values, SORT_STRING );
	return $values;
}