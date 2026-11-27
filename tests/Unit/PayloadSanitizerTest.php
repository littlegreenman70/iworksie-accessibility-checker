<?php
/**
 * Payload sanitiser tests.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Tests\Unit;

use IWorksIE\AccessibilityChecker\Scan\PayloadSanitizer;

/**
 * @covers \IWorksIE\AccessibilityChecker\Scan\PayloadSanitizer
 */
final class PayloadSanitizerTest extends TestCase {

	/**
	 * @param array<string,mixed> $data Payload.
	 * @return array<string,mixed>|null
	 */
	private function run_payload( array $data ) {
		return PayloadSanitizer::sanitize( (string) json_encode( $data ) );
	}

	public function test_rejects_garbage() {
		$this->assertNull( PayloadSanitizer::sanitize( '' ) );
		$this->assertNull( PayloadSanitizer::sanitize( 'not json' ) );
		$this->assertNull( PayloadSanitizer::sanitize( '"string"' ) );
		$this->assertNull( PayloadSanitizer::sanitize( str_repeat( ' ', PayloadSanitizer::MAX_BYTES + 1 ) ) );
	}

	public function test_keeps_valid_rule_and_normalises_fields() {
		$out = $this->run_payload(
			array(
				'engine'     => '4.14.1',
				'passes'     => 40,
				'violations' => array(
					array(
						'id'          => 'image-alt',
						'impact'      => 'critical',
						'help'        => 'Images must have alternative text',
						'description' => "Ensures <img> elements have alt text\x07",
						'helpUrl'     => 'https://dequeuniversity.com/rules/axe/4.14/image-alt',
						'tags'        => array( 'wcag2a', 'wcag111', 'bad tag!' ),
						'n'           => 3,
						'nodes'       => array(
							array(
								't' => 'img.logo',
								'h' => '<img src="x.png">',
								's' => "Fix any of the following:\n  Element does not have an alt attribute",
							),
						),
					),
				),
			)
		);
		$this->assertIsArray( $out );
		$this->assertSame( '4.14.1', $out['engine'] );
		$this->assertSame( 40, $out['passes'] );
		$rule = $out['violations'][0];
		$this->assertSame( 'image-alt', $rule['id'] );
		$this->assertSame( array( 'wcag2a', 'wcag111' ), $rule['tags'] );
		$this->assertSame( 3, $rule['n'] );
		$this->assertStringNotContainsString( "\x07", $rule['description'] );
		$this->assertStringContainsString( "\n", $rule['nodes'][0]['s'] );
		$this->assertSame( '<img src="x.png">', $rule['nodes'][0]['h'] );
	}

	public function test_drops_bad_ids_impacts_urls_and_empty_rules() {
		$out = $this->run_payload(
			array(
				'engine'     => '<script>',
				'violations' => array(
					array(
						'id'    => 'Bad Id',
						'nodes' => array( array( 't' => 'a' ) ),
					),
					array(
						'id'      => 'link-name',
						'impact'  => 'apocalyptic',
						'helpUrl' => 'javascript:alert(1)',
						'nodes'   => array( array( 't' => 'a.icon' ) ),
					),
					array(
						'id'    => 'button-name',
						'nodes' => array( array( 't' => '' ) ),
					),
					array(
						'id'    => 'link-name',
						'nodes' => array( array( 't' => 'a.duplicate' ) ),
					),
				),
			)
		);
		$this->assertSame( '', $out['engine'] );
		$this->assertCount( 1, $out['violations'] );
		$this->assertSame( 'link-name', $out['violations'][0]['id'] );
		$this->assertSame( '', $out['violations'][0]['impact'] );
		$this->assertSame( '', $out['violations'][0]['help_url'] );
		$this->assertSame( array(), $out['incomplete'] );
	}

	public function test_caps_nodes_and_lengths() {
		$nodes = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$nodes[] = array(
				't' => 'li:nth-child(' . $i . ')',
				'h' => str_repeat( 'x', 2000 ),
			);
		}
		$out  = $this->run_payload(
			array(
				'violations' => array(
					array(
						'id'    => 'list',
						'n'     => 50,
						'nodes' => $nodes,
					),
				),
			)
		);
		$rule = $out['violations'][0];
		$this->assertCount( PayloadSanitizer::MAX_NODES, $rule['nodes'] );
		$this->assertSame( 50, $rule['n'] );
		$this->assertSame( 400, strlen( $rule['nodes'][0]['h'] ) );
	}

	public function test_clean_text_matches_stored_targets() {
		$this->assertSame( 'div > a[href="x"]', PayloadSanitizer::clean_text( "  div > a[href=\"x\"]\t", 512 ) );
	}
}
