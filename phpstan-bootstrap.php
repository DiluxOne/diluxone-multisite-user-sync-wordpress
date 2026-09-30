<?php
/**
 * PHPStan analysis bootstrap.
 *
 * Defines the constants the main plugin file (wpm-user-sync.php) creates at
 * runtime. PHPStan analyses without executing anything, so it never sees the
 * `define()` there. Referenced from phpstan.neon's `bootstrapFiles:`; never
 * shipped (.distignore) and never loaded by the plugin.
 *
 * @package WPMUS
 */

if ( ! defined( 'WPMUS_VERSION' ) ) {
	define( 'WPMUS_VERSION', '0.0.0-phpstan-stub' );
}
