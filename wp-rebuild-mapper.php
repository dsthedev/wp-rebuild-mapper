<?php
/**
 * Plugin Name: WP Rebuild Mapper
 * Description: Generates a read-only content inventory for planning a WordPress rebuild.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: wp-rebuild-mapper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/relationships.php';

add_action( 'admin_menu', 'wp_rebuild_mapper_register_page' );
add_action( 'admin_post_wp_rebuild_mapper_export', 'wp_rebuild_mapper_export' );

function wp_rebuild_mapper_register_page() {
	add_management_page(
		__( 'Rebuild Mapper', 'wp-rebuild-mapper' ),
		__( 'Rebuild Mapper', 'wp-rebuild-mapper' ),
		'manage_options',
		'wp-rebuild-mapper',
		'wp_rebuild_mapper_render_page'
	);
}

function wp_rebuild_mapper_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'wp-rebuild-mapper' ), '', array( 'response' => 403 ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Rebuild Mapper', 'wp-rebuild-mapper' ); ?></h1>
		<p><?php esc_html_e( 'The content report includes page titles, URLs, and unpublished page structure. Review it before sharing.', 'wp-rebuild-mapper' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wp_rebuild_mapper_export">
			<?php wp_nonce_field( 'wp_rebuild_mapper_export', '_wpnonce', false ); ?>
			<label>
				<input type="checkbox" name="include_schema" value="1">
				<?php esc_html_e( 'Include schema (post type settings, taxonomies, and terms)', 'wp-rebuild-mapper' ); ?>
			</label>
			<br>
			<label>
				<input type="checkbox" name="include_relationships" value="1">
				<?php esc_html_e( 'Include relationships (post parents and term assignment counts)', 'wp-rebuild-mapper' ); ?>
			</label>
			<?php submit_button( __( 'Export JSON', 'wp-rebuild-mapper' ) ); ?>
		</form>
	</div>
	<?php
}

function wp_rebuild_mapper_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to export this report.', 'wp-rebuild-mapper' ), '', array( 'response' => 403 ) );
	}

	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( esc_html__( 'This export requires a POST request.', 'wp-rebuild-mapper' ), '', array( 'response' => 405 ) );
	}

	check_admin_referer( 'wp_rebuild_mapper_export' );

	$report = array(
		'report_version'    => 1,
		'site'              => array(
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
		),
		'included_sections' => array( 'content' ),
		'content'           => wp_rebuild_mapper_discover_content(),
	);

	if ( '1' === ( $_POST['include_schema'] ?? '' ) ) {
		$report['included_sections'][] = 'schema';
		$report['schema']              = wp_rebuild_mapper_discover_schema();
	}

	if ( '1' === ( $_POST['include_relationships'] ?? '' ) ) {
		$report['included_sections'][] = 'relationships';
		$report['relationships']       = wp_rebuild_mapper_discover_relationships( $report['content']['pages'] );
	}

	$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
	if ( false === $json ) {
		wp_die( esc_html__( 'The content report could not be encoded.', 'wp-rebuild-mapper' ), '', array( 'response' => 500 ) );
	}

	nocache_headers();
	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="wp-rebuild-mapper.json"' );
	header( 'X-Content-Type-Options: nosniff' );
	echo $json . "\n";
	exit;
}