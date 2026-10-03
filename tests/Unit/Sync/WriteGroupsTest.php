<?php
/**
 * Unit tests for {@see \WPMUS\Sync\WriteGroups}: a group is a transaction
 * of about a second; a commit or a start the database refuses is
 * reported, never skipped over; the filter turns grouping off.
 *
 * `$wpdb` is a stand-in that records the statements it is sent and
 * refuses the ones a test names.
 *
 * @package WPMUS\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Brain\Monkey\Filters;
use Tests\TestCase;
use WPMUS\Sync\WriteGroups;

final class WriteGroupsTest extends TestCase {

	private const SET = 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED';

	/** @var object{statements: string[], refuse: string[]} */
	private object $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = new class() {
			/** @var string[] */
			public array $statements = array();
			/** @var string[] Statements to answer with false, once each. */
			public array $refuse = array();

			/** @return int|false */
			public function query( string $statement ) {
				$this->statements[] = $statement;
				$at                 = array_search( $statement, $this->refuse, true );
				if ( false !== $at ) {
					unset( $this->refuse[ $at ] );
					return false;
				}
				return 0;
			}
		};
		$GLOBALS['wpdb'] = $this->db;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/** The group was opened more than a second ago. */
	private function age( WriteGroups $groups ): void {
		\Closure::bind(
			function (): void {
				$this->since -= WriteGroups::SECONDS + 0.1;
			},
			$groups,
			WriteGroups::class
		)();
	}

	public function test_a_group_is_one_transaction_from_begin_to_end(): void {
		$groups = new WriteGroups();

		$groups->begin();
		$groups->checkpoint();

		$this->assertTrue( $groups->end() );
		$this->assertSame( array( self::SET, 'START TRANSACTION', 'COMMIT' ), $this->db->statements, 'Under a second, a checkpoint commits nothing.' );
	}

	public function test_a_checkpoint_commits_a_group_older_than_a_second_and_opens_the_next(): void {
		$groups = new WriteGroups();
		$groups->begin();
		$this->age( $groups );

		$groups->checkpoint();

		$this->assertTrue( $groups->end() );
		$this->assertSame( array( self::SET, 'START TRANSACTION', 'COMMIT', self::SET, 'START TRANSACTION', 'COMMIT' ), $this->db->statements );
	}

	public function test_begin_twice_opens_one_group(): void {
		$groups = new WriteGroups();

		$groups->begin();
		$groups->begin();
		$groups->end();
		$groups->end();

		$this->assertSame( array( self::SET, 'START TRANSACTION', 'COMMIT' ), $this->db->statements );
	}

	public function test_a_commit_refused_at_a_checkpoint_is_reported_and_ends_grouping(): void {
		$this->db->refuse = array( 'COMMIT' );
		$groups           = new WriteGroups();
		$groups->begin();
		$this->age( $groups );

		$groups->checkpoint();
		$groups->checkpoint();

		$this->assertFalse( $groups->end(), 'The batch must not be counted as stored.' );
		$this->assertSame( array( self::SET, 'START TRANSACTION', 'COMMIT' ), $this->db->statements, 'No group opens after a lost one: the rest commits write by write.' );
	}

	public function test_a_commit_refused_at_the_end_is_reported(): void {
		$this->db->refuse = array( 'COMMIT' );
		$groups           = new WriteGroups();
		$groups->begin();

		$this->assertFalse( $groups->end() );
	}

	public function test_the_next_batch_starts_with_a_clean_record(): void {
		$this->db->refuse = array( 'COMMIT' );
		$groups           = new WriteGroups();
		$groups->begin();
		$groups->end();

		$groups->begin();

		$this->assertTrue( $groups->end() );
	}

	public function test_a_start_refused_opens_no_group_and_loses_nothing(): void {
		$this->db->refuse = array( 'START TRANSACTION' );
		$groups           = new WriteGroups();

		$groups->begin();
		$this->age( $groups );
		$groups->checkpoint();

		$this->assertTrue( $groups->end(), 'Each write committed on its own.' );
		$this->assertSame( array( self::SET, 'START TRANSACTION' ), $this->db->statements );
	}

	public function test_a_refused_isolation_level_opens_no_group(): void {
		$this->db->refuse = array( self::SET );
		$groups           = new WriteGroups();

		$groups->begin();

		$this->assertTrue( $groups->end() );
		$this->assertSame( array( self::SET ), $this->db->statements, 'No transaction at the default level, which reads a snapshot.' );
	}

	public function test_the_filter_turns_grouping_off(): void {
		Filters\expectApplied( 'wpmus_sync_group_writes' )->once()->with( true )->andReturn( false );
		$groups = new WriteGroups();

		$groups->begin();
		$groups->checkpoint();

		$this->assertTrue( $groups->end() );
		$this->assertSame( array(), $this->db->statements );
	}
}
