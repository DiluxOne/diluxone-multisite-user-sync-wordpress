<?php
/**
 * PHPUnit bootstrap for the unit-test suite.
 *
 * Unit tests for wpm-user-sync run in pure PHP without WordPress.
 * The plugin is heavily WP-coupled (multisite hooks, $wpdb access,
 * site options), so most testable logic is exercised via the
 * integration suite that boots wp-env. The unit suite covers the
 * pieces of pure logic that can be lifted out — currently a smoke
 * placeholder pending the OOP refactor (see docs/release.md and
 * the project plan).
 *
 * Brain Monkey is wired up to allow stubbing of WordPress core
 * functions in unit tests when needed. Mockery is available for
 * object mocking. Both are dev dependencies in composer.json.
 *
 * @package WPMUS\Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Brain Monkey makes WP core functions stubbable so we can write
// unit tests without booting a full WordPress runtime. Activated
// per-test via Brain\Monkey\setUp() / tearDown() in the test classes.
\Brain\Monkey\setUp();
register_shutdown_function( static function (): void {
	\Brain\Monkey\tearDown();
} );
