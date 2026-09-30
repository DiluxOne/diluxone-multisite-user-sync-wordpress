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
	 * Default role per blog id, resolved once per sync run: reading it
	 * switches to the site.
	 *
	 * @var array<int,string>
	 */
	private array $default_roles = array();

	/**
	 * Super admin user ids, as keys, loaded once per run. Super admins
	 * reach every site already and are never added as members.
	 *
	 * @var array<int,bool>|null
	 */
	private ?array $super_admins = null;

	/**
	 * Users registered in this request whose new-user sync is waiting
	 * for `wpmu_new_user` or shutdown, as keys.
	 *
	 * @var array<int,bool>
	 */
	private array $registered = array();

	/**
	 * Users the new-user sync already ran for in this request, as keys.
	 *
	 * @var array<int,bool>
	 */
	private array $synced_new_users = array();

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
	 * Trigger callback for `wp_initialize_site` — populates a
	 * freshly-created site with every existing network user. No-op when
	 * the `New Site Sync` toggle is off.
	 *
	 * Every new membership gets the new site's own default role. The
	 * role a user holds on the site the request runs on is never
	 * copied: doing so made every editor or administrator of the main
	 * site an editor or administrator of each new site.
	 *
	 * @param \WP_Site|int $site The new site (`wp_initialize_site`), or
	 *                           its id (the deprecated wrapper).
	 */
	public function on_new_site( $site ): void {
		if ( ! $this->config->is_new_site_sync_enabled() ) {
			return;
		}
		$blog_id = $site instanceof \WP_Site ? (int) $site->blog_id : (int) $site;
		if ( $blog_id <= 0 ) {
			return;
		}

		$this->sync_users_to_sites( array( $blog_id ), false, 'new_site' );
	}

	/**
	 * Trigger callback for `wpmu_new_user` — adds a brand-new user to
	 * every active site with each site's default role. No-op when the
	 * `New User Sync` toggle is off, and runs once per user.
	 */
	public function on_new_user( int $user_id ): void {
		unset( $this->registered[ $user_id ] );
		if ( ! $this->config->is_new_user_sync_enabled() ) {
			return;
		}
		if ( $user_id <= 0 || isset( $this->synced_new_users[ $user_id ] ) ) {
			return;
		}
		$this->synced_new_users[ $user_id ] = true;
		$this->add_user_to_every_site( $user_id );
	}

	/**
	 * Callback for `user_register`. Inside wpmu_create_user() it fires
	 * before core strips the account's default membership, so the work
	 * waits: `wpmu_new_user` runs it right after, and accounts made
	 * with wp_insert_user() alone, where `wpmu_new_user` never fires,
	 * are handled by {@see flush_registered_users()} at shutdown.
	 */
	public function on_user_registered( int $user_id ): void {
		if ( $user_id <= 0 || ! $this->config->is_new_user_sync_enabled() ) {
			return;
		}
		$this->registered[ $user_id ] = true;
	}

	/**
	 * Callback for `shutdown`: runs the new-user sync for accounts
	 * registered in this request that `wpmu_new_user` did not cover.
	 */
	public function flush_registered_users(): void {
		foreach ( array_keys( $this->registered ) as $user_id ) {
			$this->on_new_user( $user_id );
		}
	}

	/**
	 * Callback for `remove_user_from_blog`. Records the removal so no
	 * automatic sync adds the user back to that site; runs whatever
	 * the toggles say, so a trigger turned on later still respects it.
	 */
	public function on_user_removed_from_blog( int $user_id, int $blog_id ): void {
		if ( $user_id <= 0 || $blog_id <= 0 ) {
			return;
		}
		$this->users->record_removal( $user_id, $blog_id );
	}

	/**
	 * Callback for `add_user_to_blog`. When someone other than this
	 * plugin adds the user to a site (an administrator, another
	 * plugin), a removal recorded for that site no longer applies.
	 */
	public function on_user_added_to_blog( int $user_id, string $role, int $blog_id ): void {
		if ( $this->in_sync ) {
			return;
		}
		$this->users->forget_removal( $user_id, $blog_id );
	}

	/**
	 * Trigger callback for `set_user_role`. When a user's role is
	 * changed on one site, copy it to the other sites where the user is
	 * already a member. New memberships are NOT created here.
	 *
	 * The role is copied only to a site that defines it, never when it
	 * is `administrator` unless the `wpmus_replicate_role` filter says
	 * so, never for a super admin, and not when nothing changed (the
	 * role is empty, or equals the only role the user had).
	 *
	 * Returns immediately when {@see $in_sync} is set: the current
	 * `add_user_to_blog` call is part of another sync method's loop,
	 * not a real role change driven by an admin.
	 *
	 * @param int      $user_id   The user whose role changed.
	 * @param string   $role      The new role on the current site.
	 * @param string[] $old_roles The roles the user had there before.
	 */
	public function on_role_changed( int $user_id, string $role, array $old_roles = array() ): void {
		if ( $this->in_sync ) {
			return;
		}
		if ( ! $this->config->is_set_user_role_sync_enabled() ) {
			return;
		}
		if ( '' === $role || array( $role ) === array_values( $old_roles ) ) {
			return;
		}
		if ( in_array( $user_id, $this->users->super_admin_ids(), true ) ) {
			return;
		}

		/**
		 * Filters whether a role change on one site is copied to the
		 * user's other sites. `administrator` is not copied by default.
		 *
		 * @param bool   $replicate True to copy the role.
		 * @param string $role      The new role.
		 * @param int    $user_id   The user.
		 */
		if ( ! apply_filters( 'wpmus_replicate_role', 'administrator' !== $role, $role, $user_id ) ) {
			return;
		}

		$this->begin_run();
		$source = $this->sites->current_blog_id();
		foreach ( $this->target_blog_ids( $this->users->blog_ids_of_user( $user_id ), 'role_change' ) as $blog_id ) {
			if ( $blog_id === $source || ! $this->sites->role_exists_on_blog( $blog_id, $role ) ) {
				continue;
			}
			$this->add_to_blog_guarded( $blog_id, $user_id, $role );
		}
	}

	/**
	 * Manual action: sync every network user to every site. Existing
	 * memberships are not modified — only missing memberships are
	 * created with the destination site's default role. A user removed
	 * from a site is only added back when `$force` is true.
	 */
	public function sync_all_users_to_all_sites( bool $force = false ): void {
		$this->sync_users_to_sites( null, $force, 'manual' );
	}

	/**
	 * Manual action: sync every network user to a subset of sites
	 * (typically chosen via the network-admin UI checkboxes).
	 *
	 * @param int[] $blog_ids Sites to populate. Anything outside this
	 *                        list, or not active, is left untouched.
	 * @param bool  $force    Also add back users who were removed.
	 */
	public function sync_all_users_to_sites( array $blog_ids, bool $force = false ): void {
		$this->sync_users_to_sites( array_map( 'intval', $blog_ids ), $force, 'manual' );
	}

	/**
	 * Every network user to the given sites (null: every active site).
	 *
	 * @param int[]|null $blog_ids Requested sites.
	 * @param bool       $force    Also add back users who were removed.
	 * @param string     $context  new_site, new_user or manual.
	 */
	private function sync_users_to_sites( ?array $blog_ids, bool $force, string $context ): void {
		$this->begin_run();
		$targets = $this->target_blog_ids( $blog_ids, $context );
		if ( array() === $targets ) {
			return;
		}
		// Fetch the user list ONCE for the whole operation. The previous
		// shape of this loop refetched on every iteration, which on a
		// large network turns a single expensive query into N queries.
		$users = $this->users->all_network_users();
		foreach ( $targets as $blog_id ) {
			foreach ( $users as $user ) {
				$this->add_if_missing( (int) $user->ID, $blog_id, $force, $context );
			}
		}
	}

	/**
	 * Helper used by the new-user trigger: ensures the user is a
	 * member of every active site, with each site's default role for
	 * the new memberships it creates. Existing memberships and
	 * recorded removals are not touched.
	 */
	private function add_user_to_every_site( int $user_id ): void {
		$this->begin_run();
		foreach ( $this->target_blog_ids( null, 'new_user' ) as $blog_id ) {
			$this->add_if_missing( $user_id, $blog_id, false, 'new_user' );
		}
	}

	/**
	 * Resets what a run caches: default roles and super admins can
	 * change between runs.
	 */
	private function begin_run(): void {
		$this->default_roles = array();
		$this->super_admins  = null;
	}

	/**
	 * The sites a run may write to: the requested ones (or all) that
	 * are active on this network (not archived, spam or deleted),
	 * minus the ones the `wpmus_excluded_site_ids` filter lists.
	 *
	 * @param int[]|null $requested Requested sites, null for all.
	 * @param string     $context   new_site, new_user, manual or role_change.
	 * @return int[]
	 */
	private function target_blog_ids( ?array $requested, string $context ): array {
		$active  = $this->sites->all_blog_ids();
		$targets = null === $requested ? $active : array_values( array_intersect( $requested, $active ) );

		/**
		 * Filters the sites the sync never writes to.
		 *
		 * @param int[]  $excluded Blog ids to leave alone. Default empty.
		 * @param string $context  `new_site`, `new_user`, `manual` or `role_change`.
		 */
		$excluded = apply_filters( 'wpmus_excluded_site_ids', array(), $context );
		if ( is_array( $excluded ) && array() !== $excluded ) {
			$targets = array_values( array_diff( $targets, array_map( 'intval', $excluded ) ) );
		}
		return $targets;
	}

	/**
	 * Adds one user to one site, with that site's default role, unless
	 * they are already a member or were removed from it. `$force`
	 * overrides the removal and clears its record.
	 */
	private function add_if_missing( int $user_id, int $blog_id, bool $force, string $context ): void {
		if ( null === $this->super_admins ) {
			$this->super_admins = array_fill_keys( $this->users->super_admin_ids(), true );
		}
		if ( isset( $this->super_admins[ $user_id ] ) ) {
			return;
		}
		if ( $this->users->is_member_of( $user_id, $blog_id ) ) {
			return;
		}
		$removed = in_array( $blog_id, $this->users->removed_blog_ids( $user_id ), true );
		if ( $removed && ! $force ) {
			return;
		}
		/**
		 * Filters whether the sync adds a user to a site.
		 *
		 * Runs only for a membership the sync is about to create: super
		 * admins, existing members and removed users are already out.
		 *
		 * @param bool   $sync    True to add the user. Default true.
		 * @param int    $user_id The user.
		 * @param int    $blog_id The site.
		 * @param string $context `new_site`, `new_user` or `manual`.
		 */
		if ( ! apply_filters( 'wpmus_should_sync_user', true, $user_id, $blog_id, $context ) ) {
			return;
		}
		if ( ! isset( $this->default_roles[ $blog_id ] ) ) {
			$this->default_roles[ $blog_id ] = $this->sites->default_role_for_blog( $blog_id );
		}
		$this->add_to_blog_guarded( $blog_id, $user_id, $this->default_roles[ $blog_id ] );
		if ( $removed ) {
			$this->users->forget_removal( $user_id, $blog_id );
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
