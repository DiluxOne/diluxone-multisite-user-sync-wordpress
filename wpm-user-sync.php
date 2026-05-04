<?php
/*
Plugin Name: WPM User Sync
Plugin URI: https://pablodiloreto.com/wpm-user-sync/
Description: WPM User Sync is THE plugin that allow you to configure & automate users sync between wordpress sites when you are using a multi-site setup.
Author: Pablo Ariel Di Loreto
Version: 1.4
Requires at least: 6.6
Requires PHP: 7.4
Tested up to: 6.9
Author URI: https://pablodiloreto.com/wpm-user-sync/
Text Domain: wpm-user-sync
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load required core plugin files
require_once (dirname(__FILE__).'/core/wpmus-variables.php');
require_once (dirname(__FILE__).'/core/wpmus-functions.php');

// Load required network-admin files
require_once (dirname(__FILE__).'/network-admin/wpmus-network-sections.php');
require_once (dirname(__FILE__).'/network-admin/wpmus-network-common.php');
require_once (dirname(__FILE__).'/network-admin/wpmus-network-home.php');
require_once (dirname(__FILE__).'/network-admin/wpmus-network-syncoptions.php');
require_once (dirname(__FILE__).'/network-admin/wpmus-network-syncactions.php');

// Load required site-admin files
require_once (dirname(__FILE__).'/site-admin/wpmus-site-sections.php');
require_once (dirname(__FILE__).'/site-admin/wpmus-site-home.php');
require_once (dirname(__FILE__).'/site-admin/wpmus-site-syncactions.php');

global $wpmus_newUserSync;
global $wpmus_newSiteSync;
global $wpmus_setUserRoleSync;


// Administrative triggers
register_activation_hook( __FILE__, 'wpmus_plugin_activate' );
add_action( 'init', 'wpmus_init' );
add_action( 'network_admin_menu', 'wpmus_networkmenu_items' );
add_action( 'admin_menu', 'wpmus_sitemenu_items' );
add_action( 'admin_init', 'wpmus_check_requirements' );

// Load tabs feature at network-level for home
add_action( 'wpmus_network_home_tabs', 'wpmus_network_home_welcome_tab', 1 );
add_action( 'wpmus_network_home_tabs', 'wpmus_network_home_concepts_tab', 2 );
add_action( 'wpmus_network_home_tabs', 'wpmus_network_home_about_tab', 3 );
add_action( 'wpmus_network_home_contents', 'wpmus_network_home_welcome_content' );
add_action( 'wpmus_network_home_contents', 'wpmus_network_home_concepts_content' );
add_action( 'wpmus_network_home_contents', 'wpmus_network_home_about_content' );

// Load tabs feature at site-level for home
add_action( 'wpmus_site_home_tabs', 'wpmus_site_home_welcome_tab', 1 );
add_action( 'wpmus_site_home_tabs', 'wpmus_site_home_concepts_tab', 2 );
add_action( 'wpmus_site_home_tabs', 'wpmus_site_home_about_tab', 3 );
add_action( 'wpmus_site_home_contents', 'wpmus_site_home_welcome_content' );
add_action( 'wpmus_site_home_contents', 'wpmus_site_home_concepts_content' );
add_action( 'wpmus_site_home_contents', 'wpmus_site_home_about_content' );

// Triggers fro actions
if ($wpmus_newSiteSync == 'yes') {
    add_action( 'wpmu_new_blog', 'wpmus_sync_newsite' );
}
if ($wpmus_newUserSync == 'yes') {
    add_action( 'wpmu_new_user', 'wpmus_sync_newuser' );
    add_action( 'wp_login', 'wpmus_maybesync_newuser', 10, 1 );
    add_action( 'social_connect_login', 'wpmus_maybesync_newuser', 10, 1 );
}
if ($wpmus_setUserRoleSync == 'yes') {
    add_action( 'set_user_role', 'wpmus_sync_newrole', 10, 2 );
}

// Save configuration actions
add_action( 'network_admin_edit_wpmusSaveGlobalConfig', 'wpmus_save_GlobalConfig' );
add_action( 'network_admin_edit_wpmusSyncNetworkFromScratch', 'wpmus_sync_NetworkFromScratch' );
add_action( 'admin_action_wpmusSyncNetworkSiteFromScratch', 'wpmus_sync_NetworkSiteFromScratch' );
add_action( 'admin_action_wpmusSyncSiteSiteFromScratch', 'wpmus_sync_SiteSiteFromScratch' );

// Admin notices
add_action( 'network_admin_notices', 'wpmus_notice_updated' );
add_action( 'admin_notices', 'wpmus_notice_updated' );

// Load plugin textdomain so the plugin is ready for translations.
add_action( 'plugins_loaded', 'wpmus_load_textdomain' );
function wpmus_load_textdomain() {
    load_plugin_textdomain( 'wpm-user-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}