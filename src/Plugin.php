<?php
/**
 * Main plugin orchestrator. Constructed once from the bootstrap in
 * `wpm-user-sync.php`, builds the dependency graph, and registers
 * every WordPress hook the plugin uses. After `register()` returns
 * the plugin is fully wired and the WordPress runtime takes over.
 *
 * The class is deliberately small — its job is the wiring, not the
 * logic. Logic lives in the per-feature classes under `src/`.
 *
 * @package WPMUS
 */

declare(strict_types=1);

namespace WPMUS;

use WPMUS\Admin\NetworkHomePage;
use WPMUS\Admin\NetworkMenu;
use WPMUS\Admin\NetworkSyncActionsPage;
use WPMUS\Admin\NetworkSyncOptionsPage;
use WPMUS\Admin\SiteHomePage;
use WPMUS\Admin\SiteMenu;
use WPMUS\Admin\SiteSyncActionsPage;
use WPMUS\Repositories\SiteRepository;
use WPMUS\Repositories\UserRepository;
use WPMUS\Sync\SyncEngine;
use WPMUS\View\Header;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap class for the plugin. Constructs the dependency
 * graph and wires the WordPress hooks. Constructed exactly once from
 * `wpm-user-sync.php` on every request; the singleton handle is then
 * reachable via {@see Plugin::instance()}.
 */
final class Plugin {

	private static ?self $instance = null;

	private string $plugin_file;
	private Config $config;
	private RequirementsChecker $requirements;
	private Assets $assets;
	private Notices $notices;
	private SyncEngine $engine;
	private NetworkMenu $network_menu;
	private NetworkSyncOptionsPage $network_options;
	private NetworkSyncActionsPage $network_actions;
	private SiteMenu $site_menu;
	private SiteSyncActionsPage $site_actions;

	/**
	 * @param string $plugin_file Absolute path to wpm-user-sync.php.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
		self::$instance    = $this;

		$this->config       = new Config( $plugin_file );
		$this->requirements = new RequirementsChecker( $this->config );
		$this->assets       = new Assets( $plugin_file );
		$this->notices      = new Notices();

		$site_repo    = new SiteRepository();
		$user_repo    = new UserRepository();
		$this->engine = new SyncEngine( $this->config, $site_repo, $user_repo );

		$header                = new Header();
		$network_home          = new NetworkHomePage();
		$this->network_options = new NetworkSyncOptionsPage( $this->config );
		$this->network_actions = new NetworkSyncActionsPage( $site_repo, $this->engine );
		$this->network_menu    = new NetworkMenu( $header, $network_home, $this->network_options, $this->network_actions );

		$site_home          = new SiteHomePage();
		$this->site_actions = new SiteSyncActionsPage( $this->engine );
		$this->site_menu    = new SiteMenu( $header, $site_home, $this->site_actions );
	}

	/**
	 * Wire every WordPress hook the plugin listens on. Conditional
	 * trigger registration depends on the three site options — the
	 * legacy implementation read those once at bootstrap, so behaviour
	 * is preserved (changing a toggle requires the next page load to
	 * re-register).
	 */
	public function register(): void {
		// Lifecycle.
		register_activation_hook( $this->plugin_file, array( $this, 'on_activate' ) );
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this->requirements, 'check' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Admin menus.
		add_action( 'network_admin_menu', array( $this->network_menu, 'register' ) );
		add_action( 'admin_menu', array( $this->site_menu, 'register' ) );

		// Admin form save handlers.
		add_action( 'network_admin_edit_wpmusSaveGlobalConfig', array( $this->network_options, 'handle_save' ) );
		add_action( 'network_admin_edit_wpmusSyncNetworkFromScratch', array( $this->network_actions, 'handle_sync_all' ) );
		add_action( 'network_admin_edit_wpmusSyncNetworkSiteFromScratch', array( $this->network_actions, 'handle_sync_selected' ) );
		add_action( 'admin_action_wpmusSyncSiteSiteFromScratch', array( $this->site_actions, 'handle_sync_current_site' ) );

		// Admin notices.
		add_action( 'network_admin_notices', array( $this->notices, 'render' ) );
		add_action( 'admin_notices', array( $this->notices, 'render' ) );

		// Sync triggers (conditional on site options at boot time —
		// matches legacy behaviour where toggling required a page load).
		if ( $this->config->is_new_site_sync_enabled() ) {
			add_action( 'wpmu_new_blog', array( $this->engine, 'on_new_site' ) );
		}
		if ( $this->config->is_new_user_sync_enabled() ) {
			add_action( 'wpmu_new_user', array( $this->engine, 'on_new_user' ) );
			add_action( 'wp_login', array( $this->engine, 'maybe_on_login' ), 10, 1 );
			add_action( 'social_connect_login', array( $this->engine, 'maybe_on_login' ), 10, 1 );
		}
		if ( $this->config->is_set_user_role_sync_enabled() ) {
			add_action( 'set_user_role', array( $this->engine, 'on_role_changed' ), 10, 2 );
		}
	}

	/**
	 * Plugin-activation callback. Reserved for future activation work
	 * (table creation, default option seeding, etc.); kept registered
	 * so anyone reading the plugin metadata sees a real activation
	 * entry point.
	 */
	public function on_activate(): void {
		// Reserved for future activation work; kept as a registered
		// callback so anyone reading the plugin metadata sees a real
		// activation entry point.
	}

	/**
	 * `init` hook callback. Registers the admin-asset enqueuer.
	 */
	public function on_init(): void {
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin_styles' ) );
	}

	/**
	 * Returns the live Plugin singleton, or null if no instance has
	 * been constructed yet. The singleton is set in `__construct()`
	 * (not `register()`), so once `wpm-user-sync.php` has run its
	 * `new Plugin( __FILE__ )` line this is non-null for the rest of
	 * the request. Used by the deprecated procedural wrappers in
	 * `legacy-deprecated.php` to dispatch into the live engine.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Live SyncEngine instance. Exposed so the deprecated wrappers
	 * can dispatch into it without rebuilding the dependency graph.
	 */
	public function engine(): SyncEngine {
		return $this->engine;
	}

	/**
	 * `plugins_loaded` callback that registers the plugin's text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wpm-user-sync',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages/'
		);
	}
}
