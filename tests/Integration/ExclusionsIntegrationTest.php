<?php
/**
 * What the sync leaves alone: super admins, sites that are archived,
 * spam or deleted, and whatever a site excludes through the filters.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class ExclusionsIntegrationTest extends IntegrationTestCase {

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

	protected function tearDown(): void {
		remove_all_filters( 'wpmus_should_sync_user' );
		remove_all_filters( 'wpmus_excluded_site_ids' );
		parent::tearDown();
	}

	public function test_super_admins_are_not_added_to_sites(): void {
		$user_id = $this->make_user( $this->slug( 'superadmin' ) );
		grant_super_admin( $user_id );
		$blog_id = $this->make_site( $this->slug( 'no-supers' ) );

		try {
			( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( 'yes', '', '' );
			$this->engine()->on_new_site( $blog_id );
			$this->engine()->sync_all_users_to_all_sites();
			$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
		} finally {
			revoke_super_admin( $user_id );
		}
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function inactive_flags(): array {
		return array(
			'archived' => array( 'archived' ),
			'spam'     => array( 'spam' ),
			'deleted'  => array( 'deleted' ),
		);
	}

	/**
	 * @dataProvider inactive_flags
	 */
	public function test_inactive_sites_are_not_synced( string $flag ): void {
		$user_id = $this->make_user( $this->slug( 'flag-' . $flag ) );
		$blog_id = $this->make_site( $this->slug( 'inactive-' . $flag ) );
		update_blog_status( $blog_id, $flag, '1' );

		$this->engine()->sync_all_users_to_all_sites();
		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );

		// is_user_member_of_blog() answers false for any inactive site, so
		// read the capabilities row the membership would have written.
		global $wpdb;
		$this->assertSame( '', get_user_meta( $user_id, $wpdb->get_blog_prefix( $blog_id ) . 'capabilities', true ) );
	}

	public function test_a_filter_can_exclude_a_user(): void {
		$user_id = $this->make_user( $this->slug( 'filtered-user' ) );
		$blog_id = $this->make_site( $this->slug( 'filtered-user-site' ) );
		add_filter(
			'wpmus_should_sync_user',
			static function ( bool $sync, int $candidate ) use ( $user_id ): bool {
				return $candidate === $user_id ? false : $sync;
			},
			10,
			2
		);

		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );

		$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_a_filter_can_exclude_a_site(): void {
		$user_id = $this->make_user( $this->slug( 'filtered-site-user' ) );
		$blog_id = $this->make_site( $this->slug( 'filtered-site' ) );
		add_filter(
			'wpmus_excluded_site_ids',
			static function ( array $ids ) use ( $blog_id ): array {
				$ids[] = $blog_id;
				return $ids;
			}
		);

		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( '', 'yes', '' );
		$this->engine()->on_new_user( $user_id );
		$this->engine()->sync_all_users_to_all_sites();

		$this->assertFalse( $this->is_member( $user_id, $blog_id ) );
	}
}
