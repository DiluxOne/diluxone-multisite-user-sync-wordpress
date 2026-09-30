<?php
/**
 * The plugin listens on the hooks WordPress fires today, whatever the
 * toggles said when the request started, and each creation runs the
 * sync once.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Plugin;

final class HooksIntegrationTest extends IntegrationTestCase {

	private function slug( string $hint ): string {
		return $hint . '-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
	}

	private function config(): Config {
		return new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' );
	}

	public function test_new_site_listens_on_wp_initialize_site_after_core(): void {
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$priority = has_action( 'wp_initialize_site', array( $plugin->engine(), 'on_new_site' ) );
		$this->assertIsInt( $priority );
		$this->assertGreaterThanOrEqual( 11, $priority );
		$this->assertFalse( has_action( 'wpmu_new_blog', array( $plugin->engine(), 'on_new_site' ) ) );
	}

	public function test_creating_a_site_runs_the_trigger_turned_on_after_boot(): void {
		$user_id = $this->make_user( $this->slug( 'hooked' ) );
		$this->config()->save_toggles( 'yes', '', '' );

		$blog_id = $this->make_site( $this->slug( 'hooked-site' ) );

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_a_network_user_created_with_wpmu_create_user_joins_every_site(): void {
		$blog_id = $this->make_site( $this->slug( 'for-new-user' ) );
		$this->config()->save_toggles( '', 'yes', '' );

		$user_id = $this->make_user( $this->slug( 'wpmu-user' ) );

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_a_user_created_with_wp_insert_user_joins_every_site(): void {
		$blog_id = $this->make_site( $this->slug( 'for-inserted' ) );
		$this->config()->save_toggles( '', 'yes', '' );

		$login   = $this->slug( 'inserted' );
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'user_email' => $login . '@integration.test',
			)
		);
		$this->assertIsInt( $user_id );
		$this->created_user_ids[] = $user_id;

		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$plugin->engine()->flush_registered_users();

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}
}
