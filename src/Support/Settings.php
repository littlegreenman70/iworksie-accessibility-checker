<?php
/**
 * Remembered scan settings.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Support;

use IWorksIE\AccessibilityChecker\Scan\UrlCollector;

/**
 * Small option wrapper.
 */
final class Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'iwieac_settings';

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array{best_practice:bool,max_pages:int,sources:array<int,string>|null}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'best_practice' => isset( $saved['best_practice'] ) ? (bool) $saved['best_practice'] : true,
			'max_pages'     => isset( $saved['max_pages'] ) ? max( 1, min( UrlCollector::HARD_MAX, (int) $saved['max_pages'] ) ) : UrlCollector::DEFAULT_MAX,
			'sources'       => isset( $saved['sources'] ) && is_array( $saved['sources'] ) ? array_values( array_map( 'strval', $saved['sources'] ) ) : null,
		);
	}

	/**
	 * Save settings.
	 *
	 * @param bool              $best_practice Include best-practice rules.
	 * @param int               $max_pages     Page limit.
	 * @param array<int,string> $sources       Selected sources.
	 * @return void
	 */
	public static function save( $best_practice, $max_pages, array $sources ) {
		update_option(
			self::OPTION,
			array(
				'best_practice' => (bool) $best_practice,
				'max_pages'     => max( 1, min( UrlCollector::HARD_MAX, (int) $max_pages ) ),
				'sources'       => array_values( $sources ),
			),
			false
		);
	}
}
