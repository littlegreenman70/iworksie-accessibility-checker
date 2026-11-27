<?php
/**
 * Scan results storage: pages, issues and rule metadata.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Data;

use IWorksIE\AccessibilityChecker\Install\Schema;
use IWorksIE\AccessibilityChecker\Support\Url;
use IWorksIE\AccessibilityChecker\Support\Wcag;

/**
 * Reads and writes the pages and issues tables.
 */
final class Store {

	/**
	 * Option holding rule metadata (help text, tags, link) keyed by rule id.
	 */
	const RULES_OPTION = 'iwieac_rules';

	/**
	 * Save a successful scan for one page.
	 *
	 * @param array<string,mixed> $page    url, label, object_type, object_id.
	 * @param array<string,mixed> $payload From PayloadSanitizer::sanitize().
	 * @return int Page id (0 on failure).
	 */
	public static function save_result( array $page, array $payload ) {
		global $wpdb;
		$t       = Schema::tables();
		$page_id = self::upsert_page( $page, 'ok', '', isset( $payload['engine'] ) ? (string) $payload['engine'] : '', isset( $payload['passes'] ) ? (int) $payload['passes'] : 0 );
		if ( ! $page_id ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $t['issues'], array( 'page_id' => $page_id ), array( '%d' ) );

		$map   = Dismissals::map_for_page( $page_id );
		$rules = array();
		foreach ( array(
			'violation'  => 'violations',
			'incomplete' => 'incomplete',
		) as $kind => $key ) {
			$list = isset( $payload[ $key ] ) && is_array( $payload[ $key ] ) ? $payload[ $key ] : array();
			foreach ( $list as $rule ) {
				$nodes = $rule['nodes'];
				$total = (int) $rule['n'];
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$t['issues'],
					array(
						'page_id'      => $page_id,
						'rule_id'      => $rule['id'],
						'kind'         => $kind,
						'impact'       => $rule['impact'],
						'nodes_total'  => $total,
						'nodes_active' => Dismissals::active_count( $map, $rule['id'], $nodes, $total ),
						'nodes'        => (string) wp_json_encode( $nodes ),
					),
					array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
				);
				$rules[ $rule['id'] ] = array(
					'help'        => $rule['help'],
					'description' => $rule['description'],
					'help_url'    => $rule['help_url'],
					'tags'        => $rule['tags'],
				);
			}
		}

