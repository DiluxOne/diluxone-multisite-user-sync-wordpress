=== WPM User Sync ===
Contributors: pablodiloreto
Donate link: https://pablodiloreto.com/
Tags: multisite, wpm user sync, user sync, sync, multisite user
Requires at least: 6.6
Tested up to: 6.9.4
Stable tag: 1.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Welcome to the best free user synchronization solution for WordPress Multisite.

== Description ==

'WPM User Sync' (which actually means "WordPress Multi-Site User Synchronization") is a plugin that allow you to configure & automate users sync between wordpress sites when you are using a multi-site setup. You will find options at network & sites level, to take absolute control with what happen when: a new user is created, a new site is created, and when we change a role for an existing user. Enjoy! 

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
- Set User Role Automatic Sync: When an user role change is detected in any site (for example change an user to administrator of an specific site) this change will be replicated to all other sites (in the other sites will be administrator, too).

Also, you can execute the following actions:

- Sync from scratch: Sync all sites with all users. Each site will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.
- Sync specific site: All selected sites will receive all users with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.

= What can configure an administrator at site level? =

At site level you can not configure any option. But you can execute the following action:

- Sync from scratch: Add all network users in your site with default site role. If no default role is configured, "subscriber" role will be added. Existing users will have not changes.

= Can I avoid automatic actions and only act with manual actions? =

Yes! You can. Disable all triggers at network level & you will allow to execute only manual actions.

= Does this plugin host information in the local WordPress database? =

Yes. This plugin host information in "sitemeta" table to remember network sync options.

= Does this plugin connect to any external web service? =

Nope.

= What happens if I uninstall the plugin? Do I lose my users? =

No. Your users and their roles will remain untouched. When you uninstall WPM User Sync, only the plugin's own configuration options (the automatic sync triggers) are removed from the database. All user/site relationships created while the plugin was active remain as they are.

= I love it, how can I show my appreciation? =

If you have been impressed with this plugin and would like to somehow show some appreciation, rather than send a donation my way, please donate to your charity of choice. I will never ask for any form of reward or compensation. Helping others achieve their goals is satisfying for me :)

== Screenshots ==
 
1. Plugin Home for Network Admins.
2. Network level Options for WPM User Sync.
3. Network level Actions for WPM User Sync.
4. Plugin Home for Site Admins.
5. Site level Actions for WPM User Sync.


== Upgrade Notice ==

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
