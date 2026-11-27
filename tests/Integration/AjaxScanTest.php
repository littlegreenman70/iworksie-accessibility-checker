<?php
/**
 * AJAX scan endpoints, storage, dismissals and auth.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Integration;

use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Install\Schema;
use IWorksIE\AccessibilityChecker\Scan\Queue;
use WP_Ajax_UnitTestCase;

/**
 * Uses the WordPress AJAX test harness.
 */
final class AjaxScanTest extends WP_Ajax_UnitTestCase {

	/**
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Schema::install();
		Store::clear_all();
		Queue::clear();
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}iwieac_dismissals" ); // phpcs:ignore
	}

	/**
	 * Call an AJAX action and return the decoded JSON.
	 *
	 * @param string $action Action without prefix.
	 * @return array<string,mixed>
	 */
	private function ajax( $action ) {
		$_POST['nonce'] = isset( $_POST['nonce'] ) ? $_POST['nonce'] : wp_create_nonce( 'iwieac_admin' );
		// WordPress slashes request data (wp_magic_quotes); mimic it so wp_unslash() behaves as in production.
		$_POST = wp_slash( $_POST );
		try {
			$this->_handleAjax( 'iwieac_' . $action );
		} catch ( \WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( \WPAjaxDieStopException $e ) {
			unset( $e );
		}
		$json                 = json_decode( $this->_last_response, true );
		if ( getenv( "IWDBG" ) ) { fwrite( STDERR, $action . ": " . $this->_last_response . "\n" ); }
		$this->_last_response = '';
		$_POST                = array();
		return is_array( $json ) ? $json : array();
	}

	/**
	 * @return string
	 */
	private function payload() {
		return (string) wp_json_encode(
			array(
				'engine'     => '4.14.1',
				'passes'     => 30,
				'violations' => array(
					array(
						'id'      => 'image-alt',
						'impact'  => 'critical',
						'help'    => 'Images must have alternative text',
						'helpUrl' => 'https://dequeuniversity.com/rules/axe/4.14/image-alt',
						'tags'    => array( 'wcag2a', 'wcag111' ),
						'n'       => 2,
						'nodes'   => array(
							array(
								't' => 'img.logo',
								'h' => '<img class="logo" src="a.png">',
								's' => 'Fix any of the following',
							),
							array(
								't' => 'img.hero',
								'h' => '<img class="hero" src="b.png">',
								's' => 'Fix any of the following',
							),
						),
					),
				),
				'incomplete' => array(
					array(
						'id'     => 'color-contrast',
						'impact' => 'serious',
						'tags'   => array( 'wcag2aa', 'wcag143' ),
						'nodes'  => array( array( 't' => 'h1' ) ),
					),
				),
			)
		);
	}

	public function test_rejects_bad_nonce_and_subscriber() {
		$this->_setRole( 'administrator' );
		$_POST['nonce'] = 'bad';
		$json           = $this->ajax( 'queue_status' );
		$this->assertFalse( $json['success'] );

		$this->_setRole( 'subscriber' );
		$_POST['url']     = home_url( '/' );
		$_POST['payload'] = $this->payload();
		$json             = $this->ajax( 'save_result' );
		$this->assertFalse( $json['success'] );
		$this->assertSame( 0, Store::totals()['pages'] );
	}

	public function test_save_result_stores_counts_and_rejects_other_sites() {
		$this->_setRole( 'administrator' );
		$_POST['url']     = 'https://evil.example/';
		$_POST['payload'] = $this->payload();
		$json             = $this->ajax( 'save_result' );
		$this->assertFalse( $json['success'] );

		$_POST['url']         = home_url( '/sample-page/?iwieac_scan=abc#x' );
		$_POST['label']       = 'Sample <b>page</b>';
		$_POST['object_type'] = 'page';
		$_POST['object_id']   = '2';
		$_POST['index']       = '-1';
		$_POST['payload']     = $this->payload();
		$json                 = $this->ajax( 'save_result' );
		$this->assertTrue( $json['success'] );
		$this->assertSame( 2, $json['data']['problems'] );
		$this->assertSame( 1, $json['data']['review'] );

		$page = Store::get_page( $json['data']['pageId'] );
		$this->assertSame( home_url( '/sample-page/' ), $page['url'] );
		$this->assertSame( 'Sample page', $page['label'] );

		$totals = Store::totals();
		$this->assertSame( 1, $totals['pages'] );
		$this->assertSame( 2, $totals['problem_nodes'] );
		$this->assertSame( 1, $totals['problem_rules'] );
		$this->assertSame( 97, $totals['score'] );
		$this->assertArrayHasKey( 'image-alt', Store::rules() );
	}

	public function test_error_result_and_dismiss_restore_cycle() {
		$this->_setRole( 'administrator' );

		$_POST['url']   = home_url( '/broken/' );
		$_POST['error'] = 'frame_blocked';
		$json           = $this->ajax( 'save_result' );
		$this->assertSame( 'error', $json['data']['status'] );
		$this->assertStringContainsString( 'X-Frame-Options', $json['data']['error'] );

		$_POST['url']     = home_url( '/' );
		$_POST['payload'] = $this->payload();
		$page_id          = $this->ajax( 'save_result' )['data']['pageId'];

		$_POST['rule_id'] = 'image-alt';
		$_POST['target']  = 'img.logo';
		$_POST['page_id'] = (string) $page_id;
		$_POST['scope']   = 'page';
		$_POST['reason']  = 'nope';
		$this->assertFalse( $this->ajax( 'dismiss' )['success'] );

		$_POST['rule_id'] = 'image-alt';
		$_POST['target']  = 'img.logo';
		$_POST['page_id'] = (string) $page_id;
		$_POST['scope']   = 'all';
		$_POST['reason']  = 'confirmed';
		$_POST['note']    = 'Logo has visible text next to it';
		$this->assertTrue( $this->ajax( 'dismiss' )['success'] );
		$this->assertSame( 1, (int) Store::get_page( $page_id )['problem_nodes'] );

		// A new scan of another page applies the site-wide dismissal immediately.
		$_POST['url']     = home_url( '/other/' );
		$_POST['payload'] = $this->payload();
		$other            = $this->ajax( 'save_result' )['data'];
		$this->assertSame( 1, $other['problems'] );

		global $wpdb;
		$id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}iwieac_dismissals LIMIT 1" ); // phpcs:ignore
		$_POST['id'] = (string) $id;
		$this->assertTrue( $this->ajax( 'restore' )['success'] );
		$this->assertSame( 2, (int) Store::get_page( $page_id )['problem_nodes'] );
		$this->assertSame( 2, (int) Store::get_page( $other['pageId'] )['problem_nodes'] );

		// Whole-rule dismissal from the Issues tab.
		$_POST['rule_id'] = 'image-alt';
		$_POST['target']  = '*';
		$_POST['page_id'] = '0';
		$_POST['scope']   = 'all';
		$_POST['reason']  = 'not_relevant';
		$this->ajax( 'dismiss' );
		$this->assertSame( 0, Store::totals()['problem_nodes'] );
		$this->assertSame( array(), array_values( array_filter( Store::aggregate_rules(), static function ( $r ) { return 'violation' === $r['kind']; } ) ) );
	}

