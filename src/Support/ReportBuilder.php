<?php
/**
 * Markdown report mapped to WCAG 2.2 AA and EN 301 549.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Support;

use IWorksIE\AccessibilityChecker\Data\Dismissals;
use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Plugin;

/**
 * Builds the shareable report from stored results.
 */
final class ReportBuilder {

	/**
	 * Per-criterion result for the conformance table.
	 *
	 * @param array<int,array<string,mixed>>    $aggregate From Store::aggregate_rules().
	 * @param array<string,array<string,mixed>> $rules     Rule metadata.
	 * @return array<string,array{status:string,pages:int,rules:array<int,string>}>
	 */
	public static function criteria_results( array $aggregate, array $rules ) {
		$out = array();
		foreach ( array_keys( Wcag::criteria() ) as $sc ) {
			$out[ $sc ] = array(
				'status' => in_array( $sc, Wcag::AUTOMATED, true ) ? 'pass' : 'manual',
				'pages'  => 0,
				'rules'  => array(),
			);
		}
		foreach ( $aggregate as $row ) {
			$tags = isset( $rules[ $row['rule_id'] ]['tags'] ) && is_array( $rules[ $row['rule_id'] ]['tags'] ) ? $rules[ $row['rule_id'] ]['tags'] : array();
			foreach ( Wcag::criteria_from_tags( $tags ) as $sc ) {
				if ( ! isset( $out[ $sc ] ) ) {
					continue;
				}
				if ( 'violation' === $row['kind'] ) {
					$out[ $sc ]['status'] = 'fail';
				} elseif ( 'fail' !== $out[ $sc ]['status'] ) {
					$out[ $sc ]['status'] = 'review';
				}
				$out[ $sc ]['pages'] = max( $out[ $sc ]['pages'], (int) $row['pages'] );
				if ( ! in_array( $row['rule_id'], $out[ $sc ]['rules'], true ) ) {
					$out[ $sc ]['rules'][] = (string) $row['rule_id'];
				}
			}
		}
		return $out;
	}

	/**
	 * Count-aware "N elements across M types" line for the report summary.
	 *
	 * @param string $prefix Leading label, e.g. "Problems".
	 * @param int    $nodes  Element count.
	 * @param int    $rules  Issue-type count.
	 * @return string
	 */
	public static function nodes_across_rules_line( $prefix, $nodes, $rules ) {
		$nodes = (int) $nodes;
		$rules = (int) $rules;
		return sprintf(
			/* translators: 1: number of elements, 2: number of rules. %3$s is a translated label such as "Problems". */
			_n( '%3$s: %1$d element across %2$d types of issue', '%3$s: %1$d elements across %2$d types of issue', $nodes, 'iworksie-accessibility-checker' ),
			$nodes,
			$rules,
			$prefix
		);
	}

	/**
	 * Count-aware "N elements on M pages" line.
	 *
	 * @param int $nodes Element count.
	 * @param int $pages Page count.
	 * @return string
	 */
	public static function elements_on_pages_line( $nodes, $pages ) {
		$nodes = (int) $nodes;
		$pages = (int) $pages;
		return sprintf(
			/* translators: 1: number of elements, 2: number of pages */
			_n( '%1$s element on %2$s pages', '%1$s elements on %2$s pages', $nodes, 'iworksie-accessibility-checker' ),
			number_format_i18n( $nodes ),
			number_format_i18n( $pages )
		);
	}

	/**
	 * Translated label for a criterion status.
	 *
	 * @param string $status fail|review|pass|manual.
	 * @return string
	 */
	public static function status_label( $status ) {
		switch ( $status ) {
			case 'fail':
				return __( 'Fails', 'iworksie-accessibility-checker' );
			case 'review':
				return __( 'Needs review', 'iworksie-accessibility-checker' );
			case 'pass':
				return __( 'No automated failures', 'iworksie-accessibility-checker' );
			default:
				return __( 'Manual check needed', 'iworksie-accessibility-checker' );
		}
	}