		self::remember_rules( $rules );
		self::recount_page( $page_id );
		return $page_id;
	}

	/**
	 * Record that a page could not be scanned.
	 *
	 * @param array<string,mixed> $page    url, label, object_type, object_id.
	 * @param string              $message Plain-text reason.
	 * @return int Page id.
	 */
	public static function save_error( array $page, $message ) {
		global $wpdb;
		$t       = Schema::tables();
		$page_id = self::upsert_page( $page, 'error', $message, '', 0 );
		if ( $page_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $t['issues'], array( 'page_id' => $page_id ), array( '%d' ) );
			self::recount_page( $page_id );
		}
		return $page_id;
	}

	/**
	 * Insert or update the page row.
	 *
	 * @param array<string,mixed> $page    Page fields.
	 * @param string              $status  ok|error.
	 * @param string              $message Error message.
	 * @param string              $engine  axe version.
	 * @param int                 $passes  Rules passed.
	 * @return int
	 */
	private static function upsert_page( array $page, $status, $message, $engine, $passes ) {
		global $wpdb;
		$t     = Schema::tables();
		$url   = Url::normalize( isset( $page['url'] ) ? (string) $page['url'] : '' );
		$label = isset( $page['label'] ) ? sanitize_text_field( (string) $page['label'] ) : '';
		if ( '' === $url ) {
			return 0;
		}
		$hash = Url::hash( $url );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (url_hash, url, label, object_type, object_id, status, error_message, engine, passes, scanned_at) VALUES (%s, %s, %s, %s, %d, %s, %s, %s, %d, %d) ON DUPLICATE KEY UPDATE url = VALUES(url), label = VALUES(label), object_type = VALUES(object_type), object_id = VALUES(object_id), status = VALUES(status), error_message = VALUES(error_message), engine = VALUES(engine), passes = VALUES(passes), scanned_at = VALUES(scanned_at)',
				$t['pages'],
				$hash,
				$url,
				function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 250 ) : substr( $label, 0, 250 ),
				isset( $page['object_type'] ) ? sanitize_key( (string) $page['object_type'] ) : '',
				isset( $page['object_id'] ) ? absint( $page['object_id'] ) : 0,
				$status,
				function_exists( 'mb_substr' ) ? mb_substr( (string) $message, 0, 490 ) : substr( (string) $message, 0, 490 ),
				$engine,
				(int) $passes,
				time()
			)
		);
		if ( false === $ok ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s', $t['pages'], $hash ) );
	}

	/**
	 * Recompute a page's cached counts from its issues.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	public static function recount_page( $page_id ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN kind = 'violation' AND nodes_active > 0 THEN 1 ELSE 0 END) AS problems, SUM(CASE WHEN kind = 'violation' THEN nodes_active ELSE 0 END) AS problem_nodes, SUM(CASE WHEN kind = 'incomplete' AND nodes_active > 0 THEN 1 ELSE 0 END) AS review FROM %i WHERE page_id = %d",
				$t['issues'],
				(int) $page_id
			),
			ARRAY_A
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$t['pages'],
			array(
				'problems'      => is_array( $row ) ? (int) $row['problems'] : 0,
				'problem_nodes' => is_array( $row ) ? (int) $row['problem_nodes'] : 0,
				'review'        => is_array( $row ) ? (int) $row['review'] : 0,
			),
			array( 'id' => (int) $page_id ),
			array( '%d', '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Re-apply dismissals to issues of a rule (on one page, or all pages) and refresh counts.
	 *
	 * @param string $rule_id Rule id.
	 * @param int    $page_id Page id, or 0 for every page.
	 * @return void
	 */
	public static function reapply_dismissals( $rule_id, $page_id ) {
		global $wpdb;
		$t = Schema::tables();
		if ( $page_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, page_id, nodes_total, nodes FROM %i WHERE rule_id = %s AND page_id = %d', $t['issues'], $rule_id, $page_id ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, page_id, nodes_total, nodes FROM %i WHERE rule_id = %s', $t['issues'], $rule_id ), ARRAY_A );
		}
		if ( ! is_array( $rows ) ) {
			return;
		}
		$maps  = array();
		$pages = array();
		foreach ( $rows as $row ) {
			$pid = (int) $row['page_id'];
			if ( ! isset( $maps[ $pid ] ) ) {
				$maps[ $pid ] = Dismissals::map_for_page( $pid );
			}
			$nodes = json_decode( (string) $row['nodes'], true );
			$nodes = is_array( $nodes ) ? $nodes : array();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$t['issues'],
				array( 'nodes_active' => Dismissals::active_count( $maps[ $pid ], $rule_id, $nodes, (int) $row['nodes_total'] ) ),
				array( 'id' => (int) $row['id'] ),
				array( '%d' ),
				array( '%d' )
			);
			$pages[ $pid ] = true;
		}
		foreach ( array_keys( $pages ) as $pid ) {
			self::recount_page( $pid );
		}
	}

	/**
	 * One page row.
	 *
	 * @param int $page_id Page id.
	 * @return array<string,mixed>|null
	 */
	public static function get_page( $page_id ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $t['pages'], (int) $page_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Page row by URL.
	 *
	 * @param string $url URL.
	 * @return array<string,mixed>|null
	 */
	public static function get_page_by_url( $url ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE url_hash = %s', $t['pages'], Url::hash( Url::normalize( $url ) ) ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Paged list of pages.
	 *
	 * @param array<string,mixed> $args orderby, order, paged, per_page, status.
	 * @return array{rows:array<int,array<string,mixed>>,total:int}
	 */
	public static function list_pages( array $args ) {
		global $wpdb;
		$t        = Schema::tables();
		$columns  = array(
			'problems' => 'problem_nodes',
			'review'   => 'review',
			'scanned'  => 'scanned_at',
			'label'    => 'label',
		);
		$orderby  = isset( $args['orderby'], $columns[ $args['orderby'] ] ) ? $columns[ $args['orderby'] ] : 'problem_nodes';
		$order    = ( isset( $args['order'] ) && 'asc' === $args['order'] ) ? 'ASC' : 'DESC';
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 50;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;
		$errors   = ( isset( $args['status'] ) && 'error' === $args['status'] ) ? 1 : 0;
		$problems = ( isset( $args['status'] ) && 'problems' === $args['status'] ) ? 1 : 0;

		// Column comes from the whitelist above; direction is one of two literal queries.
		if ( 'ASC' === $order ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ( %d = 0 OR status = 'error' ) AND ( %d = 0 OR ( status = 'ok' AND problems > 0 ) ) ORDER BY %i ASC, id ASC LIMIT %d OFFSET %d", $t['pages'], $errors, $problems, $orderby, $per_page, $offset ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE ( %d = 0 OR status = 'error' ) AND ( %d = 0 OR ( status = 'ok' AND problems > 0 ) ) ORDER BY %i DESC, id ASC LIMIT %d OFFSET %d", $t['pages'], $errors, $problems, $orderby, $per_page, $offset ), ARRAY_A );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ( %d = 0 OR status = 'error' ) AND ( %d = 0 OR ( status = 'ok' AND problems > 0 ) )", $t['pages'], $errors, $problems ) );
		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Issues for one page with decoded nodes, worst first.
	 *
	 * @param int $page_id Page id.
	 * @return array<int,array<string,mixed>>
	 */
	public static function page_issues( $page_id ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE page_id = %d', $t['issues'], (int) $page_id ), ARRAY_A );
		$rows = is_array( $rows ) ? $rows : array();
		foreach ( $rows as &$row ) {
			$nodes        = json_decode( (string) $row['nodes'], true );
			$row['nodes'] = is_array( $nodes ) ? $nodes : array();
		}
		unset( $row );
		usort( $rows, array( __CLASS__, 'compare_issues' ) );
		return $rows;
	}

	/**
	 * Site-wide aggregation by rule and kind (active elements only).
	 *
	 * @return array<int,array{rule_id:string,kind:string,impact:string,pages:int,nodes:int}>
	 */
	public static function aggregate_rules() {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT rule_id, kind, impact, COUNT(DISTINCT page_id) AS pages, SUM(nodes_active) AS nodes FROM %i WHERE nodes_active > 0 GROUP BY rule_id, kind, impact', $t['issues'] ), ARRAY_A );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = $row['rule_id'] . '|' . $row['kind'];
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'rule_id' => (string) $row['rule_id'],
					'kind'    => (string) $row['kind'],
					'impact'  => (string) $row['impact'],
					'pages'   => 0,
					'nodes'   => 0,
				);
			}
			// A rule's impact can differ per element; keep the worst.
			if ( Wcag::impact_weight( (string) $row['impact'] ) > Wcag::impact_weight( $out[ $key ]['impact'] ) ) {
				$out[ $key ]['impact'] = (string) $row['impact'];
			}
			// Pages may be counted twice across impact groups; capped below by a distinct count.
			$out[ $key ]['pages'] += (int) $row['pages'];
			$out[ $key ]['nodes'] += (int) $row['nodes'];
		}
		foreach ( $out as $key => $agg ) {
			if ( $agg['pages'] > 1 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$out[ $key ]['pages'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT page_id) FROM %i WHERE rule_id = %s AND kind = %s AND nodes_active > 0', $t['issues'], $agg['rule_id'], $agg['kind'] ) );
			}
		}
		$out = array_values( $out );
		usort( $out, array( __CLASS__, 'compare_issues' ) );
		return $out;
	}

	/**
	 * Pages affected by a rule.
	 *
	 * @param string $rule_id Rule id.
	 * @param string $kind    violation|incomplete.
	 * @param int    $limit   Max rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function rule_pages( $rule_id, $kind, $limit = 20 ) {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.id, p.url, p.label, SUM(i.nodes_active) AS nodes FROM %i i INNER JOIN %i p ON p.id = i.page_id WHERE i.rule_id = %s AND i.kind = %s AND i.nodes_active > 0 GROUP BY p.id, p.url, p.label ORDER BY nodes DESC, p.id ASC LIMIT %d', $t['issues'], $t['pages'], $rule_id, $kind, (int) $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Site totals for the overview.
	 *
	 * @return array<string,int>
	 */
	public static function totals() {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$p = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS pages, SUM(CASE WHEN status = 'ok' THEN 1 ELSE 0 END) AS ok_pages, SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) AS error_pages, SUM(CASE WHEN status = 'ok' AND problems = 0 THEN 1 ELSE 0 END) AS clean_pages, SUM(problem_nodes) AS problem_nodes, SUM(passes) AS passes, SUM(problems) AS page_problems, MAX(scanned_at) AS last_scanned FROM %i", $t['pages'] ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$i = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT CASE WHEN kind = 'violation' THEN rule_id END) AS problem_rules, COUNT(DISTINCT CASE WHEN kind = 'incomplete' THEN rule_id END) AS review_rules, SUM(CASE WHEN kind = 'incomplete' THEN nodes_active ELSE 0 END) AS review_nodes FROM %i WHERE nodes_active > 0", $t['issues'] ), ARRAY_A );
		$p = is_array( $p ) ? $p : array();
		$i = is_array( $i ) ? $i : array();

		$passes   = isset( $p['passes'] ) ? (int) $p['passes'] : 0;
		$failures = isset( $p['page_problems'] ) ? (int) $p['page_problems'] : 0;
		$score    = ( $passes + $failures ) > 0 ? (int) round( $passes / ( $passes + $failures ) * 100 ) : 0;

		return array(
			'pages'         => isset( $p['pages'] ) ? (int) $p['pages'] : 0,
			'ok_pages'      => isset( $p['ok_pages'] ) ? (int) $p['ok_pages'] : 0,
			'error_pages'   => isset( $p['error_pages'] ) ? (int) $p['error_pages'] : 0,
			'clean_pages'   => isset( $p['clean_pages'] ) ? (int) $p['clean_pages'] : 0,
			'problem_nodes' => isset( $p['problem_nodes'] ) ? (int) $p['problem_nodes'] : 0,
			'problem_rules' => isset( $i['problem_rules'] ) ? (int) $i['problem_rules'] : 0,
			'review_rules'  => isset( $i['review_rules'] ) ? (int) $i['review_rules'] : 0,
			'review_nodes'  => isset( $i['review_nodes'] ) ? (int) $i['review_nodes'] : 0,
			'last_scanned'  => isset( $p['last_scanned'] ) ? (int) $p['last_scanned'] : 0,
			'score'         => $score,
		);
	}

	/**
	 * Score for one page row: rules passed / (passed + failed), 0-100.
	 *
	 * @param array<string,mixed> $page Page row.
	 * @return int|null Null when not scanned successfully.
	 */
	public static function page_score( array $page ) {
		if ( 'ok' !== $page['status'] ) {
			return null;
		}
		$passes   = (int) $page['passes'];
		$failures = (int) $page['problems'];
		if ( 0 === $passes + $failures ) {
			return null;
		}
		return (int) round( $passes / ( $passes + $failures ) * 100 );
	}

	/**
	 * Delete all scan results (dismissals are kept).
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;
		$t = Schema::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $t['issues'] ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $t['pages'] ) );
		delete_option( self::RULES_OPTION );
	}

	/**
	 * Stored rule metadata.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function rules() {
		$rules = get_option( self::RULES_OPTION, array() );
		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Metadata for one rule.
	 *
	 * @param string $rule_id Rule id.
	 * @return array<string,mixed>
	 */
	public static function rule( $rule_id ) {
		$rules = self::rules();
		return isset( $rules[ $rule_id ] ) && is_array( $rules[ $rule_id ] ) ? $rules[ $rule_id ] : array();
	}

	/**
	 * Merge new rule metadata into the option (only writes when something changed).
	 *
	 * @param array<string,array<string,mixed>> $fresh Rule id => metadata.
	 * @return void
	 */
	private static function remember_rules( array $fresh ) {
		if ( empty( $fresh ) ) {
			return;
		}
		$rules  = self::rules();
		$merged = array_merge( $rules, $fresh );
		if ( $merged !== $rules ) {
			update_option( self::RULES_OPTION, $merged, false );
		}
	}

	/**
	 * Sort: problems before needs-review, then worst impact, then most pages/elements.
	 *
	 * @param array<string,mixed> $a First.
	 * @param array<string,mixed> $b Second.
	 * @return int
	 */
	public static function compare_issues( $a, $b ) {
		$ka = 'violation' === $a['kind'] ? 0 : 1;
		$kb = 'violation' === $b['kind'] ? 0 : 1;
		if ( $ka !== $kb ) {
			return $ka - $kb;
		}
		$ia = Wcag::impact_weight( (string) $a['impact'] );
		$ib = Wcag::impact_weight( (string) $b['impact'] );
		if ( $ia !== $ib ) {
			return $ib - $ia;
		}
		$pa = isset( $a['pages'] ) ? (int) $a['pages'] : (int) $a['nodes_active'];
		$pb = isset( $b['pages'] ) ? (int) $b['pages'] : (int) $b['nodes_active'];
		if ( $pa !== $pb ) {
			return $pb - $pa;
		}
		return strcmp( (string) $a['rule_id'], (string) $b['rule_id'] );
	}
}
