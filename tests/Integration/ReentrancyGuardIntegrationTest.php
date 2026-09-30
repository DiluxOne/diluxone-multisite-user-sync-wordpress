<?php
/**
 * The integration counterpart of
 * {@see \Tests\Unit\Sync\SyncEngineTest::test_role_change_during_inner_add_to_blog_does_not_recurse}.
 *
 * The unit version uses a mock to simulate WP firing `set_user_role`
 * inside `add_user_to_blog`. This version exercises the same code
 * path against the REAL WordPress runtime — `add_user_to_blog` does
 * fire `set_user_role` for real, the plugin's actual `on_role_changed`
 * callback is wired by `Plugin::register()`, and we verify that no
 * cascade happens.
 *
 * If the in_sync guard regresses, this test fails with a runaway
 * propagation that this assertion catches: a role change on one site
 * stays on that site (and on existing memberships of the same user)
 * without spreading to UNRELATED users on other sites.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;

final class ReentrancyGuardIntegrationTest extends IntegrationTestCase {

	public function test_manual_full_sync_does_not_cascade_via_set_user_role_hook(): void {
		// Two distinct users, two extra sites.
		$user_a = $this->make_user( 'reentry-alice-' . substr( (string) microtime( true ), -5 ), '', 'editor' );
		$user_b = $this->make_user( 'reentry-bob-' . substr( (string) microtime( true ), -5 ), '', 'subscriber' );
		$site_x = $this->make_site( 'reentry-x-' . substr( (string) microtime( true ), -5 ) );
		$site_y = $this->make_site( 'reentry-y-' . substr( (string) microtime( true ), -5 ) );

		// Pre-seed user_b on site_x with the 'subscriber' role. We will
		// later assert that user_b's role on site_x is NOT touched by
		// user_a's full sync (which would happen if the cascade fired).
		add_user_to_blog( $site_x, $user_b, 'subscriber' );

		// Turn role-sync ON so any unintended `set_user_role` cascade
		// would flow through `on_role_changed` and propagate.
		( new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ) )->save_toggles( '', '', 'yes' );

		// Wire this test's own engine onto WP's hook for the duration
		// of the test, so the guard under test is the one on the engine
		// that runs the sync.
		$engine = new SyncEngine(
			new Config( dirname( __DIR__, 2 ) . '/wpm-user-sync.php' ),
			new SiteRepository(),
			new UserRepository()
		);
		add_action( 'set_user_role', array( $engine, 'on_role_changed' ), 10, 3 );

		try {
			// Run the full sync. Without the in_sync guard, the chain is:
			//   sync_all_users_to_all_sites
			//     → add_user_to_blog(user_a, role_x)
			//       → fires set_user_role(user_a, role_x)
			//         → on_role_changed iterates every blog
			//           → add_user_to_blog(user_b, role_x) (if cross-user contamination)
			//             → fires set_user_role(user_b, role_x)
			//               → ...
			// With the guard, only the outer sync_all writes happen.
			$engine->sync_all_users_to_all_sites();
		} finally {
			remove_action( 'set_user_role', array( $engine, 'on_role_changed' ), 10 );
		}

		// user_b on site_x must STILL be 'subscriber'. If the cascade
		// fired, user_a's sync would have hit on_role_changed which
		// would have re-rolled user_b's existing memberships.
		$switch = switch_to_blog( $site_x );
		$user   = new \WP_User( $user_b );
		$roles  = $user->roles;
		restore_current_blog();
		unset( $switch );

		$this->assertContains(
			'subscriber',
			$roles,
			'user_b\'s pre-existing role on site_x must remain "subscriber". A cascade would have overwritten it.'
		);
		$this->assertNotContains(
			'editor',
			$roles,
			'user_b must NOT have inherited user_a\'s role through a cascade.'
		);

		// Sanity check the actual sync did happen — user_a should
		// have been added to site_y (which had no memberships pre-test).
		$this->assertTrue(
			$this->is_member( $user_a, $site_y ),
			'The intentional sync must still have run.'
		);
	}
}
