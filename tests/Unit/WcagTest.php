<?php
/**
 * WCAG mapping tests.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use IWorksIE\AccessibilityChecker\Support\Wcag;

/**
 * @covers \IWorksIE\AccessibilityChecker\Support\Wcag
 */
final class WcagTest extends TestCase {

	public function test_maps_axe_tags_to_criteria() {
		$this->assertSame( array( '1.4.3' ), Wcag::criteria_from_tags( array( 'cat.color', 'wcag2aa', 'wcag143' ) ) );
		$this->assertSame( array( '2.4.4', '4.1.2' ), Wcag::criteria_from_tags( array( 'wcag412', 'wcag2a', 'wcag244' ) ) );
		$this->assertSame( array( '1.4.12' ), Wcag::criteria_from_tags( array( 'wcag1412' ) ) );
		$this->assertSame( array( '2.5.8' ), Wcag::criteria_from_tags( array( 'wcag22aa', 'wcag258' ) ) );
	}

	public function test_skips_aaa_obsolete_and_level_tags() {
		$this->assertSame( array(), Wcag::criteria_from_tags( array( 'wcag2aaa', 'wcag146' ) ) );
		$this->assertSame( array(), Wcag::criteria_from_tags( array( 'wcag2a-obsolete', 'wcag411' ) ) );
		$this->assertSame( array(), Wcag::criteria_from_tags( array( 'wcag21aa', 'best-practice' ) ) );
		$this->assertTrue( Wcag::is_best_practice( array( 'best-practice', 'cat.semantics' ) ) );
		$this->assertFalse( Wcag::is_best_practice( array( 'wcag111' ) ) );
	}

	public function test_en_301_549_clause() {
		$this->assertSame( '9.1.4.3', Wcag::en_clause( '1.4.3' ) );
		$this->assertSame( '9.4.1.3', Wcag::en_clause( '4.1.3' ) );
		// New in WCAG 2.2: not in EN 301 549 v3.2.1.
		$this->assertSame( '', Wcag::en_clause( '2.5.8' ) );
		$this->assertSame( '', Wcag::en_clause( '9.9.9' ) );
	}

	public function test_automated_list_is_in_catalogue_and_sorted() {
		$catalogue = Wcag::criteria();
		foreach ( Wcag::AUTOMATED as $sc ) {
			$this->assertArrayHasKey( $sc, $catalogue );
		}
		$keys   = array_keys( $catalogue );
		$sorted = $keys;
		usort( $sorted, array( Wcag::class, 'compare' ) );
		$this->assertSame( $sorted, $keys );
		$this->assertCount( 55, $catalogue );
	}

	public function test_impact_weight() {
		$this->assertGreaterThan( Wcag::impact_weight( 'serious' ), Wcag::impact_weight( 'critical' ) );
		$this->assertSame( 0, Wcag::impact_weight( 'bogus' ) );
	}
}
