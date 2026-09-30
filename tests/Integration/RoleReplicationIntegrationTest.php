<?php
/**
 * Role replication only copies a role a destination site has, never
 * copies administrator unless a filter allows it, and never loops.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class RoleReplicationIntegrationTest extends IntegrationTestCase {

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
	 * @return string[]
	 */
	private function roles_on( int $user_id, int $blog_id ): array {
		switch_to_blog( $blog_id );
		$user  = new \WP_User( $user_id );
		$roles = array_values( $user->roles );
		restore_current_blog();
		return $roles;
	}

	/**
	 * A user who is a subscriber of two sites, with role sync on.
	 *
	 * @return array{0:int,1:int,2:int} User id, source blog, other blog.
	 */
	private function member_of_two(): array {
		$user_id = $this->make_user( $this->slug( 'twosites' ) );
		$source  = $this->make_site( $this->slug( 'source' ) );
		$other   = $this->make_site( $this->slug( 'other' ) );
		add_user_to_blog( $source, $user_id, 'subscriber' );
		add_user_to_blog( $other, $user_id, 'subscriber' );
		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( '', '', 'yes' );
		return array( $user_id, $source, $other );
	}

	protected function tearDown(): void {
		remove_all_filters( 'wpmus_replicate_role' );
		parent::tearDown();
	}

	public function test_an_ordinary_role_is_replicated(): void {
		list( $user_id, $source, $other ) = $this->member_of_two();

		switch_to_blog( $source );
		$this->engine()->on_role_changed( $user_id, 'editor', array( 'subscriber' ) );
		restore_current_blog();

		$this->assertSame( array( 'editor' ), $this->roles_on( $user_id, $other ) );
	}

	public function test_administrator_is_not_replicated_by_default(): void {
		list( $user_id, $source, $other ) = $this->member_of_two();

		switch_to_blog( $source );
		$this->engine()->on_role_changed( $user_id, 'administrator', array( 'subscriber' ) );
		restore_current_blog();

		$this->assertSame( array( 'subscriber' ), $this->roles_on( $user_id, $other ) );
	}

	public function test_a_filter_can_allow_administrator(): void {
		list( $user_id, $source, $other ) = $this->member_of_two();
		add_filter( 'wpmus_replicate_role', '__return_true' );

		switch_to_blog( $source );
		$this->engine()->on_role_changed( $user_id, 'administrator', array( 'subscriber' ) );
		restore_current_blog();

		$this->assertSame( array( 'administrator' ), $this->roles_on( $user_id, $other ) );
	}

	public function test_a_role_the_destination_does_not_have_is_not_replicated(): void {
		list( $user_id, $source, $other ) = $this->member_of_two();

		switch_to_blog( $source );
		add_role( 'wpmus_source_only', 'Source only', array( 'read' => true ) );
		try {
			$this->engine()->on_role_changed( $user_id, 'wpmus_source_only', array( 'subscriber' ) );
		} finally {
			remove_role( 'wpmus_source_only' );
			restore_current_blog();
		}

		$this->assertSame( array( 'subscriber' ), $this->roles_on( $user_id, $other ) );
	}
}
