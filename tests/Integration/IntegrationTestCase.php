<?php
/**
 * Base test case for the integration suite. Each test starts from a
 * known-empty toggle state and tears down any test-created sites/users.
 *
 * Tests that touch real WordPress state — database rows, site options,
 * user memberships — go in this directory and extend this class.
 *
 * @package WPMUS\Tests\Integration
 */

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use WPMUS\Config;

abstract class IntegrationTestCase extends TestCase {

	/**
	 * Track every blog id created during the test so tearDown can
	 * remove it via `wpmu_delete_blog()` (drops the tables, removes
	 * memberships).
	 *
	 * @var int[]
	 */
	protected array $created_blog_ids = array();

	/**
	 * Track every user id created during the test so tearDown can
	 * remove it via `wpmu_delete_user()` (removes from every site
	 * and deletes the wp_user row).
	 *
	 * @var int[]
	 */
	protected array $created_user_ids = array();

	protected function setUp(): void {
		parent::setUp();
		$this->reset_toggle_state();
	}

	protected function tearDown(): void {
		// Clean up sites first so user-membership cleanup happens
		// against a smaller blog set.
		foreach ( $this->created_blog_ids as $blog_id ) {
			if ( $blog_id > 1 ) {
				wpmu_delete_blog( $blog_id, true );
			}
		}
		$this->created_blog_ids = array();

		// Then users — wpmu_delete_user removes them from every blog
		// they were members of and deletes the wp_users row.
		if ( ! function_exists( 'wpmu_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
		}
		foreach ( $this->created_user_ids as $user_id ) {
			if ( $user_id > 1 ) {
				wpmu_delete_user( $user_id );
			}
		}
		$this->created_user_ids = array();

		$this->reset_toggle_state();
		parent::tearDown();
	}

	protected function reset_toggle_state(): void {
		delete_site_option( Config::OPTION_NEW_SITE_SYNC );
		delete_site_option( Config::OPTION_NEW_USER_SYNC );
		delete_site_option( Config::OPTION_SET_USER_ROLE_SYNC );
	}

	/**
	 * Create a fresh user via WordPress's normal API. Tracks the id
	 * for tearDown so the test does not leak rows into the database.
	 *
	 * Returns the new user's id, or 0 if creation failed (the test
	 * should assert against a positive id when it expects success).
	 */
	protected function make_user( string $login, string $email = '', string $role = 'subscriber' ): int {
		if ( '' === $email ) {
			$email = $login . '@integration.test';
		}
		$user_id = wpmu_create_user( $login, 'integration-pass', $email );
		if ( ! is_int( $user_id ) || $user_id <= 0 ) {
			return 0;
		}
		// Set the network-level role intent. Memberships on individual
		// sites are added by the engine under test.
		$user = get_user_by( 'id', $user_id );
		if ( $user instanceof \WP_User ) {
			$user->set_role( $role );
		}
		$this->created_user_ids[] = $user_id;
		return (int) $user_id;
	}

	/**
	 * Create a fresh site under the current network. Returns the new
	 * blog id, or 0 if creation failed.
	 */
	protected function make_site( string $slug, ?int $admin_id = null ): int {
		$network         = get_network();
		$domain          = $network instanceof \WP_Network ? $network->domain : 'example.com';
		$path            = '/' . trim( $slug, '/' ) . '/';
		$admin_id        = $admin_id ?? 1;
		$site_or_error   = wpmu_create_blog( $domain, $path, ucfirst( $slug ), $admin_id );
		if ( ! is_int( $site_or_error ) || $site_or_error <= 0 ) {
			return 0;
		}
		$this->created_blog_ids[] = $site_or_error;
		return (int) $site_or_error;
	}

	/**
	 * Convenience wrapper for `is_user_member_of_blog` that returns a
	 * proper bool rather than the int/bool union the legacy WP function
	 * historically produced.
	 */
	protected function is_member( int $user_id, int $blog_id ): bool {
		return (bool) is_user_member_of_blog( $user_id, $blog_id );
	}
}
