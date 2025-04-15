<?php
/**
 * Plugin Name: Populate Cache
 * Plugin URI: https://github.com/donnchawp/populate-cache
 * Description: A plugin to populate the cache by loading all posts and pages in batches.
 * Version: 1.0.0
 * Author: Donncha
 * Author URI: https://odd.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: populate-cache
 *
 * @package Populate_Cache
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'POPULATE_CACHE_VERSION', '1.0.0' );
define( 'POPULATE_CACHE_BATCH_SIZE', 100 );

// Include required files.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-populate-cache.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/class-populate-cache-admin.php';

/**
 * Add custom cron schedule.
 *
 * @param array $schedules Array of existing cron schedules.
 * @return array Modified array of cron schedules.
 */
function populate_cache_add_cron_interval( $schedules ) {
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display'  => esc_html__( 'Every Minute', 'populate-cache' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'populate_cache_add_cron_interval' );

/**
 * Register plugin settings.
 */
function populate_cache_register_settings() {
	register_setting( 'populate_cache_options', 'populate_cache_scheduled' );
	register_setting(
		'populate_cache_options',
		'populate_cache_max_pages',
		array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'populate_cache_options',
		'populate_cache_processed_count',
		array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'populate_cache_options',
		'populate_cache_status',
		array(
			'type'              => 'string',
			'default'           => 'not_operating',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
}
add_action( 'admin_init', 'populate_cache_register_settings' );

/**
 * Reset processed count when scheduling.
 */
function populate_cache_reset_processed_count() {
	update_option( 'populate_cache_processed_count', 0 );
}
add_action( 'populate_cache_before_schedule', 'populate_cache_reset_processed_count' );

/**
 * Update scheduled status when process is complete.
 */
function populate_cache_update_scheduled_status() {
	update_option( 'populate_cache_scheduled', false );
}
add_action( 'populate_cache_complete', 'populate_cache_update_scheduled_status' );

/**
 * Initialize the plugin.
 */
function run_populate_cache() {
	$plugin = new Populate_Cache();
	$plugin->run();
}
run_populate_cache(); 
