<?php
/**
 * Validates the trimmed axe-core results posted from the admin's browser.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Scan;

/**
 * Turns untrusted JSON into a strict, size-limited structure.
 */
final class PayloadSanitizer {

	/**
	 * Largest accepted JSON body in bytes.
	 */
	const MAX_BYTES = 3145728;

	/**
	 * Most rules accepted per result type.
	 */
	const MAX_RULES = 200;

	/**
	 * Most elements stored per rule (the real count is kept separately).
	 */
	const MAX_NODES = 30;

	/**
	 * Allowed impact values.
	 *
	 * @var array<int,string>
	 */
	const IMPACTS = array( 'critical', 'serious', 'moderate', 'minor' );

	/**
	 * Parse and sanitise a JSON payload.
	 *
	 * @param string $json Raw JSON string.
	 * @return array{engine:string,passes:int,violations:array<int,array<string,mixed>>,incomplete:array<int,array<string,mixed>>}|null Null when invalid.
	 */
	public static function sanitize( $json ) {
		if ( ! is_string( $json ) || '' === $json || strlen( $json ) > self::MAX_BYTES ) {
			return null;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		$engine = isset( $data['engine'] ) && is_string( $data['engine'] ) ? $data['engine'] : '';
		if ( ! preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/', $engine ) ) {
			$engine = '';
		}

		return array(
			'engine'     => $engine,
			'passes'     => isset( $data['passes'] ) ? max( 0, min( 10000, (int) $data['passes'] ) ) : 0,
			'violations' => self::rules( isset( $data['violations'] ) ? $data['violations'] : array() ),
			'incomplete' => self::rules( isset( $data['incomplete'] ) ? $data['incomplete'] : array() ),
		);
	}

	/**
	 * Sanitise a list of rule results.
	 *
	 * @param mixed $items Raw list.
	 * @return array<int,array<string,mixed>>
	 */
	private static function rules( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out  = array();
		$seen = array();
		foreach ( array_slice( array_values( $items ), 0, self::MAX_RULES ) as $rule ) {
			if ( ! is_array( $rule ) || ! isset( $rule['id'] ) || ! is_string( $rule['id'] ) ) {
				continue;
			}
			$id = $rule['id'];
			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,99}$/', $id ) || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;

			$impact = isset( $rule['impact'] ) && is_string( $rule['impact'] ) && in_array( $rule['impact'], self::IMPACTS, true ) ? $rule['impact'] : '';

			$tags = array();
			if ( isset( $rule['tags'] ) && is_array( $rule['tags'] ) ) {
				foreach ( array_slice( $rule['tags'], 0, 40 ) as $tag ) {
					if ( is_string( $tag ) && preg_match( '/^[A-Za-z0-9.-]{1,40}$/', $tag ) ) {
						$tags[] = $tag;
					}
				}
			}

			$help_url = isset( $rule['helpUrl'] ) && is_string( $rule['helpUrl'] ) ? $rule['helpUrl'] : '';
			// Only keep links to Deque's rule documentation, which is what axe-core provides.
			if ( 0 !== strpos( $help_url, 'https://dequeuniversity.com/' ) ) {
				$help_url = '';
			} else {
				$help_url = esc_url_raw( $help_url, array( 'https' ) );
			}

			$nodes      = array();
			$raw_nodes  = isset( $rule['nodes'] ) && is_array( $rule['nodes'] ) ? array_values( $rule['nodes'] ) : array();
			$node_count = isset( $rule['n'] ) ? (int) $rule['n'] : count( $raw_nodes );
			foreach ( array_slice( $raw_nodes, 0, self::MAX_NODES ) as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				$target = self::text( isset( $node['t'] ) ? $node['t'] : '', 512 );
				if ( '' === $target ) {
					continue;
				}
				$nodes[] = array(
					't' => $target,
					'h' => self::text( isset( $node['h'] ) ? $node['h'] : '', 400 ),
					's' => self::text( isset( $node['s'] ) ? $node['s'] : '', 1000, true ),
				);
			}
			if ( empty( $nodes ) ) {
				continue;
			}

			$out[] = array(
				'id'          => $id,
				'impact'      => $impact,
				'help'        => self::text( isset( $rule['help'] ) ? $rule['help'] : '', 300 ),
				'description' => self::text( isset( $rule['description'] ) ? $rule['description'] : '', 600 ),
				'help_url'    => $help_url,
				'tags'        => $tags,
				'n'           => max( count( $nodes ), min( 100000, $node_count ) ),
				'nodes'       => $nodes,
			);
		}
		return $out;
	}

	/**
	 * Public wrapper used for values that must match stored element selectors exactly.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	public static function clean_text( $value, $max ) {
		return self::text( $value, $max );
	}

	/**
	 * Plain text, valid UTF-8, no control characters, length-limited. Escaped again on output.
	 *
	 * @param mixed $value     Raw value.
	 * @param int   $max       Max characters.
	 * @param bool  $multiline Keep newlines.
	 * @return string
	 */
	private static function text( $value, $max, $multiline = false ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = wp_check_invalid_utf8( $value, true );
		$value = $multiline
			? (string) preg_replace( '/[^\P{C}\n]+/u', '', $value )
			: (string) preg_replace( '/\p{C}+/u', ' ', $value );
		$value = trim( $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max );
		}
		return substr( $value, 0, $max );
	}
}
