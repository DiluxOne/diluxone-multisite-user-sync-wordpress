=== DiluxOne Multisite User Sync ===
Contributors: pablodiloreto
Tags: multisite, users, user sync, network, roles
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds your WordPress Multisite users to your sites, automatically or on demand: new users, new sites, role changes.

== Description ==

Out of the box, a WordPress Multisite network keeps its users and its sites apart: a new user joins no site, a new site starts with nobody but its administrator, and a role changed on one site stays there. DiluxOne Multisite User Sync closes that gap from Network Admin.

Formerly *WPM User Sync*: same plugin, same settings, a new name in the DiluxOne family.

= Three automatic triggers, each one on or off =

* **New user**: a user added to the network (by a super admin, by registration, or by another plugin) joins every site, with each site's default role.
* **New site**: a new site gets every user of the network, with the new site's own default role.
* **Role change**: a role changed on one site is copied to the other sites the user already belongs to, where that role exists. Administrator is never copied.

= Two manual actions =

* **Sync from scratch**: every user to every site, or only to the sites you tick. Existing memberships are never changed.
* **Add back people who were removed**, only if you tick the box: someone an administrator removed from a site stays off it otherwise, whatever the triggers say.

= Safe by default =

* Super admins are never added as members (they reach every site already), and archived, spam and deleted sites are left alone.
* Only super admins see the plugin. A site's own administrator cannot pull the network's users into their site.
* Big networks: a sync too large for one request runs in the background through WP-Cron, in batches, with its progress on the Network Sync Actions screen.
* Users are never duplicated: one account, added to more sites. Uninstalling removes the plugin's settings and keeps every membership.

Works on any WordPress Multisite host.

= For developers =

Filters: `wpmus_excluded_site_ids` (sites the sync never writes to), `wpmus_should_sync_user` (skip one user on one site), `wpmus_replicate_role` (which roles the role trigger copies), `wpmus_sync_inline_limit`, `wpmus_sync_batch_size` and `wpmus_sync_time_limit` (how big syncs are split). The source, its tests and the whole list are on GitHub: https://github.com/DiluxOne/diluxone-multisite-user-sync-wordpress

== Installation ==

1. In Network Admin › Plugins › Add New, search for "DiluxOne Multisite User Sync", install it and click **Network Activate**.
2. Go to Network Admin › User Sync › Network Sync Options and turn on the triggers you want.
3. To bring an existing network up to date, run **Sync from scratch** on Network Admin › User Sync › Network Sync Actions.

The plugin needs WordPress Multisite. On a single site it says so and deactivates itself.

== Frequently Asked Questions ==

= Does it copy or duplicate users? =

No. A WordPress network has one list of accounts; the plugin adds the same account to more sites, with a role on each.

= Which role does a user get on each site? =

The site's own default role (Settings › General › New User Default Role on that site), or subscriber when the site's default role does not exist. The role a user holds on another site is never carried over by the new-user and new-site triggers or the manual actions.

= Someone was removed from a site. Will a sync put them back? =

No. The plugin remembers who was removed from which site and no trigger adds them back. The manual actions only do so when you tick "Also add back people who were removed from a site". Adding someone back yourself clears the record.

= Can I use only the manual actions? =

Yes. Leave the three triggers off and run the actions when you want.

= What can a site administrator do? =

Nothing: site administrators do not see the plugin. From a site's dashboard, a super admin can add every network user to that one site.

= What does it store in the database? =

The three trigger settings and the progress of syncs running in the background, as network options, and the list of sites each person was removed from, as user meta. Nothing else, and nothing leaves your server: the plugin connects to no external service.

= What happens when I uninstall it? =

The plugin's own data is removed (the settings, any sync still queued with its scheduled event, and the record of removals). Users, roles and memberships stay exactly as they are.

= Is it in my language? =

