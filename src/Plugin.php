<?php
/**
 * Plugin bootstrap.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker;

use IWorksIE\AccessibilityChecker\Admin\Ajax;
use IWorksIE\AccessibilityChecker\Admin\Screens;
use IWorksIE\AccessibilityChecker\Data\Store;
use IWorksIE\AccessibilityChecker\Frontend\Frontend;
use IWorksIE\AccessibilityChecker\Install\Schema;
use IWorksIE\AccessibilityChecker\Scan\Queue;
use IWorksIE\AccessibilityChecker\Scan\UrlCollector;
use IWorksIE\AccessibilityChecker\Support\Settings;
use IWorksIE\AccessibilityChecker\Support\Url;
use IWorksIE\AccessibilityChecker\Support\Wcag;
use IWorksIE\AccessibilityChecker\Vendor\IWorksIE\Core\Core;

/**
 * Singleton plugin entry.
 */
final class Plugin {

	/**
	 * Bundled axe-core version (assets/vendor/axe-core/axe.min.js).
	 */
	const AXE_VERSION = '4.14.1';

	/**
	 * Admin page slug.
	 */
	const PAGE_SLUG = 'iworksie-accessibility-checker';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var Core|null
	 */
	private $core = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Capability needed for every feature.
	 *
	 * Site administrators (not only network admins) can check their own site on multisite.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to use the Accessibility Checker.
		 *
		 * @param string $capability Default manage_options.
		 */
		return (string) apply_filters( 'iwieac_capability', 'manage_options' );
	}

	/**
	 * @return Core|null
	 */
	public function core() {
		return $this->core;
	}

	/**
	 * Boot core, menu, AJAX and front-end hooks.
	 *
	 * @return void
	 */
	public function boot() {
		if ( null !== $this->core ) {
			return;
		}

		$this->core = Core::boot(
			array(
				'plugin_file' => IWIEAC_FILE,
				'slug'        => self::PAGE_SLUG,
				'prefix'      => 'iwieac',
				'name'        => __( 'Accessibility Checker', 'iworksie-accessibility-checker' ),
				'version'     => IWIEAC_VERSION,
				'text_domain' => 'iworksie-accessibility-checker',
				'capability'  => self::capability(),
				'wporg_url'   => 'https://wordpress.org/plugins/iworksie-accessibility-checker/',
				'description' => __( 'Full-site accessibility scan with axe-core, plain-English fixes and a WCAG 2.2 AA / EN 301 549 report.', 'iworksie-accessibility-checker' ),
				'icon'        => 'dashicons-universal-access-alt',
				'tagline'     => __( 'Checks the real pages your visitors see. No overlay, nothing sent to third parties.', 'iworksie-accessibility-checker' ),
			)
		);

		$screens = new Screens( $this->core );
		$this->core->menu()->add_submenu(
			__( 'Accessibility Checker', 'iworksie-accessibility-checker' ),
			__( 'Accessibility', 'iworksie-accessibility-checker' ),
			self::PAGE_SLUG,
			array( $screens, 'render' )
		);

		$ajax = new Ajax();
		$ajax->hooks();

		$frontend = new Frontend();
		$frontend->hooks();

		add_action( 'admin_init', array( Schema::class, 'maybe_upgrade' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_iwieac_clear_results', array( $this, 'clear_results' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
	}

	/**
	 * Whether a screen hook is this plugin's page.
	 *
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	public static function is_plugin_screen( $hook ) {
		return false !== strpos( (string) $hook, '_page_' . self::PAGE_SLUG );
	}

	/**
	 * Enqueue admin JS/CSS on the plugin page.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( null === $this->core || ! self::is_plugin_screen( $hook ) ) {
			return;
		}

		$settings = Settings::get();
		$queue    = Queue::get();

		wp_enqueue_style( 'iwieac-admin', IWIEAC_URL . 'assets/css/admin.css', array( 'iwieac-iwt-core' ), IWIEAC_VERSION );
		wp_enqueue_script( 'iwieac-admin', IWIEAC_URL . 'assets/js/admin.js', array( 'iwieac-iwt-core', 'wp-i18n' ), IWIEAC_VERSION, true );
		wp_set_script_translations( 'iwieac-admin', 'iworksie-accessibility-checker' );
		wp_localize_script(
			'iwieac-admin',
			'iwieacAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( Ajax::NONCE ),
				'scanNonce'    => wp_create_nonce( 'iwieac_scan' ),
				'axeUrl'       => IWIEAC_URL . 'assets/vendor/axe-core/axe.min.js?ver=' . self::AXE_VERSION,
				'tags'         => Wcag::AXE_TAGS,
				'bestPractice' => $settings['best_practice'],
				'autoScan'     => self::auto_scan_request(),
				'hasQueue'     => null !== $queue && (int) $queue['position'] < count( $queue['items'] ),
				'pageTimeout'  => 45000,
				'strings'      => array(
					'building'      => __( 'Collecting pages to check…', 'iworksie-accessibility-checker' ),
					/* translators: 1: current page number, 2: total pages, 3: page title */
					'scanning'      => __( 'Checking %1$d of %2$d: %3$s', 'iworksie-accessibility-checker' ),
					/* translators: %s: page title */
					'scanningOne'   => __( 'Checking %s…', 'iworksie-accessibility-checker' ),
					/* translators: %d: number of pages */
					'doneSingular'  => __( 'Finished. %d page checked.', 'iworksie-accessibility-checker' ),
					/* translators: %d: number of pages */
					'donePlural'    => __( 'Finished. %d pages checked.', 'iworksie-accessibility-checker' ),
					'doneOne'       => __( 'Finished.', 'iworksie-accessibility-checker' ),
					'cancelled'     => __( 'Stopped. Results so far are saved and you can resume later.', 'iworksie-accessibility-checker' ),
					/* translators: 1: number of problem elements, 2: number of needs-review items */
					'resultLine'    => __( 'Problems: %1$d · Needs review: %2$d', 'iworksie-accessibility-checker' ),
					'resultError'   => __( 'Could not be checked', 'iworksie-accessibility-checker' ),
					'viewDetails'   => __( 'View details', 'iworksie-accessibility-checker' ),
					'failed'        => __( 'Something went wrong. Please reload the page and try again.', 'iworksie-accessibility-checker' ),
					'leaveWarning'  => __( 'A scan is running. Leaving this page will pause it.', 'iworksie-accessibility-checker' ),
					'confirmClear'  => __( 'Delete all scan results? Dismissed items are kept.', 'iworksie-accessibility-checker' ),
					'working'       => __( 'Working…', 'iworksie-accessibility-checker' ),
					'reasonMissing' => __( 'Choose a reason.', 'iworksie-accessibility-checker' ),
				),
			)
		);
	}

	/**
	 * A "check this page" request from a row action or the admin bar (nonce-protected).
	 *
	 * @return array{url:string,label:string,type:string,id:int}|null
	 */
	public static function auto_scan_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below.
		if ( ! isset( $_GET['scan_url'], $_GET['_iwieac'] ) || ! is_string( $_GET['scan_url'] ) || ! is_string( $_GET['_iwieac'] ) ) {
			return null;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['_iwieac'] ) );
		$url   = esc_url_raw( wp_unslash( $_GET['scan_url'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( $nonce, 'iwieac_scan_url' ) || ! current_user_can( self::capability() ) || ! Url::is_same_site( $url ) ) {
			return null;
		}
		return UrlCollector::describe( $url );
	}

	/**
	 * "Check accessibility" row action on published content.
	 *
	 * @param array<string,string> $actions Actions.
	 * @param \WP_Post             $post    Post.
	 * @return array<string,string>
	 */
	public function row_action( $actions, $post ) {
		if ( ! is_array( $actions ) || ! $post instanceof \WP_Post ) {
			return $actions;
		}
		if ( 'publish' !== $post->post_status || ! current_user_can( self::capability() ) || ! is_post_type_viewable( $post->post_type ) || '' !== $post->post_password ) {
			return $actions;
		}
		$link = get_permalink( $post );
		if ( ! is_string( $link ) || '' === $link ) {
			return $actions;
		}
		$actions['iwieac_check'] = '<a href="' . esc_url( Screens::scan_url( $link ) ) . '">' . esc_html__( 'Check accessibility', 'iworksie-accessibility-checker' ) . '</a>';
		return $actions;
	}

	/**
	 * admin-post: delete all results.
	 *
	 * @return void
	 */
	public function clear_results() {
		check_admin_referer( 'iwieac_clear_results' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'iworksie-accessibility-checker' ), 403 );
		}
		Store::clear_all();
		Queue::clear();
		wp_safe_redirect( add_query_arg( 'iwieac_cleared', '1', Screens::tab_url( 'scan' ) ) );
		exit;
	}
}
