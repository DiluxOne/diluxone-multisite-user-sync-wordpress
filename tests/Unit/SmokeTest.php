<?php
/**
 * Smoke test for the unit suite — confirms the plugin's bootstrap
 * surface is intact: PHP version, plugin file readable, plugin
 * header present, and the OOP autoloader can resolve the main
 * `WPMUS\Plugin` class.
 *
 * Real coverage of the Sync engine, Config, repositories and admin
 * pages lands in PR 3 (unit tests with mocks) and PR 4 (integration
 * tests on a wp-env multisite).
 *
 * @package WPMUS\Tests\Unit
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/abspath-stub/' );
}

require_once dirname( __DIR__, 2 ) . '/src/Autoloader.php';

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

	public function test_autoloader_resolves_namespaced_classes(): void {
		// Touch each class in the project so the autoloader's spl
		// callback runs against it. If the file path is wrong or the
		// PSR-4 mapping is broken, class_exists returns false here.
		$this->assertTrue( class_exists( \WPMUS\Plugin::class ), 'WPMUS\\Plugin should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Config::class ), 'WPMUS\\Config should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Sync\SyncEngine::class ), 'WPMUS\\Sync\\SyncEngine should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Repositories\SiteRepository::class ), 'WPMUS\\Repositories\\SiteRepository should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Repositories\UserRepository::class ), 'WPMUS\\Repositories\\UserRepository should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Admin\NetworkMenu::class ), 'WPMUS\\Admin\\NetworkMenu should be autoloadable' );
		$this->assertTrue( class_exists( \WPMUS\Admin\SiteMenu::class ), 'WPMUS\\Admin\\SiteMenu should be autoloadable' );
	}

	public function test_config_constants_match_legacy_option_names(): void {
		// Critical: the OOP refactor MUST read/write the same wp_sitemeta
		// option names as 1.4 so existing installs upgrade in place
		// without losing their three toggles.
		$this->assertSame( 'wpmus_newSiteSync', \WPMUS\Config::OPTION_NEW_SITE_SYNC );
		$this->assertSame( 'wpmus_newUserSync', \WPMUS\Config::OPTION_NEW_USER_SYNC );
		$this->assertSame( 'wpmus_setUserRoleSync', \WPMUS\Config::OPTION_SET_USER_ROLE_SYNC );
		$this->assertSame( 'wpmus-validate', \WPMUS\Config::NONCE_ACTION );
	}
}
