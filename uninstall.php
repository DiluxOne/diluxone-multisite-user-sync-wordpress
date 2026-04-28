<?php
// Exit if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin site options. User accounts and roles are NOT touched.
delete_site_option( 'wpmus_newSiteSync' );
delete_site_option( 'wpmus_newUserSync' );
delete_site_option( 'wpmus_setUserRoleSync' );
