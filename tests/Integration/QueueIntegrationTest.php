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
use WPMUS\Sync\SyncJob;

final class QueueIntegrationTest extends IntegrationTestCase {

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

	/**
	 * Writes the queue the way another request would: straight to the
	 * database, behind this request's cached copy of the option. Null
	 * empties it.
	 *
	 * @param SyncJob[]|null $jobs The queue the other request leaves.
	 */
	private function another_request_writes( ?array $jobs ): void {
		global $wpdb;
		$where = array(
			'site_id'  => get_current_network_id(),
			'meta_key' => JobQueue::OPTION_JOBS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		);
		$wpdb->delete( $wpdb->sitemeta, $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null !== $jobs ) {
			$value = array_map(
				static function ( SyncJob $job ): array {
					return $job->to_array();
				},
				$jobs
			);
			$wpdb->insert( $wpdb->sitemeta, $where + array( 'meta_value' => maybe_serialize( $value ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
	}

	/**
	 * @return string[]
	 */
	private function queued_ids(): array {
		return array_map(
			static function ( SyncJob $job ): string {
				return $job->id;
			},
			( new JobQueue() )->all()
		);
	}

	public function test_a_job_another_request_queues_during_a_batch_is_kept(): void {
		$queue   = new JobQueue();
		$running = new SyncJob( 'manual', null, array( 1 ), false );
		$queue->add( $running );
		$queue->all();

		$arrived = new SyncJob( 'new_site', null, array( 2 ), false );
		$this->another_request_writes( array( $running, $arrived ) );
		$running->processed = 1;
		$queue->update( $running );

		$this->assertSame( array( $running->id, $arrived->id ), $this->queued_ids(), 'Storing a batch\'s progress keeps the job queued meanwhile.' );

		$running->done = true;
		$queue->remove( $running->id );
		$this->assertSame( array( $arrived->id ), $this->queued_ids(), 'Dropping the finished job keeps the one queued meanwhile.' );
	}

	public function test_a_job_queued_by_another_request_on_an_empty_queue_is_kept(): void {
		$queue = new JobQueue();
		$this->assertSame( array(), $queue->all() );

		$first = new SyncJob( 'new_site', null, array( 2 ), false );
		$this->another_request_writes( array( $first ) );
		$second = new SyncJob( 'new_user', array( 1 ), null, false );
		$queue->add( $second );

		$this->assertSame( array( $first->id, $second->id ), $this->queued_ids() );
	}

	public function test_a_queue_another_request_emptied_stays_empty(): void {
		$queue   = new JobQueue();
		$running = new SyncJob( 'manual', null, array( 1 ), false );
		$queue->add( $running );
		$queue->all();

		$this->another_request_writes( null );
		$running->processed = 1;
		$queue->update( $running );

		$this->assertSame( array(), $this->queued_ids(), 'A batch that ends after Network Admin emptied the queue does not bring its job back.' );
	}

	/**
	 * When the queue's cron event is due on the main site, or false.
	 *
	 * @return int|false
	 */
	private function next_run() {
		switch_to_blog( get_main_site_id() );
		$next = wp_next_scheduled( JobQueue::CRON_HOOK );
		restore_current_blog();
		return $next;
	}

	public function test_a_run_that_finds_the_queue_locked_leaves_a_retry_for_when_the_lock_goes_stale(): void {
		$user_id = $this->make_user( $this->slug( 'locked' ) );
		$blog_id = $this->make_site( $this->slug( 'locked-site' ) );
		$engine  = $this->engine();
		$queue   = new JobQueue();
		$queue->add( new SyncJob( 'manual', null, array( $blog_id ), false ) );

		$held_since = time() - 60;
		update_site_option( JobQueue::OPTION_LOCK, $held_since );
		$engine->process_queue();

		$this->assertSame( 0, $queue->first()->processed, 'The run leaves the job alone.' );
		$this->assertSame( $held_since + JobQueue::LOCK_TTL, $this->next_run(), 'A retry waits for the lock to go stale.' );

		$queue->schedule();
		$this->assertLessThanOrEqual( time(), $this->next_run(), 'A run that ends first brings the retry forward.' );

		update_site_option( JobQueue::OPTION_LOCK, time() - JobQueue::LOCK_TTL - 1 );
		$engine->process_queue();
		$this->assertSame( array(), $queue->all(), 'A stale lock is taken over and the job finishes.' );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
		$this->assertFalse( get_site_option( JobQueue::OPTION_LOCK ), 'The run releases the lock it took over.' );
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
