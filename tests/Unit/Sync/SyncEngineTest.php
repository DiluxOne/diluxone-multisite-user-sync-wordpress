<?php
/**
 * Unit tests for {@see \WPMUS\Sync\SyncEngine}.
 *
 * SyncEngine has zero direct WordPress coupling — every interaction
 * goes through Config / SiteRepository / UserRepository. The test
 * suite mocks those collaborators with Mockery and asserts the
 * call shape: which methods fire, how many times, with what args.
 *
 * The most important test in the file is
 * {@see test_role_change_during_inner_add_to_blog_does_not_recurse}
 * which is the regression test for the re-entrancy bug Copilot
 * caught on PR #18 review: `add_user_to_blog()` synchronously fires
 * WordPress's `set_user_role` action, which would re-enter
 * `on_role_changed()` and cascade roles across the network if not
 * guarded.
 *
 * @package WPMUS\Tests\Unit\Sync
 */

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class SyncEngineTest extends TestCase {

	/** @var Config&MockInterface */
	private $config;
	/** @var SiteRepository&MockInterface */
	private $sites;
	/** @var UserRepository&MockInterface */
	private $users;

	protected function setUp(): void {
		parent::setUp();
		$this->config = Mockery::mock( Config::class );
		$this->sites  = Mockery::mock( SiteRepository::class );
		$this->users  = Mockery::mock( UserRepository::class );
		$this->users->shouldReceive( 'removed_blog_ids' )->andReturn( array() )->byDefault();
	}

	protected function tearDown(): void {
		Mockery::close();
		parent::tearDown();
	}

	private function engine(): SyncEngine {
		/** @var Config $config */
		$config = $this->config;
		/** @var SiteRepository $sites */
		$sites = $this->sites;
		/** @var UserRepository $users */
		$users = $this->users;

		return new SyncEngine( $config, $sites, $users );
	}

	// ---------------------------------------------------------------------
	// on_new_site
	// ---------------------------------------------------------------------

	public function test_on_new_site_returns_early_when_toggle_off(): void {
		$this->config->shouldReceive( 'is_new_site_sync_enabled' )->once()->andReturn( false );
		$this->users->shouldNotReceive( 'all_network_users' );
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_new_site( 7 );
	}

	public function test_on_new_site_gives_the_sites_default_role_whatever_role_the_user_has_elsewhere(): void {
		$editor = new \WP_User( 5, array( 'editor' ) );
		$admin  = new \WP_User( 6, array( 'administrator' ) );
		$this->config->shouldReceive( 'is_new_site_sync_enabled' )->andReturn( true );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( $editor, $admin ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 7 )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 7, 5, 'subscriber' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 7, 6, 'subscriber' )->andReturn( true );

		$this->engine()->on_new_site( 7 );
	}

	public function test_on_new_site_skips_users_already_member(): void {
		$existing_member = new \WP_User( 5, array( 'editor' ) );
		$this->config->shouldReceive( 'is_new_site_sync_enabled' )->andReturn( true );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( $existing_member ) );
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 7 )->andReturn( true );
		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 7 )->andReturn( 'subscriber' );
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_new_site( 7 );
	}

	// ---------------------------------------------------------------------
	// on_new_user
	// ---------------------------------------------------------------------

	public function test_on_new_user_returns_early_when_toggle_off(): void {
		$this->config->shouldReceive( 'is_new_user_sync_enabled' )->once()->andReturn( false );
		$this->sites->shouldNotReceive( 'all_blog_ids' );
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_new_user( 5 );
	}

	public function test_on_new_user_adds_user_to_every_missing_site(): void {
		$this->config->shouldReceive( 'is_new_user_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2, 3 ) );

		// User is already on blog 1, missing from 2 and 3.
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 1 )->andReturn( true );
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 2 )->andReturn( false );
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 3 )->andReturn( false );

		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 2 )->andReturn( 'subscriber' );
		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 3 )->andReturn( 'editor' );

		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'subscriber' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'editor' )->andReturn( true );

		$this->engine()->on_new_user( 5 );
	}

	// ---------------------------------------------------------------------
	// Removals
	// ---------------------------------------------------------------------

	public function test_a_removal_is_recorded(): void {
		$this->users->shouldReceive( 'record_removal' )->once()->with( 5, 3 );

		$this->engine()->on_user_removed_from_blog( 5, 3 );
	}

	public function test_someone_else_adding_the_user_back_forgets_the_removal(): void {
		$this->users->shouldReceive( 'forget_removal' )->once()->with( 5, 3 );

		$this->engine()->on_user_added_to_blog( 5, 'subscriber', 3 );
	}

	public function test_new_user_trigger_skips_sites_the_user_was_removed_from(): void {
		$this->config->shouldReceive( 'is_new_user_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 2, 3 ) );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->users->shouldReceive( 'removed_blog_ids' )->with( 5 )->andReturn( array( 3 ) );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'subscriber' )->andReturn( true );

		$this->engine()->on_new_user( 5 );
	}

	public function test_forced_manual_sync_adds_a_removed_user_back_and_forgets_the_removal(): void {
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->users->shouldReceive( 'removed_blog_ids' )->with( 5 )->andReturn( array( 3 ) );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'subscriber' )->andReturn( true );
		$this->users->shouldReceive( 'forget_removal' )->once()->with( 5, 3 );

		$this->engine()->sync_all_users_to_sites( array( 3 ), true );
	}

	// ---------------------------------------------------------------------
	// on_role_changed
	// ---------------------------------------------------------------------

	public function test_on_role_changed_returns_early_when_toggle_off(): void {
		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->once()->andReturn( false );
		$this->sites->shouldNotReceive( 'all_blog_ids' );

		$this->engine()->on_role_changed( 5, 'editor' );
	}

	public function test_on_role_changed_propagates_role_to_existing_memberships_only(): void {
		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2, 3 ) );

		$this->users->shouldReceive( 'is_member_of' )->with( 5, 1 )->andReturn( true );
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 2 )->andReturn( false ); // not a member → no add
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 3 )->andReturn( true );

		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 1, 5, 'editor' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'editor' )->andReturn( true );
		// blog 2 explicitly not added — only existing memberships are touched.

		$this->engine()->on_role_changed( 5, 'editor' );
	}

	// ---------------------------------------------------------------------
	// sync_all_users_to_all_sites
	// ---------------------------------------------------------------------

	public function test_sync_all_users_to_all_sites_runs_unconditionally(): void {
		// No call to is_*_enabled — manual actions are gated by the
		// admin handler's nonce + capability check, not by the
		// trigger toggles.
		$this->config->shouldNotReceive( 'is_new_site_sync_enabled' );
		$this->config->shouldNotReceive( 'is_new_user_sync_enabled' );
		$this->config->shouldNotReceive( 'is_set_user_role_sync_enabled' );

		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );

		$this->users->shouldReceive( 'is_member_of' )->with( 5, 1 )->andReturn( true );
		$this->users->shouldReceive( 'is_member_of' )->with( 5, 2 )->andReturn( false );

		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 2 )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'subscriber' )->andReturn( true );

		$this->engine()->sync_all_users_to_all_sites();
	}

	// ---------------------------------------------------------------------
	// sync_all_users_to_sites — perf regression: users fetched once
	// ---------------------------------------------------------------------

	public function test_sync_all_users_to_sites_fetches_users_once_for_n_sites(): void {
		// Regression test: previously the user list was fetched inside
		// the outer site loop, turning a single expensive query into
		// N. The hoisted version does it once.
		$this->users->shouldReceive( 'all_network_users' )
			->once() // <-- the assertion
			->andReturn( array( new \WP_User( 5 ) ) );

		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->andReturn( true );

		$this->engine()->sync_all_users_to_sites( array( 1, 2, 3, 4, 5 ) );
	}

	// ---------------------------------------------------------------------
	// Re-entrancy guard — the regression test for the Copilot finding
	// ---------------------------------------------------------------------

	public function test_role_change_during_inner_add_to_blog_does_not_recurse(): void {
		// Scenario: role-sync is ON. The admin runs a manual `Sync from
		// scratch`, which calls add_to_blog → WP fires set_user_role →
		// on_role_changed gets invoked. Without the in_sync guard the
		// inner on_role_changed would re-iterate every blog and call
		// add_to_blog again, which fires set_user_role again, etc.
		//
		// We simulate the WP-side dispatch here: the add_to_blog mock
		// invokes on_role_changed during its execution. The assertion
		// is that all_blog_ids is called EXACTLY ONCE (from
		// sync_all_users_to_all_sites). If the guard fails, the inner
		// on_role_changed call would also call all_blog_ids for its
		// own iteration, producing >1 calls.
		$engine = $this->engine();

		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );

		// One call from the outer sync_all_users_to_all_sites.
		$this->sites->shouldReceive( 'all_blog_ids' )->once()->andReturn( array( 1, 2 ) );

		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );

		// User missing on both sites — both will be add_to_blog'd.
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );

		// Simulate WP firing set_user_role during add_user_to_blog.
		$this->users->shouldReceive( 'add_to_blog' )->andReturnUsing(
			static function ( int $blog_id, int $user_id, string $role ) use ( $engine ) {
				$engine->on_role_changed( $user_id, $role );
				return true;
			}
		);

		$engine->sync_all_users_to_all_sites();
		// Mockery's expectations on call counts (the `->once()` on
		// all_blog_ids) provide the assertion. Without a guard,
		// Mockery would fail the test with a too-many-invocations
		// error.
		$this->assertTrue( true ); // satisfy PHPUnit risky-test detection.
	}

	public function test_role_change_outside_a_sync_method_runs_normally(): void {
		// Sanity check: the in_sync flag is reset between calls, so a
		// real role-change event after a manual sync still propagates
		// across existing memberships.
		$engine = $this->engine();

		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2 ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->twice()->andReturn( true );

		$engine->on_role_changed( 5, 'editor' );
	}
}