	/**
	 * Build the Markdown report.
	 *
	 * @return string
	 */
	public static function build() {
		$totals    = Store::totals();
		$aggregate = Store::aggregate_rules();
		$rules     = Store::rules();
		$criteria  = self::criteria_results( $aggregate, $rules );
		$catalogue = Wcag::criteria();
		$lines     = array();

		/* translators: %s: site name */
		$lines[] = '# ' . sprintf( __( 'Accessibility report: %s', 'iworksie-accessibility-checker' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$lines[] = '';
		$lines[] = '- ' . __( 'Site', 'iworksie-accessibility-checker' ) . ': ' . home_url( '/' );
		$lines[] = '- ' . __( 'Report date', 'iworksie-accessibility-checker' ) . ': ' . wp_date( 'Y-m-d H:i' );
		if ( $totals['last_scanned'] > 0 ) {
			$lines[] = '- ' . __( 'Last page checked', 'iworksie-accessibility-checker' ) . ': ' . wp_date( 'Y-m-d H:i', $totals['last_scanned'] );
		}
		$lines[] = '- ' . __( 'Pages checked', 'iworksie-accessibility-checker' ) . ': ' . $totals['ok_pages'] . ( $totals['error_pages'] > 0 ? ' (' . sprintf(
			/* translators: %d: number of pages */
			_n( '%d could not be checked', '%d could not be checked', (int) $totals['error_pages'], 'iworksie-accessibility-checker' ),
			(int) $totals['error_pages']
		) . ')' : '' );
		$lines[] = '- ' . __( 'Standard', 'iworksie-accessibility-checker' ) . ': WCAG 2.2 Level AA; EN 301 549 v3.2.1 ' . __( 'clause 9 (web content)', 'iworksie-accessibility-checker' );
		$lines[] = '- ' . __( 'Tool', 'iworksie-accessibility-checker' ) . ': iWorks.ie Accessibility Checker ' . IWIEAC_VERSION . ', axe-core ' . Plugin::AXE_VERSION;
		$lines[] = '';

		$lines[] = '## ' . __( 'Summary', 'iworksie-accessibility-checker' );
		$lines[] = '';
		$lines[] = '- ' . self::nodes_across_rules_line( __( 'Problems', 'iworksie-accessibility-checker' ), (int) $totals['problem_nodes'], (int) $totals['problem_rules'] );
		$lines[] = '- ' . self::nodes_across_rules_line( __( 'Needs review', 'iworksie-accessibility-checker' ), (int) $totals['review_nodes'], (int) $totals['review_rules'] );
		/* translators: 1: pages without problems, 2: pages checked */
		$lines[] = '- ' . sprintf( __( 'Pages with no automated problems: %1$d of %2$d', 'iworksie-accessibility-checker' ), $totals['clean_pages'], $totals['ok_pages'] );
		$fails   = 0;
		foreach ( $criteria as $row ) {
			if ( 'fail' === $row['status'] ) {
				++$fails;
			}
		}
		$lines[] = '- ' . sprintf(
			/* translators: %d: number of success criteria */
			_n( 'WCAG 2.2 AA success criterion with automated failures: %d', 'WCAG 2.2 AA success criteria with automated failures: %d', $fails, 'iworksie-accessibility-checker' ),
			$fails
		);
		$dismissed = Dismissals::count();
		if ( $dismissed > 0 ) {
			$lines[] = '- ' . sprintf(
				/* translators: %d: number of dismissed items */
				_n( 'Item dismissed by a person (with reasons, listed in the plugin): %d', 'Items dismissed by a person (with reasons, listed in the plugin): %d', $dismissed, 'iworksie-accessibility-checker' ),
				$dismissed
			);
		}
		$lines[] = '';

		$lines[] = '## ' . __( 'What this report can and cannot tell you', 'iworksie-accessibility-checker' );
		$lines[] = '';
		$lines[] = __( 'Automated testing finds roughly a third of accessibility problems. It is reliable for things like missing alternative text, low colour contrast, missing form labels and broken ARIA, but it cannot judge whether alt text is meaningful, whether the site works with a keyboard alone, or whether videos have accurate captions. "No automated failures" is not the same as "conforms". A full conformance claim under the European Accessibility Act needs a manual audit by a person.', 'iworksie-accessibility-checker' );
		$lines[] = '';

		$lines[] = '## ' . __( 'Results by WCAG 2.2 success criterion', 'iworksie-accessibility-checker' );
		$lines[] = '';
		$lines[] = '| ' . implode( ' | ', array( __( 'Criterion', 'iworksie-accessibility-checker' ), __( 'Level', 'iworksie-accessibility-checker' ), 'EN 301 549', __( 'Result', 'iworksie-accessibility-checker' ), __( 'Pages affected', 'iworksie-accessibility-checker' ) ) ) . ' |';
		$lines[] = '|---|---|---|---|---|';
		foreach ( $criteria as $sc => $row ) {
			$en      = Wcag::en_clause( $sc );
			$lines[] = '| ' . implode(
				' | ',
				array(
					$sc . ' ' . $catalogue[ $sc ][0],
					$catalogue[ $sc ][1],
					'' !== $en ? $en : __( 'WCAG 2.2 only', 'iworksie-accessibility-checker' ),
					self::status_label( $row['status'] ),
					$row['pages'] > 0 ? (string) $row['pages'] : '',
				)
			) . ' |';
		}
		$lines[] = '';

		foreach ( array( 'violation', 'incomplete' ) as $kind ) {
			$rows = array_values(
				array_filter(
					$aggregate,
					static function ( $row ) use ( $kind ) {
						return $row['kind'] === $kind;
					}
				)
			);
			if ( empty( $rows ) ) {
				continue;
			}
			$lines[] = '## ' . ( 'violation' === $kind ? __( 'Problems found', 'iworksie-accessibility-checker' ) : __( 'Needs review by a person', 'iworksie-accessibility-checker' ) );
			$lines[] = '';
			foreach ( $rows as $row ) {
				$meta    = isset( $rules[ $row['rule_id'] ] ) ? $rules[ $row['rule_id'] ] : array();
				$guide   = RuleGuide::get( $row['rule_id'], $meta );
				$tags    = isset( $meta['tags'] ) && is_array( $meta['tags'] ) ? $meta['tags'] : array();
				$scs     = Wcag::criteria_from_tags( $tags );
				$lines[] = '### ' . $guide['title'];
				$lines[] = '';
				$lines[] = '- ' . __( 'Impact', 'iworksie-accessibility-checker' ) . ': ' . Wcag::impact_label( $row['impact'] );
				$lines[] = '- WCAG: ' . ( empty( $scs ) ? __( 'Best practice (not a WCAG requirement)', 'iworksie-accessibility-checker' ) : implode( ', ', $scs ) );
				$lines[] = '- ' . self::elements_on_pages_line( (int) $row['nodes'], (int) $row['pages'] );
				$lines[] = '- ' . __( 'Rule', 'iworksie-accessibility-checker' ) . ': `' . $row['rule_id'] . '`' . ( ! empty( $meta['help_url'] ) ? ' (' . $meta['help_url'] . ')' : '' );
				if ( 'violation' === $kind ) {
					if ( '' !== $guide['why'] ) {
						$lines[] = '';
						$lines[] = '**' . __( 'Why it matters', 'iworksie-accessibility-checker' ) . ':** ' . $guide['why'];
					}
					$lines[] = '';
					$lines[] = '**' . __( 'How to fix', 'iworksie-accessibility-checker' ) . ':** ' . $guide['fix'];
				}
				$lines[] = '';
				foreach ( Store::rule_pages( $row['rule_id'], $kind, 10 ) as $page ) {
					$lines[] = '  - ' . $page['label'] . ': ' . $page['url'] . ' (' . (int) $page['nodes'] . ')';
				}
				if ( $row['pages'] > 10 ) {
					$more_pages = (int) $row['pages'] - 10;
					$lines[]    = '  - ' . sprintf(
						/* translators: %d: number of further pages */
						_n( '…and %d more page', '…and %d more pages', $more_pages, 'iworksie-accessibility-checker' ),
						$more_pages
					);
				}
				$lines[] = '';
			}
		}

		if ( $totals['error_pages'] > 0 ) {
			$lines[] = '## ' . __( 'Pages that could not be checked', 'iworksie-accessibility-checker' );
			$lines[] = '';
			$errors  = Store::list_pages(
				array(
					'status'   => 'error',
					'per_page' => 50,
					'orderby'  => 'label',
					'order'    => 'asc',
				)
			);
			foreach ( $errors['rows'] as $page ) {
				$lines[] = '- ' . $page['url'] . ': ' . $page['error_message'];
			}
			$lines[] = '';
		}

		$lines[] = '## ' . __( 'Manual checks to do next', 'iworksie-accessibility-checker' );
		$lines[] = '';
		$lines[] = '- ' . __( 'Use the site with only a keyboard (Tab, Shift+Tab, Enter, Space, Esc). Can you reach and use every link, menu, form and pop-up, and always see where focus is?', 'iworksie-accessibility-checker' );
		$lines[] = '- ' . __( 'Zoom the browser to 200% and 400%. Does everything still fit and work without scrolling sideways?', 'iworksie-accessibility-checker' );
		$lines[] = '- ' . __( 'Try a screen reader (VoiceOver on Mac/iPhone, NVDA on Windows, TalkBack on Android) on your key pages and forms.', 'iworksie-accessibility-checker' );
		$lines[] = '- ' . __( 'Check that alt text is meaningful, videos have accurate captions, and form errors are explained in text.', 'iworksie-accessibility-checker' );
		$lines[] = '- ' . __( 'Publish an accessibility statement with a way for visitors to report problems (required for public sector sites and expected under the European Accessibility Act).', 'iworksie-accessibility-checker' );
		$lines[] = '';
		$lines[] = __( 'Need a full manual audit, fixes or an accessibility statement? iWorks can help: https://www.iworks.ie', 'iworksie-accessibility-checker' );
		$lines[] = '';

		return implode( "\n", $lines );
	}
}
