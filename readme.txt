=== WPM User Sync ===
Contributors: pablodiloreto
Donate link: https://pablodiloreto.com/
Tags: multisite, wpm user sync, user sync, sync, multisite user
Requires at least: 6.6
Tested up to: 6.9
Stable tag: 1.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Welcome to the best free user synchronization solution for WordPress Multisite.

== Description ==

**Optimized for Microsoft Azure and Azure App Service** — built and battle-tested in production on Azure-hosted WordPress Multisite environments, while remaining fully compatible with any other WordPress Multisite host (DigitalOcean, AWS, dedicated servers, shared hosting, etc.).

'WPM User Sync' (which actually means "WordPress Multi-Site User Synchronization") is a plugin that allows you to configure & automate users sync between WordPress sites when you are using a multi-site setup. You will find options at network & sites level, to take absolute control with what happens when: a new user is created, a new site is created, and when we change a role for an existing user. Enjoy!

== Installation ==

The normal plugin install process applies, that is search for 'WPM User Sync' from your plugin screen or via the manual method:

1. Upload the 'WPM User Sync' folder into your '/wp-content/plugins/' directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

That's it! 'WPM User Sync' will appear in your dashboard at Network & Site level

== Frequently Asked Questions ==

= What exactly does this plugin do? =

WPM User Sync is a plugin that enable the user synchronization in your Wordpress Multisite, that is a type of WordPress installation that allows you to create and manage a network of multiple websites from a single WordPress dashboard. Key concepts:

- WPM User sync is a plugin, not a core feature of WordPress. It was built by external developers to WordPress. However, it goes through a detailed testing process to ensure smooth operation as it interacts with core aspects of the CMS.
- In out-of-the-box WordPress multisite setup, when you create a new user, it never sync to other sites in your network. Also, when you create a new site in your network, no users are synced to this new site. This means that you must manually register or associate users to your site, or your new site with your users. This is a tedious and manual process.
- This plugin bring you the possibility to automate all this scenarios: a) when you create a new user, this user can be synced to all existing sites in your network; b) when you create a new site, all users can be automatic synced to it; c) when we change an user role in one site, you can configure to replicate this change to all sites in your network; d) if you do not want automation, with this plugin you can do all previous things in manual mode :-).
- Last but not least, when we talk about 'user synchronization', we never duplicate user data. The user is only one, and the same identity is the one that is added to the sites in a reference model. If you are using "SUBDOMAIN_INSTALL" option (that is, each site on your network will be a subdomain) and you want "single-sign on experience", you should configure some cookies aspects in your WP-CONFIG. Check plugin's website help for more information.

= What is a trigger? Which ones exist here? =

In WPM User Sync you will can configure some triggers to automate user sync. A trigger is procedural code that is automatically executed in response to certain events, and in the particular case of WPM User Sync & WordPress, to one of the following events:

- New user creation: when an user register in your site, or an admin create a new one.
- New site creation: when an admin or authorized user create a new site in your network.
- User role edited in one site: when you edit a user role in one of your network sites.

Its very important to remember that you can configure all these 3 triggers from network level options.

= What kind of options do I have at the network level? =

At network level you can configure the 3 triggers that we descripted in the past:

- New Site Automatic Sync: When a new site is created in the network, all users in the database will be added to this new site with default site role. If no default role is configured, "subscriber" role will be added.
- New User Automatic Sync: When a new user is created in the network, will be added to all sites in the database with each default site role. If no default role is configured, "subscriber" role will be added.
- Set User Role Automatic Sync: When an user role change is detected in any site (for example change an user to editor of an specific site) this change will be replicated to the other sites where the user is already a member and that have that role. The administrator role is never replicated (a developer can allow it with the `wpmus_replicate_role` filter), and super admins are left alone.

Also, you can execute the following actions:

- Sync from scratch: Sync all sites with all users. Each site will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.
- Sync specific site: All selected sites will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.

= What can configure an administrator at site level? =

Nothing. Site administrators do not see the plugin: pulling every network account into a site is a network decision. From a site's dashboard, a super admin (anyone who can manage the network's users) can run one action:

- Sync from scratch: Add all network users to that site with its default role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes, and people removed from the site stay removed.

= Can I avoid automatic actions and only act with manual actions? =

Yes! You can. Disable all triggers at network level & you will allow to execute only manual actions.

= Does it work with DiluxOne Users+? =

Yes. If DiluxOne Users+ is active, its network membership policy defers to WPM User Sync while any automatic trigger is on.

= Does this plugin host information in the local WordPress database? =

Yes. This plugin host information in the "sitemeta" table to remember network sync options and the progress of large syncs running in the background, and in the "usermeta" table to remember which sites each person was removed from.

= Does this plugin connect to any external web service? =

Nope.

= What happens if I uninstall the plugin? Do I lose my users? =

No. Your users and their roles will remain untouched. When you uninstall WPM User Sync, only the plugin's own data is removed from the database: the automatic sync triggers, any sync still queued in the background, and its record of who was removed from which site. All user/site relationships created while the plugin was active remain as they are.

= I love it, how can I show my appreciation? =

If you have been impressed with this plugin and would like to somehow show some appreciation, rather than send a donation my way, please donate to your charity of choice. I will never ask for any form of reward or compensation. Helping others achieve their goals is satisfying for me :)

== Screenshots ==
 
1. Plugin Home for Network Admins.
2. Network level Options for WPM User Sync.
3. Network level Actions for WPM User Sync.
4. Plugin Home for Site Admins.
5. Site level Actions for WPM User Sync.


== Upgrade Notice ==

