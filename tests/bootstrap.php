<?php
/**
 * PHPUnit bootstrap — loads the WP test suite with this plugin's isolated
 * config, then loads the plugin itself on muplugins_loaded.
 */

// Use the plugin's local config when present (developer machines). When it's
// absent — CI — fall back to the config that install-wp-tests.sh writes into
// WP_TESTS_DIR, via the test suite's default discovery.
$_local_config = __DIR__ . '/wp-tests-config.php';
if ( file_exists( $_local_config ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', $_local_config );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php." . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin for testing.
 */
function _manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/mai-publish-requirements.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require "{$_tests_dir}/includes/bootstrap.php";
