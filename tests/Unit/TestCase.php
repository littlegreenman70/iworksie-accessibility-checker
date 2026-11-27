<?php
/**
 * Base unit test case with Brain Monkey and common WordPress stubs.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Sets up Brain Monkey around each test.
 */
abstract class TestCase extends PolyfillTestCase {

	/**
	 * @return void
	 */
	protected function set_up() {
		parent::set_up();
		Monkey\setUp();
		Functions\stubTranslationFunctions();
		Functions\stubs(
			array(
				'wp_parse_url'         => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
				},
				'wp_check_invalid_utf8' => static function ( $text ) {
					return (string) $text;
				},
				'esc_url_raw'          => static function ( $url ) {
					return (string) $url;
				},
				'home_url'             => static function ( $path = '' ) {
					return 'https://example.test' . $path;
				},
			)
		);
	}

	/**
	 * @return void
	 */
	protected function tear_down() {
		Monkey\tearDown();
		parent::tear_down();
	}
}
