<?php
/**
 * URL helpers: normalising, same-site checks, hashing.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Support;

/**
 * Static URL helpers.
 */
final class Url {

	/**
	 * Query args the plugin adds and must never be stored.
	 *
	 * @var array<int,string>
	 */
	const OWN_ARGS = array( 'iwieac_scan', 'iwieac_highlight', 'iwieac_rule', '_iwieac' );

	/**
	 * Normalise a URL for storage: drop fragment and the plugin's own query args.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize( $url ) {
		$url = trim( (string) $url );
		$pos = strpos( $url, '#' );
		if ( false !== $pos ) {
			$url = substr( $url, 0, $pos );
		}
		return (string) remove_query_arg( self::OWN_ARGS, $url );
	}

	/**
	 * Stable hash for the unique key.
	 *
	 * @param string $url Normalised URL.
	 * @return string
	 */
	public static function hash( $url ) {
		return md5( $url );
	}

	/**
	 * Whether a URL is an http(s) URL on this site's front-end host.
	 *
	 * The scan runs in a same-origin iframe, so other hosts could never be scanned anyway;
	 * refusing them up front gives a clear message and keeps stored data to this site.
	 *
	 * @param string $url       URL to test.
	 * @param string $home_url  Site home URL (injectable for tests).
	 * @return bool
	 */
	public static function is_same_site( $url, $home_url = '' ) {
		if ( '' === $home_url ) {
			$home_url = home_url( '/' );
		}
		$parts = wp_parse_url( (string) $url );
		$home  = wp_parse_url( $home_url );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['host'] ) || empty( $home['host'] ) ) {
			return false;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		return strtolower( $parts['host'] ) === strtolower( $home['host'] )
			&& ( isset( $parts['port'] ) ? (int) $parts['port'] : 0 ) === ( isset( $home['port'] ) ? (int) $home['port'] : 0 );
	}

	/**
	 * Short readable path for tables, e.g. /about-us/.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function path( $url ) {
		$parts = wp_parse_url( (string) $url );
		if ( ! is_array( $parts ) ) {
			return (string) $url;
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}
		return $path;
	}
}
