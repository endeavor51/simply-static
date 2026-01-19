<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Hide WordPress Class
 *
 * Main orchestrator for WordPress fingerprint removal from static exports.
 * Removes all identifying WordPress metadata, version numbers, and signatures.
 */
class Hide_WordPress {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hooks are registered by the integration class
	}

	/**
	 * Clean HTML output - remove all WordPress fingerprints
	 *
	 * @param string $html HTML content to clean.
	 * @param string $url  URL of the page being processed.
	 * @return string Cleaned HTML.
	 */
	public function clean_html( $html, $url = '' ) {
		// Remove generator meta tags
		$html = preg_replace( '/<meta[^>]+name=["\']generator["\'][^>]*>/i', '', $html );

		// Remove version strings from URLs
		$html = preg_replace( '/\?ver=[\d\.\-a-zA-Z]+/', '', $html );

		// Remove wp-json REST API links
		$html = preg_replace( '/<link[^>]+rel=["\']https:\/\/api\.w\.org\/["\'][^>]*>/i', '', $html );

		// Remove shortlink
		$html = preg_replace( '/<link[^>]+rel=["\']shortlink["\'][^>]*>/i', '', $html );

		// Remove wlwmanifest
		$html = preg_replace( '/<link[^>]+rel=["\']wlwmanifest["\'][^>]*>/i', '', $html );

		// Remove EditURI (RSD)
		$html = preg_replace( '/<link[^>]+rel=["\']EditURI["\'][^>]*>/i', '', $html );

		// Remove RSD link
		$html = preg_replace( '/<link[^>]+type=["\']application\/rsd\+xml["\'][^>]*>/i', '', $html );

		// Remove oEmbed links
		$html = preg_replace( '/<link[^>]+type=["\']application\/json\+oembed["\'][^>]*>/i', '', $html );
		$html = preg_replace( '/<link[^>]+type=["\']text\/xml\+oembed["\'][^>]*>/i', '', $html );

		// Remove DNS prefetch for WordPress CDNs
		$html = preg_replace( '/<link[^>]+rel=["\']dns-prefetch["\'][^>]+href=["\'][^"\']*w\.org[^"\']*["\'][^>]*>/i', '', $html );

		// Remove WordPress emoji scripts
		$html = preg_replace( '/<script[^>]+wp-emoji-release[^>]*>.*?<\/script>/is', '', $html );

		// Remove WordPress emoji styles
		$html = preg_replace( '/<style[^>]*>.*?\.wp-emoji.*?<\/style>/is', '', $html );

		// Remove admin bar
		$html = preg_replace( '/<div[^>]+id=["\']wpadminbar["\'][^>]*>.*?<\/div>/is', '', $html );

		// Remove admin bar CSS/JS
		$html = preg_replace( '/<link[^>]+admin-bar[^>]*>/i', '', $html );
		$html = preg_replace( '/<script[^>]+admin-bar[^>]*>.*?<\/script>/is', '', $html );

		// Remove WordPress HTML comments
		$html = preg_replace( '/<!--.*?WordPress.*?-->/is', '', $html );

		// Remove Gutenberg block comments
		$html = preg_replace( '/<!--\s*wp:[^>]*-->/i', '', $html );
		$html = preg_replace( '/<!--\s*\/wp:[^>]*-->/i', '', $html );

		// Strip WordPress CSS classes if enabled
		if ( $this->get_option( 'strip_wp_classes' ) ) {
			$html = $this->remove_wp_classes( $html );
		}

		// Remove timestamps if enabled
		if ( $this->get_option( 'remove_timestamps' ) ) {
			$html = $this->remove_timestamps( $html );
		}

		return $html;
	}

	/**
	 * Remove WordPress-specific CSS class names
	 *
	 * @param string $html HTML content.
	 * @return string HTML with WordPress classes removed.
	 */
	private function remove_wp_classes( $html ) {
		$wp_patterns = array(
			'wp-block-',
			'wp-',
			'post-',
			'page-',
			'entry-',
			'site-',
			'nav-menu',
			'menu-item',
			'widget'
		);

		foreach ( $wp_patterns as $pattern ) {
			// Remove class names that start with the pattern
			$html = preg_replace(
				'/class="([^"]*)\b' . preg_quote( $pattern, '/' ) . '[^\s"]*/',
				'class="$1',
				$html
			);
		}

		// Clean up empty class attributes
		$html = preg_replace( '/class="\s*"/', '', $html );

		// Clean up multiple spaces in class attributes
		$html = preg_replace_callback( '/class="([^"]+)"/', function( $matches ) {
			return 'class="' . trim( preg_replace( '/\s+/', ' ', $matches[1] ) ) . '"';
		}, $html );

		return $html;
	}

	/**
	 * Remove timestamp-based classes and comments
	 *
	 * @param string $html HTML content.
	 * @return string HTML with timestamps removed.
	 */
	private function remove_timestamps( $html ) {
		// Remove date-based classes
		$html = preg_replace(
			'/class="[^"]*\bdate-\d{4}-\d{2}-\d{2}[^"]*"/i',
			'',
			$html
		);

		// Remove timestamp comments
		$html = preg_replace(
			'/<!--.*?\d{4}-\d{2}-\d{2}.*?-->/i',
			'',
			$html
		);

		return $html;
	}

	/**
	 * Clean CSS output - remove WordPress metadata
	 *
	 * @param string $css       CSS content to clean.
	 * @param string $file_path Path to the CSS file.
	 * @return string Cleaned CSS.
	 */
	public function clean_css( $css, $file_path = '' ) {
		if ( ! $this->get_option( 'clean_metadata' ) ) {
			return $css;
		}

		// Remove theme headers
		$css = preg_replace( '/\/\*\s*Theme Name:.*?\*\//is', '', $css );
		$css = preg_replace( '/\/\*\s*Author:.*?\*\//is', '', $css );
		$css = preg_replace( '/\/\*\s*Version:.*?\*\//is', '', $css );
		$css = preg_replace( '/\/\*\s*Template:.*?\*\//is', '', $css );
		$css = preg_replace( '/\/\*\s*Description:.*?\*\//is', '', $css );
		$css = preg_replace( '/\/\*\s*Text Domain:.*?\*\//is', '', $css );

		return $css;
	}

	/**
	 * Clean JavaScript output - remove WordPress metadata
	 *
	 * @param string $js        JavaScript content to clean.
	 * @param string $file_path Path to the JS file.
	 * @return string Cleaned JavaScript.
	 */
	public function clean_js( $js, $file_path = '' ) {
		if ( ! $this->get_option( 'clean_metadata' ) ) {
			return $js;
		}

		// Remove plugin version comments
		$js = preg_replace( '/\/\*\!.*?Version:.*?\*\//is', '', $js );
		$js = preg_replace( '/\/\*.*?WordPress.*?\*\//is', '', $js );

		return $js;
	}

	/**
	 * Get security option value
	 *
	 * @param string $key Option key (without ss_ prefix).
	 * @return mixed Option value or false.
	 */
	private function get_option( $key ) {
		$options = Options::instance();
		return $options->get( $key );
	}
}
