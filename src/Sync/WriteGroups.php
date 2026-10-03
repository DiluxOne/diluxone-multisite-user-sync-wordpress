<?php
/**
 * Groups a sync's database writes into transactions of about a second.
 *
 * Core's `add_user_to_blog()` makes several writes per membership, and
 * the database commits each one on its own, waiting for the disk every
 * time; that wait, not the work, is most of what a big sync costs. In a
 * transaction the writes of a whole second are committed together.
 *
 * A group is short, so other requests are never kept waiting long. When
 * a run dies inside one, the database drops that group's memberships and
 * the next run adds them again (the cursor is stored after the batch, so
 * it never moves past them): nothing is lost or doubled, but whatever
 * other plugins did on `add_user_to_blog` for that group happens twice.
 * The `wpmus_sync_group_writes` filter turns grouping off for a network
 * where that matters.
 *
 * @package WPMUS\Sync
 */

declare(strict_types=1);

namespace WPMUS\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Note: not declared `final` so Mockery can subclass it for unit
 * tests. The class is still treated as a leaf in production.
 */
class WriteGroups {

	/**
	 * Seconds of writes one group holds before it is committed.
	 */
	public const SECONDS = 1.0;

	private bool $open = false;

	private float $since = 0.0;

	/**
	 * Opens a group, unless grouping is filtered off or the request is
	 * already inside a transaction of someone else's: starting one would
	 * commit theirs.
	 */
	public function begin(): void {
		if ( $this->open ) {
			return;
		}
		/**
		 * Filters whether a sync groups its writes into transactions of
		 * about a second. Off, every write commits on its own, as core
		 * does by itself: slower, and no group to redo if a run dies.
		 *
		 * @param bool $group Default true.
		 */
		if ( ! apply_filters( 'wpmus_sync_group_writes', true ) || $this->in_transaction() ) {
			return;
		}
		$this->query( 'START TRANSACTION' );
		$this->open  = true;
		$this->since = microtime( true );
	}

	/**
	 * Commits the open group once it holds a second of writes, and opens
	 * the next.
	 */
	public function checkpoint(): void {
		if ( ! $this->open || microtime( true ) - $this->since < self::SECONDS ) {
			return;
		}
		$this->query( 'COMMIT' );
		$this->query( 'START TRANSACTION' );
		$this->since = microtime( true );
	}

	/**
	 * Commits the open group, if any.
	 */
	public function end(): void {
		if ( ! $this->open ) {
			return;
		}
		$this->query( 'COMMIT' );
		$this->open = false;
	}

	/**
	 * True when the connection is inside a transaction already. A
	 * database that cannot tell (no `@@in_transaction`, before MySQL 5.7
	 * or MariaDB 10.3) answers null, read as no.
	 */
	private function in_transaction(): bool {
		global $wpdb;
		$suppressed = $wpdb->suppress_errors( true );
		$inside     = $wpdb->get_var( 'SELECT @@in_transaction' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->suppress_errors( $suppressed );
		return '1' === (string) $inside;
	}

	/**
	 * Sends one of the fixed transaction statements.
	 */
	private function query( string $statement ): void {
		global $wpdb;
		$wpdb->query( $statement ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- a fixed statement, no input.
	}
}
