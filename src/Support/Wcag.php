<?php
/**
 * WCAG 2.2 success criteria catalogue and EN 301 549 mapping.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Support;

/**
 * Maps axe-core tags to WCAG success criteria and EN 301 549 clauses.
 */
final class Wcag {

	/**
	 * Success criteria axe-core tests automatically (Level A/AA, non-experimental rules in axe 4.14).
	 *
	 * Everything else in the catalogue needs a person to check it.
	 *
	 * @var array<int,string>
	 */
	const AUTOMATED = array(
		'1.1.1',
		'1.2.2',
		'1.3.1',
		'1.3.5',
		'1.4.1',
		'1.4.2',
		'1.4.3',
		'1.4.4',
		'1.4.12',
		'2.1.1',
		'2.2.1',
		'2.2.2',
		'2.4.1',
		'2.4.2',
		'2.4.4',
		'2.5.3',
		'2.5.8',
		'3.1.1',
		'3.1.2',
		'3.3.2',
		'4.1.2',
	);

	/**
	 * Tags passed to axe.run() (best-practice is added separately when enabled).
	 *
	 * @var array<int,string>
	 */
	const AXE_TAGS = array( 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' );

	/**
	 * WCAG 2.2 Level A and AA success criteria.
	 *
	 * Values: [ name, level, WCAG version that introduced it ]. Names are the official
	 * W3C titles and are deliberately not translated.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function criteria() {
		return array(
			'1.1.1'  => array( 'Non-text Content', 'A', '2.0' ),
			'1.2.1'  => array( 'Audio-only and Video-only (Prerecorded)', 'A', '2.0' ),
			'1.2.2'  => array( 'Captions (Prerecorded)', 'A', '2.0' ),
			'1.2.3'  => array( 'Audio Description or Media Alternative (Prerecorded)', 'A', '2.0' ),
			'1.2.4'  => array( 'Captions (Live)', 'AA', '2.0' ),
			'1.2.5'  => array( 'Audio Description (Prerecorded)', 'AA', '2.0' ),
			'1.3.1'  => array( 'Info and Relationships', 'A', '2.0' ),
			'1.3.2'  => array( 'Meaningful Sequence', 'A', '2.0' ),
			'1.3.3'  => array( 'Sensory Characteristics', 'A', '2.0' ),
			'1.3.4'  => array( 'Orientation', 'AA', '2.1' ),
			'1.3.5'  => array( 'Identify Input Purpose', 'AA', '2.1' ),
			'1.4.1'  => array( 'Use of Color', 'A', '2.0' ),
			'1.4.2'  => array( 'Audio Control', 'A', '2.0' ),
			'1.4.3'  => array( 'Contrast (Minimum)', 'AA', '2.0' ),
			'1.4.4'  => array( 'Resize Text', 'AA', '2.0' ),
			'1.4.5'  => array( 'Images of Text', 'AA', '2.0' ),
			'1.4.10' => array( 'Reflow', 'AA', '2.1' ),
			'1.4.11' => array( 'Non-text Contrast', 'AA', '2.1' ),
			'1.4.12' => array( 'Text Spacing', 'AA', '2.1' ),
			'1.4.13' => array( 'Content on Hover or Focus', 'AA', '2.1' ),
			'2.1.1'  => array( 'Keyboard', 'A', '2.0' ),
			'2.1.2'  => array( 'No Keyboard Trap', 'A', '2.0' ),
			'2.1.4'  => array( 'Character Key Shortcuts', 'A', '2.1' ),
			'2.2.1'  => array( 'Timing Adjustable', 'A', '2.0' ),
			'2.2.2'  => array( 'Pause, Stop, Hide', 'A', '2.0' ),
			'2.3.1'  => array( 'Three Flashes or Below Threshold', 'A', '2.0' ),
			'2.4.1'  => array( 'Bypass Blocks', 'A', '2.0' ),
			'2.4.2'  => array( 'Page Titled', 'A', '2.0' ),
			'2.4.3'  => array( 'Focus Order', 'A', '2.0' ),
			'2.4.4'  => array( 'Link Purpose (In Context)', 'A', '2.0' ),
			'2.4.5'  => array( 'Multiple Ways', 'AA', '2.0' ),
			'2.4.6'  => array( 'Headings and Labels', 'AA', '2.0' ),
			'2.4.7'  => array( 'Focus Visible', 'AA', '2.0' ),
			'2.4.11' => array( 'Focus Not Obscured (Minimum)', 'AA', '2.2' ),
			'2.5.1'  => array( 'Pointer Gestures', 'A', '2.1' ),
			'2.5.2'  => array( 'Pointer Cancellation', 'A', '2.1' ),
			'2.5.3'  => array( 'Label in Name', 'A', '2.1' ),
			'2.5.4'  => array( 'Motion Actuation', 'A', '2.1' ),
			'2.5.7'  => array( 'Dragging Movements', 'AA', '2.2' ),
			'2.5.8'  => array( 'Target Size (Minimum)', 'AA', '2.2' ),
			'3.1.1'  => array( 'Language of Page', 'A', '2.0' ),
			'3.1.2'  => array( 'Language of Parts', 'AA', '2.0' ),
			'3.2.1'  => array( 'On Focus', 'A', '2.0' ),
			'3.2.2'  => array( 'On Input', 'A', '2.0' ),
			'3.2.3'  => array( 'Consistent Navigation', 'AA', '2.0' ),
			'3.2.4'  => array( 'Consistent Identification', 'AA', '2.0' ),
			'3.2.6'  => array( 'Consistent Help', 'A', '2.2' ),
			'3.3.1'  => array( 'Error Identification', 'A', '2.0' ),
			'3.3.2'  => array( 'Labels or Instructions', 'A', '2.0' ),
			'3.3.3'  => array( 'Error Suggestion', 'AA', '2.0' ),
			'3.3.4'  => array( 'Error Prevention (Legal, Financial, Data)', 'AA', '2.0' ),
			'3.3.7'  => array( 'Redundant Entry', 'A', '2.2' ),
			'3.3.8'  => array( 'Accessible Authentication (Minimum)', 'AA', '2.2' ),
			'4.1.2'  => array( 'Name, Role, Value', 'A', '2.0' ),
			'4.1.3'  => array( 'Status Messages', 'AA', '2.1' ),
		);
	}

	/**
	 * Success criteria referenced by a list of axe tags, e.g. wcag143 → 1.4.3.
	 *
	 * Only criteria in the A/AA catalogue are returned (AAA and obsolete 4.1.1 are skipped).
	 *
	 * @param array<int,string> $tags axe tags.
	 * @return array<int,string>
	 */
	public static function criteria_from_tags( array $tags ) {
		$catalogue = self::criteria();
		$out       = array();
		foreach ( $tags as $tag ) {
			if ( ! is_string( $tag ) || ! preg_match( '/^wcag(\d)(\d)(\d{1,2})$/', $tag, $m ) ) {
				continue;
			}
			$sc = $m[1] . '.' . $m[2] . '.' . (int) $m[3];
			if ( isset( $catalogue[ $sc ] ) && ! in_array( $sc, $out, true ) ) {
				$out[] = $sc;
			}
		}
		usort( $out, array( __CLASS__, 'compare' ) );
		return $out;
	}

	/**
	 * Whether a rule is a best-practice rule rather than a WCAG requirement.
	 *
	 * @param array<int,string> $tags axe tags.
	 * @return bool
	 */
	public static function is_best_practice( array $tags ) {
		return empty( self::criteria_from_tags( $tags ) );
	}

	/**
	 * EN 301 549 v3.2.1 clause for a WCAG criterion (chapter 9 mirrors WCAG 2.1 A/AA).
	 *
	 * Criteria new in WCAG 2.2 have no clause in v3.2.1 and return an empty string.
	 *
	 * @param string $sc Success criterion, e.g. 1.4.3.
	 * @return string e.g. 9.1.4.3, or '' when not in EN 301 549 v3.2.1.
	 */
	public static function en_clause( $sc ) {
		$catalogue = self::criteria();
		if ( ! isset( $catalogue[ $sc ] ) || '2.2' === $catalogue[ $sc ][2] ) {
			return '';
		}
		return '9.' . $sc;
	}

	/**
	 * Natural sort for criteria numbers.
	 *
	 * @param string $a First.
	 * @param string $b Second.
	 * @return int
	 */
	public static function compare( $a, $b ) {
		return version_compare( $a, $b );
	}

	/**
	 * Numeric weight for axe impact levels (higher is worse).
	 *
	 * @param string $impact critical|serious|moderate|minor.
	 * @return int
	 */
	public static function impact_weight( $impact ) {
		$map = array(
			'critical' => 4,
			'serious'  => 3,
			'moderate' => 2,
			'minor'    => 1,
		);
		return isset( $map[ $impact ] ) ? $map[ $impact ] : 0;
	}

	/**
	 * Translated impact label.
	 *
	 * @param string $impact Impact key.
	 * @return string
	 */
	public static function impact_label( $impact ) {
		switch ( $impact ) {
			case 'critical':
				return __( 'Critical', 'iworksie-accessibility-checker' );
			case 'serious':
				return __( 'Serious', 'iworksie-accessibility-checker' );
			case 'moderate':
				return __( 'Moderate', 'iworksie-accessibility-checker' );
			case 'minor':
				return __( 'Minor', 'iworksie-accessibility-checker' );
			default:
				return __( 'Unknown', 'iworksie-accessibility-checker' );
		}
	}
}
