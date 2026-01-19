<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Path Mapper Class
 *
 * Consistently maps WordPress paths to clean, generic paths.
 * CRITICAL: Must be deterministic - same input always produces same output.
 */
class Path_Mapper {

	/**
	 * Path mapping rules
	 *
	 * @var array
	 */
	private $mappings = array(
		'/wp-content/themes/'  => '/assets/theme/',
		'/wp-content/uploads/' => '/media/',
		'/wp-content/plugins/' => '/assets/plugins/',
		'/wp-includes/'        => '/assets/core/'
	);

	/**
	 * Get clean path for an original WordPress path
	 *
	 * @param string $original_path Original WordPress path.
	 * @return string Clean mapped path.
	 */
	public function get_clean_path( $original_path ) {
		// Check if we've already mapped this path
		$cached = Path_Mapping::get_clean_path( $original_path );
		if ( $cached ) {
			return $cached;
		}

		$clean_path = $original_path;

		// Apply mapping rules
		foreach ( $this->mappings as $old => $new ) {
			if ( strpos( $clean_path, $old ) !== false ) {
				$clean_path = str_replace( $old, $new, $clean_path );
				break;
			}
		}

		// Store mapping for consistency
		Path_Mapping::save_mapping( $original_path, $clean_path );

		return $clean_path;
	}

	/**
	 * Rewrite paths in HTML content
	 *
	 * @param string $html HTML content with WordPress paths.
	 * @return string HTML with rewritten paths.
	 */
	public function rewrite_html_paths( $html ) {
		// Rewrite asset paths in src/href attributes
		$html = preg_replace_callback(
			'/(src|href|srcset|data-src|data-href)=(["\'])([^"\']+)\2/',
			function( $matches ) {
				$attr  = $matches[1];
				$quote = $matches[2];
				$url   = $matches[3];

				// Handle srcset which can have multiple URLs
				if ( $attr === 'srcset' ) {
					$url = $this->rewrite_srcset( $url );
				} else {
					// Only rewrite local URLs
					if ( $this->is_local_url( $url ) ) {
						$url = $this->get_clean_path( $url );
					}
				}

				return $attr . '=' . $quote . $url . $quote;
			},
			$html
		);

		// Rewrite background images in inline styles
		$html = preg_replace_callback(
			'/url\(["\']?([^)"\'\s]+)["\']?\)/',
			function( $matches ) {
				$url = $matches[1];
				if ( $this->is_local_url( $url ) ) {
					$url = $this->get_clean_path( $url );
				}
				return 'url(' . $url . ')';
			},
			$html
		);

		return $html;
	}

	/**
	 * Rewrite srcset attribute value (can contain multiple URLs)
	 *
	 * @param string $srcset Srcset attribute value.
	 * @return string Rewritten srcset value.
	 */
	private function rewrite_srcset( $srcset ) {
		$parts = explode( ',', $srcset );
		$rewritten = array();

		foreach ( $parts as $part ) {
			$part = trim( $part );
			// Split URL and descriptor (e.g., "image.jpg 300w")
			$pieces = preg_split( '/\s+/', $part );

			if ( ! empty( $pieces[0] ) && $this->is_local_url( $pieces[0] ) ) {
				$pieces[0] = $this->get_clean_path( $pieces[0] );
			}

			$rewritten[] = implode( ' ', $pieces );
		}

		return implode( ', ', $rewritten );
	}

	/**
	 * Rewrite paths in CSS content
	 *
	 * @param string $css CSS content with WordPress paths.
	 * @return string CSS with rewritten paths.
	 */
	public function rewrite_css_paths( $css ) {
		// Rewrite url() references in CSS
		$css = preg_replace_callback(
			'/url\(["\']?([^)"\'\s]+)["\']?\)/',
			function( $matches ) {
				$url = $matches[1];
				if ( $this->is_local_url( $url ) ) {
					$url = $this->get_clean_path( $url );
				}
				return 'url(' . $url . ')';
			},
			$css
		);

		return $css;
	}

	/**
	 * Check if URL is local (should be rewritten)
	 *
	 * @param string $url URL to check.
	 * @return boolean True if local URL.
	 */
	private function is_local_url( $url ) {
		// Empty URLs
		if ( empty( $url ) ) {
			return false;
		}

		// Data URIs, anchors, etc.
		if ( strpos( $url, 'data:' ) === 0 || strpos( $url, '#' ) === 0 ) {
			return false;
		}

		// External URLs that don't contain site URL
		if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
			$site_url = get_site_url();
			return strpos( $url, $site_url ) !== false;
		}

		// Protocol-relative URLs
		if ( strpos( $url, '//' ) === 0 ) {
			$site_host = parse_url( get_site_url(), PHP_URL_HOST );
			return strpos( $url, $site_host ) !== false;
		}

		// Relative URLs are local
		return true;
	}

	/**
	 * Get physical file path mapping (for copying files)
	 *
	 * @param string $source_path Source file path.
	 * @return string Destination file path.
	 */
	public function get_file_destination( $source_path ) {
		// Get the relative path from WordPress root
		$wp_root = ABSPATH;
		$relative = str_replace( $wp_root, '/', $source_path );

		// Apply path mapping
		return $this->get_clean_path( $relative );
	}

	/**
	 * Clear all cached path mappings
	 * Useful when starting a fresh export with new settings.
	 *
	 * @return void
	 */
	public function clear_cache() {
		Path_Mapping::clear_all();
	}
}
