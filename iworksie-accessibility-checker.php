<?php
/**
 * Plugin Name:       iWorks.ie Accessibility Checker
 * Plugin URI:        https://www.iworks.ie
 * Description:       Free full-site accessibility checker built on axe-core. Plain-English fixes and a WCAG 2.2 AA / EN 301 549 report. No overlay, no upsells, nothing sent to third parties.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            iWorks.ie
 * Author URI:        https://www.iworks.ie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iworksie-accessibility-checker
 * Domain Path:       /languages
 *
 * @package IWorksIE\AccessibilityChecker
 */

defined( 'ABSPATH' ) || exit;

define( 'IWIEAC_VERSION', '1.0.0' );
define( 'IWIEAC_FILE', __FILE__ );
define( 'IWIEAC_PATH', plugin_dir_path( __FILE__ ) );
define( 'IWIEAC_URL', plugin_dir_url( __FILE__ ) );
define( 'IWIEAC_MIN_PHP', '7.4' );
define( 'IWIEAC_MIN_WP', '6.5' );

/**
 * Admin notice when requirements are not met.
 *
 * @param string $message Notice text.
 * @return void
 */
function iwieac_requirements_notice( $message ) {
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	);
}

if ( version_compare( PHP_VERSION, IWIEAC_MIN_PHP, '<' ) ) {
	iwieac_requirements_notice(
		sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'iWorks.ie Accessibility Checker requires PHP %1$s or higher. This site is running PHP %2$s.', 'iworksie-accessibility-checker' ),
			IWIEAC_MIN_PHP,
			PHP_VERSION
		)
	);
	return;
}

global $wp_version;
if ( isset( $wp_version ) && version_compare( $wp_version, IWIEAC_MIN_WP, '<' ) ) {
	iwieac_requirements_notice(
		sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version */
			__( 'iWorks.ie Accessibility Checker requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'iworksie-accessibility-checker' ),
			IWIEAC_MIN_WP,
			$wp_version
		)
	);
	return;
}

$iwieac_prefixed = IWIEAC_PATH . 'vendor-prefixed/autoload.php';
if ( ! is_readable( $iwieac_prefixed ) ) {
	iwieac_requirements_notice(
		__( 'iWorks.ie Accessibility Checker is missing its vendor-prefixed library. Run composer install, or install the release zip from wordpress.org.', 'iworksie-accessibility-checker' )
	);
	return;
}
require_once $iwieac_prefixed;

$iwieac_autoload = IWIEAC_PATH . 'vendor/autoload.php';
if ( is_readable( $iwieac_autoload ) ) {
	require_once $iwieac_autoload;
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'IWorksIE\\AccessibilityChecker\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$relative = substr( $class_name, strlen( $prefix ) );
			$file     = IWIEAC_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( '\IWorksIE\AccessibilityChecker\Install\Schema', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( '\IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\Core' ) ) {
			iwieac_requirements_notice(
				__( 'iWorks.ie Accessibility Checker could not load its shared tools library.', 'iworksie-accessibility-checker' )
			);
			return;
		}
		// Boot after init so __() / translate() are not called too early (WP 6.7+).
		add_action(
			'init',
			static function () {
				\IWorksIE\AccessibilityChecker\Plugin::instance()->boot();
			},
			5
		);
	},
	5
);
