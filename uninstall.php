<?php
/**
 * Uninstall cleanup for iWorks.ie Accessibility Checker.
 *
 * Removes the plugin's tables, options and transients (on every site of a multisite network).
 *
 * @package IWorksIE\AccessibilityChecker
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove everything this plugin created on the current site.
 *
 * @return void
 */
function iwieac_uninstall_site() {
	global $wpdb;

	foreach ( array( 'iwieac_pages', 'iwieac_issues', 'iwieac_dismissals' ) as $iwieac_table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $iwieac_table ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$iwieac_options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'iwieac_' ) . '%',
			$wpdb->esc_like( '_transient_iwieac_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_iwieac_' ) . '%'
		)
	);
	foreach ( (array) $iwieac_options as $iwieac_option ) {
		if ( 0 === strpos( $iwieac_option, '_transient_timeout_' ) ) {
			delete_transient( substr( $iwieac_option, strlen( '_transient_timeout_' ) ) );
		} elseif ( 0 === strpos( $iwieac_option, '_transient_' ) ) {
			delete_transient( substr( $iwieac_option, strlen( '_transient_' ) ) );
		} else {
			delete_option( $iwieac_option );
		}
	}
}

if ( is_multisite() ) {
	$iwieac_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $iwieac_site_ids as $iwieac_site_id ) {
		switch_to_blog( (int) $iwieac_site_id );
		iwieac_uninstall_site();
		restore_current_blog();
	}
} else {
	iwieac_uninstall_site();
}
