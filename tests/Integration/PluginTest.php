<?php
/**
 * Boot, menu, scan mode, report and uninstall.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Integration;

use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Install\Schema;
use IWorksIE\AccessibilityChecker\Plugin;
use IWorksIE\AccessibilityChecker\Support\ReportBuilder;
use WP_UnitTestCase;

/**
 * Plugin-level integration tests.
 */
final class PluginTest extends WP_UnitTestCase {

	public function test_tables_exist_after_install() {
		global $wpdb;
		Schema::install();
		// The WP test suite turns CREATE TABLE into temporary tables, so check by querying them.
		foreach ( Schema::tables() as $table ) {
			$this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ); // phpcs:ignore
			$this->assertSame( '', $wpdb->last_error );
		}
		$this->assertSame( Schema::DB_VERSION, get_option( Schema::VERSION_OPTION ) );
	}

	public function test_submenu_registered_under_iworks_tools() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		do_action( 'admin_menu' );
		global $submenu;
		$this->assertArrayHasKey( 'iworks-tools', $submenu );
		$slugs = wp_list_pluck( $submenu['iworks-tools'], 2 );
		$this->assertContains( Plugin::PAGE_SLUG, $slugs );
	}

	public function test_scan_mode_hides_admin_bar_only_with_valid_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$frontend = new \IWorksIE\AccessibilityChecker\Frontend\Frontend();
		$this->assertTrue( $frontend->filter_admin_bar( true ) );

		$_GET['iwieac_scan'] = 'forged';
		$frontend            = new \IWorksIE\AccessibilityChecker\Frontend\Frontend();
		$this->assertTrue( $frontend->filter_admin_bar( true ) );

		$_GET['iwieac_scan'] = wp_create_nonce( 'iwieac_scan' );
		$frontend            = new \IWorksIE\AccessibilityChecker\Frontend\Frontend();
		$this->assertFalse( $frontend->filter_admin_bar( true ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$_GET['iwieac_scan'] = wp_create_nonce( 'iwieac_scan' );
		$frontend            = new \IWorksIE\AccessibilityChecker\Frontend\Frontend();
		$this->assertTrue( $frontend->filter_admin_bar( true ) );
		unset( $_GET['iwieac_scan'] );
	}

	public function test_row_action_for_published_posts_only() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$published = get_post( self::factory()->post->create() );
		$draft     = get_post( self::factory()->post->create( array( 'post_status' => 'draft' ) ) );
		$plugin    = Plugin::instance();
		$this->assertArrayHasKey( 'iwieac_check', $plugin->row_action( array(), $published ) );
		$this->assertArrayNotHasKey( 'iwieac_check', $plugin->row_action( array(), $draft ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertArrayNotHasKey( 'iwieac_check', $plugin->row_action( array(), $published ) );
	}

	public function test_report_builds_with_results() {
		Schema::install();
		Store::clear_all();
		Store::save_result(
			array(
				'url'   => home_url( '/' ),
				'label' => 'Home',
			),
			array(
				'engine'     => '4.14.1',
				'passes'     => 10,
				'violations' => array(
					array(
						'id'          => 'link-name',
						'impact'      => 'serious',
						'help'        => 'Links must have discernible text',
						'description' => '',
						'help_url'    => '',
						'tags'        => array( 'wcag2a', 'wcag244', 'wcag412' ),
						'n'           => 1,
						'nodes'       => array(
							array(
								't' => 'a.icon',
								'h' => '<a class="icon" href="/"></a>',
								's' => '',
							),
						),
					),
				),
				'incomplete' => array(),
			)
		);
		$md = ReportBuilder::build();
		$this->assertStringContainsString( '| 2.4.4 Link Purpose (In Context) | A | 9.2.4.4 | Fails | 1 |', $md );
		$this->assertStringContainsString( '| 2.5.8 Target Size (Minimum) | AA | WCAG 2.2 only | No automated failures |', $md );
		$this->assertStringContainsString( 'Links have no text', $md );
		$this->assertStringContainsString( 'https://www.iworks.ie', $md );
	}

	public function test_uninstall_removes_tables_and_options() {
		global $wpdb;
		Schema::install();
		update_option( 'iwieac_settings', array( 'max_pages' => 5 ) );
		set_transient( 'iwieac_report_abc', 'x', 60 );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'iworksie-accessibility-checker/iworksie-accessibility-checker.php' );
		}
		// Uninstall drops tables, which ends the test transaction; run it last.
		include dirname( __DIR__, 2 ) . '/uninstall.php';
		$wpdb->suppress_errors( true );
		$wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}iwieac_pages" ); // phpcs:ignore
		$this->assertNotSame( '', $wpdb->last_error );
		$wpdb->suppress_errors( false );
		$this->assertFalse( get_option( 'iwieac_settings' ) );
		$this->assertFalse( get_option( Schema::VERSION_OPTION ) );
		$this->assertFalse( get_transient( 'iwieac_report_abc' ) );
		Schema::install();
	}
}
