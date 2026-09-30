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
	 * One page of the ids of every user on the network (regardless of
	 * which sites they belong to), oldest account first. `blog_id => 0`
	 * asks WordPress for all users; only ids are loaded.
	 *
	 * @return int[]
	 */
	public function network_user_ids( int $offset, int $limit ): array {
		$ids = get_users(
			array(
				'blog_id'     => 0,
				'fields'      => 'ID',
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'number'      => $limit,
				'offset'      => $offset,
				'count_total' => false,
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * How many users the network has.
	 */
	public function count_network_users(): int {
		$query = new \WP_User_Query(
			array(
				'blog_id'     => 0,
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);
		return (int) $query->get_total();
	}

	/**
	 * Ids of the network's super admins. `get_super_admins()` returns
	 * logins, so each one is resolved to its user.
	 *
	 * @return int[]
	 */
	public function super_admin_ids(): array {
		$ids = array();
		foreach ( get_super_admins() as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user instanceof \WP_User ) {
				$ids[] = (int) $user->ID;
			}
		}
		return $ids;
	}

	/**
	 * Ids of the active sites the user is a member of.
	 *
	 * @return int[]
	 */
	public function blog_ids_of_user( int $user_id ): array {
		return array_map( 'intval', array_keys( get_blogs_of_user( $user_id ) ) );
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
