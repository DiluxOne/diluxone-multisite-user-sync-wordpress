<?php
/**
 * The queue of sync jobs too big for one request, and the WP-Cron
 * event that works through it.
 *
 * Jobs live in a network option, so every site of the network sees
 * the same queue. The cron event is scheduled on the main site:
 * WP-Cron stores events per site, and the main site is the one that
 * reliably gets traffic.
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
class JobQueue {

	public const OPTION_JOBS = 'wpmus_sync_jobs';
	public const OPTION_LOCK = 'wpmus_sync_lock';
	public const CRON_HOOK   = 'wpmus_process_sync_queue';

	/**
	 * A lock older than this is considered abandoned (a run that
	 * died), in seconds.
	 */
	public const LOCK_TTL = 600;

	/**
	 * Every queued job, oldest first.
	 *
	 * @return SyncJob[]
	 */
	public function all(): array {
		$stored = get_site_option( self::OPTION_JOBS, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		$jobs = array();
		foreach ( $stored as $data ) {
			$job = SyncJob::from_array( $data );
			if ( null !== $job ) {
				$jobs[] = $job;
			}
		}
		return $jobs;
	}

	/**
	 * The job to work on next, or null when the queue is empty.
	 */
	public function first(): ?SyncJob {
		$jobs = $this->all();
		return array() === $jobs ? null : $jobs[0];
	}

	/**
	 * Appends a job.
	 */
	public function add( SyncJob $job ): void {
		$jobs   = $this->all();
		$jobs[] = $job;
		$this->save( $jobs );
	}

	/**
	 * Stores a job's progress.
	 */
	public function update( SyncJob $job ): void {
		$jobs = $this->all();
		foreach ( $jobs as $i => $stored ) {
			if ( $stored->id === $job->id ) {
				$jobs[ $i ] = $job;
			}
		}
		$this->save( $jobs );
	}

	/**
	 * Drops a job.
	 */
	public function remove( string $id ): void {
		$jobs = array_filter(
			$this->all(),
			static function ( SyncJob $job ) use ( $id ): bool {
				return $job->id !== $id;
			}
		);
		$this->save( array_values( $jobs ) );
	}

	/**
	 * Empties the queue and removes its cron event.
	 */
	public function clear(): void {
		delete_site_option( self::OPTION_JOBS );
		delete_site_option( self::OPTION_LOCK );
		$this->on_main_site(
			static function (): void {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
		);
	}

	/**
	 * Takes the lock that keeps two cron runs from working the same
	 * job. False when another run holds it.
	 */
	public function acquire_lock(): bool {
		$now = time();
		if ( add_site_option( self::OPTION_LOCK, $now ) ) {
			return true;
		}
		$held_since = (int) get_site_option( self::OPTION_LOCK, 0 );
		if ( $now - $held_since < self::LOCK_TTL ) {
			return false;
		}
		update_site_option( self::OPTION_LOCK, $now );
		return true;
	}

	/**
	 * Releases the lock.
	 */
	public function release_lock(): void {
		delete_site_option( self::OPTION_LOCK );
	}

	/**
	 * Schedules the next run on the main site, unless one is pending.
	 */
	public function schedule(): void {
		$this->on_main_site(
			static function (): void {
				if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
					wp_schedule_single_event( time(), self::CRON_HOOK );
				}
			}
		);
	}

	/**
	 * True when a run is scheduled on the main site.
	 */
	public function is_scheduled(): bool {
		$scheduled = false;
		$this->on_main_site(
			static function () use ( &$scheduled ): void {
				$scheduled = false !== wp_next_scheduled( self::CRON_HOOK );
			}
		);
		return $scheduled;
	}

	/**
	 * Stores the job list; an empty list deletes the option.
	 *
	 * @param SyncJob[] $jobs Jobs to store.
	 */
	private function save( array $jobs ): void {
		if ( array() === $jobs ) {
			delete_site_option( self::OPTION_JOBS );
			return;
		}
		update_site_option(
			self::OPTION_JOBS,
			array_map(
				static function ( SyncJob $job ): array {
					return $job->to_array();
				},
				$jobs
			)
		);
	}

	/**
	 * Runs `$callback` switched to the network's main site.
	 *
	 * @param callable():void $callback What to run.
	 */
	private function on_main_site( callable $callback ): void {
		switch_to_blog( get_main_site_id() );
		try {
			$callback();
		} finally {
			restore_current_blog();
		}
	}
}
