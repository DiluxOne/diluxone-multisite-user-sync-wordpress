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
 * ## Re-entrancy
 *
 * `add_user_to_blog()` fires WordPress's `set_user_role` action as a
 * side effect of every membership write. With the role-sync trigger
 * enabled, that re-enters {@see SyncEngine::on_role_changed()} and can
 * cascade across the network — propagating a destination site's role
 * back onto every other membership the user already has, recursively.
 *
 * To prevent that without temporarily detaching/re-attaching WP hooks
 * (which interacts badly with priority and unrelated subscribers), the
 * class carries a private `$in_sync` flag. Every method that calls
 * `add_to_blog()` does so via {@see SyncEngine::add_to_blog_guarded()},
 * which sets the flag for the duration of the call. `on_role_changed()`
 * early-returns when the flag is set, breaking the recursion.
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

/**
 * Multisite user-synchronisation engine. Centralises every membership
 * write triggered by the plugin — both the WP hook callbacks and the
 * manual admin actions go through this class.
 */
final class SyncEngine {

	private Config $config;
	private SiteRepository $sites;
	private UserRepository $users;

	/**
	 * Re-entrancy guard. Set to `true` while any sync method is in the
	 * middle of writing a membership, so the `set_user_role` hook fired
	 * as a side effect of `add_user_to_blog()` does not recurse back
	 * into {@see on_role_changed()}.
	 */
	private bool $in_sync = false;

	/**
	 * @param Config         $config Toggle accessors + plugin metadata.
	 * @param SiteRepository $sites  Wraps `get_sites()` / `get_blog_option()`.
	 * @param UserRepository $users  Wraps `get_users()` / `add_user_to_blog()`
	 *                               / `is_user_member_of_blog()`.
	 */
	public function __construct( Config $config, SiteRepository $sites, UserRepository $users ) {
		$this->config = $config;
		$this->sites  = $sites;
		$this->users  = $users;
	}

	/**
	 * Trigger callback for `wpmu_new_blog` (legacy hook) — populates a
	 * freshly-created site with every existing network user. No-op when
	 * the `New Site Sync` toggle is off.
	 *
	 * Every new membership gets the new site's own default role. The
	 * role a user holds on the site the request runs on is never
	 * copied: doing so made every editor or administrator of the main
	 * site an editor or administrator of each new site.
	 *
	 * @param int $blog_id The blog ID of the newly created site.
	 */
	public function on_new_site( int $blog_id ): void {
		if ( ! $this->config->is_new_site_sync_enabled() ) {
			return;
		}

		$role = $this->sites->default_role_for_blog( $blog_id );
		foreach ( $this->users->all_network_users() as $user ) {
			if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
				continue;
			}
			$this->add_to_blog_guarded( $blog_id, (int) $user->ID, $role );
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
	 *
	 * Returns immediately when {@see $in_sync} is set: the current
	 * `add_user_to_blog` call is part of another sync method's loop,
	 * not a real role change driven by an admin.
	 */
	public function on_role_changed( int $user_id, string $role ): void {
		if ( $this->in_sync ) {
			return;
		}
		if ( ! $this->config->is_set_user_role_sync_enabled() ) {
			return;
		}
		foreach ( $this->sites->all_blog_ids() as $blog_id ) {
			if ( ! $this->users->is_member_of( $user_id, $blog_id ) ) {
				continue;
			}
			$this->add_to_blog_guarded( $blog_id, $user_id, $role );
		}
	}

	/**
	 * Manual action: sync every network user to every site. Existing
	 * memberships are not modified — only missing memberships are
	 * created with the destination site's default role.
	 */
	public function sync_all_users_to_all_sites(): void {
		$blog_ids = $this->sites->all_blog_ids();
		$users    = $this->users->all_network_users();
		foreach ( $users as $user ) {
			foreach ( $blog_ids as $blog_id ) {
				if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
					continue;
				}
				$this->add_to_blog_guarded( $blog_id, (int) $user->ID, $this->sites->default_role_for_blog( $blog_id ) );
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
		// Fetch the user list ONCE for the whole operation. The previous
		// shape of this loop refetched on every iteration, which on a
		// large network turns a single expensive query into N queries.
		$users = $this->users->all_network_users();
		foreach ( $blog_ids as $blog_id ) {
			$blog_id = (int) $blog_id;
			foreach ( $users as $user ) {
				if ( $this->users->is_member_of( (int) $user->ID, $blog_id ) ) {
					continue;
				}
				$this->add_to_blog_guarded( $blog_id, (int) $user->ID, $this->sites->default_role_for_blog( $blog_id ) );
			}
		}
	}

	/**
	 * Helper used by the new-user / login triggers: ensures the user
	 * is a member of every existing site, with each site's default
	 * role for the new memberships it creates. Existing memberships
	 * are not touched.
	 */
	private function add_user_to_every_site( int $user_id ): void {
		foreach ( $this->sites->all_blog_ids() as $blog_id ) {
			if ( $this->users->is_member_of( $user_id, $blog_id ) ) {
				continue;
			}
			$this->add_to_blog_guarded( $blog_id, $user_id, $this->sites->default_role_for_blog( $blog_id ) );
		}
	}

	/**
	 * Wraps `UserRepository::add_to_blog()` with the {@see $in_sync}
	 * re-entrancy guard. Every membership write inside this class
	 * MUST go through this helper rather than calling the repository
	 * directly, otherwise the `set_user_role` cascade described in
	 * the class-level docblock kicks in.
	 *
	 * The previous flag value is preserved + restored so the helper
	 * is safe under nested calls (extension code is unlikely to nest,
	 * but the bookkeeping costs nothing).
	 */
	private function add_to_blog_guarded( int $blog_id, int $user_id, string $role ): void {
		$previously_in_sync = $this->in_sync;
		$this->in_sync      = true;
		try {
			$this->users->add_to_blog( $blog_id, $user_id, $role );
		} finally {
			$this->in_sync = $previously_in_sync;
		}
	}
}