Translations for Spanish (Argentina, Spain, Mexico), Portuguese (Brazil, Portugal), French, German and Italian are written with the plugin and contributed to translate.wordpress.org. WordPress installs a language from there once it is approved; the plugin itself carries no translation files.

== Screenshots ==

1. Network Admin › User Sync: the home and the first steps.
2. Network Sync Options: the three automatic triggers, each one on or off.
3. Network Sync Actions: sync every site, or only the sites you pick.
4. A large sync running in the background, with its progress.
5. A site's dashboard, for super admins: the site's own home.
6. Site Sync Actions: add every network user to this one site.

== Upgrade Notice ==

= 2.0.0 =
Requires PHP 8.0. Security fixes: new sites no longer copy main-site roles, removed people stay removed, administrator is never copied, and site administrators can no longer pull the whole network into their site.

= 1.5.0 =
Security hardening (capability checks on every admin action, escaped output, no role-sync cascades) and a full refactor. Same settings, same behavior.

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

= 2.0.0 =
Unreleased.

* The plugin is now called DiluxOne Multisite User Sync, in the DiluxOne family. Nothing else changes for your network: same settings, same screens, same slug.
* Requires PHP 8.0 or newer (it was 7.4). Tested with WordPress 7.1.
* Security: the new-site trigger gave each new site's members the role they held on the main site, so main-site editors and administrators became editors and administrators of every new site. Every new membership now gets the new site's own default role, and subscriber when that role does not exist there.
* Security: the role trigger copied any role, administrator included, to every site the user belonged to, even where the role does not exist. It now copies a role only to sites that define it, never copies administrator (a developer can allow it with the `wpmus_replicate_role` filter), leaves super admins alone and ignores changes that change nothing.
* Security: any site administrator could run the site-level "Sync from scratch" and pull every account of the network into their site. The site screens and their action are now for super admins only, and so are the network actions.
* People removed from a site were put back on it at their next sign-in, and by the new-user trigger and the manual sync. The plugin now remembers who was removed from which site and no sync adds them back, unless you tick "Also add back people who were removed from a site" on a manual sync. Adding someone back yourself clears the record.
* Removed: the catch-up sync that ran on every sign-in. Accounts created outside the normal flows (imports, SQL) are added with the manual network sync.
* Super admins are no longer added as members of every site, and archived, spam and deleted sites, and the sites of other networks on the same install, are left alone.
* The new-site trigger now listens on `wp_initialize_site` instead of the deprecated `wpmu_new_blog`, and the new-user trigger also covers accounts created with `wp_insert_user()` alone (some registration plugins), once per account.
* Turning a trigger on or off takes effect immediately instead of from the next page load.
* Big networks: syncs no longer load every user into memory, and a large one (a new site or a manual sync on a big network, a new user on a network with many sites) runs in the background through WP-Cron in batches, with its progress on the Network Sync Actions screen. Small syncs still finish at once.
* A background sync whose run died midway (a timeout, a restart) could stay stuck in the queue, because nothing was left scheduled to pick it up. A run that finds the queue busy now leaves a retry for when the busy run would count as dead, ten minutes later.
* A site or user added while a background sync was running could lose its own sync, and a background sync emptied from the queue could come back. Every change to the queue now starts from what is stored.
* New filters for developers: `wpmus_excluded_site_ids`, `wpmus_should_sync_user`, `wpmus_replicate_role`, `wpmus_sync_inline_limit`, `wpmus_sync_batch_size` and `wpmus_sync_time_limit`.
* On a site that is not a network, the plugin now only explains that it needs WordPress Multisite and deactivates itself; nothing else of it runs in the meantime.
* Uninstalling now also removes the background sync queue, its scheduled event and the record of removals.
* The plugin's stylesheet loads only on its own screens instead of on every admin screen.
* Translations written for Spanish (Argentina, Spain, Mexico), Portuguese (Brazil, Portugal), French, German and Italian, to be contributed to translate.wordpress.org; WordPress installs them from there. The plugin no longer loads translations itself.

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
