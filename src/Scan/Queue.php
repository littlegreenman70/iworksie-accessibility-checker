<?php
/**
 * Full-site scan queue, stored so an interrupted scan can be resumed.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Scan;

/**
 * Option-backed queue: list of URLs and the position reached.
 */
final class Queue {

	/**
	 * Option name (not autoloaded).
	 */
	const OPTION = 'iwieac_queue';

	/**
	 * Start a new queue.
	 *
	 * @param array<int,array<string,mixed>> $items         Items from UrlCollector.
	 * @param bool                           $best_practice Include best-practice rules.
	 * @return array<string,mixed>
	 */
	public static function create( array $items, $best_practice ) {
		$queue = array(
			'items'         => array_values( $items ),
			'position'      => 0,
			'best_practice' => (bool) $best_practice,
			'created_at'    => time(),
			'updated_at'    => time(),
			'user_id'       => get_current_user_id(),
		);
		update_option( self::OPTION, $queue, false );
		return $queue;
	}

	/**
	 * Current queue, or null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get() {
		$queue = get_option( self::OPTION, null );
		if ( ! is_array( $queue ) || ! isset( $queue['items'], $queue['position'] ) || ! is_array( $queue['items'] ) ) {
			return null;
		}
		return $queue;
	}

	/**
	 * Whether there is an unfinished queue to resume.
	 *
	 * @return bool
	 */
	public static function has_unfinished() {
		$queue = self::get();
		return null !== $queue && (int) $queue['position'] < count( $queue['items'] );
	}

	/**
	 * Mark item $index as done if it is the next expected one and matches the URL.
	 *
	 * @param int    $index Queue index.
	 * @param string $url   Normalised URL that was scanned.
	 * @return void
	 */
	public static function advance( $index, $url ) {
		$queue = self::get();
		if ( null === $queue || ! isset( $queue['items'][ $index ] ) ) {
			return;
		}
		if ( (string) $queue['items'][ $index ]['url'] !== (string) $url ) {
			return;
		}
		if ( $index + 1 > (int) $queue['position'] ) {
			$queue['position']   = $index + 1;
			$queue['updated_at'] = time();
			update_option( self::OPTION, $queue, false );
		}
	}

	/**
	 * Remove the queue.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Public view for the browser.
	 *
	 * @param array<string,mixed>|null $queue Queue.
	 * @return array<string,mixed>|null
	 */
	public static function view( $queue ) {
		if ( ! is_array( $queue ) ) {
			return null;
		}
		return array(
			'items'        => array_values( $queue['items'] ),
			'position'     => (int) $queue['position'],
			'total'        => count( $queue['items'] ),
			'bestPractice' => ! empty( $queue['best_practice'] ),
		);
	}
}
