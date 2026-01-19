<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Metadata Cleaner Class
 *
 * Removes sensitive files and metadata that could reveal system information.
 * Also handles cache busting for static assets.
 */
class Metadata_Cleaner {

	/**
	 * Remove sensitive files from export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @return array List of removed files.
	 */
	public function remove_sensitive_files( $export_dir ) {
		$dangerous_files = array(
			'wp-config.php',
			'readme.html',
			'license.txt',
			'.git',
			'.svn',
			'.env',
			'.env.local',
			'.env.production',
			'composer.json',
			'composer.lock',
			'package.json',
			'package-lock.json',
			'yarn.lock',
			'.DS_Store',
			'Thumbs.db',
			'.htaccess.old',
			'error_log',
			'debug.log',
			'wp-config-sample.php',
			'.gitignore',
			'.gitattributes'
		);

		$removed = array();
		$export_dir = trailingslashit( $export_dir );

		foreach ( $dangerous_files as $file ) {
			$path = $export_dir . $file;

			if ( file_exists( $path ) ) {
				if ( is_dir( $path ) ) {
					if ( $this->recursive_delete( $path ) ) {
						$removed[] = $file;
					}
				} else {
					if ( @unlink( $path ) ) {
						$removed[] = $file;
					}
				}
			}
		}

		return $removed;
	}

	/**
	 * Recursively delete a directory
	 *
	 * @param string $dir Directory path.
	 * @return boolean Success status.
	 */
	private function recursive_delete( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return false;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->recursive_delete( $path );
			} else {
				@unlink( $path );
			}
		}

		return @rmdir( $dir );
	}

	/**
	 * Add cache busting to asset URLs in HTML
	 *
	 * @param string $html HTML content.
	 * @return string HTML with cache busting.
	 */
	public function add_cache_busting( $html ) {
		$site_hash = $this->get_site_hash();

		// Add to CSS/JS files
		$html = preg_replace_callback(
			'/(href|src)="([^"]+\.(css|js))"/',
			function( $matches ) use ( $site_hash ) {
				$attr = $matches[1];
				$url  = $matches[2];

				// Skip external URLs
				if ( $this->is_external_url( $url ) ) {
					return $matches[0];
				}

				// Add version hash
				$separator = strpos( $url, '?' ) !== false ? '&' : '?';
				return $attr . '="' . $url . $separator . 'v=' . $site_hash . '"';
			},
			$html
		);

		return $html;
	}

	/**
	 * Get consistent site hash for cache busting
	 *
	 * @return string 8-character hash.
	 */
	private function get_site_hash() {
		// Use site URL + auth key for consistent but unique hash
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'simply-static';
		return substr( md5( get_site_url() . $auth_key ), 0, 8 );
	}

	/**
	 * Check if URL is external
	 *
	 * @param string $url URL to check.
	 * @return boolean True if external.
	 */
	private function is_external_url( $url ) {
		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			$site_url = get_site_url();
			return strpos( $url, $site_url ) === false;
		}

		return false;
	}

	/**
	 * Set secure file permissions on export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @return array Statistics about permissions set.
	 */
	public function set_secure_permissions( $export_dir ) {
		$stats = array(
			'directories' => 0,
			'files'       => 0,
			'errors'      => 0
		);

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $export_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			try {
				if ( $item->isDir() ) {
					if ( @chmod( $item->getPathname(), 0755 ) ) {
						$stats['directories']++;
					} else {
						$stats['errors']++;
					}
				} else {
					if ( @chmod( $item->getPathname(), 0644 ) ) {
						$stats['files']++;
					} else {
						$stats['errors']++;
					}
				}
			} catch ( \Exception $e ) {
				$stats['errors']++;
			}
		}

		return $stats;
	}

	/**
	 * Scan for potential security issues in export
	 *
	 * @param string $export_dir Export directory path.
	 * @return array List of potential issues found.
	 */
	public function scan_for_issues( $export_dir ) {
		$issues = array();

		// Check for common sensitive files
		$sensitive_patterns = array(
			'*.sql',
			'*.bak',
			'*.backup',
			'*config.php',
			'*.log'
		);

		foreach ( $sensitive_patterns as $pattern ) {
			$files = glob( trailingslashit( $export_dir ) . $pattern );
			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					$issues[] = array(
						'type' => 'sensitive_file',
						'file' => basename( $file ),
						'severity' => 'high'
					);
				}
			}
		}

		return $issues;
	}
}
