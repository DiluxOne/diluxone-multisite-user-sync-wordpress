<?php
/**
 * Wraps WordPress multisite-site lookups so the rest of the codebase
 * can talk to a thin, mockable abstraction instead of `$wpdb` and
 * `get_sites()` directly.
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
class SiteRepository {

	/**
	 * Every blog ID in the current network. Returns int[] regardless of
	 * how WP encodes them internally.
	 *
	 * @return int[]
	 */
	public function all_blog_ids(): array {
		$sites = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => 0,
				'update_site_meta_cache' => false,
			)
		);
		return array_map( 'intval', $sites );
	}

	/**
	 * Site rows for UI rendering (domain + path + blog_id).
	 *
	 * @return \WP_Site[]
	 */
	public function all_sites(): array {
		$sites = get_sites(
			array(
				'number'                 => 0,
				'update_site_meta_cache' => false,
			)
		);
		/** @var \WP_Site[] $sites */
		return $sites;
	}

	/**
	 * Resolves the default user role configured for `$blog_id`,
	 * falling back to `subscriber` when the site has no `default_role`
	 * option (or it is empty / non-string).
	 */
	public function default_role_for_blog( int $blog_id ): string {
		$role = get_blog_option( $blog_id, 'default_role', 'subscriber' );
		return is_string( $role ) && '' !== $role ? $role : 'subscriber';
	}
}
