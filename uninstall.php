<?php
/**
 * Uninstall handler. Runs only when the user explicitly deletes the
 * plugin from the wp-admin Plugins screen — `WP_UNINSTALL_PLUGIN` is
 * defined by WordPress before this file is loaded. Removes the three
 * network-level (`wp_sitemeta`) options that store the trigger
 * toggles — the plugin is multisite-only so all configuration is
 * network-scoped. Users and roles are deliberately NOT touched.
 *
 * @package WPMUS
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_site_option( 'wpmus_newSiteSync' );
delete_site_option( 'wpmus_newUserSync' );
delete_site_option( 'wpmus_setUserRoleSync' );
