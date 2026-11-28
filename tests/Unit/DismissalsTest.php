<?php
/**
 * Dismissal matching tests.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use IWorksIE\AccessibilityChecker\Data\Dismissals;

/**
 * @covers \IWorksIE\AccessibilityChecker\Data\Dismissals
 */
final class DismissalsTest extends TestCase {

	/**
	 * @param string $rule   Rule.
	 * @param string $target Target.
	 * @param int    $page   Page id.
	 * @param string $reason Reason.
	 * @return array<string,mixed>
	 */
	private function row( $rule, $target, $page, $reason = 'false_positive' ) {
		return array(
			'rule_id' => $rule,
			'target'  => $target,
			'page_id' => $page,
			'reason'  => $reason,
		);
	}

	public function test_page_specific_wins_over_site_wide() {
		$map = Dismissals::to_map(
			array(
				$this->row( 'color-contrast', '.footer a', 5, 'confirmed' ),
				$this->row( 'color-contrast', '.footer a', 0, 'third_party' ),
			)
		);
		$this->assertSame( 'confirmed', $map['color-contrast']['.footer a']['reason'] );
	}

	public function test_match_exact_and_wildcard() {
		$map = Dismissals::to_map(
			array(
				$this->row( 'region', '*', 0 ),
				$this->row( 'image-alt', 'img.logo', 0 ),
			)
		);
		$this->assertNotNull( Dismissals::match( $map, 'region', 'div.cookie' ) );
		$this->assertNotNull( Dismissals::match( $map, 'image-alt', 'img.logo' ) );
		$this->assertNull( Dismissals::match( $map, 'image-alt', 'img.hero' ) );
		$this->assertNull( Dismissals::match( $map, 'link-name', 'a' ) );
	}

	public function test_active_count() {
		$map   = Dismissals::to_map( array( $this->row( 'image-alt', 'img.logo', 0 ) ) );
		$nodes = array( array( 't' => 'img.logo' ), array( 't' => 'img.hero' ) );
		$this->assertSame( 1, Dismissals::active_count( $map, 'image-alt', $nodes, 2 ) );
		// Elements beyond the stored 30 stay active.
		$this->assertSame( 39, Dismissals::active_count( $map, 'image-alt', $nodes, 40 ) );
		$this->assertSame( 2, Dismissals::active_count( $map, 'link-name', $nodes, 2 ) );

		$all = Dismissals::to_map( array( $this->row( 'image-alt', '*', 0 ) ) );
		$this->assertSame( 0, Dismissals::active_count( $all, 'image-alt', $nodes, 40 ) );
	}

	public function test_reasons_are_translatable_keys() {
		$this->assertSame( array( 'false_positive', 'confirmed', 'third_party', 'not_relevant' ), array_keys( Dismissals::reasons() ) );
	}
}
