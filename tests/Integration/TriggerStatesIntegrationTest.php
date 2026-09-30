<?php
/**
 * The trigger states the other suites do not reach through the live
 * hooks: a trigger that is off leaves everything alone, a super admin's
 * role change stays put, an activated invitee is synced again, and the
 * 1.4 function names still reach the engine.
 *
 * Everything runs through the hooks the plugin registered at boot, the
 * way WordPress fires them.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;

final class TriggerStatesIntegrationTest extends IntegrationTestCase {

	private function slug( string $hint ): string {
		return $hint . '-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
	}

	private function toggles( string $new_site, string $new_user, string $role ): void {
		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( $new_site, $new_user, $role );
	}

	private function role_on( int $user_id, int $blog_id ): string {
		switch_to_blog( $blog_id );
		$user = new \WP_User( $user_id );
		$role = implode( ',', $user->roles );
		restore_current_blog();
		return $role;
	}

	protected function tearDown(): void {
		remove_all_filters( 'deprecated_function_trigger_error' );
		remove_all_filters( 'pre_wp_mail' );
		parent::tearDown();
	}

	public function test_with_the_new_site_trigger_off_a_new_site_gets_nobody(): void {
		$user_id = $this->make_user( $this->slug( 'ns-off' ) );
		$this->toggles( '', 'yes', 'yes' );

		$blog_id = $this->make_site( $this->slug( 'ns-off-site' ) );

		$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_with_the_role_trigger_off_a_role_change_stays_on_its_site(): void {
		$user_id = $this->make_user( $this->slug( 'role-off' ) );
		$source  = $this->make_site( $this->slug( 'role-off-a' ) );
		$other   = $this->make_site( $this->slug( 'role-off-b' ) );
		add_user_to_blog( $source, $user_id, 'subscriber' );
		add_user_to_blog( $other, $user_id, 'subscriber' );
		$this->toggles( 'yes', 'yes', '' );

		switch_to_blog( $source );
		( new \WP_User( $user_id ) )->set_role( 'editor' );
		restore_current_blog();

		$this->assertSame( 'subscriber', $this->role_on( $user_id, $other ) );
	}

	public function test_a_super_admins_role_change_is_not_copied(): void {
		$user_id = $this->make_user( $this->slug( 'role-super' ) );
		$source  = $this->make_site( $this->slug( 'role-super-a' ) );
		$other   = $this->make_site( $this->slug( 'role-super-b' ) );
		add_user_to_blog( $source, $user_id, 'subscriber' );
		add_user_to_blog( $other, $user_id, 'subscriber' );
		grant_super_admin( $user_id );
		$this->toggles( '', '', 'yes' );

		try {
			switch_to_blog( $source );
			( new \WP_User( $user_id ) )->set_role( 'editor' );
			restore_current_blog();

			$this->assertSame( 'subscriber', $this->role_on( $user_id, $other ) );
		} finally {
			revoke_super_admin( $user_id );
		}
	}

	public function test_an_activated_invitee_is_synced_again(): void {
		$blog_id = $this->make_site( $this->slug( 'invite-site' ) );
		$user_id = $this->make_user( $this->slug( 'invitee' ) );
		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'Made with the trigger off.' );
		$this->toggles( '', 'yes', '' );

		// What wpmu_activate_signup() fires once the invitee confirms; core's
		// welcome e-mail hangs off it too, and there is no mail server here.
		add_filter( 'pre_wp_mail', '__return_false' );
		do_action( 'wpmu_activate_user', $user_id, 'password', array() );

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_the_1_4_function_names_still_reach_the_engine(): void {
		add_filter( 'deprecated_function_trigger_error', '__return_false' );
		$blog_id = $this->make_site( $this->slug( 'legacy-site' ) );
		$user_id = $this->make_user( $this->slug( 'legacy' ) );
		$this->toggles( '', 'yes', '' );

		wpmus_sync_newuser( $user_id );

		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}
}
