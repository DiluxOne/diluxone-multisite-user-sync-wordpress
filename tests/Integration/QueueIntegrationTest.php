<?php
/**
 * Big syncs run in the background in batches, with a progress record
 * the network admin can read; small ones still finish in the request.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\JobQueue;
use WPMUS\Sync\SyncEngine;

final class QueueIntegrationTest extends IntegrationTestCase {

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

	protected function setUp(): void {
		parent::setUp();
		( new JobQueue() )->clear();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wpmus_sync_inline_limit' );
		remove_all_filters( 'wpmus_sync_batch_size' );
		remove_all_filters( 'wpmus_sync_time_limit' );
		( new JobQueue() )->clear();
		parent::tearDown();
	}

	public function test_a_small_sync_finishes_in_the_request(): void {
		$user_id = $this->make_user( $this->slug( 'small' ) );
		$blog_id = $this->make_site( $this->slug( 'small-site' ) );

		$this->assertTrue( $this->engine()->sync_all_users_to_sites( array( $blog_id ) ) );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_a_big_sync_is_queued_and_processed_in_batches(): void {
		add_filter( 'wpmus_sync_inline_limit', '__return_zero' );
		add_filter(
			'wpmus_sync_batch_size',
			static function (): int {
				return 1;
			}
		);
		add_filter( 'wpmus_sync_time_limit', '__return_zero' );

		$user_ids = array(
			$this->make_user( $this->slug( 'batch-a' ) ),
			$this->make_user( $this->slug( 'batch-b' ) ),
		);
		$blog_id  = $this->make_site( $this->slug( 'batch-site' ) );
		$engine   = $this->engine();
		$queue    = new JobQueue();

		$this->assertFalse( $engine->sync_all_users_to_sites( array( $blog_id ) ), 'Over the limit, the sync is queued.' );
		$this->assertFalse( $this->is_member( $user_ids[0], $blog_id ) );
		$this->assertCount( 1, $queue->all() );
		$this->assertTrue( $queue->is_scheduled(), 'A cron event carries the job.' );

		$engine->process_queue();
		$jobs = $queue->all();
		$this->assertCount( 1, $jobs, 'One batch of one pair does not finish the job.' );
		$this->assertSame( 1, $jobs[0]->processed );
		$this->assertGreaterThan( 1, $jobs[0]->total );

		for ( $i = 0; $i < 500 && array() !== $queue->all(); $i++ ) {
			$engine->process_queue();
		}

		$this->assertSame( array(), $queue->all(), 'The queue drains.' );
		foreach ( $user_ids as $user_id ) {
			$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
		}
	}
}
