<?php
/**
 * Report criteria status tests.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use IWorksIE\AccessibilityChecker\Support\ReportBuilder;
use IWorksIE\AccessibilityChecker\Support\RuleGuide;

/**
 * @covers \IWorksIE\AccessibilityChecker\Support\ReportBuilder
 * @covers \IWorksIE\AccessibilityChecker\Support\RuleGuide
 */
final class ReportCriteriaTest extends TestCase {

	public function test_criteria_statuses() {
		$rules     = array(
			'color-contrast' => array( 'tags' => array( 'wcag2aa', 'wcag143' ) ),
			'link-name'      => array( 'tags' => array( 'wcag2a', 'wcag244', 'wcag412' ) ),
			'region'         => array( 'tags' => array( 'best-practice' ) ),
		);
		$aggregate = array(
			array(
				'rule_id' => 'color-contrast',
				'kind'    => 'incomplete',
				'impact'  => 'serious',
				'pages'   => 4,
				'nodes'   => 9,
			),
			array(
				'rule_id' => 'link-name',
				'kind'    => 'violation',
				'impact'  => 'serious',
				'pages'   => 2,
				'nodes'   => 2,
			),
			array(
				'rule_id' => 'region',
				'kind'    => 'violation',
				'impact'  => 'moderate',
				'pages'   => 10,
				'nodes'   => 10,
			),
		);
		$out       = ReportBuilder::criteria_results( $aggregate, $rules );
		$this->assertSame( 'review', $out['1.4.3']['status'] );
		$this->assertSame( 4, $out['1.4.3']['pages'] );
		$this->assertSame( 'fail', $out['2.4.4']['status'] );
		$this->assertSame( 'fail', $out['4.1.2']['status'] );
		$this->assertSame( 'pass', $out['1.1.1']['status'] );
		$this->assertSame( 'manual', $out['2.1.2']['status'] );
		// Best-practice rules never mark a criterion as failing.
		$fails = array_filter(
			$out,
			static function ( $row ) {
				return 'fail' === $row['status'];
			}
		);
		$this->assertCount( 2, $fails );
	}

	public function test_rule_guide_known_and_fallback() {
		$known = RuleGuide::get( 'image-alt' );
		$this->assertTrue( $known['known'] );
		$this->assertNotSame( '', $known['why'] );
		$this->assertNotSame( '', $known['fix'] );

		$fallback = RuleGuide::get(
			'some-new-rule',
			array(
				'help'        => 'Thing must be good',
				'description' => 'Ensures thing is good',
			)
		);
		$this->assertFalse( $fallback['known'] );
		$this->assertSame( 'Thing must be good', $fallback['title'] );
		$this->assertSame( 'x-rule', RuleGuide::get( 'x-rule' )['title'] );
	}
}
