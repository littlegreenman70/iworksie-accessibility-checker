<?php
/**
 * Plain-English messages for pages that could not be scanned.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Scan;

/**
 * Maps error codes sent by the browser to translated messages.
 */
final class ScanErrors {

	/**
	 * Message for a code (unknown codes get a generic message).
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function message( $code ) {
		switch ( $code ) {
			case 'timeout':
				return __( 'The page took longer than 45 seconds to load, so it was skipped. Try checking it on its own.', 'iworksie-accessibility-checker' );
			case 'frame_blocked':
				return __( 'The page could not be opened inside the checker. A security plugin or server header (X-Frame-Options: DENY or a Content-Security-Policy frame-ancestors rule) blocks it. Allow same-origin framing (SAMEORIGIN) and try again.', 'iworksie-accessibility-checker' );
			case 'other_site':
				return __( 'The page redirected to a different address (another domain or http/https), so it could not be checked from here.', 'iworksie-accessibility-checker' );
			case 'script_blocked':
				return __( 'The checker script could not be loaded into the page. A Content-Security-Policy may be blocking scripts from this plugin.', 'iworksie-accessibility-checker' );
			case 'axe_error':
				return __( 'The accessibility engine stopped with an error on this page. This can happen with very unusual markup; try checking the page again.', 'iworksie-accessibility-checker' );
			case 'too_large':
				return __( 'The results for this page were too large to save.', 'iworksie-accessibility-checker' );
			default:
				return __( 'The page could not be checked.', 'iworksie-accessibility-checker' );
		}
	}
}
