<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    Populate_Cache
 */

class Populate_Cache {
	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Populate_Cache_Loader    $loader    Maintains and registers all hooks.
	 */
	protected $loader;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-populate-cache-loader.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-populate-cache-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-populate-cache-cron.php';

		$this->loader = new Populate_Cache_Loader();
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Populate_Cache_Admin();
		$plugin_cron = new Populate_Cache_Cron();

		$this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
		$this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
		$this->loader->add_action('wp_ajax_populate_cache_schedule', $plugin_admin, 'schedule_cache_population');
		$this->loader->add_action('wp_ajax_populate_cache_unschedule', $plugin_admin, 'unschedule_cache_population');
		
		// Cron hooks
		$this->loader->add_action('populate_cache_cron_hook', $plugin_cron, 'process_batch');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		// Public hooks will be added here if needed
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}
} 