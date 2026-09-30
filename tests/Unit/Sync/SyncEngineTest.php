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
		$this->users->shouldReceive( 'super_admin_ids' )->andReturn( array() )->byDefault();
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
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 7 ) );
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
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 7 ) );
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

	public function test_on_new_site_accepts_the_wp_site_from_wp_initialize_site(): void {
		$this->config->shouldReceive( 'is_new_site_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 7 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->with( 7 )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 7, 5, 'subscriber' )->andReturn( true );

		$this->engine()->on_new_site( new \WP_Site( 7, 'localhost', '/seven/' ) );
	}

	public function test_a_new_user_is_synced_once_whichever_hooks_fire(): void {
		$this->config->shouldReceive( 'is_new_user_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->once()->andReturn( array( 2 ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'subscriber' )->andReturn( true );

		// wpmu_create_user(): user_register, then wpmu_new_user, then shutdown.
		$engine = $this->engine();
		$engine->on_user_registered( 5 );
		$engine->on_new_user( 5 );
		$engine->flush_registered_users();
	}

	public function test_a_user_made_with_wp_insert_user_alone_is_synced_at_shutdown(): void {
		$this->config->shouldReceive( 'is_new_user_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 2 ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );

		$added = array();
		$this->users->shouldReceive( 'add_to_blog' )->andReturnUsing(
			static function ( int $blog_id, int $user_id, string $role ) use ( &$added ): bool {
				$added[] = array( $blog_id, $user_id, $role );
				return true;
			}
		);

		$engine = $this->engine();
		$engine->on_user_registered( 6 );
		$this->assertSame( array(), $added, 'Nothing happens at user_register.' );

		$engine->flush_registered_users();
		$this->assertSame( array( array( 2, 6, 'subscriber' ) ), $added );
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
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 3 ) );
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

	/**
	 * Role sync on, the change made on site 1, the user a member of
	 * sites 1, 2 and 3, every site active and defining every role.
	 */
	private function role_change_on_site_one(): void {
		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );
		$this->sites->shouldReceive( 'current_blog_id' )->andReturn( 1 );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2, 3, 4 ) );
		$this->users->shouldReceive( 'blog_ids_of_user' )->with( 5 )->andReturn( array( 1, 2, 3 ) );
		$this->sites->shouldReceive( 'role_exists_on_blog' )->andReturn( true )->byDefault();
	}

	public function test_on_role_changed_copies_the_role_to_the_users_other_sites(): void {
		$this->role_change_on_site_one();
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'editor' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'editor' )->andReturn( true );

		$this->engine()->on_role_changed( 5, 'editor', array( 'subscriber' ) );
	}

	public function test_on_role_changed_skips_a_site_without_that_role(): void {
		$this->role_change_on_site_one();
		$this->sites->shouldReceive( 'role_exists_on_blog' )->with( 2, 'shop_manager' )->andReturn( false );
		$this->sites->shouldReceive( 'role_exists_on_blog' )->with( 3, 'shop_manager' )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'shop_manager' )->andReturn( true );

		$this->engine()->on_role_changed( 5, 'shop_manager', array( 'subscriber' ) );
	}

	public function test_on_role_changed_does_not_copy_administrator(): void {
		$this->role_change_on_site_one();
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_role_changed( 5, 'administrator', array( 'subscriber' ) );
	}

	public function test_on_role_changed_copies_administrator_when_the_filter_allows_it(): void {
		$this->role_change_on_site_one();
		\Brain\Monkey\Filters\expectApplied( 'wpmus_replicate_role' )->once()->with( false, 'administrator', 5 )->andReturn( true );
		$this->users->shouldReceive( 'add_to_blog' )->twice()->andReturn( true );

		$this->engine()->on_role_changed( 5, 'administrator', array( 'subscriber' ) );
	}

	public function test_on_role_changed_ignores_an_unchanged_or_empty_role(): void {
		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );
		$this->users->shouldNotReceive( 'blog_ids_of_user' );
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_role_changed( 5, 'editor', array( 'editor' ) );
		$this->engine()->on_role_changed( 5, '', array( 'editor' ) );
	}

	public function test_on_role_changed_leaves_super_admins_alone(): void {
		$this->config->shouldReceive( 'is_set_user_role_sync_enabled' )->andReturn( true );
		$this->users->shouldReceive( 'super_admin_ids' )->andReturn( array( 5 ) );
		$this->users->shouldNotReceive( 'add_to_blog' );

		$this->engine()->on_role_changed( 5, 'editor', array( 'subscriber' ) );
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
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2, 3, 4, 5 ) );
		$this->users->shouldReceive( 'all_network_users' )
			->once() // <-- the assertion
			->andReturn( array( new \WP_User( 5 ) ) );

		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->andReturn( true );

		$this->engine()->sync_all_users_to_sites( array( 1, 2, 3, 4, 5 ) );
	}

	// ---------------------------------------------------------------------
	// Exclusions
	// ---------------------------------------------------------------------

	public function test_super_admins_are_never_added(): void {
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 3 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 1 ), new \WP_User( 5 ) ) );
		$this->users->shouldReceive( 'super_admin_ids' )->andReturn( array( 1 ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 3, 5, 'subscriber' )->andReturn( true );

		$this->engine()->sync_all_users_to_all_sites();
	}

	public function test_requested_sites_that_are_not_active_are_left_alone(): void {
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 2, 5, 'subscriber' )->andReturn( true );

		$this->engine()->sync_all_users_to_sites( array( 2, 9 ) );
	}

	public function test_the_site_filter_excludes_sites(): void {
		\Brain\Monkey\Filters\expectApplied( 'wpmus_excluded_site_ids' )->once()->with( array(), 'manual' )->andReturn( array( 2 ) );
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 1, 5, 'subscriber' )->andReturn( true );

		$this->engine()->sync_all_users_to_all_sites();
	}

	public function test_the_user_filter_excludes_a_user_from_a_site(): void {
		\Brain\Monkey\Filters\expectApplied( 'wpmus_should_sync_user' )->twice()->andReturnUsing(
			static function ( bool $sync, int $user_id, int $blog_id ): bool {
				return 2 !== $blog_id;
			}
		);
		$this->sites->shouldReceive( 'all_blog_ids' )->andReturn( array( 1, 2 ) );
		$this->users->shouldReceive( 'all_network_users' )->andReturn( array( new \WP_User( 5 ) ) );
		$this->users->shouldReceive( 'is_member_of' )->andReturn( false );
		$this->sites->shouldReceive( 'default_role_for_blog' )->andReturn( 'subscriber' );
		$this->users->shouldReceive( 'add_to_blog' )->once()->with( 1, 5, 'subscriber' )->andReturn( true );

		$this->engine()->sync_all_users_to_all_sites();
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
				$engine->on_role_changed( $user_id, $role, array() );
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
		$this->role_change_on_site_one();
		$this->users->shouldReceive( 'add_to_blog' )->twice()->andReturn( true );

		$this->engine()->on_role_changed( 5, 'editor', array( 'subscriber' ) );
	}
}
