<?php
/**
 * CLI command for related posts.
 *
 * @package hm-related-posts
 */

namespace HM\Related_Posts;

\WP_CLI::add_command( 'hm-related-posts', __NAMESPACE__ . '\\CLI' );

class CLI {

	/**
	 * Migrate to 1.0
	 *
	 * Cleanup pre 1.0 transients.
	 */
	public function migrate_1_0() {
		global $wpdb;

		\WP_CLI::line( 'Cleaning up old HM Related Post transients.' );

		$query = "DELETE FROM $wpdb->options WHERE `option_name` REGEXP '^\_transient\_[^_]+$'";
		$count = $wpdb->query( $query ); // phpcs:ignore

		\WP_CLI::success( "$count old transients deleted." );
	}

	/**
	 * Flush Transient Data
	 *
	 * Cleanup all current transient data
	 */
	public function flush_transient_data() {
		global $wpdb;

		\WP_CLI::line( 'Deleting all HM Related Post transients.' );

		$query = "DELETE FROM $wpdb->options WHERE `option_name` REGEXP '^_transient_hmrp_[0-9]+_[^_]+$'";
		$count = $wpdb->query( $query ); // phpcs:ignore

		\WP_CLI::success( "$count HM Related Posts transients deleted." );

		$query = "DELETE FROM $wpdb->options WHERE `option_name` REGEXP '^_transient_timeout_hmrp_[0-9]+_[^_]+$'";
		$count = $wpdb->query( $query ); // phpcs:ignore

		\WP_CLI::success( "$count HM Related Posts transient timeouts deleted." );
	}

}
