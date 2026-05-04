<?php
/**
 * End-to-end exercises of the sync engine against a real WordPress
 * Multisite. Each test creates the test fixtures (users, sites)
 * directly via WordPress's APIs, calls the engine, and asserts the
 * resulting membership state on the `wp_blogs` / `wp_usermeta` tables.
 *
 * The unit suite mocks every collaborator; this suite exercises the
 * engine against actual `add_user_to_blog`, `is_user_member_of_blog`,
 * `get_users`, and `get_blog_option` calls so any drift between the
 * mock contract and real WordPress behaviour surfaces here.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class SyncEngineIntegrationTest extends IntegrationTestCase {

	private function make_engine(): SyncEngine {
		return new SyncEngine(
			new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ),
			new SiteRepository(),
			new UserRepository()
		);
	}

	private function unique_slug( string $hint ): string {
		return $hint . '-' . substr( (string) microtime( true ), -5 );
	}

	public function test_sync_all_users_to_all_sites_creates_missing_memberships(): void {
		$user_id = $this->make_user( $this->unique_slug( 'alice' ) );
		$blog_id = $this->make_site( $this->unique_slug( 'alphasite' ) );

		$this->assertGreaterThan( 0, $user_id );
		$this->assertGreaterThan( 0, $blog_id );
		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'Pre-condition: user should not yet be on the new blog.' );

		$this->make_engine()->sync_all_users_to_all_sites();

		$this->assertTrue(
			$this->is_member( $user_id, $blog_id ),
			'After sync_all_users_to_all_sites the new user must belong to the new site.'
		);
	}

	public function test_sync_all_users_to_sites_only_targets_listed_sites(): void {
		$user_id   = $this->make_user( $this->unique_slug( 'bob' ) );
		$included  = $this->make_site( $this->unique_slug( 'included' ) );
		$excluded  = $this->make_site( $this->unique_slug( 'excluded' ) );

		$this->make_engine()->sync_all_users_to_sites( array( $included ) );

		$this->assertTrue(
			$this->is_member( $user_id, $included ),
			'User must be added to the listed site.'
		);
		$this->assertFalse(
			$this->is_member( $user_id, $excluded ),
			'User must NOT be added to a site that was not in the list.'
		);
	}

	public function test_on_new_user_with_toggle_off_is_a_noop(): void {
		$blog_id = $this->make_site( $this->unique_slug( 'noop-blog' ) );
		$user_id = $this->make_user( $this->unique_slug( 'noop-user' ) );

		// Toggle is off (cleared in setUp).
		$this->make_engine()->on_new_user( $user_id );

		$this->assertFalse(
			$this->is_member( $user_id, $blog_id ),
			'With New User Sync OFF, on_new_user must not propagate.'
		);
	}

	public function test_on_new_user_with_toggle_on_propagates_to_existing_sites(): void {
		$blog_id = $this->make_site( $this->unique_slug( 'on-blog' ) );
		$user_id = $this->make_user( $this->unique_slug( 'on-user' ) );

		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( '', 'yes', '' );

		$this->make_engine()->on_new_user( $user_id );

		$this->assertTrue(
			$this->is_member( $user_id, $blog_id ),
			'With New User Sync ON, on_new_user must add the user to existing sites.'
		);
	}

	public function test_on_new_site_with_toggle_on_seeds_new_site_with_existing_users(): void {
		$user_id = $this->make_user( $this->unique_slug( 'seeded' ) );
		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( 'yes', '', '' );

		$blog_id = $this->make_site( $this->unique_slug( 'seed-target' ) );
		// `wpmu_create_blog` does NOT fire `wpmu_new_blog` on every WP
		// version automatically; call the engine entry point directly
		// to assert the engine's logic, not WP's hook firing.
		$this->make_engine()->on_new_site( $blog_id );

		$this->assertTrue(
			$this->is_member( $user_id, $blog_id ),
			'With New Site Sync ON, on_new_site must add existing users to the new site.'
		);
	}

	public function test_on_role_changed_propagates_only_to_existing_memberships(): void {
		$user_id = $this->make_user( $this->unique_slug( 'rolesync' ), '', 'subscriber' );
		$member_blog    = $this->make_site( $this->unique_slug( 'member' ) );
		$nonmember_blog = $this->make_site( $this->unique_slug( 'stranger' ) );

		add_user_to_blog( $member_blog, $user_id, 'editor' );

		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( '', '', 'yes' );

		// Trigger entry point: simulate a role change to 'author'.
		$this->make_engine()->on_role_changed( $user_id, 'author' );

		// User was already a member of $member_blog → role updated.
		$switch = switch_to_blog( $member_blog );
		$user   = new \WP_User( $user_id );
		$roles  = $user->roles;
		restore_current_blog();
		$this->assertContains( 'author', $roles, 'Role must propagate to existing memberships.' );

		// User was NOT a member of $nonmember_blog → still not a member.
		$this->assertFalse(
			$this->is_member( $user_id, $nonmember_blog ),
			'Role propagation must not create new memberships.'
		);

		// Suppress unused-variable warning from PHPStan/static analysis
		// for the discarded switch_to_blog() return value.
		unset( $switch );
	}
}
