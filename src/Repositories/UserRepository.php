<?php
/**
 * Wraps WordPress user-related multisite operations so the sync engine
 * can talk to a thin, mockable abstraction instead of `add_user_to_blog`
 * and `is_user_member_of_blog` directly.
 *
 * @package WPMUS\Repositories
 */

declare(strict_types=1);

namespace WPMUS\Repositories;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Note: not declared `final` so Mockery can subclass it for unit
 * tests. The class is still treated as a leaf in production.
 */
class UserRepository {

	/**
	 * Every WP_User in the network (regardless of which sites they
	 * currently belong to). The legacy implementation passed `blog_id => 0`
	 * to `get_users()`, which WP interprets as "all sites".
	 *
	 * @return \WP_User[]
	 */
	public function all_network_users(): array {
		$users = get_users( array( 'blog_id' => 0 ) );
		/** @var \WP_User[] $users */
		return $users;
	}

	/**
	 * Returns true when the user is a member of the given blog.
	 * Coerces the int/bool union historic versions of WordPress
	 * sometimes returned to a strict `bool`.
	 */
	public function is_member_of( int $user_id, int $blog_id ): bool {
		return (bool) is_user_member_of_blog( $user_id, $blog_id );
	}

	/**
	 * Add a user to a blog with the given role. Returns the WP return
	 * value as-is — `add_user_to_blog` returns `true|WP_Error|null`.
	 *
	 * @return true|\WP_Error|null
	 */
	public function add_to_blog( int $blog_id, int $user_id, string $role ) {
		return add_user_to_blog( $blog_id, $user_id, $role );
	}

	/**
	 * Find a user by login name. Returns null (not false) when the
	 * login does not match any user, so callers can pattern-match
	 * with `=== null`.
	 */
	public function find_by_login( string $login ): ?\WP_User {
		$user = get_user_by( 'login', $login );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Returns true when the user has the legacy `msum_has_caps` meta
	 * flag set to the literal string `"true"`. Used by the login
	 * trigger to skip users that the old MSUM plugin already
	 * processed.
	 */
	public function has_legacy_msum_caps( int $user_id ): bool {
		return 'true' === (string) get_user_meta( $user_id, 'msum_has_caps', true );
	}
}
