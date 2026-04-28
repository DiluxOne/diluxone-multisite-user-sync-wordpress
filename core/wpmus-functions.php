<?php

    // When the plugin is activated
    function wpmus_plugin_activate() {

        //
        
    }

    // When WP Load
    function wpmus_init() {

		add_action ( 'admin_enqueue_scripts', 'wpmus_add_css' );

    }

    function wpmus_add_css() {

        wp_enqueue_style ( 'wpmus_styles', plugins_url('css/wpmus_styles.css', dirname(__FILE__)), '', '1.0' );

    }

    function wpmus_check_requirements() {
        global $wp_version;
        global $pluginBaseName;
        global $pluginData;
        global $pluginRequireWp;
        global $wpdb;
    
        if ( version_compare( $wp_version, $pluginRequireWp, "<" ) ) {
            if( is_plugin_active($pluginBaseName) ) {
                deactivate_plugins( $pluginBaseName );
                wp_die( "<strong>".$pluginData['Name']."</strong> requires <strong>WordPress ".$pluginRequireWp."</strong> or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to the WordPress <a href='".get_admin_url(null, 'plugins.php')."'>Plugins page</a>." );
            }
        }

        if ( ! is_multisite() ) {
            if( is_plugin_active($pluginBaseName) ) {
                deactivate_plugins( $pluginBaseName );
                wp_die( "<strong>".$pluginData['Name']."</strong> requires <strong>WordPress Multisite</strong> installation, and has been deactivated! Please configure WordPress for multisite porpuses and try again.<br /><br />Back to the WordPress <a href='".get_admin_url(null, 'plugins.php')."'>Plugins page</a>." );
            }
		}

    }

    // New site trigger
    function wpmus_sync_newsite( $blog_id ) {
        global $wpmus_newSiteSync;
        global $wpdb;

        if ($wpmus_newSiteSync == 'yes') {

            $args = array( 'blog_id' => 0 );
            $users = get_users( $args );

            remove_action( 'wpmu_new_blog', 'wpmus_sync_newsite', 10, 3 );
            foreach ( $users as $user ) {
                
                if ( ! is_user_member_of_blog( $user->ID, $blog_id ) ) {

                    if ( is_array( $user->roles ) && $user->roles ) {
                        add_user_to_blog( $blog_id, $user->ID, $user->roles[0] );
                    }
                    else {
                        add_user_to_blog( $blog_id, $user->ID, get_blog_option( $blog_id, 'default_role', 'subscriber' ) );

                    }
                }

            }
            add_action( 'wpmu_new_blog', 'wpmus_sync_newsite', 10, 3 );

        }

    }

    // New user trigger. Only activate when an admin create the user
    function wpmus_sync_newuser( $user_id ) {
        global $wpmus_newUserSync;
        global $wpdb;
	
        if ($wpmus_newUserSync == 'yes') {

            $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
        
            remove_action( 'wpmu_new_user', 'wpmus_sync_newuser', 10, 3 );
            foreach ( $blogids as $blogid ) {

                if ( ! is_user_member_of_blog( $user_id, $blogid ) ) {
                    add_user_to_blog( $blogid, $user_id, get_blog_option( $blogid, 'default_role', 'subscriber' ) );
                }

            }
            
            add_action( 'set_user_role', 'wpmus_sync_newuser', 10, 3 );

        }
    }


    // New login trigger. This action is needed to check if the user is on all sites
    function wpmus_maybesync_newuser( $user_login ) {
        global $wpmus_newUserSync;

        if ($wpmus_newUserSync == 'yes') {

            if ( function_exists( 'get_user_by' ) )
                $userdata = get_user_by( 'login', $user_login );
            else
                $userdata = get_userdatabylogin( $user_login );
        
            if ($userdata != false && get_user_meta( $userdata->ID, 'msum_has_caps', true ) != 'true' )
                wpmus_sync_newuser( $userdata->ID );

        }
    }
    
    // Set user role trigger.
    function wpmus_sync_newrole( $user_id, $role ) {
        global $wpmus_setUserRoleSync;
        global $wpdb;
	
        if ($wpmus_setUserRoleSync == 'yes') {
	
            $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
        
            remove_action( 'set_user_role', 'wpmus_sync_newrole', 10, 2 );
            foreach ( $blogids as $blogid ) {

                if ( is_user_member_of_blog( $user_id, $blogid ) ) {
                    add_user_to_blog( $blogid, $user_id, $role );
                }

            }
            add_action( 'set_user_role', 'wpmus_sync_newrole', 10, 2 );

        }
    }

    // Action: sync all sites from scratch
    function wpmus_sync_NetworkFromScratch() {

        check_admin_referer( 'wpmus-validate' ); // Nonce security check

        global $wpdb;
        $blogids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->base_prefix}blogs" );
        $args = array( 'blog_id' => 0 );
        $users = get_users( $args );
            
        foreach ( $users as $user ) {
            foreach ( $blogids as $blogid ) {
                if ( ! is_user_member_of_blog( $user->ID, $blogid ) ) {
                    add_user_to_blog( $blogid, $user->ID, get_blog_option( $blogid, 'default_role', 'subscriber' ) );
                }
            }
        }

        wp_redirect( add_query_arg( array(
            'page' => 'wpmus-networksyncactions',
            'synced' => true ), network_admin_url('admin.php')
        ));
     
        exit;
    }

    // Action: selected sites from scratch
    function wpmus_sync_NetworkSiteFromScratch() {

        check_admin_referer( 'wpmus-validate' ); // Nonce security check

        global $wpdb;

        $args = array( 'blog_id' => 0 );
        $users = get_users( $args );

        $listSites = isset($_POST['listSites']) ? array_map('sanitize_text_field', $_POST['listSites']) : array();

        if(! empty($listSites)) {


            foreach ( $listSites as $blogid) {

                foreach ( $users as $user ) {

                    if ( ! is_user_member_of_blog( $user->ID, $blogid ) ) {
                        add_user_to_blog( $blogid, $user->ID, get_blog_option( $blogid, 'default_role', 'subscriber' ) );
                    }

                }

            }

            wp_redirect( add_query_arg( array(
                'page' => 'wpmus-networksyncactions',
                'synced' => true ), network_admin_url('admin.php')
            ));

        }else{
            wp_redirect( add_query_arg( array(
                'page' => 'wpmus-networksyncactions',
                'nosynced' => true ), network_admin_url('admin.php')
            ));
        }

        exit;
    }


    // Action: sync just one (actual) site from scratch
    function wpmus_sync_SiteSiteFromScratch() {

        check_admin_referer( 'wpmus-validate' ); // Nonce security check

        global $wpdb;

        $args = array( 'blog_id' => 0 );
        $users = get_users( $args );

        $blogid = sanitize_text_field($_POST['wpmus_blogid']);
            
        foreach ( $users as $user ) {

            if ( ! is_user_member_of_blog( $user->ID, $blogid ) ) {
                add_user_to_blog( $blogid, $user->ID, get_blog_option( $blogid, 'default_role', 'subscriber' ) );
            }

        }

        wp_redirect( add_query_arg( array(
            'page' => 'wpmus-sitesyncactions',
            'synced' => true ), admin_url('admin.php')
        ));

        exit;
    }
    
    // Save config
    function wpmus_save_GlobalConfig(){
 
        check_admin_referer( 'wpmus-validate' ); // Nonce security check
     
        update_site_option( 'wpmus_newSiteSync', sanitize_text_field($_POST['wpmus_newSiteSync']) );
        update_site_option( 'wpmus_newUserSync', sanitize_text_field($_POST['wpmus_newUserSync']) );
        update_site_option( 'wpmus_setUserRoleSync', sanitize_text_field($_POST['wpmus_setUserRoleSync']) );
     
        wp_redirect( add_query_arg( array(
            'page' => 'wpmus-networksyncoptions',
            'updated' => true ), network_admin_url('admin.php')
        ));
     
        exit;
     
    }
    
    // Admin notice
    function wpmus_notice_updated(){
     
        if( isset($_GET['page']) && isset( $_GET['updated'] )  ) {
            echo '<div id="message" class="updated notice is-dismissible"><p>Settings updated. You\'re the best!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        }

        if( isset($_GET['page']) && isset( $_GET['synced'] )  ) {
            echo '<div id="message" class="updated notice is-dismissible"><p>Sync done. You\'re a champion!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        }

        if( isset($_GET['page']) && isset( $_GET['nosynced'] )  ) {
            echo '<div id="message" class="notice notice-warning is-dismissible"><p>Sync did not happen. You\'re must select at least one site!</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
        }
     
    }