	public function test_build_queue_and_resume_position() {
		$this->_setRole( 'administrator' );
		$ids = self::factory()->post->create_many( 3 );
		self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::factory()->post->create( array( 'post_password' => 'secret' ) );

		$_POST['sources']       = array( 'home', 'post' );
		$_POST['max_pages']     = '10';
		$_POST['best_practice'] = '0';
		$json                   = $this->ajax( 'build_queue' );
		$this->assertTrue( $json['success'] );
		$this->assertSame( 4, $json['data']['total'] );
		$this->assertSame( home_url( '/' ), $json['data']['items'][0]['url'] );
		$this->assertFalse( $json['data']['bestPractice'] );
		$urls = wp_list_pluck( $json['data']['items'], 'url' );
		foreach ( $ids as $id ) {
			$this->assertContains( get_permalink( $id ), $urls );
		}

		$_POST['url']     = $json['data']['items'][0]['url'];
		$_POST['index']   = '0';
		$_POST['payload'] = $this->payload();
		$this->ajax( 'save_result' );
		$status = $this->ajax( 'queue_status' );
		$this->assertSame( 1, $status['data']['position'] );

		$this->assertTrue( $this->ajax( 'cancel_queue' )['success'] );
		$this->assertNull( Queue::get() );

		$_POST['sources']   = array( 'home', 'templates', 'post' );
		$_POST['max_pages'] = '2';
		$this->assertSame( 2, $this->ajax( 'build_queue' )['data']['total'] );
	}

	public function test_check_url() {
		$this->_setRole( 'administrator' );
		$id           = self::factory()->post->create( array( 'post_title' => 'Hello a11y' ) );
		$_POST['url'] = get_permalink( $id );
		$json         = $this->ajax( 'check_url' );
		$this->assertSame( 'Hello a11y', $json['data']['label'] );
		$this->assertSame( $id, $json['data']['id'] );

		$_POST['url'] = 'https://elsewhere.example/page/';
		$this->assertFalse( $this->ajax( 'check_url' )['success'] );
	}
}
