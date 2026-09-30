<?php
/**
 * Verifies that the plugin's bootstrap sequence runs end-to-end on a
 * real multisite WordPress and registers the expected hook callbacks.
 *
 * These tests are tightly coupled to {@see \WPMUS\Plugin::register()};
 * if a hook is removed or re-named, the matching assertion fails.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Plugin;

final class PluginBootstrapTest extends IntegrationTestCase {

	public function test_plugin_singleton_is_constructed_at_boot(): void {
		$this->assertNotNull(
			Plugin::instance(),
			'wpm-user-sync.php constructs the Plugin singleton at boot. If null, the bootstrap did not run.'
		);
	}

	public function test_engine_is_accessible_via_the_singleton(): void {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$this->assertInstanceOf( \WPMUS\Sync\SyncEngine::class, $plugin->engine() );
	}

	public function test_admin_init_hook_runs_the_requirements_check(): void {
		$this->assertNotFalse(
			has_action( 'admin_init' ),
			'The plugin must register at least one admin_init listener for the requirements check.'
		);
	}

	public function test_textdomain_loader_is_registered_on_plugins_loaded(): void {
		$this->assertNotFalse(
			has_action( 'plugins_loaded' ),
			'The plugin must register a plugins_loaded listener for load_plugin_textdomain.'
		);
	}

	public function test_form_save_handlers_are_registered_at_network_admin_edit_endpoints(): void {
		$this->assertNotFalse(
			has_action( 'network_admin_edit_wpmusSaveGlobalConfig' ),
			'Save handler for network options form must be registered.'
		);
		$this->assertNotFalse(
			has_action( 'network_admin_edit_wpmusSyncNetworkFromScratch' ),
			'Save handler for the full-sync action must be registered.'
		);
		$this->assertNotFalse(
			has_action( 'network_admin_edit_wpmusSyncNetworkSiteFromScratch' ),
			'Save handler for the selected-sites action must be registered.'
		);
		$this->assertNotFalse(
			has_action( 'admin_action_wpmusSyncSiteSiteFromScratch' ),
			'Save handler for the site-level sync action must be registered.'
		);
	}

	public function test_sync_triggers_are_hooked_whatever_the_toggles_said_at_boot(): void {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$engine = $plugin->engine();

		$this->assertSame( 11, has_action( 'wp_initialize_site', array( $engine, 'on_new_site' ) ) );
		$this->assertSame( 10, has_action( 'wpmu_new_user', array( $engine, 'on_new_user' ) ) );
		$this->assertSame( 10, has_action( 'user_register', array( $engine, 'on_user_registered' ) ) );
		$this->assertSame( 10, has_action( 'set_user_role', array( $engine, 'on_role_changed' ) ) );
		$this->assertSame( 10, has_action( 'remove_user_from_blog', array( $engine, 'on_user_removed_from_blog' ) ) );
		$this->assertFalse( has_action( 'wp_login', array( $engine, 'maybe_on_login' ) ) );
	}
}
