<?php
/**
 * Verifies that {@see \WPMUS\Config} reads and writes through to the
 * real `wp_sitemeta` table — the unit suite uses Brain Monkey stubs,
 * so this is the only place the actual storage is exercised.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;

final class ConfigPersistenceTest extends IntegrationTestCase {

	private function make_config(): Config {
		return new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' );
	}

	public function test_save_toggles_persists_yes_for_yes_input(): void {
		$this->make_config()->save_toggles( 'yes', 'yes', 'yes' );

		$this->assertSame( 'yes', get_site_option( Config::OPTION_NEW_SITE_SYNC ) );
		$this->assertSame( 'yes', get_site_option( Config::OPTION_NEW_USER_SYNC ) );
		$this->assertSame( 'yes', get_site_option( Config::OPTION_SET_USER_ROLE_SYNC ) );
	}

	public function test_save_toggles_normalises_non_yes_inputs_to_empty_string(): void {
		$this->make_config()->save_toggles( '', 'no', 'on' );

		$this->assertSame( '', get_site_option( Config::OPTION_NEW_SITE_SYNC ) );
		$this->assertSame( '', get_site_option( Config::OPTION_NEW_USER_SYNC ) );
		$this->assertSame( '', get_site_option( Config::OPTION_SET_USER_ROLE_SYNC ) );
	}

	public function test_is_enabled_accessors_round_trip_with_save_toggles(): void {
		$config = $this->make_config();
		$config->save_toggles( 'yes', '', 'yes' );

		$this->assertTrue( $config->is_new_site_sync_enabled() );
		$this->assertFalse( $config->is_new_user_sync_enabled() );
		$this->assertTrue( $config->is_set_user_role_sync_enabled() );
	}

	public function test_is_enabled_accessors_default_to_false_when_options_missing(): void {
		$config = $this->make_config();
		// No save_toggles call here. tearDown deleted any leftover state.
		$this->assertFalse( $config->is_new_site_sync_enabled() );
		$this->assertFalse( $config->is_new_user_sync_enabled() );
		$this->assertFalse( $config->is_set_user_role_sync_enabled() );
	}
}
