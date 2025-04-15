<?php
/**
 * The cron functionality of the plugin.
 *
 * Handles the batch processing of posts and pages to populate the cache.
 *
 * @since      1.0.0
 * @package    Populate_Cache
 */

class Populate_Cache_Cron {
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Filter the posts WHERE clause to get posts after the last processed ID.
	 *
	 * @since    1.0.0
	 * @param    string    $where    The WHERE clause of the query.
	 * @return   string              Modified WHERE clause.
	 */
	public function filter_posts_where( $where ) {
		global $wpdb;

		// Get the last processed ID from options.
		$last_processed_id = get_option( 'populate_cache_last_processed_id', 0 );

		$where .= " AND {$wpdb->posts}.ID >= {$last_processed_id}";
		return $where;
	}

	/**
	 * Process a batch of posts and pages.
	 *
	 * @since    1.0.0
	 */
	public function process_batch() {
		// Check if the process has been cancelled.
		$is_cancelled = get_option( 'populate_cache_cancelled', false );
		if ( $is_cancelled ) {
			// Process has been cancelled, clean up and exit.
			update_option( 'populate_cache_status', 'not_operating' );
			update_option( 'populate_cache_scheduled', false );
			update_option( 'populate_cache_cancelled', false );
			return;
		}

		// Get the max pages setting.
		$max_pages = get_option( 'populate_cache_max_pages', 0 );
		
		// Get the count of processed items.
		$processed_count = get_option( 'populate_cache_processed_count', 0 );
		
		// Get the delay setting in milliseconds.
		$delay_ms = get_option( 'populate_cache_delay_ms', 0 );
		
		// Get the batch size setting.
		$batch_size = get_option( 'populate_cache_batch_size', 10 );
		
		// Update status to in progress.
		update_option( 'populate_cache_status', 'in_progress' );
		
		add_filter( 'posts_where', array( $this, 'filter_posts_where' ) );
		
		// Get the next batch of posts and pages.
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$query = new WP_Query( $args );
		
		remove_filter( 'posts_where', array( $this, 'filter_posts_where' ) );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				
				// Check if we've reached the max pages limit.
				if ( $max_pages > 0 && $processed_count >= $max_pages ) {
					// We've reached the limit, stop processing.
					wp_reset_postdata();
					
					// Reset the last processed ID.
					update_option( 'populate_cache_last_processed_id', 0 );
					update_option( 'populate_cache_processed_count', 0 );
					
					// Update status to finished.
					update_option( 'populate_cache_status', 'finished' );
					
					// Unschedule the cron job since we're done.
					wp_clear_scheduled_hook( 'populate_cache_cron_hook' );
					update_option( 'populate_cache_scheduled', false );
					
					// Trigger action to notify that the process is complete.
					do_action( 'populate_cache_complete' );
					
					return;
				}
				
				// Get the permalink.
				$permalink = get_permalink( $post_id );
				
				// Make the request to populate cache.
				$response = wp_remote_get(
					$permalink,
					array(
						'timeout'     => 30,
						'user-agent'  => 'WordPress/Populate-Cache',
						'blocking'    => true,
					)
				);

				if ( is_wp_error( $response ) ) {
					error_log(
						sprintf(
							'Populate Cache: Error processing post %d: %s',
							$post_id,
							$response->get_error_message()
						)
					);
				}

				// Apply delay after each page request if set.
				if ( $delay_ms > 0 ) {
					usleep( $delay_ms * 1000 ); // Convert milliseconds to microseconds.
				}

				// Update the last processed ID.
				update_option( 'populate_cache_last_processed_id', $post_id );
				
				// Increment the processed count.
				$processed_count++;
				update_option( 'populate_cache_processed_count', $processed_count );
			}
			
			// Check again if the process has been cancelled before scheduling the next batch.
			$is_cancelled = get_option( 'populate_cache_cancelled', false );
			if ( ! $is_cancelled ) {
				// Schedule the next batch immediately.
				wp_schedule_single_event( time(), 'populate_cache_cron_hook' );
			} else {
				// Process has been cancelled, clean up and exit.
				update_option( 'populate_cache_status', 'not_operating' );
				update_option( 'populate_cache_scheduled', false );
				update_option( 'populate_cache_cancelled', false );
			}
		} else {
			// No more posts to process, reset the last processed ID.
			update_option( 'populate_cache_last_processed_id', 0 );
			update_option( 'populate_cache_processed_count', 0 );
			
			// Update status to finished.
			update_option( 'populate_cache_status', 'finished' );
			
			// Unschedule the cron job since we're done.
			wp_clear_scheduled_hook( 'populate_cache_cron_hook' );
			update_option( 'populate_cache_scheduled', false );
			
			// Trigger action to notify that the process is complete.
			do_action( 'populate_cache_complete' );
		}

		wp_reset_postdata();
	}
} 