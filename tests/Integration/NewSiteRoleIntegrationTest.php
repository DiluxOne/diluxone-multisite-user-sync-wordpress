<?php
/**
 * Regression tests for the new-site trigger's role choice.
 *
 * Up to 1.5.0 the trigger copied the user's first role on the site
 * the request ran on (usually the main site) into the new site, so an
 * editor or administrator of the main site became an editor or
 * administrator of every new site. A new membership must always get
 * the destination site's own default role.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class NewSiteRoleIntegrationTest extends IntegrationTestCase {

	private function engine(): SyncEngine {
		return new SyncEngine(
			new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ),
			new SiteRepository(),
			new UserRepository()
		);
	}

	private function slug( string $hint ): string {
		return $hint . '-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
	}

	/**
	 * @return string[]
	 */
	private function roles_on( int $user_id, int $blog_id ): array {
		switch_to_blog( $blog_id );
		$user  = new \WP_User( $user_id );
		$roles = array_values( $user->roles );
		restore_current_blog();
		return $roles;
	}

	public function test_main_site_editor_gets_the_new_sites_default_role(): void {
		$user_id = $this->make_user( $this->slug( 'mainsite-editor' ), '', 'editor' );
		$blog_id = $this->make_site( $this->slug( 'fresh' ) );
		update_blog_option( $blog_id, 'default_role', 'author' );

		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( 'yes', '', '' );
		$this->engine()->on_new_site( $blog_id );

		$this->assertSame( array( 'author' ), $this->roles_on( $user_id, $blog_id ) );
	}

	public function test_main_site_administrator_is_not_made_administrator_of_the_new_site(): void {
		$user_id = $this->make_user( $this->slug( 'mainsite-admin' ), '', 'administrator' );
		$blog_id = $this->make_site( $this->slug( 'fresh-admin' ) );

		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( 'yes', '', '' );
		$this->engine()->on_new_site( $blog_id );

		$this->assertSame( array( get_blog_option( $blog_id, 'default_role', 'subscriber' ) ), $this->roles_on( $user_id, $blog_id ) );
		$this->assertNotContains( 'administrator', $this->roles_on( $user_id, $blog_id ) );
	}

	public function test_a_default_role_the_site_does_not_have_falls_back_to_subscriber(): void {
		$user_id = $this->make_user( $this->slug( 'ghost-role' ) );
		$blog_id = $this->make_site( $this->slug( 'ghost-role-site' ) );
		update_blog_option( $blog_id, 'default_role', 'no_such_role' );

		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( 'yes', '', '' );
		$this->engine()->on_new_site( $blog_id );

		$this->assertSame( array( 'subscriber' ), $this->roles_on( $user_id, $blog_id ) );
	}
}
