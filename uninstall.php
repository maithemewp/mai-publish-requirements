<?php
/**
 * Mai Publish Requirements — uninstall cleanup.
 *
 * Removes the plugin's option and its per-user "kept as Pending" notice
 * transients. No post data is touched.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || die;

global $wpdb;

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mai_publish_requirements%'" );

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_mai_publish_requirements_%'
	    OR option_name LIKE '_transient_timeout_mai_publish_requirements_%'"
);
