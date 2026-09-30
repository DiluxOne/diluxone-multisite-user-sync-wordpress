<?php
/**
 * Uninstalling removes everything the plugin stored (its options, the
 * background queue and its cron event, the record of removals) and
 * leaves users and memberships alone.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\JobQueue;
use WPMUS\Sync\SyncJob;

final class UninstallIntegrationTest extends IntegrationTestCase {

	public function test_uninstall_removes_the_plugins_data_and_nothing_else(): void {
		$slug    = 'uninstall-' . substr( str_replace( '.', '', (string) microtime( true ) ), -7 );
		$user_id = $this->make_user( $slug );
		$blog_id = $this->make_site( $slug . '-site' );
		add_user_to_blog( $blog_id, $user_id, 'author' );

		( new Config( WP_PLUGIN_DIR . '/wpm-user-sync/wpm-user-sync.php' ) )->save_toggles( 'yes', 'yes', 'yes' );
		$queue = new JobQueue();
		$queue->add( new SyncJob( 'manual', null, null, false ) );
		$queue->schedule();
		update_user_meta( $user_id, UserRepository::META_REMOVED_FROM, array( 99 ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wpm-user-sync/wpm-user-sync.php' );
		}
		include WP_PLUGIN_DIR . '/wpm-user-sync/uninstall.php';

		$this->assertFalse( get_site_option( Config::OPTION_NEW_SITE_SYNC ) );
		$this->assertFalse( get_site_option( JobQueue::OPTION_JOBS ) );
		$this->assertFalse( $queue->is_scheduled() );
		$this->assertSame( '', get_user_meta( $user_id, UserRepository::META_REMOVED_FROM, true ) );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ), 'Memberships stay.' );
	}
}
