<?php

WP_CLI::add_command( 'hm-related-posts', 'HM_Related_Posts_CLI' );

class HM_Related_Posts_CLI {

	/**
	 * Migrate to 1.0
	 *
	 * Cleanup pre 1.0 transients.
	 */
	public function migrate_1_0() {

		global $wpdb;

		WP_CLI::line( "Cleaning up old HM Related Post transients.");

		$query = "DELETE FROM `wp_options` WHERE `option_name` REGEXP '^\_transient\_[^_]+$'";
		$count = $wpdb->query( $query );

		WP_CLI::success( "$count old transients deleted.");

	}

}
