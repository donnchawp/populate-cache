<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @since      1.0.0
 * @package    Populate_Cache
 */

class Populate_Cache_Admin {
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Add AJAX handlers.
		add_action( 'wp_ajax_populate_cache_check_status', array( $this, 'check_status' ) );
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_management_page(
			'Populate Cache',
			'Populate Cache',
			'manage_options',
			'populate-cache',
			array( $this, 'display_plugin_admin_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
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
			'populate_cache_delay_ms',
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'populate_cache_options',
			'populate_cache_batch_size',
			array(
				'type'              => 'integer',
				'default'           => 100,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'populate_cache_options',
			'populate_cache_cancelled',
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
	}

	/**
	 * Render the admin page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$is_scheduled     = get_option( 'populate_cache_scheduled', false );
		$max_pages        = get_option( 'populate_cache_max_pages', 0 );
		$delay_ms         = get_option( 'populate_cache_delay_ms', 0 );
		$batch_size       = get_option( 'populate_cache_batch_size', 100 );
		$status           = get_option( 'populate_cache_status', 'not_operating' );
		$processed_count  = get_option( 'populate_cache_processed_count', 0 );
		
		// Get total number of published posts and pages.
		$total_posts = wp_count_posts( 'post' )->publish;
		$total_pages = wp_count_posts( 'page' )->publish;
		$total_items = $total_posts + $total_pages;
		
		// If max_pages is 0, set it to the total number of items.
		if ( 0 === $max_pages ) {
			$max_pages = $total_items;
		}
		
		// Calculate progress percentage.
		$progress_percentage = 0;
		if ( $max_pages > 0 ) {
			$progress_percentage = min( 100, round( ( $processed_count / $max_pages ) * 100 ) );
		}
		
		// If status is finished, ensure the scheduled status is updated.
		if ( 'finished' === $status && $is_scheduled ) {
			update_option( 'populate_cache_scheduled', false );
			$is_scheduled = false;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="notice notice-info">
				<p><?php esc_html_e( 'This tool will populate your cache by loading posts and pages in batches.', 'populate-cache' ); ?></p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Cache Population Status', 'populate-cache' ); ?></h2>
				
				<p class="status-indicator">
					<?php if ( 'in_progress' === $status ) : ?>
						<span class="dashicons dashicons-update" style="color: #0073aa; animation: spin 2s linear infinite;"></span>
						<?php esc_html_e( 'Cache population is currently in progress.', 'populate-cache' ); ?>
					<?php elseif ( 'finished' === $status ) : ?>
						<span class="dashicons dashicons-yes" style="color: green;"></span>
						<?php esc_html_e( 'Cache population has finished successfully.', 'populate-cache' ); ?>
					<?php else : ?>
						<span class="dashicons dashicons-no" style="color: red;"></span>
						<?php esc_html_e( 'Cache population is not operating.', 'populate-cache' ); ?>
					<?php endif; ?>
				</p>
				
				<?php if ( 'in_progress' === $status || 'finished' === $status ) : ?>
				<div class="progress-bar-container" style="background-color: #f1f1f1; width: 100%; height: 20px; border-radius: 10px; margin: 10px 0;">
					<div class="progress-bar" style="background-color: #0073aa; width: <?php echo esc_attr( $progress_percentage ); ?>%; height: 100%; border-radius: 10px; text-align: center; line-height: 20px; color: white;">
						<?php echo esc_html( $progress_percentage ); ?>%
					</div>
				</div>
				<p class="progress-text">
					<?php
					printf(
						/* translators: 1: number of processed pages, 2: total number of pages */
						esc_html__( 'Processed %1$d of %2$d pages', 'populate-cache' ),
						$processed_count,
						$max_pages
					);
					?>
				</p>
				<?php endif; ?>

				<p>
					<label for="max-pages"><?php esc_html_e( 'Number of pages to process:', 'populate-cache' ); ?></label>
					<input type="number" id="max-pages" name="max-pages" value="<?php echo esc_attr( $max_pages ); ?>" min="1" max="<?php echo esc_attr( $total_items ); ?>" class="small-text">
					<span class="description"><?php printf( esc_html__( '(Total available: %d)', 'populate-cache' ), $total_items ); ?></span>
				</p>
				
				<p>
					<label for="batch-size"><?php esc_html_e( 'Batch size (pages per batch):', 'populate-cache' ); ?></label>
					<input type="number" id="batch-size" name="batch-size" value="<?php echo esc_attr( $batch_size ); ?>" min="1" max="1000" class="small-text">
					<span class="description"><?php esc_html_e( '(Number of pages processed in one go)', 'populate-cache' ); ?></span>
				</p>
				
				<p>
					<label for="delay-ms"><?php esc_html_e( 'Delay after each page (ms):', 'populate-cache' ); ?></label>
					<input type="number" id="delay-ms" name="delay-ms" value="<?php echo esc_attr( $delay_ms ); ?>" min="0" class="small-text">
					<span class="description"><?php esc_html_e( '(1000ms = 1 second)', 'populate-cache' ); ?></span>
				</p>

				<p>
					<?php if ( ! $is_scheduled ) : ?>
						<button type="button" class="button button-primary" id="schedule-cache-population">
							<?php esc_html_e( 'Schedule Cache Population', 'populate-cache' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button button-secondary" id="unschedule-cache-population">
							<?php esc_html_e( 'Unschedule Cache Population', 'populate-cache' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<style>
		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		</style>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#schedule-cache-population').on('click', function() {
				var maxPages = $('#max-pages').val();
				var delayMs = $('#delay-ms').val();
				var batchSize = $('#batch-size').val();
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'populate_cache_schedule',
						nonce: '<?php echo esc_js( wp_create_nonce( 'populate_cache_schedule' ) ); ?>',
						max_pages: maxPages,
						delay_ms: delayMs,
						batch_size: batchSize
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error scheduling cache population', 'populate-cache' ) ); ?>');
						}
					}
				});
			});

			$('#unschedule-cache-population').on('click', function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'populate_cache_unschedule',
						nonce: '<?php echo esc_js( wp_create_nonce( 'populate_cache_unschedule' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('<?php echo esc_js( __( 'Error unscheduling cache population', 'populate-cache' ) ); ?>');
						}
					}
				});
			});
			
			// Check status periodically if cache population is in progress.
			<?php if ( 'in_progress' === $status ) : ?>
			var statusCheckInterval = setInterval(function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'populate_cache_check_status',
						nonce: '<?php echo esc_js( wp_create_nonce( 'populate_cache_check_status' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							var data = response.data;
							
							// Update status indicator.
							if ('in_progress' === data.status) {
								$('.status-indicator').html('<span class="dashicons dashicons-update" style="color: #0073aa; animation: spin 2s linear infinite;"></span> <?php echo esc_js( __( 'Cache population is currently in progress.', 'populate-cache' ) ); ?>');
							} else if ('finished' === data.status) {
								$('.status-indicator').html('<span class="dashicons dashicons-yes" style="color: green;"></span> <?php echo esc_js( __( 'Cache population has finished successfully.', 'populate-cache' ) ); ?>');
								clearInterval(statusCheckInterval);
								
								// Update button.
								$('#schedule-cache-population').show();
								$('#unschedule-cache-population').hide();
							} else {
								$('.status-indicator').html('<span class="dashicons dashicons-no" style="color: red;"></span> <?php echo esc_js( __( 'Cache population is not operating.', 'populate-cache' ) ); ?>');
								clearInterval(statusCheckInterval);
								
								// Update button.
								$('#schedule-cache-population').show();
								$('#unschedule-cache-population').hide();
							}
							
							// Update progress bar.
							if ('in_progress' === data.status || 'finished' === data.status) {
								$('.progress-bar').css('width', data.progress_percentage + '%');
								$('.progress-bar').text(data.progress_percentage + '%');
								$('.progress-text').text('<?php echo esc_js( __( 'Processed', 'populate-cache' ) ); ?> ' + data.processed_count + ' <?php echo esc_js( __( 'of', 'populate-cache' ) ); ?> ' + data.max_pages + ' <?php echo esc_js( __( 'pages', 'populate-cache' ) ); ?>');
							}
							
							// If the process is finished, reload the page to update the UI
							if ( data.status === 'finished' ) {
								setTimeout(function() {
									location.reload();
								}, 2000);
							}
						}
					}
				});
			}, 1000); // Check every second
			<?php endif; ?>
		});
		</script>
		<?php
	}

	/**
	 * Schedule cache population.
	 *
	 * @since    1.0.0
	 */
	public function schedule_cache_population() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'populate_cache_schedule' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Get and sanitize input values.
		$max_pages  = isset( $_POST['max_pages'] ) ? absint( $_POST['max_pages'] ) : 0;
		$delay_ms   = isset( $_POST['delay_ms'] ) ? absint( $_POST['delay_ms'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 10;

		// Update options.
		update_option( 'populate_cache_max_pages', $max_pages );
		update_option( 'populate_cache_delay_ms', $delay_ms );
		update_option( 'populate_cache_batch_size', $batch_size );
		update_option( 'populate_cache_scheduled', true );
		update_option( 'populate_cache_status', 'in_progress' );
		update_option( 'populate_cache_processed_count', 0 );
		update_option( 'populate_cache_last_processed_id', 0 );

		// Schedule the first batch.
		wp_schedule_single_event( time(), 'populate_cache_cron_hook' );

		wp_send_json_success();
	}

	/**
	 * Unschedule cache population.
	 *
	 * @since    1.0.0
	 */
	public function unschedule_cache_population() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'populate_cache_unschedule' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// Update options.
		update_option( 'populate_cache_cancelled', true );
		update_option( 'populate_cache_scheduled', false );
		update_option('populate_cache_status', 'not_operating');
		wp_clear_scheduled_hook('populate_cache_cron_hook');

		wp_send_json_success();
	}

	/**
	 * Check the status of cache population.
	 *
	 * @since    1.0.0
	 */
	public function check_status() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'populate_cache_check_status' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$status            = get_option( 'populate_cache_status', 'not_operating' );
		$processed_count   = get_option( 'populate_cache_processed_count', 0 );
		$max_pages         = get_option( 'populate_cache_max_pages', 0 );
		$progress_percentage = 0;

		if ( $max_pages > 0 ) {
			$progress_percentage = min( 100, round( ( $processed_count / $max_pages ) * 100 ) );
		}

		wp_send_json_success(
			array(
				'status'             => $status,
				'processed_count'    => $processed_count,
				'max_pages'          => $max_pages,
				'progress_percentage' => $progress_percentage,
			)
		);
	}
} 