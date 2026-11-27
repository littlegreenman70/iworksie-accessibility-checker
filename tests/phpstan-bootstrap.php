<?php
/**
 * PHPStan bootstrap stubs.
 *
 * @package IWorksIE\AccessibilityChecker
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'IWIEAC_VERSION' ) ) {
	define( 'IWIEAC_VERSION', '1.0.0' );
}
if ( ! defined( 'IWIEAC_FILE' ) ) {
	define( 'IWIEAC_FILE', '/tmp/iworksie-accessibility-checker/iworksie-accessibility-checker.php' );
}
if ( ! defined( 'IWIEAC_PATH' ) ) {
	define( 'IWIEAC_PATH', '/tmp/iworksie-accessibility-checker/' );
}
if ( ! defined( 'IWIEAC_URL' ) ) {
	define( 'IWIEAC_URL', 'https://example.com/wp-content/plugins/iworksie-accessibility-checker/' );
}
if ( ! defined( 'IWIEAC_MIN_PHP' ) ) {
	define( 'IWIEAC_MIN_PHP', '7.4' );
}
if ( ! defined( 'IWIEAC_MIN_WP' ) ) {
	define( 'IWIEAC_MIN_WP', '6.5' );
}

if ( ! class_exists( '\IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\Core' ) && is_readable( dirname( __DIR__ ) . '/vendor-prefixed/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor-prefixed/autoload.php';
}
