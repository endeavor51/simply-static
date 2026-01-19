<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Security Headers Class
 *
 * Generates hardened .htaccess and security headers for static exports.
 */
class Security_Headers {

	/**
	 * Generate .htaccess security rules
	 *
	 * @return string .htaccess content.
	 */
	public function generate_htaccess() {
		$timestamp = current_time( 'mysql' );

		$rules = "# Simply Static Security Headers
# Generated: {$timestamp}

# Prevent clickjacking
<IfModule mod_headers.c>
    Header always set X-Frame-Options \"SAMEORIGIN\"
    Header always set X-Content-Type-Options \"nosniff\"
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"

    # Uncomment to force HTTPS (only if using SSL)
    # Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"
</IfModule>

# Remove server signature
ServerSignature Off

# Disable directory listing
Options -Indexes

# Block access to sensitive files
<FilesMatch \"(wp-config\.php|xmlrpc\.php|wp-cron\.php|readme\.html|license\.txt|composer\.json|composer\.lock|package\.json|package-lock\.json|\.env|\.git|\.svn|\.htaccess\.old|error_log|debug\.log)\">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Custom 404 error page
ErrorDocument 404 /404.html

# Prevent access to hidden files
<FilesMatch \"^\\.\">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable PHP execution in upload directories
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>

# END Simply Static Security Headers
";

		return $rules;
	}

	/**
	 * Write .htaccess file to export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @return boolean Success status.
	 */
	public function write_htaccess( $export_dir ) {
		$htaccess_path = trailingslashit( $export_dir ) . '.htaccess';
		$rules = $this->generate_htaccess();

		return file_put_contents( $htaccess_path, $rules ) !== false;
	}

	/**
	 * Generate robots.txt content
	 *
	 * @param string $site_url Site URL for sitemap reference.
	 * @return string robots.txt content.
	 */
	public function generate_robots_txt( $site_url = '' ) {
		if ( empty( $site_url ) ) {
			$options = Options::instance();
			$site_url = $options->get( 'destination_url' ) ?: get_site_url();
		}

		$site_url = trailingslashit( $site_url );

		$content = "User-agent: *
Allow: /

# Block common attack vectors
Disallow: /cgi-bin/
Disallow: /*.php$
Disallow: /*.inc$
Disallow: /*.sql$

Sitemap: {$site_url}sitemap.xml
";

		return $content;
	}

	/**
	 * Write robots.txt file to export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @param string $site_url   Optional site URL.
	 * @return boolean Success status.
	 */
	public function write_robots_txt( $export_dir, $site_url = '' ) {
		$robots_path = trailingslashit( $export_dir ) . 'robots.txt';
		$content = $this->generate_robots_txt( $site_url );

		return file_put_contents( $robots_path, $content ) !== false;
	}

	/**
	 * Generate custom 404 page HTML
	 *
	 * @return string 404 page HTML.
	 */
	public function generate_404_page() {
		$content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        .container {
            background: white;
            padding: 60px 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            font-size: 72px;
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }
        h2 {
            font-size: 24px;
            color: #666;
            margin-bottom: 30px;
            font-weight: 400;
        }
        p {
            color: #999;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 16px;
        }
        a {
            display: inline-block;
            padding: 12px 30px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            font-weight: 500;
        }
        a:hover {
            background: #0052a3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you\'re looking for doesn\'t exist or has been moved.</p>
        <a href="/">Return to Homepage</a>
    </div>
</body>
</html>';

		return $content;
	}

	/**
	 * Write custom 404 page to export directory
	 *
	 * @param string $export_dir Export directory path.
	 * @return boolean Success status.
	 */
	public function write_404_page( $export_dir ) {
		$page_path = trailingslashit( $export_dir ) . '404.html';
		$content = $this->generate_404_page();

		return file_put_contents( $page_path, $content ) !== false;
	}
}
