<?php
/**
 * Front-end pieces: scan mode, the "show on page" highlighter, and the admin bar link.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Frontend;

use IWorksIE\AccessibilityChecker\Admin\Screens;
use IWorksIE\AccessibilityChecker\Data\Dismissals;
use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Plugin;
use IWorksIE\AccessibilityChecker\Support\RuleGuide;
use IWorksIE\AccessibilityChecker\Support\Url;
use IWorksIE\AccessibilityChecker\Support\Wcag;

/**
 * Nothing here runs for visitors: every feature needs a logged-in admin and a nonce.
 */
final class Frontend {

	/**
	 * Cached scan-mode decision.
	 *
	 * @var bool|null
	 */
	private $scan_mode = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'show_admin_bar', array( $this, 'filter_admin_bar' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_highlighter' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 100 );
	}

	/**
	 * Whether this request is a page being scanned by the checker (valid nonce, allowed user).
	 *
	 * @return bool
	 */
	public function is_scan_mode() {
		if ( null !== $this->scan_mode ) {
			return $this->scan_mode;
		}
		$this->scan_mode = false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the value IS the nonce.
		if ( isset( $_GET['iwieac_scan'] ) && is_string( $_GET['iwieac_scan'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$nonce           = sanitize_text_field( wp_unslash( $_GET['iwieac_scan'] ) );
			$this->scan_mode = (bool) wp_verify_nonce( $nonce, 'iwieac_scan' ) && current_user_can( Plugin::capability() );
		}
		return $this->scan_mode;
	}

	/**
	 * Hide the admin bar on pages being scanned so it is not reported as part of the site.
	 *
	 * @param bool $show Current value.
	 * @return bool
	 */
	public function filter_admin_bar( $show ) {
		return $this->is_scan_mode() ? false : $show;
	}

	/**
	 * Load the highlighter when an admin clicks "Show on page".
	 *
	 * @return void
	 */
	public function maybe_enqueue_highlighter() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		if ( ! isset( $_GET['iwieac_highlight'], $_GET['_iwieac'] ) || ! is_string( $_GET['_iwieac'] ) ) {
			return;
		}
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_iwieac'] ) );
		$page_id = absint( $_GET['iwieac_highlight'] );
		$rule    = isset( $_GET['iwieac_rule'] ) && is_string( $_GET['iwieac_rule'] ) ? sanitize_key( wp_unslash( $_GET['iwieac_rule'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, 'iwieac_highlight' ) || ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$page = Store::get_page( $page_id );
		if ( null === $page ) {
			return;
		}

		$map   = Dismissals::map_for_page( $page_id );
		$items = array();
		foreach ( Store::page_issues( $page_id ) as $issue ) {
			if ( (int) $issue['nodes_active'] < 1 || ( '' !== $rule && $issue['rule_id'] !== $rule ) ) {
				continue;
			}
			$targets = array();
			foreach ( $issue['nodes'] as $node ) {
				if ( null === Dismissals::match( $map, $issue['rule_id'], (string) $node['t'] ) ) {
					$targets[] = (string) $node['t'];
				}
			}
			if ( empty( $targets ) ) {
				continue;
			}
			$guide   = RuleGuide::get( $issue['rule_id'], Store::rule( $issue['rule_id'] ) );
			$items[] = array(
				'rule'    => $issue['rule_id'],
				'title'   => $guide['title'],
				'kind'    => $issue['kind'],
				'impact'  => Wcag::impact_label( (string) $issue['impact'] ),
				'targets' => $targets,
			);
		}

		wp_enqueue_style( 'iwieac-highlight', IWIEAC_URL . 'assets/css/highlight.css', array(), IWIEAC_VERSION );
		wp_enqueue_script( 'iwieac-highlight', IWIEAC_URL . 'assets/js/highlight.js', array(), IWIEAC_VERSION, true );
		wp_localize_script(
			'iwieac-highlight',
			'iwieacHighlight',
			array(
				'items'   => $items,
				'backUrl' => Screens::page_url( $page_id ),
				'strings' => array(
					'panelTitle'  => __( 'Accessibility issues on this page', 'iworksie-accessibility-checker' ),
					'problem'     => __( 'Problem', 'iworksie-accessibility-checker' ),
					'review'      => __( 'Needs review', 'iworksie-accessibility-checker' ),
					'prev'        => __( 'Previous', 'iworksie-accessibility-checker' ),
					'next'        => __( 'Next', 'iworksie-accessibility-checker' ),
					'close'       => __( 'Hide highlights', 'iworksie-accessibility-checker' ),
					'back'        => __( 'Back to results', 'iworksie-accessibility-checker' ),
					/* translators: 1: current element number, 2: total elements */
					'position'    => __( 'Element %1$d of %2$d', 'iworksie-accessibility-checker' ),
					'notFound'    => __( 'This element was not found on the page now. It may have been fixed, or it only appears after an interaction (for example inside a menu or pop-up).', 'iworksie-accessibility-checker' ),
					'none'        => __( 'No open issues to show on this page.', 'iworksie-accessibility-checker' ),
					'changedNote' => __( 'Highlights come from the last scan. Rescan after making changes.', 'iworksie-accessibility-checker' ),
				),
			)
		);
	}

	/**
	 * "Check accessibility" link in the front-end admin bar.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public function admin_bar_link( $bar ) {
		/**
		 * Filters whether the front-end admin bar shows "Check accessibility".
		 *
		 * @param bool $show Default true.
		 */
		if ( is_admin() || ! current_user_can( Plugin::capability() ) || ! apply_filters( 'iwieac_show_admin_bar_link', true ) ) {
			return;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url  = Url::normalize( set_url_scheme( 'http://' . $host . $uri ) );
		if ( ! Url::is_same_site( $url ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'iwieac-check',
				'title' => esc_html__( 'Check accessibility', 'iworksie-accessibility-checker' ),
				'href'  => Screens::scan_url( $url ),
			)
		);
	}
}
