<?php
/**
 * Dismissed elements ("not a problem") with reason and audit trail.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Data;

use IWorksIE\AccessibilityChecker\Install\Schema;

/**
 * CRUD and matching for dismissals.
 */
final class Dismissals {

	/**
	 * Target value meaning "every element for this rule".
	 */
	const ALL_TARGETS = '*';

	/**
	 * Allowed reasons.
	 *
	 * @return array<string,string> key => label
	 */
	public static function reasons() {
		return array(
			'false_positive' => __( 'False positive: not actually a problem', 'iworksie-accessibility-checker' ),
			'confirmed'      => __( 'Checked by a person and accessible', 'iworksie-accessibility-checker' ),
			'third_party'    => __( 'Third-party content we cannot change', 'iworksie-accessibility-checker' ),
			'not_relevant'   => __( 'Not relevant to this site', 'iworksie-accessibility-checker' ),
		);
	}

	/**
	 * Add (or update) a dismissal.
	 *
	 * @param string $rule_id Rule id.
	 * @param string $target  Selector or ALL_TARGETS.
	 * @param int    $page_id Page id, or 0 for every page.
	 * @param string $reason  Reason key.
	 * @param string $note    Optional note.
	 * @return bool
	 */
	public static function add( $rule_id, $target, $page_id, $reason, $note = '' ) {
		global $wpdb;
		$reasons = self::reasons();
		if ( ! isset( $reasons[ $reason ] ) ) {
			return false;
		}
		$t      = Schema::tables();
		$target = (string) $target;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, values prepared.
		$ok = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (rule_id, target, target_hash, page_id, reason, note, user_id, created_at) VALUES (%s, %s, %s, %d, %s, %s, %d, %d) ON DUPLICATE KEY UPDATE reason = VALUES(reason), note = VALUES(note), user_id = VALUES(user_id), created_at = VALUES(created_at)',
				$t['dismissals'],
				(string) $rule_id,
				$target,
				md5( $target ),
				(int) $page_id,
				$reason,
				(string) $note,
				get_current_user_id(),
				time()
			)
		);
		return false !== $ok;
	}

	/**
	 * Remove a dismissal and return the removed row.
	 *
	 * @param int $id Dismissal id.
	 * @return array<string,mixed>|null
	 */
	public static function remove( $id ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['dismissals'], (int) $id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $t['dismissals'], array( 'id' => (int) $id ), array( '%d' ) );
		return $row;
	}

	/**
	 * All dismissals, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all() {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT 1000', $t['dismissals'] ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Number of dismissals.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $t['dismissals'] ) );
	}

	/**
	 * Dismissals that apply to a page, as rule_id => [ target => row ].
	 *
	 * @param int $page_id Page id.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public static function map_for_page( $page_id ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE page_id = 0 OR page_id = %d', $t['dismissals'], (int) $page_id ), ARRAY_A );
		return self::to_map( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Build rule_id => [ target => row ] from rows. Page-specific rows win over site-wide ones.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public static function to_map( array $rows ) {
		$map = array();
		foreach ( $rows as $row ) {
			$rule   = (string) $row['rule_id'];
			$target = (string) $row['target'];
			if ( isset( $map[ $rule ][ $target ] ) && (int) $map[ $rule ][ $target ]['page_id'] > 0 ) {
				continue;
			}
			$map[ $rule ][ $target ] = $row;
		}
		return $map;
	}

	/**
	 * The dismissal covering one element, if any.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $map     From map_for_page().
	 * @param string                                          $rule_id Rule id.
	 * @param string                                          $target  Element selector.
	 * @return array<string,mixed>|null
	 */
	public static function match( array $map, $rule_id, $target ) {
		if ( isset( $map[ $rule_id ][ $target ] ) ) {
			return $map[ $rule_id ][ $target ];
		}
		if ( isset( $map[ $rule_id ][ self::ALL_TARGETS ] ) ) {
			return $map[ $rule_id ][ self::ALL_TARGETS ];
		}
		return null;
	}

	/**
	 * Count of elements still active after dismissals.
	 *
	 * Only stored elements (up to 30) can be matched one by one; extra elements beyond
	 * those stay active unless the whole rule is dismissed.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $map     From map_for_page().
	 * @param string                                          $rule_id Rule id.
	 * @param array<int,array<string,string>>                 $nodes   Stored nodes.
	 * @param int                                             $total   Real element count.
	 * @return int
	 */
	public static function active_count( array $map, $rule_id, array $nodes, $total ) {
		if ( isset( $map[ $rule_id ][ self::ALL_TARGETS ] ) ) {
			return 0;
		}
		$dismissed = 0;
		foreach ( $nodes as $node ) {
			if ( isset( $node['t'], $map[ $rule_id ][ $node['t'] ] ) ) {
				++$dismissed;
			}
		}
		return max( 0, (int) $total - $dismissed );
	}
}
