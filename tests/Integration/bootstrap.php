<?php
/**
 * Integration-suite bootstrap. Loads Composer's autoloader and then
 * boots the full WordPress runtime via `wp-load.php`. Designed to be
 * executed inside the wp-env `tests-cli` container, where WordPress
 * lives at `/var/www/html/`.
 *
 * The path can be overridden with the `WP_LOAD` environment variable
 * for runners that put WordPress somewhere else (custom Docker images,
 * bedrock-style installs, etc.).
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// WP-CLI / PHPUnit do not populate web-request superglobals. Several
// multisite functions (notably `wpmu_create_blog`) read $_SERVER keys
// like REMOTE_ADDR / HTTP_HOST without `isset()` guards, which under
// `convertWarningsToExceptions=true` would surface as undefined-key
// errors. Stub them with safe placeholders so test fixtures can use
// the real WordPress APIs to create sites.
$_SERVER['REMOTE_ADDR']     = $_SERVER['REMOTE_ADDR']     ?? '127.0.0.1';
$_SERVER['HTTP_HOST']       = $_SERVER['HTTP_HOST']       ?? 'localhost:8889';
$_SERVER['SERVER_PROTOCOL'] = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
$_SERVER['REQUEST_METHOD']  = $_SERVER['REQUEST_METHOD']  ?? 'GET';
$_SERVER['REQUEST_URI']     = $_SERVER['REQUEST_URI']     ?? '/';

$wp_load = getenv( 'WP_LOAD' ) ?: '/var/www/html/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite(
		STDERR,
		"\nIntegration tests require a WordPress runtime.\n" .
		"Could not find wp-load.php at: {$wp_load}\n\n" .
		"Run via:\n" .
		"  npx wp-env start\n" .
		"  npx wp-env run tests-cli ./vendor/bin/phpunit -c phpunit-integration.xml.dist\n\n" .
		"Or set WP_LOAD=/path/to/your/wp-load.php if WordPress is elsewhere.\n\n"
	);
	exit( 1 );
}

require_once $wp_load;

if ( ! is_multisite() ) {
	fwrite(
		STDERR,
		"\nIntegration tests require WordPress Multisite.\n" .
		"The host install does not have MULTISITE enabled.\n" .
		"The wp-env stack should boot multisite automatically when\n" .
		"`.wp-env.json` declares `\"multisite\": true`.\n\n"
	);
	exit( 1 );
}