= 1.5.0 =
* Major modernization release — full OOP refactor with security hardening (capability checks on every admin handler, output escaping throughout, re-entrancy guard preventing role-sync cascades). Description now leads with the Microsoft Azure App Service positioning. Same toggles, same behavior, dramatically safer internals.

= 1.4 =
Compatibility with the latest WordPress versions. Added clean uninstall support, proper multisite network flag, and translations-ready loading.

= 1.3 =
Bug fixes.

= 1.2 =
Bug fixes.

= 1.1 =
Bug fixes.

= 1.0 =
First release. Check help for all features.

== Changelog ==

= Unreleased =
* **Fix (security)**: the New Site trigger gave each new site's members the role they held on the main site, so main-site editors and administrators became editors and administrators of every new site. Every new membership now gets the new site's own default role, and a default role the site does not define falls back to subscriber.
* **Fix**: people removed from a site were put back on it at their next sign-in (and by the new-user trigger and the manual sync). The plugin now remembers who was removed from which site and no automatic sync adds them back; the network "Sync from scratch" and "Sync specific sites" actions only do so when you tick "Also add back people who were removed from a site". Adding someone back yourself clears the record.
* **Removed**: the catch-up sync that ran on every sign-in (`wp_login`, `social_connect_login`). Accounts created outside the normal flows (imports, SQL) are added with the manual network sync.
* **Fix**: super admins are no longer added as members of every site (they already reach every site), and archived, spam and deleted sites, and the sites of other networks on the same install, are no longer synced.
* **New**: two filters for developers, `wpmus_excluded_site_ids` (sites the sync never writes to) and `wpmus_should_sync_user` (skip one user on one site).
* **Fix (security)**: the Set User Role trigger copied any role, administrator included, to every site the user belonged to, including roles those sites do not define. It now copies a role only to sites that define it, never copies administrator unless the new `wpmus_replicate_role` filter allows it, leaves super admins alone, and ignores calls where the role did not change.
* **Fix (security)**: any site administrator could run the site-level "Sync from scratch" and pull every account on the network into their site. The site-level pages and action now require the `manage_network_users` capability (super admins), and so do the network sync actions.
* **Fix**: the New Site trigger listened on the deprecated `wpmu_new_blog` hook; it now uses `wp_initialize_site`. The New User trigger also covers accounts created with `wp_insert_user()` alone (some registration plugins), and runs once per account.
* **Fix**: turning a trigger on or off now takes effect immediately, instead of from the next page load.
* **Performance**: syncs no longer load every user of the network into memory, and large ones (a new site or a manual sync on a big network, a new user on a network with many sites) run in the background through WP-Cron in batches instead of in the request that started them, with their progress listed on the Network Sync Actions page. Small syncs still finish immediately. Developers can tune this with the `wpmus_sync_inline_limit`, `wpmus_sync_batch_size` and `wpmus_sync_time_limit` filters.
* **New**: works alongside DiluxOne Users+ without depending on it: while any automatic trigger is on, WPM User Sync answers Users+'s `diluxone_users_membership_managed_by` filter, so Users+'s network membership policy steps aside.
* **Fix**: uninstalling now also removes the background sync queue, its scheduled event and the record of removals.

= 1.5.0 (2026-05-05) =
* **Description repositioned**: leads with "Optimized for Microsoft Azure and Azure App Service" while explicitly noting compatibility with any WordPress Multisite host (DigitalOcean, AWS, dedicated servers, shared hosting, etc.).
* **Security hardening**: every admin save handler now requires `manage_network_options` (network forms) or `manage_options` (site form) on top of the existing nonce check. Previously only nonces were enforced.
* **Output escaping**: every admin page now escapes all dynamic output via `esc_html`, `esc_attr`, `esc_url`, or `wp_kses` with explicit allow-lists.
* **Input sanitisation**: every `$_GET` / `$_POST` access now goes through `wp_unslash` + the appropriate `sanitize_*` (with `absint()` on blog IDs).
* **Re-entrancy guard in the sync engine**: when role-sync is on, `add_user_to_blog` no longer cascades through `set_user_role` → `on_role_changed` → `add_user_to_blog`, which previously could mass-reset roles across the network.
* **Better error messages on requirements failure**: the `wp_die` shown when activated on single-site or below the minimum WP version is now translatable and clearer.
* **Internal: full developer-experience stack**: PHPCS+WPCS strict at zero violations, PHPStan level 8 (no baseline), Psalm taint analysis, 46 unit tests, 16 multisite integration tests on wp-env, Plugin Check enforced strict, CI matrix on PHP 7.4–8.3. None of this changes runtime behavior.
* **Internal: procedural code refactored to OOP** under the `WPMUS\` namespace with PSR-4 autoloading. Legacy `wpmus_*` functions retained as `@deprecated 1.5.0` thin wrappers for back-compat.

= 1.4 (2026-04-22) =
* Added "Network: true" header flag (proper multisite-only declaration).
* Added uninstall.php: plugin options are cleaned up on uninstall. Users and roles are NOT touched.
* Added internationalization loading via load_plugin_textdomain (ready for translations).
* Added "Requires PHP" header and "License URI".
* Updated compatibility with the latest WordPress versions.
* Aligned "Requires at least" between plugin header and readme.
* Minor typo corrections in readme and deduped a repeated entry in the Upgrade Notice.

= 1.3 (2024-10-28) =
* Bug fixed: "Sync did not happen. You're must select at least one site!".

= 1.2 (2024-10-27) =
* Bug fixed: AJAX responses - function wpmus_maybesync_newuser echo "maybe".

= 1.1 (2020-04-11) =
* Bug fixed: user sync when end-user register in the network.
* Performance improved adding some conditional during triggers.

= 1.0 (2020-04-05) =
* Initial source code.
* Bump tested WordPress version to 5.4
* Check help for all features.
