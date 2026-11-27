<?php
/**
 * Database tables for scan results and dismissals.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Install;

/**
 * Creates and upgrades the plugin's three tables (per site on multisite).
 */
final class Schema {

	/**
	 * Bump when the table definitions change.
	 */
	const DB_VERSION = '1';

	/**
	 * Option holding the installed schema version.
	 */
	const VERSION_OPTION = 'iwieac_db_version';

	/**
	 * Activation hook.
	 *
	 * @param bool $network_wide Whether the plugin is being network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// Create tables for the first 100 sites now; the rest are created lazily on first admin visit.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	/**
	 * Install or upgrade when the stored version differs.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Table names for the current site.
	 *
	 * @return array{pages:string,issues:string,dismissals:string}
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'pages'      => $wpdb->prefix . 'iwieac_pages',
			'issues'     => $wpdb->prefix . 'iwieac_issues',
			'dismissals' => $wpdb->prefix . 'iwieac_dismissals',
		);
	}

	/**
	 * Run dbDelta for all tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		$sql = array(
			"CREATE TABLE {$t['pages']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  url_hash char(32) NOT NULL,
  url text NOT NULL,
  label varchar(255) NOT NULL DEFAULT '',
  object_type varchar(40) NOT NULL DEFAULT '',
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'ok',
  error_message varchar(500) NOT NULL DEFAULT '',
  problems int(10) unsigned NOT NULL DEFAULT 0,
  problem_nodes int(10) unsigned NOT NULL DEFAULT 0,
  review int(10) unsigned NOT NULL DEFAULT 0,
  passes int(10) unsigned NOT NULL DEFAULT 0,
  engine varchar(20) NOT NULL DEFAULT '',
  scanned_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY url_hash (url_hash),
  KEY object (object_type,object_id)
) $charset;",
			"CREATE TABLE {$t['issues']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  rule_id varchar(100) NOT NULL,
  kind varchar(12) NOT NULL DEFAULT 'violation',
  impact varchar(12) NOT NULL DEFAULT '',
  nodes_total int(10) unsigned NOT NULL DEFAULT 0,
  nodes_active int(10) unsigned NOT NULL DEFAULT 0,
  nodes longtext NOT NULL,
  PRIMARY KEY  (id),
  KEY page_id (page_id),
  KEY rule_kind (rule_id,kind)
) $charset;",
			"CREATE TABLE {$t['dismissals']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rule_id varchar(100) NOT NULL,
  target varchar(512) NOT NULL DEFAULT '',
  target_hash char(32) NOT NULL,
  page_id bigint(20) unsigned NOT NULL DEFAULT 0,
  reason varchar(30) NOT NULL DEFAULT '',
  note varchar(500) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY match_key (rule_id,target_hash,page_id),
  KEY page_id (page_id)
) $charset;",
		);

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop all tables for the current site (used by uninstall).
	 *
	 * @return void
	 */
	public static function drop() {
		global $wpdb;
		foreach ( self::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}
		delete_option( self::VERSION_OPTION );
	}
}
