<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Honeypot Class
 *
 * Creates fake WordPress files to detect reconnaissance attempts.
 * These files return 404 and optionally log access attempts.
 */
class Honeypot {

	/**
	 * Create honeypot files in export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @return array List of created honeypot files.
	 */
	public function create_honeypots( $export_dir ) {
		$honeypots = array(
			'wp-login.php'       => $this->get_honeypot_content( 'wp-login' ),
			'xmlrpc.php'         => $this->get_honeypot_content( 'xmlrpc' ),
			'wp-admin/index.php' => $this->get_honeypot_content( 'wp-admin' )
		);

		$created = array();
		$export_dir = trailingslashit( $export_dir );

		foreach ( $honeypots as $file => $content ) {
			$path = $export_dir . $file;

			// Create directory if needed
			$dir = dirname( $path );
			if ( ! file_exists( $dir ) ) {
				@mkdir( $dir, 0755, true );
			}

			if ( file_put_contents( $path, $content ) !== false ) {
				$created[] = $file;
			}
		}

		return $created;
	}

	/**
	 * Get honeypot file content
	 *
	 * @param string $type Honeypot type (wp-login, xmlrpc, wp-admin).
	 * @return string PHP content for honeypot file.
	 */
	private function get_honeypot_content( $type ) {
		$content = '<?php
/**
 * Honeypot file - ' . esc_attr( $type ) . '
 * This is a static site. If you\'re seeing this, someone is scanning for WordPress.
 */

// Return 404 Not Found
header( "HTTP/1.0 404 Not Found" );

// Optional: Log the attempt
$log_file = __DIR__ . "/honeypot.log";
$timestamp = date( "Y-m-d H:i:s" );
$ip = isset( $_SERVER["REMOTE_ADDR"] ) ? $_SERVER["REMOTE_ADDR"] : "unknown";
$ua = isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : "unknown";
$data = "{$timestamp} - {$ip} - ' . esc_attr( $type ) . ' - {$ua}\n";

// Only log if file is writable (uncomment to enable logging)
// @file_put_contents( $log_file, $data, FILE_APPEND );

exit;
?>';

		return $content;
	}

	/**
	 * Create .htaccess rules to block honeypot access
	 * (Alternative to PHP honeypots for better performance)
	 *
	 * @return string .htaccess rules for blocking.
	 */
	public function get_htaccess_honeypot_rules() {
		$rules = '
# Honeypot Protection - Block WordPress scan attempts
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Log and block wp-login.php attempts
    RewriteCond %{REQUEST_URI} ^/wp-login\.php [NC]
    RewriteRule .* - [F,L]

    # Log and block xmlrpc.php attempts
    RewriteCond %{REQUEST_URI} ^/xmlrpc\.php [NC]
    RewriteRule .* - [F,L]

    # Block wp-admin access
    RewriteCond %{REQUEST_URI} ^/wp-admin [NC]
    RewriteRule .* - [F,L]
</IfModule>
';

		return $rules;
	}

	/**
	 * Check if honeypot log exists and return entries
	 *
	 * @param string $export_dir Export directory path.
	 * @return array Log entries.
	 */
	public function get_honeypot_log( $export_dir ) {
		$log_file = trailingslashit( $export_dir ) . 'honeypot.log';

		if ( ! file_exists( $log_file ) ) {
			return array();
		}

		$log_content = file_get_contents( $log_file );
		$lines = explode( "\n", $log_content );

		$entries = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}

			$parts = explode( ' - ', $line );
			if ( count( $parts ) >= 3 ) {
				$entries[] = array(
					'timestamp' => $parts[0],
					'ip'        => $parts[1],
					'type'      => $parts[2],
					'user_agent' => isset( $parts[3] ) ? $parts[3] : ''
				);
			}
		}

		return $entries;
	}
}
