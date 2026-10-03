<?php
/**
 * A background run commits its writes in transactions of about a second,
 * on the real database: the memberships are there once it returns and
 * nothing is left open; a sync in the request that starts it opens none;
 * the filter turns grouping off; and a group the database does not commit
 * leaves its batch to the next run instead of being skipped.
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
use WPMUS\Sync\WriteGroups;

final class WriteGroupsIntegrationTest extends IntegrationTestCase {

	/** @var string[] The transaction statements the database was sent. */
	private array $statements = array();

	/** When true, the next COMMIT is rolled back and reported failed. */
	private bool $fail_next_commit = false;

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
	 * Records the transaction statements; turns the COMMIT a test asked to
	 * fail into what a lost connection leaves: the group rolled back, and
	 * an error for the statement.
	 *
	 * @param string $query The statement.
	 */
	public function record( $query ) {
		global $wpdb;
		if ( ! in_array( $query, array( 'START TRANSACTION', 'COMMIT' ), true ) ) {
			return $query;
		}
		$this->statements[] = $query;
		if ( 'COMMIT' === $query && $this->fail_next_commit ) {
			$this->fail_next_commit = false;
			remove_filter( 'query', array( $this, 'record' ) );
			$wpdb->query( 'ROLLBACK' );
			add_filter( 'query', array( $this, 'record' ) );
			$wpdb->suppress_errors( true );
			return 'COMMIT /* refused */ NOT VALID';
		}
		return $query;
	}

	protected function setUp(): void {
		parent::setUp();
		( new JobQueue() )->clear();
		add_filter( 'wpmus_sync_inline_limit', '__return_zero' );
		add_filter( 'query', array( $this, 'record' ) );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->suppress_errors( false );
		remove_filter( 'query', array( $this, 'record' ) );
		remove_all_filters( 'wpmus_sync_inline_limit' );
		remove_all_filters( 'wpmus_sync_group_writes' );
		( new JobQueue() )->clear();
		parent::tearDown();
	}

	/** MariaDB, the tests network's database, can say it. */
	private function in_transaction(): bool {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( 'SELECT @@in_transaction' );
	}

	public function test_a_background_run_commits_its_writes_in_a_group_and_leaves_nothing_open(): void {
		$user_id = $this->make_user( $this->slug( 'grouped' ) );
		$blog_id = $this->make_site( $this->slug( 'grouped-site' ) );
		$engine  = $this->engine();
		$engine->sync_all_users_to_sites( array( $blog_id ) );
		$this->statements = array();

		$engine->process_queue();

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->statements );
		$this->assertFalse( $this->in_transaction(), 'The last group is committed before the run ends.' );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_a_sync_in_the_request_that_starts_it_writes_one_by_one(): void {
		remove_all_filters( 'wpmus_sync_inline_limit' );
		$user_id = $this->make_user( $this->slug( 'inline' ) );
		$blog_id = $this->make_site( $this->slug( 'inline-site' ) );
		$this->statements = array();

		$this->assertTrue( $this->engine()->sync_all_users_to_sites( array( $blog_id ) ) );

		$this->assertSame( array(), $this->statements, 'Inside someone else\'s request, no transaction of ours.' );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_the_filter_turns_grouping_off(): void {
		add_filter( 'wpmus_sync_group_writes', '__return_false' );
		$user_id = $this->make_user( $this->slug( 'ungrouped' ) );
		$blog_id = $this->make_site( $this->slug( 'ungrouped-site' ) );
		$engine  = $this->engine();
		$engine->sync_all_users_to_sites( array( $blog_id ) );
		$this->statements = array();

		$engine->process_queue();

		$this->assertSame( array(), $this->statements );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_inside_a_group_a_membership_another_request_just_added_is_seen(): void {
		global $wpdb;
		$user_id = $this->make_user( $this->slug( 'seen' ) );
		$blog_id = $this->make_site( $this->slug( 'seen-site' ) );
		$key     = $wpdb->get_blog_prefix( $blog_id ) . 'capabilities';
		$count   = static function () use ( $wpdb, $user_id, $key ): int {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) );
		};
		$groups = new WriteGroups();
		$groups->begin();
		$this->assertSame( 0, $count(), 'Not a member yet, read inside the group.' );

		// Another request, on its own connection, adds the membership.
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->insert( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => $key, 'meta_value' => serialize( array( 'subscriber' => true ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$other->close();

		$this->assertSame( 1, $count(), 'The group reads what is committed now, not a snapshot: the sync sees it and does not add it twice.' );
		$this->assertTrue( $groups->end() );
	}

	public function test_a_group_the_database_did_not_commit_is_redone_by_the_next_run(): void {
		$user_id = $this->make_user( $this->slug( 'lost' ) );
		$blog_id = $this->make_site( $this->slug( 'lost-site' ) );
		$engine  = $this->engine();
		$queue   = new JobQueue();
		$engine->sync_all_users_to_sites( array( $blog_id ) );

		$this->fail_next_commit = true;
		$engine->process_queue();
		clean_user_cache( $user_id );

		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'The group was dropped.' );
		$this->assertNotNull( $queue->first(), 'The job is still queued, not counted as done.' );
		$this->assertSame( 0, $queue->first()->processed, 'The cursor did not move past it.' );
		$this->assertTrue( $queue->is_scheduled(), 'The next run is on its way.' );

		$engine->process_queue();

		$this->assertSame( array(), $queue->all() );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}
}
