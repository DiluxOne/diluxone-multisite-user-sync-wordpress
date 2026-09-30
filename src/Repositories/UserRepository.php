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
	 * User meta (network-wide, users live in one table) listing the
	 * blog ids a user was removed from.
	 */
	public const META_REMOVED_FROM = 'wpmus_removed_from_blogs';

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
	 * Blog ids the user was removed from while the plugin was active.
	 * Automatic syncs never add the user back to these sites.
	 *
	 * @return int[]
	 */
	public function removed_blog_ids( int $user_id ): array {
		$ids = get_user_meta( $user_id, self::META_REMOVED_FROM, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * Remembers that the user was removed from `$blog_id`.
	 */
	public function record_removal( int $user_id, int $blog_id ): void {
		$ids = $this->removed_blog_ids( $user_id );
		if ( in_array( $blog_id, $ids, true ) ) {
			return;
		}
		$ids[] = $blog_id;
		update_user_meta( $user_id, self::META_REMOVED_FROM, $ids );
	}

	/**
	 * Forgets a removal, once the user is a member of `$blog_id` again.
	 */
	public function forget_removal( int $user_id, int $blog_id ): void {
		$ids = $this->removed_blog_ids( $user_id );
		if ( ! in_array( $blog_id, $ids, true ) ) {
			return;
		}
		$ids = array_values( array_diff( $ids, array( $blog_id ) ) );
		if ( array() === $ids ) {
			delete_user_meta( $user_id, self::META_REMOVED_FROM );
			return;
		}
		update_user_meta( $user_id, self::META_REMOVED_FROM, $ids );
	}
}
