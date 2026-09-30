<?php
/**
 * A person a site administrator removed from a site stays removed.
 *
 * Up to 1.5.0 every automatic path (new user, new site, and a
 * catch-up on every login) put the person back on every site, so a
 * removal lasted until their next sign-in. The plugin now records the
 * removal and every automatic path, and the manual sync unless the
 * administrator forces it, respects it.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Plugin;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class RemovalRespectedIntegrationTest extends IntegrationTestCase {

	private function engine(): SyncEngine {
		return new SyncEngine(
			new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ),
			new SiteRepository(),
			new UserRepository()
		);
	}

	private function slug( string $hint ): string {
		return $hint . '-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
	}

	/**
	 * A user who was on the site and was removed from it.
	 *
	 * @return array{0:int,1:int} User id, blog id.
	 */
	private function removed_member(): array {
		$user_id = $this->make_user( $this->slug( 'removed' ) );
		$blog_id = $this->make_site( $this->slug( 'left-site' ) );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		remove_user_from_blog( $user_id, $blog_id );
		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'Pre-condition: the user was removed.' );
		return array( $user_id, $blog_id );
	}

	public function test_new_user_trigger_does_not_put_a_removed_user_back(): void {
		list( $user_id, $blog_id ) = $this->removed_member();
		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( '', 'yes', '' );

		$this->engine()->on_new_user( $user_id );

		$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_manual_sync_does_not_put_a_removed_user_back_unless_forced(): void {
		list( $user_id, $blog_id ) = $this->removed_member();

		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );
		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'A plain manual sync respects the removal.' );

		$this->engine()->sync_all_users_to_sites( array( $blog_id ), true );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ), 'A forced manual sync puts the user back.' );
	}

	public function test_signing_in_does_not_put_a_removed_user_back(): void {
		list( $user_id, $blog_id ) = $this->removed_member();
		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( 'yes', 'yes', 'yes' );

		$user = get_user_by( 'id', $user_id );
		$this->assertInstanceOf( \WP_User::class, $user );
		do_action( 'wp_login', $user->user_login, $user );

		$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
		$plugin = Plugin::instance();
		$this->assertNotNull( $plugin );
		$this->assertFalse( method_exists( $plugin->engine(), 'maybe_on_login' ), 'The sign-in catch-up is gone.' );
	}

	public function test_an_administrator_adding_the_user_back_clears_the_record(): void {
		list( $user_id, $blog_id ) = $this->removed_member();

		add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		remove_user_from_blog( $user_id, $blog_id );
		add_user_to_blog( $blog_id, $user_id, 'subscriber' );

		$removed = get_user_meta( $user_id, UserRepository::META_REMOVED_FROM, true );
		$this->assertNotContains( $blog_id, is_array( $removed ) ? array_map( 'intval', $removed ) : array() );
	}
}
