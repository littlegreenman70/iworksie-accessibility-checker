<?php
/**
 * PHPUnit bootstrap.
 *
 * @package IWorksIE\AccessibilityChecker
 */

$iwieac_root = dirname( __DIR__ );

require_once $iwieac_root . '/vendor/autoload.php';

$iwieac_prefixed = $iwieac_root . '/vendor-prefixed/autoload.php';
if ( is_readable( $iwieac_prefixed ) ) {
	require_once $iwieac_prefixed;
}

$iwieac_suite = getenv( 'IWIEAC_TESTSUITE' );
if ( ! $iwieac_suite ) {
	$iwieac_suite = 'unit';
}

if ( 'integration' === $iwieac_suite ) {
	$iwieac_wp_tests = getenv( 'WP_TESTS_DIR' );
	if ( ! $iwieac_wp_tests ) {
		$iwieac_wp_tests = $iwieac_root . '/tests/wordpress/wordpress-tests-lib';
	}
	if ( ! is_readable( $iwieac_wp_tests . '/includes/functions.php' ) ) {
		fwrite( STDERR, "WP test suite not found at {$iwieac_wp_tests}. Run bin/install-wp-tests.sh first.\n" );
		exit( 1 );
	}
	require_once $iwieac_wp_tests . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $iwieac_root ) {
			$plugin = $iwieac_root . '/iworksie-accessibility-checker.php';
			if ( is_readable( $plugin ) ) {
				require $plugin;
			}
		}
	);

	require_once $iwieac_wp_tests . '/includes/bootstrap.php';
}
