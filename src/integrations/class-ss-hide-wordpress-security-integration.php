<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Hide WordPress Security Integration
 *
 * Orchestrates all WordPress fingerprint removal and security hardening features.
 * This integration ensures the static site gives zero indication it was generated from WordPress.
 */
class Hide_WordPress_Security_Integration extends Integration {

	/**
	 * Integration ID
	 *
	 * @var string
	 */
	protected $id = 'hide-wordpress-security';

	/**
	 * Integration name
	 *
	 * @var string
	 */
	protected $name = 'Hide WordPress & Advanced Security';

	/**
	 * Integration description
	 *
	 * @var string
	 */
	protected $description = 'Remove all WordPress fingerprints and add security hardening to static exports.';

	/**
	 * Always active by default
	 *
	 * @var boolean
	 */
	protected $always_active = false;

	/**
	 * Active by default
	 *
	 * @var boolean
	 */
	protected $active_by_default = true;

	/**
	 * Security instances
	 *
	 * @var array
	 */
	private $hide_wp;
	private $path_mapper;
	private $security_headers;
	private $metadata_cleaner;
	private $honeypot;

	/**
	 * Run the integration
	 *
	 * @return void
	 */
	public function run() {
		// Initialize security classes
		$this->hide_wp = new Hide_WordPress();
		$this->path_mapper = new Path_Mapper();
		$this->security_headers = new Security_Headers();
		$this->metadata_cleaner = new Metadata_Cleaner();
		$this->honeypot = new Honeypot();

		// Hook into export process
		$this->setup_hooks();
	}

	/**
	 * Setup hooks for export process
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Before export starts
		add_action( 'ss_before_static_export', array( $this, 'before_export' ), 10, 2 );

		// Filter HTML content before saving
		add_filter( 'simply_static_content_before_save', array( $this, 'process_html_content' ), 999, 2 );

		// After export completes
		add_action( 'ss_completed', array( $this, 'after_export' ), 10, 2 );
	}

	/**
	 * Before export starts - initialize
	 *
	 * @param int    $blog_id Blog ID.
	 * @param string $type    Export type.
	 * @return void
	 */
	public function before_export( $blog_id, $type ) {
		$options = Options::instance();

		// Clear path mapping cache if starting fresh export
		if ( $options->get( 'clean_paths' ) ) {
			$this->path_mapper->clear_cache();
			Util::debug_log( 'Hide WordPress Security: Path mapping cache cleared' );
		}

		Util::debug_log( 'Hide WordPress Security: Integration initialized for export' );
	}

	/**
	 * Process HTML content before saving
	 *
	 * @param string $content  Content to process.
	 * @param object $extractor URL_Extractor instance.
	 * @return string Processed content.
	 */
	public function process_html_content( $content, $extractor ) {
		$options = Options::instance();

		// Skip if not HTML
		if ( ! $extractor || ! method_exists( $extractor, 'get_url' ) ) {
			return $content;
		}

		$url = $extractor->get_url();

		// Clean WordPress fingerprints from HTML
		$content = $this->hide_wp->clean_html( $content, $url );

		// Rewrite paths if enabled
		if ( $options->get( 'clean_paths' ) ) {
			$content = $this->path_mapper->rewrite_html_paths( $content );
		}

		// Add cache busting if enabled
		if ( $options->get( 'cache_busting' ) ) {
			$content = $this->metadata_cleaner->add_cache_busting( $content );
		}

		return $content;
	}

	/**
	 * After export completes - finalize security
	 *
	 * @param string $status  Export status.
	 * @param string $message Export message.
	 * @return void
	 */
	public function after_export( $status, $message ) {
		// Only run if export succeeded
		if ( $status !== 'finished' ) {
			return;
		}

		$options = Options::instance();
		$export_dir = $this->get_export_directory();

		if ( ! $export_dir || ! file_exists( $export_dir ) ) {
			Util::debug_log( 'Hide WordPress Security: Export directory not found' );
			return;
		}

		Util::debug_log( 'Hide WordPress Security: Finalizing security for: ' . $export_dir );

		// Generate .htaccess if enabled
		if ( $options->get( 'security_headers' ) ) {
			if ( $this->security_headers->write_htaccess( $export_dir ) ) {
				Util::debug_log( 'Hide WordPress Security: .htaccess created' );
			}
		}

		// Generate custom 404 if enabled
		if ( $options->get( 'custom_404' ) ) {
			if ( $this->security_headers->write_404_page( $export_dir ) ) {
				Util::debug_log( 'Hide WordPress Security: Custom 404 page created' );
			}
		}

		// Generate robots.txt if enabled
		if ( $options->get( 'custom_robots' ) ) {
			if ( $this->security_headers->write_robots_txt( $export_dir ) ) {
				Util::debug_log( 'Hide WordPress Security: robots.txt created' );
			}
		}

		// Create honeypots if enabled
		if ( $options->get( 'honeypots' ) ) {
			$created = $this->honeypot->create_honeypots( $export_dir );
			if ( ! empty( $created ) ) {
				Util::debug_log( 'Hide WordPress Security: Honeypots created: ' . implode( ', ', $created ) );
			}
		}

		// Remove sensitive files (always run)
		$removed = $this->metadata_cleaner->remove_sensitive_files( $export_dir );
		if ( ! empty( $removed ) ) {
			Util::debug_log( 'Hide WordPress Security: Sensitive files removed: ' . implode( ', ', $removed ) );
		}

		// Set secure permissions if enabled
		if ( $options->get( 'secure_permissions' ) ) {
			$stats = $this->metadata_cleaner->set_secure_permissions( $export_dir );
			Util::debug_log( 'Hide WordPress Security: Permissions set - ' . $stats['directories'] . ' directories, ' . $stats['files'] . ' files' );
		}

		Util::debug_log( 'Hide WordPress Security: Finalization complete' );
	}

	/**
	 * Get export directory path
	 *
	 * @return string|false Export directory path or false.
	 */
	private function get_export_directory() {
		$options = Options::instance();
		$delivery_method = $options->get( 'delivery_method' );

		if ( $delivery_method === 'local' ) {
			$local_dir = $options->get( 'local_dir' );
			return apply_filters( 'ss_local_dir', $local_dir );
		} elseif ( $delivery_method === 'zip' ) {
			// For ZIP exports, we need to get the temp directory
			return $options->get( 'archive_dir' );
		}

		return false;
	}
}
