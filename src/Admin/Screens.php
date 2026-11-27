<?php
/**
 * Admin screens: Overview, Scan, Pages (and page detail), Issues, Report, Dismissed.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Admin;

use IWorksIE\AccessibilityChecker\Data\Dismissals;
use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Plugin;
use IWorksIE\AccessibilityChecker\Scan\Queue;
use IWorksIE\AccessibilityChecker\Scan\UrlCollector;
use IWorksIE\AccessibilityChecker\Support\ReportBuilder;
use IWorksIE\AccessibilityChecker\Support\RuleGuide;
use IWorksIE\AccessibilityChecker\Support\Settings;
use IWorksIE\AccessibilityChecker\Support\Url;
use IWorksIE\AccessibilityChecker\Support\Wcag;
use IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\Core;
use IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\Strings;
use IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\UI;

/**
 * Renders all plugin screens. Every value is escaped at output.
 */
final class Screens {

	/**
	 * Pages per screen in the Pages table.
	 */
	const PER_PAGE = 50;

	/**
	 * @var Core
	 */
	private $core;

	/**
	 * @param Core $core Core.
	 */
	public function __construct( Core $core ) {
		$this->core = $core;
	}

	/**
	 * URL of a tab.
	 *
	 * @param string               $tab  Tab key.
	 * @param array<string,scalar> $args Extra query args.
	 * @return string
	 */
	public static function tab_url( $tab, array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => Plugin::PAGE_SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * URL of a page's detail view.
	 *
	 * @param int $page_id Page id.
	 * @return string
	 */
	public static function page_url( $page_id ) {
		return self::tab_url( 'pages', array( 'view' => (int) $page_id ) );
	}

	/**
	 * Scan tab URL that checks one page straight away (nonce-protected).
	 *
	 * @param string $url Front-end URL.
	 * @return string
	 */
	public static function scan_url( $url ) {
		return self::tab_url(
			'scan',
			array(
				'scan_url' => rawurlencode( $url ),
				'_iwieac'  => wp_create_nonce( 'iwieac_scan_url' ),
			)
		);
	}

	/**
	 * Front-end URL that highlights a page's issues.
	 *
	 * @param array<string,mixed> $page    Page row.
	 * @param string              $rule_id Optional rule filter.
	 * @return string
	 */
	public static function highlight_url( array $page, $rule_id = '' ) {
		$args = array(
			'iwieac_highlight' => (int) $page['id'],
			'_iwieac'          => wp_create_nonce( 'iwieac_highlight' ),
		);
		if ( '' !== $rule_id ) {
			$args['iwieac_rule'] = $rule_id;
		}
		return add_query_arg( $args, (string) $page['url'] );
	}

	/**
	 * Menu callback.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'iworksie-accessibility-checker' ) );
		}

		$dismissed = Dismissals::count();
		$tabs      = array(
			'overview'  => __( 'Overview', 'iworksie-accessibility-checker' ),
			'scan'      => __( 'Scan', 'iworksie-accessibility-checker' ),
			'pages'     => __( 'Pages', 'iworksie-accessibility-checker' ),
			'issues'    => __( 'Issues', 'iworksie-accessibility-checker' ),
			'report'    => __( 'Report', 'iworksie-accessibility-checker' ),
			/* translators: %d: number of dismissed items */
			'dismissed' => $dismissed > 0 ? sprintf( __( 'Dismissed (%d)', 'iworksie-accessibility-checker' ), $dismissed ) : __( 'Dismissed', 'iworksie-accessibility-checker' ),
		);

