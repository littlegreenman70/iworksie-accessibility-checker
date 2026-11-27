<?php
/**
 * AJAX endpoints for scanning and dismissals.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Admin;

use IWorksIE\AccessibilityChecker\Data\Dismissals;
use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Plugin;
use IWorksIE\AccessibilityChecker\Scan\PayloadSanitizer;
use IWorksIE\AccessibilityChecker\Scan\Queue;
use IWorksIE\AccessibilityChecker\Scan\ScanErrors;
use IWorksIE\AccessibilityChecker\Scan\UrlCollector;
use IWorksIE\AccessibilityChecker\Support\Settings;
use IWorksIE\AccessibilityChecker\Support\Url;

/**
 * Every handler checks the nonce and capability first.
 */
final class Ajax {

	/**
	 * Nonce action shared by all admin AJAX calls.
	 */
	const NONCE = 'iwieac_admin';

	/**
	 * Register handlers.
	 *
	 * @return void
	 */
	public function hooks() {
		$actions = array(
			'build_queue',
			'queue_status',
			'cancel_queue',
			'check_url',
			'save_result',
			'dismiss',
			'restore',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_iwieac_' . $action, array( $this, $action ) );
		}
	}

	/**
	 * Verify nonce and capability or stop.
	 *
	 * @return void
	 */
	private function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page and try again.', 'iworksie-accessibility-checker' ) ), 403 );
		}
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'iworksie-accessibility-checker' ) ), 403 );
		}
	}

	/**
	 * Read a POST string.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_string( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verifies the nonce before any read.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	/**
	 * Read a POST URL without sanitize_text_field (which strips %-encoded characters).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_url( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- guard() verifies the nonce; esc_url_raw() sanitises.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? esc_url_raw( trim( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Build a new full-site queue.
	 *
	 * @return void
	 */
	public function build_queue() {
		$this->guard();
		$sources = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in guard(); each value sanitised below.
		$raw = isset( $_POST['sources'] ) && is_array( $_POST['sources'] ) ? wp_unslash( $_POST['sources'] ) : array();
		foreach ( (array) $raw as $source ) {
			if ( is_string( $source ) ) {
				$sources[] = sanitize_key( $source );
			}
		}
		$sources       = array_values( array_unique( $sources ) );
		$max           = absint( self::post_string( 'max_pages' ) );
		$max           = $max > 0 ? $max : UrlCollector::DEFAULT_MAX;
		$best_practice = '1' === self::post_string( 'best_practice' );

		Settings::save( $best_practice, $max, $sources );

		$items = UrlCollector::collect( $sources, $max );
		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'Nothing to scan. Choose at least one kind of content.', 'iworksie-accessibility-checker' ) ) );
		}
		wp_send_json_success( Queue::view( Queue::create( $items, $best_practice ) ) );
	}

	/**
	 * Current queue (for resume).
	 *
	 * @return void
	 */
	public function queue_status() {
		$this->guard();
		wp_send_json_success( Queue::view( Queue::get() ) );
	}

	/**
	 * Cancel the queue (results already saved are kept).
	 *
	 * @return void
	 */
	public function cancel_queue() {
		$this->guard();
		Queue::clear();
		wp_send_json_success();
	}

	/**
	 * Validate and describe one URL.
	 *
	 * @return void
	 */
	public function check_url() {
		$this->guard();
		$url = self::post_url( 'url' );
		if ( '' === $url || ! Url::is_same_site( $url ) ) {
			wp_send_json_error(
				array(
					/* translators: %s: site address */
					'message' => sprintf( __( 'Enter the address of a page on this site (%s).', 'iworksie-accessibility-checker' ), home_url( '/' ) ),
				)
			);
		}
		wp_send_json_success( UrlCollector::describe( $url ) );
	}

	/**
	 * Save one page's result (or error).
	 *
	 * @return void
	 */
	public function save_result() {
		$this->guard();
		$url = Url::normalize( self::post_url( 'url' ) );
		if ( '' === $url || ! Url::is_same_site( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid page address.', 'iworksie-accessibility-checker' ) ) );
		}
		$page  = array(
			'url'         => $url,
			'label'       => self::post_string( 'label' ),
			'object_type' => sanitize_key( self::post_string( 'object_type' ) ),
			'object_id'   => absint( self::post_string( 'object_id' ) ),
		);
		$index = (int) self::post_string( 'index' );
		$error = sanitize_key( self::post_string( 'error' ) );

		if ( '' !== $error ) {
			$page_id = Store::save_error( $page, ScanErrors::message( $error ) );
		} else {
			// Raw JSON: validated field by field in PayloadSanitizer, never output unescaped.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$json    = isset( $_POST['payload'] ) && is_string( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '';
			$payload = PayloadSanitizer::sanitize( $json );
			if ( null === $payload ) {
				$page_id = Store::save_error( $page, ScanErrors::message( strlen( $json ) > PayloadSanitizer::MAX_BYTES ? 'too_large' : 'axe_error' ) );
			} else {
				$page_id = Store::save_result( $page, $payload );
			}
		}

		if ( $index >= 0 ) {
			Queue::advance( $index, $url );
		}

		$row = $page_id ? Store::get_page( $page_id ) : null;
		if ( null === $row ) {
			wp_send_json_error( array( 'message' => __( 'The result could not be saved.', 'iworksie-accessibility-checker' ) ) );
		}
		wp_send_json_success(
			array(
				'pageId'    => (int) $row['id'],
				'status'    => (string) $row['status'],
				'problems'  => (int) $row['problem_nodes'],
				'rules'     => (int) $row['problems'],
				'review'    => (int) $row['review'],
				'error'     => (string) $row['error_message'],
				'detailUrl' => Screens::page_url( (int) $row['id'] ),
			)
		);
	}

	/**
	 * Dismiss an element (or a whole rule).
	 *
	 * @return void
	 */
	public function dismiss() {
		$this->guard();
		$rule_id = self::post_string( 'rule_id' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in guard(); clean_text() sanitises to match stored selectors.
		$target  = isset( $_POST['target'] ) && is_string( $_POST['target'] ) ? PayloadSanitizer::clean_text( wp_unslash( $_POST['target'] ), 512 ) : '';
		$scope   = self::post_string( 'scope' );
		$reason  = sanitize_key( self::post_string( 'reason' ) );
		$note    = self::post_string( 'note' );
		$page_id = absint( self::post_string( 'page_id' ) );

		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,99}$/', $rule_id ) || '' === $target || strlen( $target ) > 512 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'iworksie-accessibility-checker' ) ) );
		}
		if ( 'all' === $scope ) {
			$page_id = 0;
		} elseif ( 0 === $page_id || null === Store::get_page( $page_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'iworksie-accessibility-checker' ) ) );
		}
		$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 490 ) : substr( $note, 0, 490 );

		if ( ! Dismissals::add( $rule_id, $target, $page_id, $reason, $note ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a reason.', 'iworksie-accessibility-checker' ) ) );
		}
		Store::reapply_dismissals( $rule_id, $page_id );
		wp_send_json_success( array( 'message' => __( 'Dismissed.', 'iworksie-accessibility-checker' ) ) );
	}

	/**
	 * Restore a dismissed item.
	 *
	 * @return void
	 */
	public function restore() {
		$this->guard();
		$row = Dismissals::remove( absint( self::post_string( 'id' ) ) );
		if ( null === $row ) {
			wp_send_json_error( array( 'message' => __( 'That item was already restored.', 'iworksie-accessibility-checker' ) ) );
		}
		Store::reapply_dismissals( (string) $row['rule_id'], (int) $row['page_id'] );
		wp_send_json_success( array( 'message' => __( 'Restored.', 'iworksie-accessibility-checker' ) ) );
	}
}
