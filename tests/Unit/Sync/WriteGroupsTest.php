<?php
/**
 * Unit tests for {@see \WPMUS\Sync\WriteGroups}: a group is a transaction
 * of about a second, never opened inside someone else's transaction or
 * when the filter turns grouping off.
 *
 * `$wpdb` is a stand-in that records the statements it is sent.
 *
 * @package WPMUS\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Brain\Monkey\Filters;
use Tests\TestCase;
use WPMUS\Sync\WriteGroups;

final class WriteGroupsTest extends TestCase {

	/** @var object{statements: string[], inside: mixed} */
	private object $db;

	protected function setUp(): void {
		parent::setUp();
		$this->db = new class() {
			/** @var string[] */
			public array $statements = array();
			/** @var mixed What `SELECT @@in_transaction` answers. */
			public $inside = '0';
			private bool $suppress = false;

			public function query( string $statement ): int {
				$this->statements[] = $statement;
				return 0;
			}

			/** @return mixed */
			public function get_var( string $statement ) {
				return $this->inside;
			}

			public function suppress_errors( bool $suppress ): bool {
				$was            = $this->suppress;
				$this->suppress = $suppress;
				return $was;
			}
		};
		$GLOBALS['wpdb'] = $this->db;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function test_a_group_is_one_transaction_from_begin_to_end(): void {
		$groups = new WriteGroups();

		$groups->begin();
		$groups->checkpoint();
		$groups->end();

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->db->statements, 'Under a second, a checkpoint commits nothing.' );
	}

	public function test_a_checkpoint_commits_a_group_older_than_a_second_and_opens_the_next(): void {
		$groups = new WriteGroups();
		$groups->begin();
		// The group was opened more than a second ago.
		\Closure::bind(
			function (): void {
				$this->since -= WriteGroups::SECONDS + 0.1;
			},
			$groups,
			WriteGroups::class
		)();

		$groups->checkpoint();
		$groups->end();

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT', 'START TRANSACTION', 'COMMIT' ), $this->db->statements );
	}

	public function test_begin_twice_opens_one_group(): void {
		$groups = new WriteGroups();

		$groups->begin();
		$groups->begin();
		$groups->end();
		$groups->end();

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->db->statements );
	}

	public function test_nothing_is_opened_inside_someone_elses_transaction(): void {
		$this->db->inside = '1';
		$groups           = new WriteGroups();

		$groups->begin();
		$groups->checkpoint();
		$groups->end();

		$this->assertSame( array(), $this->db->statements, 'Starting one would commit theirs.' );
	}

	public function test_a_database_that_cannot_tell_counts_as_outside_a_transaction(): void {
		$this->db->inside = null;
		$groups           = new WriteGroups();

		$groups->begin();
		$groups->end();

		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $this->db->statements );
	}

	public function test_the_filter_turns_grouping_off(): void {
		Filters\expectApplied( 'wpmus_sync_group_writes' )->once()->with( true )->andReturn( false );
		$groups = new WriteGroups();

		$groups->begin();
		$groups->checkpoint();
		$groups->end();

		$this->assertSame( array(), $this->db->statements );
	}
}
