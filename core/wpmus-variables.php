<?php

$pluginRoute = plugin_basename( 'wpm-user-sync/wpm-user-sync.php' );
$pluginBaseName = plugin_basename( $pluginRoute );
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$pluginData = get_plugin_data(realpath(dirname(__FILE__) . '/..') . '/wpm-user-sync.php');
$pluginRequireWp = $pluginData["RequiresWP"];

$wpmus_newSiteSync = get_site_option( 'wpmus_newSiteSync');
$wpmus_newUserSync = get_site_option( 'wpmus_newUserSync');
$wpmus_setUserRoleSync = get_site_option( 'wpmus_setUserRoleSync');