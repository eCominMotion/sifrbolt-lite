<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package SifrBolt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
if ( is_readable( $dropin ) ) {
	$contents = file_get_contents( $dropin ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Drop-in removal uses direct filesystem access.
	if ( is_string( $contents ) && str_contains( $contents, 'SIFRBOLT_SPARK_LITE_DROPIN v1' ) ) {
		wp_delete_file( $dropin );
	}
}

$config = WP_CONTENT_DIR . '/sifrbolt-cache-config.php';
if ( file_exists( $config ) ) {
	wp_delete_file( $config );
}

$cache_dir = WP_CONTENT_DIR . '/cache/sifrbolt';
if ( is_dir( $cache_dir ) && function_exists( 'wp_delete_directory' ) ) {
	wp_delete_directory( $cache_dir );
}

$cron_bridge = WP_CONTENT_DIR . '/mu-plugins/sifrbolt-cron-bridge.php';
if ( is_readable( $cron_bridge ) ) {
	wp_delete_file( $cron_bridge );
}

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'sifrbolt_transients_janitor_event' );
	wp_clear_scheduled_hook( 'sifrbolt_lite_send_telemetry' );
}

delete_option( 'sifrbolt_lite_state' );
delete_option( 'sifrbolt_lite_dropin' );
delete_option( 'sifrbolt_lite_telemetry' );
delete_option( 'sifrbolt_lite_cwv_buckets' );
delete_option( 'sifrbolt_blueprint_journal' );
delete_option( 'sifrbolt_lite_plan' );

if ( function_exists( 'delete_site_transient' ) ) {
	delete_site_transient( 'sifrbolt_lite_feature_manifest' );
}
