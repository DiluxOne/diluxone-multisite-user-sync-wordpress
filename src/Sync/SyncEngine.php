<?php
/**
 * Core synchronisation logic.
 *
 * Three trigger entry points (called from {@see \WPMUS\Plugin} hook
 * registrations) plus three manual entry points (called from network
 * and site admin actions). All of them read the toggle state from
 * {@see \WPMUS\Config} and delegate the multisite-side work to
 * {@see \WPMUS\Repositories\SiteRepository} and
 * {@see \WPMUS\Repositories\UserRepository}.
 *
 * The class deliberately does NOT reach into `$wpdb` or call WP user
 * functions directly — every interaction goes through the repositories,
 * so unit tests can mock them.
 *
 * @package WPMUS\Sync
 */

declare(strict_types=1);

namespace WPMUS\Sync;

use WPMUS\Config;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SyncEngine {

	private Config $config;
	private SiteRepository $sites;
	private UserRepository $users;

	public function __construct( Config $config, SiteRepository $sites, UserRepository $users ) {
		$this->config = $config;
		$this->sites  = $sites;
		$this->users  = $users;
	}

	/**
	 * Trigger callback for `wpmu_new_blog` (legacy hook) — populates a
	 * freshly-created site with every existing network user, using each
	 * site's default role. No-op when the `New Site Sync` toggle is off.
	 *
	 * @param int $blog_id The blog ID of the newly created site.
	 */
	public function on_new_site( int $blog_id ): void {
		if ( ! $this->config->is_new_site_sync_enabled() ) {
			return;
		}

		foreach ( $this->users->all_network_users() as $user ) {
			if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
				continue;
			}
			$role = is_array( $user->roles ) && isset( $user->roles[0] ) && '' !== $user->roles[0]
				? (string) $user->roles[0]
				: $this->sites->default_role_for_blog( $blog_id );
			$this->users->add_to_blog( $blog_id, (int) $user->ID, $role );
		}
	}

	/**
	 * Trigger callback for `wpmu_new_user` — adds a brand-new user to
	 * every existing site with each site's default role. No-op when the
	 * `New User Sync` toggle is off.
	 */
	public function on_new_user( int $user_id ): void {
		if ( ! $this->config->is_new_user_sync_enabled() ) {
			return;
		}
		$this->add_user_to_every_site( $user_id );
	}

	/**
	 * Trigger callback for `wp_login` and `social_connect_login`.
	 * Re-runs the new-user sync when the user logs in for the first
	 * time after creation, in case `wpmu_new_user` did not fire (e.g.
	 * users imported via SQL or third-party flows).
	 *
	 * The legacy `msum_has_caps` user-meta gate is preserved so previous
	 * installs that stored that flag still skip the redundant sync.
	 */
	public function maybe_on_login( string $user_login ): void {
		if ( ! $this->config->is_new_user_sync_enabled() ) {
			return;
		}
		$user = $this->users->find_by_login( $user_login );
		if ( null === $user ) {
			return;
		}
		if ( $this->users->has_legacy_msum_caps( (int) $user->ID ) ) {
			return;
		}
		$this->add_user_to_every_site( (int) $user->ID );
	}

	/**
	 * Trigger callback for `set_user_role`. When a user's role is
	 * changed on one site, propagate the same role to every other site
	 * where the user is already a member. New memberships are NOT
	 * created here — that's what the new-user trigger is for.
	 */
	public function on_role_changed( int $user_id, string $role ): void {
		if ( ! $this->config->is_set_user_role_sync_enabled() ) {
			return;
		}
		foreach ( $this->sites->all_blog_ids() as $blog_id ) {
			if ( ! $this->users->is_member_of( $user_id, $blog_id ) ) {
				continue;
			}
			$this->users->add_to_blog( $blog_id, $user_id, $role );
		}
	}

	/**
	 * Manual action: sync every network user to every site. Existing
	 * memberships are not modified — only missing memberships are
	 * created with the destination site's default role.
	 */
	public function sync_all_users_to_all_sites(): void {
		$blog_ids = $this->sites->all_blog_ids();
		foreach ( $this->users->all_network_users() as $user ) {
			foreach ( $blog_ids as $blog_id ) {
				if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
					continue;
				}
				$this->users->add_to_blog( $blog_id, (int) $user->ID, $this->sites->default_role_for_blog( $blog_id ) );
			}
		}
	}

	/**
	 * Manual action: sync every network user to a subset of sites
	 * (typically chosen via the network-admin UI checkboxes).
	 *
	 * @param int[] $blog_ids Sites to populate. Anything outside this
	 *                       list is left untouched.
	 */
	public function sync_all_users_to_sites( array $blog_ids ): void {
		foreach ( $blog_ids as $blog_id ) {
			$blog_id = (int) $blog_id;
			foreach ( $this->users->all_network_users() as $user ) {
				if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
					continue;
				}
				$this->users->add_to_blog( $blog_id, (int) $user->ID, $this->sites->default_role_for_blog( $blog_id ) );
			}
		}
	}

	private function add_user_to_every_site( int $user_id ): void {
		foreach ( $this->sites->all_blog_ids() as $blog_id ) {
			if ( $this->users->is_member_of( $user_id, $blog_id ) ) {
				continue;
			}
			$this->users->add_to_blog( $blog_id, $user_id, $this->sites->default_role_for_blog( $blog_id ) );
		}
	}
}