		$this->core->page()->render(
			array(
				'title'   => (string) $this->core->config( 'name' ),
				'version' => (string) $this->core->config( 'version' ),
				'tagline' => (string) $this->core->config( 'tagline' ),
				'tabs'    => $tabs,
			),
			function ( $active ) {
				switch ( $active ) {
					case 'scan':
						$this->render_scan();
						break;
					case 'pages':
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view parameter.
						$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
						if ( $view > 0 ) {
							$this->render_page_detail( $view );
						} else {
							$this->render_pages();
						}
						break;
					case 'issues':
						$this->render_issues();
						break;
					case 'report':
						$this->render_report();
						break;
					case 'dismissed':
						$this->render_dismissed();
						break;
					default:
						$this->render_overview();
						break;
				}
			}
		);
	}

	/**
	 * Overview tab.
	 *
	 * @return void
	 */
	private function render_overview() {
		$totals = Store::totals();

		if ( 0 === $totals['pages'] ) {
			echo '<div class="iwt-empty">';
			echo '<div class="iwt-empty__icon" aria-hidden="true"><span class="dashicons dashicons-universal-access-alt"></span></div>';
			echo '<h2 class="iwt-empty__title">' . esc_html__( 'Check your whole site for accessibility problems', 'iworksie-accessibility-checker' ) . '</h2>';
			echo '<p class="iwt-empty__body">' . esc_html__( 'The scan opens each page in your browser and tests it with axe-core, the engine used by accessibility professionals. Every post type, every page, free. Nothing is sent to any third party.', 'iworksie-accessibility-checker' ) . '</p>';
			echo '<p class="iwt-empty__action"><a class="button button-primary button-hero" href="' . esc_url( self::tab_url( 'scan' ) ) . '">' . esc_html__( 'Start a scan', 'iworksie-accessibility-checker' ) . '</a></p>';
			echo '</div>';
			$this->render_about();
			return;
		}

		if ( Queue::has_unfinished() ) {
			echo UI::notice( 'warning', __( 'A full-site scan was not finished. Open the Scan tab to resume it.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- UI::notice escapes.
		}

		echo '<div class="iwieac-overview">';
		echo '<div class="iwieac-score iwieac-score--' . esc_attr( self::score_band( $totals['score'] ) ) . '">';
		echo '<span class="iwieac-score__value">' . esc_html( (string) $totals['score'] ) . '</span>';
		echo '<span class="iwieac-score__label">' . esc_html__( 'Automated score', 'iworksie-accessibility-checker' ) . '</span>';
		echo '</div>';
		echo '<div class="iwt-stat-tiles iwieac-tiles">';
		self::tile( __( 'Pages checked', 'iworksie-accessibility-checker' ), number_format_i18n( $totals['ok_pages'] ), self::tab_url( 'pages' ) );
		/* translators: %d: number of issue types */
		self::tile( __( 'Problems', 'iworksie-accessibility-checker' ), number_format_i18n( $totals['problem_nodes'] ), self::tab_url( 'issues' ), sprintf( _n( '%d type of issue', '%d types of issue', $totals['problem_rules'], 'iworksie-accessibility-checker' ), $totals['problem_rules'] ) );
		/* translators: %d: number of issue types */
		self::tile( __( 'Needs review', 'iworksie-accessibility-checker' ), number_format_i18n( $totals['review_nodes'] ), self::tab_url( 'issues' ) . '#iwieac-review', sprintf( _n( '%d type of issue', '%d types of issue', $totals['review_rules'], 'iworksie-accessibility-checker' ), $totals['review_rules'] ) );
		echo '</div></div>';

		echo '<p class="iwieac-meta">';
		/* translators: 1: pages with no problems, 2: pages checked */
		echo esc_html( sprintf( __( '%1$s of %2$s pages have no automated problems.', 'iworksie-accessibility-checker' ), number_format_i18n( $totals['clean_pages'] ), number_format_i18n( $totals['ok_pages'] ) ) ) . ' ';
		if ( $totals['last_scanned'] > 0 ) {
			/* translators: %s: time ago, e.g. "5 minutes" */
			echo esc_html( sprintf( __( 'Last page checked %s ago.', 'iworksie-accessibility-checker' ), human_time_diff( $totals['last_scanned'], time() ) ) ) . ' ';
		}
		if ( $totals['error_pages'] > 0 ) {
			/* translators: %s: number of pages */
			echo '<a href="' . esc_url( self::tab_url( 'pages', array( 'status' => 'error' ) ) ) . '">' . esc_html( sprintf( _n( '%s page could not be checked.', '%s pages could not be checked.', $totals['error_pages'], 'iworksie-accessibility-checker' ), number_format_i18n( $totals['error_pages'] ) ) ) . '</a>';
		}
		echo '</p>';

		$top = array_slice(
			array_values(
				array_filter(
					Store::aggregate_rules(),
					static function ( $row ) {
						return 'violation' === $row['kind'];
					}
				)
			),
			0,
			8
		);

		if ( empty( $top ) ) {
			echo UI::notice( 'success', __( 'No automated problems found on the pages checked. Remember that automated tests find only part of the picture; see the Report tab for the manual checks to do next.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<h2>' . esc_html__( 'Top problems to fix', 'iworksie-accessibility-checker' ) . '</h2>';
			self::table_open( __( 'Top problems to fix', 'iworksie-accessibility-checker' ) );
			echo '<table class="widefat striped iwt-table iwieac-table"><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Issue', 'iworksie-accessibility-checker' ) . '</th>';
			echo '<th scope="col">' . esc_html__( 'Impact', 'iworksie-accessibility-checker' ) . '</th>';
			echo '<th scope="col">WCAG</th>';
			echo '<th scope="col" class="num">' . esc_html__( 'Pages', 'iworksie-accessibility-checker' ) . '</th>';
			echo '<th scope="col" class="num">' . esc_html__( 'Elements', 'iworksie-accessibility-checker' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $top as $row ) {
				$meta  = Store::rule( $row['rule_id'] );
				$guide = RuleGuide::get( $row['rule_id'], $meta );
				echo '<tr>';
				echo '<td><a href="' . esc_url( self::tab_url( 'issues' ) . '#iwieac-rule-' . $row['rule_id'] ) . '">' . esc_html( $guide['title'] ) . '</a></td>';
				echo '<td>' . self::impact_badge( $row['impact'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				echo '<td>' . esc_html( self::criteria_text( $meta ) ) . '</td>';
				echo '<td class="num">' . esc_html( number_format_i18n( $row['pages'] ) ) . '</td>';
				echo '<td class="num">' . esc_html( number_format_i18n( $row['nodes'] ) ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '</div>';
		}

		echo '<p class="iwt-toolbar"><a class="button button-primary" href="' . esc_url( self::tab_url( 'scan' ) ) . '">' . esc_html__( 'Scan again', 'iworksie-accessibility-checker' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( self::tab_url( 'report' ) ) . '">' . esc_html__( 'Get the report', 'iworksie-accessibility-checker' ) . '</a></p>';
		$this->render_about();
	}

	/**
	 * Short "how it works / no overlay" box.
	 *
	 * @return void
	 */
	private function render_about() {
		echo '<div class="iwieac-about">';
		echo '<h2>' . esc_html__( 'How this checker works', 'iworksie-accessibility-checker' ) . '</h2>';
		echo '<ul>';
		echo '<li>' . esc_html__( 'It tests the real rendered pages, including your theme, plugins and JavaScript, against WCAG 2.2 Level AA.', 'iworksie-accessibility-checker' ) . '</li>';
		echo '<li>' . esc_html__( 'Problems are definite failures. "Needs review" items could not be decided automatically and need a person to look.', 'iworksie-accessibility-checker' ) . '</li>';
		echo '<li>' . esc_html__( 'It never changes your site and never adds an "accessibility overlay" widget. Overlays do not make a site compliant; fixing the issues does.', 'iworksie-accessibility-checker' ) . '</li>';
		echo '<li>' . esc_html__( 'Automated tests find roughly a third of accessibility problems. Use the report\'s manual checklist for the rest.', 'iworksie-accessibility-checker' ) . '</li>';
		echo '</ul></div>';
	}

	/**
	 * Scan tab.
	 *
	 * @return void
	 */
	private function render_scan() {
		$settings = Settings::get();
		$types    = UrlCollector::post_types();
		$selected = null === $settings['sources'] ? array_merge( array( 'home', 'templates' ), array_keys( $types ) ) : $settings['sources'];
		$queue    = Queue::get();
		$resume   = null !== $queue && (int) $queue['position'] < count( $queue['items'] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after redirect.
		if ( isset( $_GET['iwieac_cleared'] ) ) {
			echo UI::notice( 'success', __( 'All scan results were deleted.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '<p class="iwieac-intro">' . esc_html__( 'Scanning happens in this browser tab: each page is opened out of sight and tested with axe-core. Keep the tab open until it finishes. If you leave, you can resume later.', 'iworksie-accessibility-checker' ) . '</p>';

		echo '<div class="iwieac-scan-grid">';

		// Full-site scan form.
		echo '<form class="iwieac-panel" id="iwieac-full-form" data-iwieac-full-form>';
		echo '<h2>' . esc_html__( 'Full-site scan', 'iworksie-accessibility-checker' ) . '</h2>';
		echo '<fieldset><legend>' . esc_html__( 'What to check', 'iworksie-accessibility-checker' ) . '</legend>';
		self::checkbox( 'home', __( 'Home page (and blog page)', 'iworksie-accessibility-checker' ), in_array( 'home', $selected, true ) );
		self::checkbox( 'templates', __( 'One of each template: archives, categories, author, search results, 404 page', 'iworksie-accessibility-checker' ), in_array( 'templates', $selected, true ) );
		foreach ( $types as $slug => $type ) {
			/* translators: 1: post type name, 2: number published */
			self::checkbox( $slug, sprintf( __( '%1$s (%2$s published)', 'iworksie-accessibility-checker' ), $type['label'], number_format_i18n( $type['count'] ) ), in_array( $slug, $selected, true ) );
		}
		echo '</fieldset>';
		echo '<p><label for="iwieac-max-pages">' . esc_html__( 'Maximum pages', 'iworksie-accessibility-checker' ) . '</label> ';
		echo '<input type="number" id="iwieac-max-pages" name="max_pages" min="1" max="' . esc_attr( (string) UrlCollector::HARD_MAX ) . '" value="' . esc_attr( (string) $settings['max_pages'] ) . '" class="small-text" aria-describedby="iwieac-max-help"> ';
		echo '<span id="iwieac-max-help" class="description">' . esc_html__( 'Newest content first. Each page takes a few seconds.', 'iworksie-accessibility-checker' ) . '</span></p>';
		echo '<p><label><input type="checkbox" name="best_practice" value="1"' . checked( $settings['best_practice'], true, false ) . '> ' . esc_html__( 'Include best-practice checks (useful, but not WCAG requirements)', 'iworksie-accessibility-checker' ) . '</label></p>';
		echo '<p class="iwt-toolbar">';
		echo '<button type="submit" class="button button-primary" data-iwieac-start>' . esc_html__( 'Start full scan', 'iworksie-accessibility-checker' ) . '</button> ';
		echo '<button type="button" class="button" data-iwieac-resume' . ( $resume ? '' : ' hidden' ) . '>';
		if ( $resume ) {
			/* translators: 1: pages done, 2: total pages */
			echo esc_html( sprintf( __( 'Resume (%1$d of %2$d done)', 'iworksie-accessibility-checker' ), (int) $queue['position'], count( $queue['items'] ) ) );
		} else {
			echo esc_html( Strings::get( 'resume' ) );
		}
		echo '</button> ';
		echo '<button type="button" class="button" data-iwieac-stop hidden>' . esc_html__( 'Stop', 'iworksie-accessibility-checker' ) . '</button>';
		echo '</p></form>';

		// Single page form.
		$auto = Plugin::auto_scan_request();
		echo '<form class="iwieac-panel" id="iwieac-one-form" data-iwieac-one-form>';
		echo '<h2>' . esc_html__( 'Check one page', 'iworksie-accessibility-checker' ) . '</h2>';
		echo '<p><label for="iwieac-one-url">' . esc_html__( 'Page address', 'iworksie-accessibility-checker' ) . '</label><br>';
		echo '<input type="url" id="iwieac-one-url" name="url" class="large-text" required placeholder="' . esc_attr( home_url( '/' ) ) . '" value="' . esc_attr( null !== $auto ? $auto['url'] : '' ) . '"></p>';
		echo '<p><button type="submit" class="button button-primary" data-iwieac-one>' . esc_html__( 'Check page', 'iworksie-accessibility-checker' ) . '</button></p>';
		echo '<p class="description">' . esc_html__( 'Tip: every post and page also has a "Check accessibility" link in its row actions and in the admin bar when you view it.', 'iworksie-accessibility-checker' ) . '</p>';
		echo '</form>';

		echo '</div>';

		// Progress, live preview and results log.
		echo '<div class="iwieac-run" data-iwieac-run hidden>';
		echo '<div class="iwt-progress" data-iwieac-progress>';
		echo '<div class="iwt-progress__row"><div class="iwt-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="' . esc_attr__( 'Scan progress', 'iworksie-accessibility-checker' ) . '"><div class="iwt-progress__bar" style="width:0%"></div></div>';
		echo '<span class="iwt-progress__pct" aria-hidden="true">0%</span></div>';
		echo '<p class="iwt-progress__message" data-iwieac-message></p>';
		echo '</div>';
		echo '<p class="screen-reader-text" role="status" aria-live="polite" data-iwieac-announce></p>';
		echo '<div class="iwieac-run__body">';
		echo '<div class="iwieac-stage" data-iwieac-stage aria-hidden="true"></div>';
		echo '<ol class="iwieac-log" data-iwieac-log aria-label="' . esc_attr__( 'Pages checked in this run', 'iworksie-accessibility-checker' ) . '"></ol>';
		echo '</div></div>';

		// Clear results.
		if ( Store::totals()['pages'] > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="iwieac-clear" data-iwieac-clear>';
			echo '<input type="hidden" name="action" value="iwieac_clear_results">';
			wp_nonce_field( 'iwieac_clear_results' );
			echo '<button type="submit" class="button-link button-link-delete">' . esc_html__( 'Delete all scan results', 'iworksie-accessibility-checker' ) . '</button>';
			echo '</form>';
		}
	}

	/**
	 * Pages tab (list).
	 *
	 * @return void
	 */
	private function render_pages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'problems';
		$order   = isset( $_GET['order'] ) && 'asc' === $_GET['order'] ? 'asc' : 'desc';
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$result = Store::list_pages(
			array(
				'orderby'  => $orderby,
				'order'    => $order,
				'status'   => $status,
				'paged'    => $paged,
				'per_page' => self::PER_PAGE,
			)
		);

		if ( 0 === $result['total'] && '' === $status ) {
			echo '<p>' . esc_html__( 'No pages checked yet.', 'iworksie-accessibility-checker' ) . ' <a href="' . esc_url( self::tab_url( 'scan' ) ) . '">' . esc_html__( 'Start a scan', 'iworksie-accessibility-checker' ) . '</a></p>';
			return;
		}

		$filters = array(
			''         => __( 'All', 'iworksie-accessibility-checker' ),
			'problems' => __( 'With problems', 'iworksie-accessibility-checker' ),
			'error'    => __( 'Could not be checked', 'iworksie-accessibility-checker' ),
		);
		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $filters as $key => $label ) {
			$url     = self::tab_url( 'pages', '' === $key ? array() : array( 'status' => $key ) );
			$current = $key === $status ? ' class="current" aria-current="page"' : '';
			$sep     = ( 'error' === $key ) ? '' : ' |';
			$links[] = '<li><a href="' . esc_url( $url ) . '"' . $current . '>' . esc_html( $label ) . '</a>' . $sep . '</li>';
		}
		echo implode( '', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		echo '</ul><br class="clear">';

		self::table_open( __( 'Pages checked', 'iworksie-accessibility-checker' ) );
		echo '<table class="widefat striped iwt-table iwieac-table iwieac-pages"><thead><tr>';
		self::sortable_th( 'label', __( 'Page', 'iworksie-accessibility-checker' ), $orderby, $order, $status );
		self::sortable_th( 'problems', __( 'Problems', 'iworksie-accessibility-checker' ), $orderby, $order, $status, 'num' );
		self::sortable_th( 'review', __( 'Needs review', 'iworksie-accessibility-checker' ), $orderby, $order, $status, 'num' );
		echo '<th scope="col" class="num">' . esc_html__( 'Score', 'iworksie-accessibility-checker' ) . '</th>';
		self::sortable_th( 'scanned', __( 'Checked', 'iworksie-accessibility-checker' ), $orderby, $order, $status );
		echo '</tr></thead><tbody>';
		if ( empty( $result['rows'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No pages match this filter.', 'iworksie-accessibility-checker' ) . '</td></tr>';
		}
		foreach ( $result['rows'] as $page ) {
			$score = Store::page_score( $page );
			echo '<tr>';
			echo '<td><a class="row-title" href="' . esc_url( self::page_url( (int) $page['id'] ) ) . '">' . esc_html( '' !== $page['label'] ? $page['label'] : Url::path( $page['url'] ) ) . '</a>';
			echo '<br><span class="iwieac-url">' . esc_html( Url::path( $page['url'] ) ) . '</span>';
			if ( 'error' === $page['status'] ) {
				echo '<br><span class="iwieac-error">' . esc_html( $page['error_message'] ) . '</span>';
			}
			echo '</td>';
			echo '<td class="num">' . ( 'ok' === $page['status'] ? esc_html( number_format_i18n( (int) $page['problem_nodes'] ) ) : '—' ) . '</td>';
			echo '<td class="num">' . ( 'ok' === $page['status'] ? esc_html( number_format_i18n( (int) $page['review'] ) ) : '—' ) . '</td>';
			echo '<td class="num">' . ( null === $score ? '—' : '<span class="iwieac-score-pill iwieac-score--' . esc_attr( self::score_band( $score ) ) . '">' . esc_html( (string) $score ) . '</span>' ) . '</td>';
			echo '<td>' . esc_html( self::ago( (int) $page['scanned_at'] ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		$pages = (int) ceil( $result['total'] / self::PER_PAGE );
		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg(
						'paged',
						'%#%',
						self::tab_url(
							'pages',
							array_filter(
								array(
									'orderby' => $orderby,
									'order'   => $order,
									'status'  => $status,
								)
							)
						)
					),
					'format'    => '',
					'current'   => $paged,
					'total'     => $pages,
					'prev_text' => __( '« Previous', 'iworksie-accessibility-checker' ),
					'next_text' => __( 'Next »', 'iworksie-accessibility-checker' ),
				)
			);
			if ( is_string( $links ) ) {
				echo '<nav class="iwieac-pagination" aria-label="' . esc_attr__( 'Pages list navigation', 'iworksie-accessibility-checker' ) . '">' . wp_kses_post( $links ) . '</nav>';
			}
		}
	}

	/**
	 * One page's issues.
	 *
	 * @param int $page_id Page id.
	 * @return void
	 */
	private function render_page_detail( $page_id ) {
		$page = Store::get_page( $page_id );
		echo '<p><a href="' . esc_url( self::tab_url( 'pages' ) ) . '">' . esc_html__( '← All pages', 'iworksie-accessibility-checker' ) . '</a></p>';
		if ( null === $page ) {
			echo UI::notice( 'error', __( 'That page is not in the results any more. It may have been cleared.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo '<div class="iwieac-page-head">';
		echo '<h2>' . esc_html( '' !== $page['label'] ? $page['label'] : Url::path( $page['url'] ) ) . '</h2>';
		echo '<p class="iwieac-url"><a href="' . esc_url( $page['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $page['url'] ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-accessibility-checker' ) . '</span></a></p>';
		/* translators: %s: time ago */
		echo '<p class="iwieac-meta">' . esc_html( sprintf( __( 'Checked %s', 'iworksie-accessibility-checker' ), self::ago( (int) $page['scanned_at'] ) ) );
		if ( '' !== $page['engine'] ) {
			echo ' · axe-core ' . esc_html( $page['engine'] );
		}
		echo '</p>';
		echo '<p class="iwt-toolbar">';
		echo '<button type="button" class="button button-primary" data-iwieac-rescan="' . esc_attr( $page['url'] ) . '">' . esc_html__( 'Check again', 'iworksie-accessibility-checker' ) . '</button> ';
		if ( 'ok' === $page['status'] && ( (int) $page['problems'] + (int) $page['review'] ) > 0 ) {
			echo '<a class="button" href="' . esc_url( self::highlight_url( $page ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Show issues on the page', 'iworksie-accessibility-checker' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-accessibility-checker' ) . '</span></a>';
		}
		echo '</p></div>';

		// Hidden run area used by "Check again".
		echo '<div class="iwieac-run" data-iwieac-run hidden><div class="iwt-progress" data-iwieac-progress><div class="iwt-progress__row"><div class="iwt-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="' . esc_attr__( 'Scan progress', 'iworksie-accessibility-checker' ) . '"><div class="iwt-progress__bar" style="width:0%"></div></div><span class="iwt-progress__pct" aria-hidden="true">0%</span></div><p class="iwt-progress__message" data-iwieac-message></p></div><p class="screen-reader-text" role="status" aria-live="polite" data-iwieac-announce></p><div class="iwieac-run__body"><div class="iwieac-stage" data-iwieac-stage aria-hidden="true"></div><ol class="iwieac-log" data-iwieac-log></ol></div></div>';

		if ( 'error' === $page['status'] ) {
			echo UI::notice( 'error', (string) $page['error_message'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$issues = Store::page_issues( $page_id );
		$map    = Dismissals::map_for_page( $page_id );

		echo '<div class="iwt-stat-tiles iwieac-tiles">';
		self::tile( __( 'Problems', 'iworksie-accessibility-checker' ), number_format_i18n( (int) $page['problem_nodes'] ) );
		self::tile( __( 'Needs review', 'iworksie-accessibility-checker' ), number_format_i18n( (int) $page['review'] ) );
		$score = Store::page_score( $page );
		self::tile( __( 'Automated score', 'iworksie-accessibility-checker' ), null === $score ? '—' : (string) $score );
		echo '</div>';

		if ( empty( $issues ) ) {
			echo UI::notice( 'success', __( 'No automated problems on this page.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		foreach ( array( 'violation', 'incomplete' ) as $kind ) {
			$group = array_values(
				array_filter(
					$issues,
					static function ( $issue ) use ( $kind ) {
						return $issue['kind'] === $kind;
					}
				)
			);
			if ( empty( $group ) ) {
				continue;
			}
			echo '<section class="iwt-group">';
			echo '<h2 class="iwt-group__heading">' . esc_html( 'violation' === $kind ? __( 'Problems', 'iworksie-accessibility-checker' ) : __( 'Needs review', 'iworksie-accessibility-checker' ) ) . '</h2>';
			if ( 'incomplete' === $kind ) {
				echo '<p class="description">' . esc_html__( 'The engine could not decide these automatically (for example text over a background image). Look at each one; dismiss it if it is fine.', 'iworksie-accessibility-checker' ) . '</p>';
			}
			foreach ( $group as $issue ) {
				$this->issue_card( $issue, $page, $map );
			}
			echo '</section>';
		}
	}

	/**
	 * One issue card on the page detail view.
	 *
	 * @param array<string,mixed>                             $issue Issue row.
	 * @param array<string,mixed>                             $page  Page row.
	 * @param array<string,array<string,array<string,mixed>>> $map   Dismissals map.
	 * @return void
	 */
	private function issue_card( array $issue, array $page, array $map ) {
		$meta     = Store::rule( $issue['rule_id'] );
		$guide    = RuleGuide::get( $issue['rule_id'], $meta );
		$active   = (int) $issue['nodes_active'];
		$total    = (int) $issue['nodes_total'];
		$status   = self::card_status( $issue['kind'], $issue['impact'] );
		$panel_id = 'iwieac-issue-' . (int) $issue['id'];
		$users    = array();

		echo '<article class="iwt-result iwt-result--' . esc_attr( $active > 0 ? $status : 'good' ) . ' iwieac-issue" id="' . esc_attr( ( 'violation' === $issue['kind'] ? 'iwieac-rule-' : 'iwieac-review-' ) . $issue['rule_id'] ) . '">';
		echo '<h3 class="iwt-result__heading"><button type="button" class="iwt-result__toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
		echo '<span class="iwt-result__title">' . esc_html( $guide['title'] ) . '</span>';
		echo '<span class="iwt-result__meta">';
		if ( $active > 0 ) {
			echo self::impact_badge( (string) $issue['impact'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			/* translators: %d: number of elements */
			echo '<span class="iwieac-count">' . esc_html( sprintf( _n( '%d element', '%d elements', $active, 'iworksie-accessibility-checker' ), $active ) ) . '</span>';
		} else {
			echo '<span class="iwieac-count">' . esc_html__( 'All dismissed', 'iworksie-accessibility-checker' ) . '</span>';
		}
		echo '<span class="iwt-result__chevron" aria-hidden="true"></span></span></button></h3>';

		echo '<div class="iwt-result__body" id="' . esc_attr( $panel_id ) . '" hidden>';
		self::guide_html( $guide, $meta, $issue['kind'] );

		echo '<h4>' . esc_html__( 'Elements on this page', 'iworksie-accessibility-checker' ) . '</h4>';
		echo '<ul class="iwieac-nodes">';
		foreach ( $issue['nodes'] as $node ) {
			$target    = (string) $node['t'];
			$dismissal = Dismissals::match( $map, $issue['rule_id'], $target );
			echo '<li class="iwieac-node' . ( null !== $dismissal ? ' is-dismissed' : '' ) . '">';
			echo '<p class="iwieac-node__target"><code>' . esc_html( $target ) . '</code></p>';
			if ( '' !== $node['h'] ) {
				echo '<pre class="iwieac-node__html">' . esc_html( $node['h'] ) . '</pre>';
			}
			if ( '' !== $node['s'] ) {
				echo '<p class="iwieac-node__summary">' . nl2br( esc_html( $node['s'] ) ) . '</p>';
			}
			if ( null !== $dismissal ) {
				$uid = (int) $dismissal['user_id'];
				if ( ! isset( $users[ $uid ] ) ) {
					$user          = get_userdata( $uid );
					$users[ $uid ] = $user ? $user->display_name : __( 'Unknown user', 'iworksie-accessibility-checker' );
				}
				$reasons = Dismissals::reasons();
				$reason  = isset( $reasons[ $dismissal['reason'] ] ) ? $reasons[ $dismissal['reason'] ] : (string) $dismissal['reason'];
				echo '<p class="iwieac-node__dismissed">';
				/* translators: 1: reason, 2: user name, 3: time ago */
				echo esc_html( sprintf( __( 'Dismissed: %1$s (by %2$s, %3$s ago)', 'iworksie-accessibility-checker' ), $reason, $users[ $uid ], human_time_diff( (int) $dismissal['created_at'], time() ) ) );
				if ( '' !== $dismissal['note'] ) {
					echo ' · ' . esc_html( $dismissal['note'] );
				}
				echo ' <button type="button" class="button-link" data-iwieac-restore="' . esc_attr( (string) $dismissal['id'] ) . '">' . esc_html__( 'Restore', 'iworksie-accessibility-checker' ) . '</button>';
				echo '</p>';
			} else {
				echo '<p class="iwieac-node__actions">';
				echo '<a href="' . esc_url( self::highlight_url( $page, $issue['rule_id'] ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Show on page', 'iworksie-accessibility-checker' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-accessibility-checker' ) . '</span></a>';
				echo '</p>';
				self::dismiss_form( $issue['rule_id'], $target, (int) $page['id'] );
			}
			echo '</li>';
		}
		echo '</ul>';
		if ( $total > count( $issue['nodes'] ) ) {
			/* translators: %d: number of elements not listed */
			echo '<p class="description">' . esc_html( sprintf( __( '%d more elements are not listed. Fix the ones above and check again.', 'iworksie-accessibility-checker' ), $total - count( $issue['nodes'] ) ) ) . '</p>';
		}
		echo '</div></article>';
	}

	/**
	 * Inline dismiss form (native <details> so it works with keyboard and screen readers).
	 *
	 * @param string $rule_id Rule id.
	 * @param string $target  Element selector or '*'.
	 * @param int    $page_id Page id (0 for rule-wide from the Issues tab).
	 * @return void
	 */
	private static function dismiss_form( $rule_id, $target, $page_id ) {
		$uid = wp_unique_id( 'iwieac-dismiss-' );
		echo '<details class="iwieac-dismiss"><summary>' . esc_html( Dismissals::ALL_TARGETS === $target ? __( 'Ignore this issue on every page…', 'iworksie-accessibility-checker' ) : __( 'Dismiss…', 'iworksie-accessibility-checker' ) ) . '</summary>';
		echo '<form class="iwieac-dismiss__form" data-iwieac-dismiss>';
		echo '<input type="hidden" name="rule_id" value="' . esc_attr( $rule_id ) . '">';
		echo '<input type="hidden" name="target" value="' . esc_attr( $target ) . '">';
		echo '<input type="hidden" name="page_id" value="' . esc_attr( (string) $page_id ) . '">';
		echo '<p><label for="' . esc_attr( $uid ) . '-reason">' . esc_html__( 'Reason', 'iworksie-accessibility-checker' ) . '</label><br>';
		echo '<select id="' . esc_attr( $uid ) . '-reason" name="reason" required><option value="">' . esc_html__( 'Choose…', 'iworksie-accessibility-checker' ) . '</option>';
		foreach ( Dismissals::reasons() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		if ( $page_id > 0 ) {
			echo '<fieldset><legend>' . esc_html__( 'Where', 'iworksie-accessibility-checker' ) . '</legend>';
			echo '<label><input type="radio" name="scope" value="page" checked> ' . esc_html__( 'Only on this page', 'iworksie-accessibility-checker' ) . '</label><br>';
			echo '<label><input type="radio" name="scope" value="all"> ' . esc_html__( 'On every page (for headers, footers and other shared parts)', 'iworksie-accessibility-checker' ) . '</label>';
			echo '</fieldset>';
		} else {
			echo '<input type="hidden" name="scope" value="all">';
		}
		echo '<p><label for="' . esc_attr( $uid ) . '-note">' . esc_html__( 'Note (optional)', 'iworksie-accessibility-checker' ) . '</label><br>';
		echo '<input type="text" id="' . esc_attr( $uid ) . '-note" name="note" class="regular-text" maxlength="490"></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Dismiss', 'iworksie-accessibility-checker' ) . '</button> <span class="iwieac-dismiss__status" role="status"></span></p>';
		echo '</form></details>';
	}

	/**
	 * Issues tab: site-wide by rule.
	 *
	 * @return void
	 */
	private function render_issues() {
		$aggregate = Store::aggregate_rules();
		if ( empty( $aggregate ) ) {
			if ( 0 === Store::totals()['pages'] ) {
				echo '<p>' . esc_html__( 'No pages checked yet.', 'iworksie-accessibility-checker' ) . ' <a href="' . esc_url( self::tab_url( 'scan' ) ) . '">' . esc_html__( 'Start a scan', 'iworksie-accessibility-checker' ) . '</a></p>';
			} else {
				echo UI::notice( 'success', __( 'No open issues on the pages checked.', 'iworksie-accessibility-checker' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		echo '<p class="iwieac-intro">' . esc_html__( 'Each issue type is listed once with the pages it affects. Fixing a theme or plugin template often fixes many pages at once.', 'iworksie-accessibility-checker' ) . '</p>';

		foreach ( array( 'violation', 'incomplete' ) as $kind ) {
			$group = array_values(
				array_filter(
					$aggregate,
					static function ( $row ) use ( $kind ) {
						return $row['kind'] === $kind;
					}
				)
			);
			if ( empty( $group ) ) {
				continue;
			}
			echo '<section class="iwt-group"' . ( 'incomplete' === $kind ? ' id="iwieac-review"' : '' ) . '>';
			/* translators: %d: number of issue types */
			$heading = 'violation' === $kind ? sprintf( __( 'Problems (%d)', 'iworksie-accessibility-checker' ), count( $group ) ) : sprintf( __( 'Needs review (%d)', 'iworksie-accessibility-checker' ), count( $group ) );
			echo '<h2 class="iwt-group__heading">' . esc_html( $heading ) . '</h2>';
			foreach ( $group as $row ) {
				$meta     = Store::rule( $row['rule_id'] );
				$guide    = RuleGuide::get( $row['rule_id'], $meta );
				$panel_id = 'iwieac-agg-' . $kind . '-' . $row['rule_id'];
				echo '<article class="iwt-result iwt-result--' . esc_attr( self::card_status( $kind, $row['impact'] ) ) . ' iwieac-issue" id="' . esc_attr( 'violation' === $kind ? 'iwieac-rule-' . $row['rule_id'] : 'iwieac-review-' . $row['rule_id'] ) . '">';
				echo '<h3 class="iwt-result__heading"><button type="button" class="iwt-result__toggle" aria-expanded="false" aria-controls="' . esc_attr( $panel_id ) . '">';
				echo '<span class="iwt-result__title">' . esc_html( $guide['title'] ) . '</span><span class="iwt-result__meta">';
				echo self::impact_badge( $row['impact'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				/* translators: 1: elements, 2: pages */
				echo '<span class="iwieac-count">' . esc_html( sprintf( __( '%1$s elements on %2$s pages', 'iworksie-accessibility-checker' ), number_format_i18n( $row['nodes'] ), number_format_i18n( $row['pages'] ) ) ) . '</span>';
				echo '<span class="iwt-result__chevron" aria-hidden="true"></span></span></button></h3>';
				echo '<div class="iwt-result__body" id="' . esc_attr( $panel_id ) . '" hidden>';
				self::guide_html( $guide, $meta, $kind );
				echo '<h4>' . esc_html__( 'Affected pages', 'iworksie-accessibility-checker' ) . '</h4><ul class="iwieac-affected">';
				foreach ( Store::rule_pages( $row['rule_id'], $kind, 25 ) as $page ) {
					echo '<li><a href="' . esc_url( self::page_url( (int) $page['id'] ) . ( 'violation' === $kind ? '#iwieac-rule-' : '#iwieac-review-' ) . $row['rule_id'] ) . '">' . esc_html( '' !== $page['label'] ? $page['label'] : Url::path( $page['url'] ) ) . '</a>';
					/* translators: %d: number of elements */
					echo ' <span class="iwieac-url">' . esc_html( sprintf( _n( '(%d element)', '(%d elements)', (int) $page['nodes'], 'iworksie-accessibility-checker' ), (int) $page['nodes'] ) ) . '</span></li>';
				}
				echo '</ul>';
				if ( $row['pages'] > 25 ) {
					/* translators: %d: number of pages */
					echo '<p class="description">' . esc_html( sprintf( __( '…and %d more. The Pages tab lists them all.', 'iworksie-accessibility-checker' ), $row['pages'] - 25 ) ) . '</p>';
				}
				self::dismiss_form( $row['rule_id'], Dismissals::ALL_TARGETS, 0 );
				echo '</div></article>';
			}
			echo '</section>';
		}
	}

	/**
	 * Report tab.
	 *
	 * @return void
	 */
	private function render_report() {
		$totals = Store::totals();
		if ( 0 === $totals['ok_pages'] ) {
			echo '<p>' . esc_html__( 'Run a scan first; the report is built from its results.', 'iworksie-accessibility-checker' ) . ' <a href="' . esc_url( self::tab_url( 'scan' ) ) . '">' . esc_html__( 'Start a scan', 'iworksie-accessibility-checker' ) . '</a></p>';
			return;
		}

		$criteria  = ReportBuilder::criteria_results( Store::aggregate_rules(), Store::rules() );
		$catalogue = Wcag::criteria();

		echo '<p class="iwieac-intro">' . esc_html__( 'Results mapped to WCAG 2.2 Level AA and EN 301 549 (the European standard used for the European Accessibility Act and the Web Accessibility Directive). Share it with your developer or keep it as evidence of ongoing work.', 'iworksie-accessibility-checker' ) . '</p>';

		echo '<h2>' . esc_html__( 'WCAG 2.2 AA at a glance', 'iworksie-accessibility-checker' ) . '</h2>';
		self::table_open( __( 'WCAG 2.2 AA at a glance', 'iworksie-accessibility-checker' ) );
		echo '<table class="widefat striped iwt-table iwieac-table iwieac-criteria"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Success criterion', 'iworksie-accessibility-checker' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Level', 'iworksie-accessibility-checker' ) . '</th>';
		echo '<th scope="col">EN 301 549</th>';
		echo '<th scope="col">' . esc_html__( 'Result', 'iworksie-accessibility-checker' ) . '</th>';
		echo '<th scope="col" class="num">' . esc_html__( 'Pages', 'iworksie-accessibility-checker' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $criteria as $sc => $row ) {
			$en = Wcag::en_clause( $sc );
			echo '<tr>';
			echo '<td>' . esc_html( $sc . ' ' . $catalogue[ $sc ][0] ) . '</td>';
			echo '<td>' . esc_html( $catalogue[ $sc ][1] ) . '</td>';
			echo '<td>' . esc_html( '' !== $en ? $en : __( 'WCAG 2.2 only', 'iworksie-accessibility-checker' ) ) . '</td>';
			echo '<td><span class="iwieac-sc iwieac-sc--' . esc_attr( $row['status'] ) . '">' . esc_html( ReportBuilder::status_label( $row['status'] ) ) . '</span></td>';
			echo '<td class="num">' . ( $row['pages'] > 0 ? esc_html( number_format_i18n( $row['pages'] ) ) : '' ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		$markdown = ReportBuilder::build();
		$url      = $this->core->report()->store_for_download( $markdown );
		echo '<h2>' . esc_html__( 'Full report', 'iworksie-accessibility-checker' ) . '</h2>';
		echo '<div class="iwt-report-actions">';
		echo UI::copy_button( 'iwieac-report-source' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<a class="button" href="' . esc_url( $url ) . '" data-iwt-download-report download="iworksie-accessibility-report.md">' . esc_html( Strings::get( 'download_report' ) ) . '</a>';
		echo '</div>';
		echo '<pre id="iwieac-report-source" class="iwt-report-box" tabindex="0">' . esc_html( $markdown ) . '</pre>';

		echo '<div class="iwieac-cta"><p><strong>' . esc_html__( 'Need a full audit?', 'iworksie-accessibility-checker' ) . '</strong> ';
		echo esc_html__( 'A manual audit covers what automated tools cannot: keyboard use, screen readers, captions and forms. iWorks can audit, fix and write your accessibility statement.', 'iworksie-accessibility-checker' ) . ' ';
		echo '<a href="' . esc_url( 'https://www.iworks.ie/?utm_source=wp-plugin&utm_medium=iworksie-accessibility-checker&utm_campaign=audit' ) . '" rel="noopener noreferrer">' . esc_html__( 'Talk to iWorks →', 'iworksie-accessibility-checker' ) . '</a></p></div>';
	}

	/**
	 * Dismissed tab.
	 *
	 * @return void
	 */
	private function render_dismissed() {
		$rows = Dismissals::all();
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'Nothing dismissed. When you mark an issue as not a problem, it is listed here with the reason, who did it and when, so the decision can be reviewed later.', 'iworksie-accessibility-checker' ) . '</p>';
			return;
		}
		$reasons = Dismissals::reasons();
		$users   = array();
		self::table_open( __( 'Dismissed items', 'iworksie-accessibility-checker' ) );
		echo '<table class="widefat striped iwt-table iwieac-table"><thead><tr>';
		foreach ( array( __( 'Issue', 'iworksie-accessibility-checker' ), __( 'Element', 'iworksie-accessibility-checker' ), __( 'Where', 'iworksie-accessibility-checker' ), __( 'Reason', 'iworksie-accessibility-checker' ), __( 'By', 'iworksie-accessibility-checker' ), __( 'When', 'iworksie-accessibility-checker' ), '' ) as $heading ) {
			echo '<th scope="col">' . ( '' !== $heading ? esc_html( $heading ) : '<span class="screen-reader-text">' . esc_html__( 'Actions', 'iworksie-accessibility-checker' ) . '</span>' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$guide = RuleGuide::get( (string) $row['rule_id'], Store::rule( (string) $row['rule_id'] ) );
			$uid   = (int) $row['user_id'];
			if ( ! isset( $users[ $uid ] ) ) {
				$user          = get_userdata( $uid );
				$users[ $uid ] = $user ? $user->display_name : __( 'Unknown user', 'iworksie-accessibility-checker' );
			}
			$where = __( 'Every page', 'iworksie-accessibility-checker' );
			if ( (int) $row['page_id'] > 0 ) {
				$page  = Store::get_page( (int) $row['page_id'] );
				$where = null !== $page ? ( '' !== $page['label'] ? $page['label'] : Url::path( $page['url'] ) ) : __( '(page no longer in results)', 'iworksie-accessibility-checker' );
			}
			echo '<tr>';
			echo '<td>' . esc_html( $guide['title'] ) . '</td>';
			echo '<td>' . ( Dismissals::ALL_TARGETS === $row['target'] ? esc_html__( 'All elements', 'iworksie-accessibility-checker' ) : '<code>' . esc_html( $row['target'] ) . '</code>' ) . '</td>';
			echo '<td>' . esc_html( $where ) . '</td>';
			echo '<td>' . esc_html( isset( $reasons[ $row['reason'] ] ) ? $reasons[ $row['reason'] ] : $row['reason'] );
			if ( '' !== $row['note'] ) {
				echo '<br><span class="description">' . esc_html( $row['note'] ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . esc_html( $users[ $uid ] ) . '</td>';
			echo '<td>' . esc_html( self::ago( (int) $row['created_at'] ) ) . '</td>';
			echo '<td><button type="button" class="button" data-iwieac-restore="' . esc_attr( (string) $row['id'] ) . '">' . esc_html__( 'Restore', 'iworksie-accessibility-checker' ) . '</button></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * "What it means / Why / How to fix / technical" block.
	 *
	 * @param array<string,mixed> $guide From RuleGuide::get().
	 * @param array<string,mixed> $meta  Rule metadata.
	 * @param string              $kind  violation|incomplete.
	 * @return void
	 */
	private static function guide_html( array $guide, array $meta, $kind ) {
		if ( '' !== $guide['why'] ) {
			echo '<div class="iwt-result__section"><h4>' . esc_html( Strings::get( 'why_it_matters' ) ) . '</h4><p>' . esc_html( $guide['why'] ) . '</p></div>';
		}
		echo '<div class="iwt-result__section"><h4>' . esc_html( 'incomplete' === $kind ? __( 'What to check', 'iworksie-accessibility-checker' ) : Strings::get( 'how_to_fix' ) ) . '</h4><p>' . esc_html( $guide['fix'] ) . '</p></div>';
		$criteria = self::criteria_text( $meta );
		echo '<p class="iwieac-tech">';
		echo esc_html( 'WCAG: ' . $criteria );
		if ( ! empty( $meta['help_url'] ) ) {
			echo ' · <a href="' . esc_url( (string) $meta['help_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Technical guidance (Deque University)', 'iworksie-accessibility-checker' ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'iworksie-accessibility-checker' ) . '</span></a>';
		}
		echo '</p>';
	}

	/**
	 * Criteria text such as "1.4.3 Contrast (Minimum)".
	 *
	 * @param array<string,mixed> $meta Rule metadata.
	 * @return string
	 */
	private static function criteria_text( array $meta ) {
		$tags = isset( $meta['tags'] ) && is_array( $meta['tags'] ) ? $meta['tags'] : array();
		$scs  = Wcag::criteria_from_tags( $tags );
		if ( empty( $scs ) ) {
			return __( 'Best practice', 'iworksie-accessibility-checker' );
		}
		$catalogue = Wcag::criteria();
		$parts     = array();
		foreach ( $scs as $sc ) {
			$parts[] = $sc . ' ' . $catalogue[ $sc ][0] . ' (' . $catalogue[ $sc ][1] . ')';
		}
		return implode( ', ', $parts );
	}

	/**
	 * Impact badge HTML (escaped).
	 *
	 * @param string $impact Impact.
	 * @return string
	 */
	private static function impact_badge( $impact ) {
		return '<span class="iwieac-impact iwieac-impact--' . esc_attr( '' !== $impact ? $impact : 'unknown' ) . '">' . esc_html( Wcag::impact_label( $impact ) ) . '</span>';
	}

	/**
	 * Core card status for an issue.
	 *
	 * @param string $kind   violation|incomplete.
	 * @param string $impact Impact.
	 * @return string
	 */
	private static function card_status( $kind, $impact ) {
		if ( 'incomplete' === $kind ) {
			return 'info';
		}
		return Wcag::impact_weight( $impact ) >= 3 ? 'critical' : 'recommended';
	}

	/**
	 * Score band for colour.
	 *
	 * @param int $score 0-100.
	 * @return string
	 */
	private static function score_band( $score ) {
		if ( $score >= 90 ) {
			return 'good';
		}
		return $score >= 60 ? 'recommended' : 'critical';
	}

	/**
	 * Stat tile.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $url   Optional link.
	 * @param string $sub   Optional sub text.
	 * @return void
	 */
	private static function tile( $label, $value, $url = '', $sub = '' ) {
		echo '<div class="iwt-stat-tile">';
		echo '<p class="iwt-stat-tile__label">' . esc_html( $label ) . '</p>';
		echo '<p class="iwt-stat-tile__value">';
		if ( '' !== $url ) {
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $value ) . '</a>';
		} else {
			echo esc_html( $value );
		}
		echo '</p>';
		if ( '' !== $sub ) {
			echo '<p class="iwieac-tile-sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Open a horizontally scrollable, keyboard-focusable wrapper for a wide table.
	 *
	 * @param string $label Accessible name of the scroll region.
	 * @return void
	 */
	private static function table_open( $label ) {
		echo '<div class="iwieac-table-wrap" role="region" tabindex="0" aria-label="' . esc_attr( $label ) . '">';
	}

	/**
	 * Checkbox for a scan source.
	 *
	 * @param string $value   Value.
	 * @param string $label   Label.
	 * @param bool   $checked Checked.
	 * @return void
	 */
	private static function checkbox( $value, $label, $checked ) {
		echo '<label class="iwieac-check"><input type="checkbox" name="sources[]" value="' . esc_attr( $value ) . '"' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label>';
	}

	/**
	 * Sortable column header.
	 *
	 * @param string $key     Sort key.
	 * @param string $label   Label.
	 * @param string $orderby Current sort.
	 * @param string $order   Current order.
	 * @param string $status  Current filter.
	 * @param string $css_class Extra class.
	 * @return void
	 */
	private static function sortable_th( $key, $label, $orderby, $order, $status, $css_class = '' ) {
		$is_current = $key === $orderby;
		$next       = $is_current && 'desc' === $order ? 'asc' : 'desc';
		if ( ! $is_current ) {
			$next = 'label' === $key ? 'asc' : 'desc';
		}
		$args = array(
			'orderby' => $key,
			'order'   => $next,
		);
		if ( '' !== $status ) {
			$args['status'] = $status;
		}
		$aria = $is_current ? ' aria-sort="' . ( 'asc' === $order ? 'ascending' : 'descending' ) . '"' : '';
		echo '<th scope="col" class="' . esc_attr( trim( $css_class . ' iwieac-sortable' ) ) . '"' . $aria . '><a href="' . esc_url( self::tab_url( 'pages', $args ) ) . '">' . esc_html( $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $aria is a fixed string.
		if ( $is_current ) {
			echo '<span class="iwieac-sort" aria-hidden="true">' . ( 'asc' === $order ? '▲' : '▼' ) . '</span>';
		}
		echo '</a></th>';
	}

	/**
	 * "5 minutes ago" or a dash.
	 *
	 * @param int $time Timestamp.
	 * @return string
	 */
	private static function ago( $time ) {
		if ( $time <= 0 ) {
			return '—';
		}
		/* translators: %s: human time difference */
		return sprintf( __( '%s ago', 'iworksie-accessibility-checker' ), human_time_diff( $time, time() ) );
	}
}
