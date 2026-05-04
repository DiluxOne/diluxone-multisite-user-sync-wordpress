<?php
/**
 * Smoke test placeholder for the unit suite.
 *
 * Exists so the PHPUnit `unit` testsuite is non-empty and the CI
 * job has something to assert against from PR 1 onwards. Real unit
 * tests land alongside the OOP refactor (PR 3 in the plan: tests
 * for SyncEngine, Config, repositories, etc.).
 *
 * @package WPMUS\Tests\Unit
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {

	public function test_php_version_satisfies_plugin_runtime_minimum(): void {
		$this->assertTrue(
			PHP_VERSION_ID >= 70400,
			'wpm-user-sync requires PHP 7.4 or newer per its plugin header. The test runner is on an unsupported version.'
		);
	}

	public function test_main_plugin_file_is_readable(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/wpm-user-sync.php';
		$this->assertFileExists( $plugin_file );
		$this->assertFileIsReadable( $plugin_file );
	}

	public function test_main_plugin_file_has_plugin_header(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/wpm-user-sync.php';
		$contents    = (string) file_get_contents( $plugin_file );
		$this->assertStringContainsString( 'Plugin Name: WPM User Sync', $contents );
		$this->assertStringContainsString( 'Network: true', $contents );
	}
}
