<?php
/**
 * URL helper tests.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use Brain\Monkey\Functions;
use IWorksIE\AccessibilityChecker\Support\Url;

/**
 * @covers \IWorksIE\AccessibilityChecker\Support\Url
 */
final class UrlTest extends TestCase {

	public function test_same_site() {
		$home = 'https://example.test/';
		$this->assertTrue( Url::is_same_site( 'https://example.test/about/', $home ) );
		$this->assertTrue( Url::is_same_site( 'http://EXAMPLE.test/?p=1', $home ) );
		$this->assertFalse( Url::is_same_site( 'https://evil.test/', $home ) );
		$this->assertFalse( Url::is_same_site( 'https://example.test.evil.test/', $home ) );
		$this->assertFalse( Url::is_same_site( 'https://example.test:8080/', $home ) );
		$this->assertFalse( Url::is_same_site( 'javascript:alert(1)', $home ) );
		$this->assertFalse( Url::is_same_site( 'https://user:pw@example.test/', $home ) );
		$this->assertFalse( Url::is_same_site( '/relative', $home ) );
		$this->assertTrue( Url::is_same_site( 'https://example.test/x' ) );
	}

	public function test_normalize_strips_fragment_and_own_args() {
		Functions\when( 'remove_query_arg' )->alias(
			static function ( $keys, $url ) {
				$parts = explode( '?', $url, 2 );
				if ( 2 !== count( $parts ) ) {
					return $url;
				}
				parse_str( $parts[1], $query );
				foreach ( (array) $keys as $key ) {
					unset( $query[ $key ] );
				}
				return $parts[0] . ( $query ? '?' . http_build_query( $query ) : '' );
			}
		);
		$this->assertSame( 'https://example.test/a/?x=1', Url::normalize( 'https://example.test/a/?x=1&iwieac_scan=abc#top' ) );
		$this->assertSame( 'https://example.test/a/', Url::normalize( ' https://example.test/a/#frag ' ) );
	}

	public function test_path() {
		$this->assertSame( '/a/b/?s=x', Url::path( 'https://example.test/a/b/?s=x' ) );
		$this->assertSame( '/', Url::path( 'https://example.test' ) );
	}
}
