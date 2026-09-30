<?php
/**
 * Unit tests for {@see \WPMUS\Sync\SyncJob}: what is stored in the
 * network option comes back as the same job, and anything else is
 * rejected.
 *
 * @package WPMUS\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Tests\TestCase;
use WPMUS\Sync\SyncJob;

final class SyncJobTest extends TestCase {

	public function test_a_stored_job_comes_back_with_its_progress(): void {
		$job               = new SyncJob( 'manual', null, array( '3', 4 ), true );
		$job->user_offset  = 12;
		$job->last_blog_id = 4;
		$job->processed    = 25;
		$job->total        = 90;

		$copy = SyncJob::from_array( $job->to_array() );

		$this->assertNotNull( $copy );
		$this->assertSame( $job->to_array(), $copy->to_array() );
		$this->assertSame( array( 3, 4 ), $copy->blog_ids );
		$this->assertNull( $copy->user_ids );
		$this->assertTrue( $copy->force );
		$this->assertFalse( $copy->done );
	}

	public function test_anything_that_is_not_a_job_is_rejected(): void {
		$this->assertNull( SyncJob::from_array( 'nope' ) );
		$this->assertNull( SyncJob::from_array( array( 'context' => 'manual' ) ) );
		$this->assertNull( SyncJob::from_array( array( 'id' => 5, 'context' => 'manual' ) ) );
	}
}
