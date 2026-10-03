<?php
/**
 * A sync commits its writes in transactions of about a second, on the real
 * database: the memberships are there once it returns, nothing is left
 * open, no group is opened inside someone else's transaction, and the
 * filter turns grouping off.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class WriteGroupsIntegrationTest extends IntegrationTestCase {

	/** @var string[] The transaction statements the database was sent. */
	private array $statements = array();

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
	 * @param string $query The statement.
	 */
	public function record( $query ) {
		if ( in_array( $query, array( 'START TRANSACTION', 'COMMIT' ), true ) ) {
			$this->statements[] = $query;
		}
		return $query;
	}

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'query', array( $this, 'record' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'query', array( $this, 'record' ) );
		remove_all_filters( 'wpmus_sync_group_writes' );
		parent::tearDown();
	}

	private function in_transaction(): bool {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( 'SELECT @@in_transaction' );
	}

	public function test_a_sync_commits_its_writes_in_a_group_and_leaves_nothing_open(): void {
		$user_id = $this->make_user( $this->slug( 'grouped' ) );
		$blog_id = $this->make_site( $this->slug( 'grouped-site' ) );

		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->statements );
		$this->assertFalse( $this->in_transaction(), 'The last group is committed before the sync returns.' );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_the_filter_turns_grouping_off(): void {
		add_filter( 'wpmus_sync_group_writes', '__return_false' );
		$user_id = $this->make_user( $this->slug( 'ungrouped' ) );
		$blog_id = $this->make_site( $this->slug( 'ungrouped-site' ) );

		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );

		$this->assertSame( array(), $this->statements );
		$this->assertTrue( $this->is_member( $user_id, $blog_id ) );
	}

	public function test_inside_someone_elses_transaction_no_group_is_opened_and_theirs_is_not_committed(): void {
		global $wpdb;
		$user_id = $this->make_user( $this->slug( 'theirs' ) );
		$blog_id = $this->make_site( $this->slug( 'theirs-site' ) );
		$wpdb->query( 'START TRANSACTION' );
		$this->statements = array();

		$this->engine()->sync_all_users_to_sites( array( $blog_id ) );

		$this->assertSame( array(), $this->statements );
		$this->assertTrue( $this->in_transaction(), 'Their transaction is still theirs to end.' );
		$wpdb->query( 'ROLLBACK' );
		clean_user_cache( $user_id );
		$this->assertFalse( $this->is_member( $user_id, $blog_id ), 'Rolled back with theirs: the sync committed nothing of it.' );
	}
}
