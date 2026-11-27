<?php
/**
 * Builds the list of URLs for a full-site scan from WordPress itself.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Scan;

use IWorksIE\AccessibilityChecker\Support\Url;

/**
 * Collects home, template samples and published content of the chosen post types.
 */
final class UrlCollector {

	/**
	 * Default page limit for a full scan.
	 */
	const DEFAULT_MAX = 500;

	/**
	 * Hard upper limit.
	 */
	const HARD_MAX = 5000;

	/**
	 * Public post types that can be scanned, with published counts.
	 *
	 * @return array<string,array{label:string,count:int}>
	 */
	public static function post_types() {
		$out   = array();
		$types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);
		foreach ( $types as $slug => $type ) {
			if ( 'attachment' === $slug ) {
				continue;
			}
			$counts = wp_count_posts( $slug );
			$count  = isset( $counts->publish ) ? (int) $counts->publish : 0;
			if ( 0 === $count ) {
				continue;
			}
			$out[ $slug ] = array(
				'label' => (string) $type->labels->name,
				'count' => $count,
			);
		}
		return $out;
	}

	/**
	 * Build the queue items.
	 *
	 * @param array<int,string> $sources 'home', 'templates', and post type slugs.
	 * @param int               $max     Max URLs.
	 * @return array<int,array{url:string,label:string,type:string,id:int}>
	 */
	public static function collect( array $sources, $max ) {
		$max   = max( 1, min( self::HARD_MAX, (int) $max ) );
		$items = array();
		$seen  = array();

		$add = static function ( $url, $label, $type, $id ) use ( &$items, &$seen, $max ) {
			if ( count( $items ) >= $max || ! is_string( $url ) || '' === $url ) {
				return;
			}
			$url = Url::normalize( $url );
			if ( isset( $seen[ $url ] ) || ! Url::is_same_site( $url ) ) {
				return;
			}
			$seen[ $url ] = true;
			$items[]      = array(
				'url'   => $url,
				'label' => wp_strip_all_tags( (string) $label ),
				'type'  => (string) $type,
				'id'    => (int) $id,
			);
		};

		if ( in_array( 'home', $sources, true ) ) {
			$add( home_url( '/' ), __( 'Home page', 'iworksie-accessibility-checker' ), 'home', 0 );
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( 'page' === get_option( 'show_on_front' ) && $posts_page > 0 ) {
				$add( get_permalink( $posts_page ), get_the_title( $posts_page ), 'page', $posts_page );
			}
		}

		if ( in_array( 'templates', $sources, true ) ) {
			foreach ( self::template_samples() as $sample ) {
				$add( $sample['url'], $sample['label'], $sample['type'], $sample['id'] );
			}
		}

		$types = self::post_types();
		foreach ( $sources as $source ) {
			if ( count( $items ) >= $max ) {
				break;
			}
			if ( ! isset( $types[ $source ] ) ) {
				continue;
			}
			$ids = get_posts(
				array(
					'post_type'              => $source,
					'post_status'            => 'publish',
					'has_password'           => false,
					'fields'                 => 'ids',
					'posts_per_page'         => $max - count( $items ),
					'orderby'                => 'date',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'suppress_filters'       => false,
					'update_post_term_cache' => false,
				)
			);
			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				$title   = get_the_title( $post_id );
				$add( get_permalink( $post_id ), '' !== $title ? $title : '#' . $post_id, $source, $post_id );
			}
		}

		return $items;
	}

	/**
	 * One example of each template type: archives, author, search, 404.
	 *
	 * Archives of the same type share a template, so one sample each is enough to find
	 * theme-level problems without scanning every category page.
	 *
	 * @return array<int,array{url:string,label:string,type:string,id:int}>
	 */
	public static function template_samples() {
		$out = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $slug => $type ) {
			if ( empty( $type->has_archive ) ) {
				continue;
			}
			$link = get_post_type_archive_link( $slug );
			if ( $link ) {
				$out[] = array(
					/* translators: %s: post type name, e.g. Products */
					'label' => sprintf( __( 'Archive: %s', 'iworksie-accessibility-checker' ), $type->labels->name ),
					'url'   => $link,
					'type'  => 'archive',
					'id'    => 0,
				);
			}
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' === $tax->name ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'number'     => 1,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'hide_empty' => true,
				)
			);
			if ( ! is_array( $terms ) || empty( $terms ) ) {
				continue;
			}
			$link = get_term_link( $terms[0] );
			if ( is_string( $link ) ) {
				$out[] = array(
					/* translators: 1: taxonomy name, e.g. Categories, 2: term name */
					'label' => sprintf( __( '%1$s: %2$s', 'iworksie-accessibility-checker' ), $tax->labels->singular_name, $terms[0]->name ),
					'url'   => $link,
					'type'  => 'term',
					'id'    => (int) $terms[0]->term_id,
				);
			}
		}

		$latest = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $latest ) ) {
			$author = (int) $latest[0]->post_author;
			$out[]  = array(
				'label' => __( 'Author archive', 'iworksie-accessibility-checker' ),
				'url'   => get_author_posts_url( $author ),
				'type'  => 'author',
				'id'    => $author,
			);
		}

		$out[] = array(
			'label' => __( 'Search results', 'iworksie-accessibility-checker' ),
			'url'   => add_query_arg( 's', 'a', home_url( '/' ) ),
			'type'  => 'search',
			'id'    => 0,
		);
		$out[] = array(
			'label' => __( 'Page not found (404)', 'iworksie-accessibility-checker' ),
			'url'   => home_url( '/iwieac-page-not-found-check/' ),
			'type'  => '404',
			'id'    => 0,
		);

		return $out;
	}

	/**
	 * Describe a single URL for the "check one page" form.
	 *
	 * @param string $url URL on this site.
	 * @return array{url:string,label:string,type:string,id:int}
	 */
	public static function describe( $url ) {
		$url     = Url::normalize( $url );
		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			$title = get_the_title( $post_id );
			return array(
				'url'   => $url,
				'label' => '' !== $title ? wp_strip_all_tags( $title ) : '#' . $post_id,
				'type'  => (string) get_post_type( $post_id ),
				'id'    => (int) $post_id,
			);
		}
		if ( untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) {
			return array(
				'url'   => $url,
				'label' => __( 'Home page', 'iworksie-accessibility-checker' ),
				'type'  => 'home',
				'id'    => 0,
			);
		}
		return array(
			'url'   => $url,
			'label' => Url::path( $url ),
			'type'  => 'url',
			'id'    => 0,
		);
	}
}
